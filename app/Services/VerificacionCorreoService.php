<?php

namespace App\Services;

use App\Config\Config;
use App\Core\Model;
use App\Core\Session;
use App\Models\Usuario;
use PDO;
use RuntimeException;
use Throwable;

/**
 * OTP de verificación de correo (registro público / login no verificado).
 * No reutiliza activacion_cuenta ni recuperacion_password.
 */
class VerificacionCorreoService extends Model
{
    public const SESION_CLAVE = 'registro_verificacion';

    public const EXPIRACION_MINUTOS = 10;
    public const MAX_INTENTOS = 5;
    public const COOLDOWN_SEGUNDOS = 60;
    public const MAX_REENVIOS = 10;

    private Usuario $usuarioModel;

    public function __construct()
    {
        parent::__construct();
        $this->asegurarTimezoneApp();
        $this->usuarioModel = new Usuario();
    }

    /**
     * Alinea date()/time() con APP_TIMEZONE (y con la sesión MySQL de Database).
     * Evita falsos EXPIRADO en CLI u otros entrypoints sin public/index.php.
     */
    private function asegurarTimezoneApp(): void
    {
        $tz = trim((string) Config::get('APP_TIMEZONE', 'America/Mexico_City'));
        if ($tz === '') {
            $tz = 'America/Mexico_City';
        }

        if (date_default_timezone_get() !== $tz) {
            date_default_timezone_set($tz);
        }
    }

    public function esquemaDisponible(): bool
    {
        return $this->columnaExiste('usuario', 'CorreoVerificado')
            && $this->tablaExiste('verificacion_correo');
    }

    /**
     * Crea OTP, lo envía y prepara contexto de sesión NO autenticado.
     *
     * @return array{ok: bool, mensaje: string, codigo?: string, enviado?: bool}
     */
    public function iniciarTrasRegistro(
        string $clvUsu,
        string $correo,
        string $nombreDestino = ''
    ): array {
        if (!$this->esquemaDisponible()) {
            return [
                'ok' => false,
                'codigo' => 'ESQUEMA',
                'mensaje' =>
                    'La verificación de correo aún no está disponible. Contacta al consultorio.',
            ];
        }

        $resultado = $this->crearYEnviar($clvUsu, $correo, $nombreDestino, true);
        $this->guardarContextoSesion($clvUsu, $correo);

        return $resultado;
    }

    /**
     * Tras login con credenciales OK pero CorreoVerificado=0.
     *
     * @return array{ok: bool, mensaje: string, codigo?: string, enviado?: bool}
     */
    public function iniciarDesdeLogin(
        string $clvUsu,
        string $correo,
        string $nombreDestino = ''
    ): array {
        if (!$this->esquemaDisponible()) {
            return [
                'ok' => false,
                'codigo' => 'ESQUEMA',
                'mensaje' =>
                    'Debes verificar tu correo. Contacta al consultorio si el problema continúa.',
            ];
        }

        $this->guardarContextoSesion($clvUsu, $correo);

        $pendiente = $this->obtenerPendienteVigente($clvUsu);
        if ($pendiente !== null) {
            $ultimo = strtotime((string) ($pendiente['FechaUltimoEnvio'] ?? ''));
            if ($ultimo !== false && (time() - $ultimo) < self::COOLDOWN_SEGUNDOS) {
                return [
                    'ok' => true,
                    'enviado' => false,
                    'mensaje' =>
                        'Tu cuenta aún no ha verificado el correo. '
                        . 'Ingresa el código enviado o espera para reenviar.',
                ];
            }
        }

        return $this->crearYEnviar($clvUsu, $correo, $nombreDestino, true);
    }

    /**
     * @return array{ok: bool, mensaje: string, codigo?: string, enviado?: bool}
     */
    public function reenviar(): array
    {
        $ctx = $this->obtenerContextoSesion();
        if ($ctx === null) {
            return [
                'ok' => false,
                'codigo' => 'SIN_CONTEXTO',
                'mensaje' => 'No hay una verificación pendiente. Inicia sesión o regístrate.',
            ];
        }

        $clvUsu = (string) $ctx['ClvUsu'];
        $usuario = $this->usuarioModel->buscarPorCorreo(
            (string) ($ctx['correo'] ?? '')
        );
        if ($usuario === null) {
            $this->limpiarContextoSesion();
            return [
                'ok' => false,
                'codigo' => 'USUARIO',
                'mensaje' => 'No fue posible reenviar el código.',
            ];
        }

        if ((int) ($usuario['CorreoVerificado'] ?? 0) === 1) {
            $this->limpiarContextoSesion();
            return [
                'ok' => false,
                'codigo' => 'YA_VERIFICADO',
                'mensaje' => 'Tu correo ya está verificado. Puedes iniciar sesión.',
            ];
        }

        $pendiente = $this->obtenerPendienteVigente($clvUsu);
        if ($pendiente !== null) {
            $ultimo = strtotime((string) ($pendiente['FechaUltimoEnvio'] ?? ''));
            if ($ultimo !== false && (time() - $ultimo) < self::COOLDOWN_SEGUNDOS) {
                $resta = self::COOLDOWN_SEGUNDOS - (time() - $ultimo);
                return [
                    'ok' => false,
                    'codigo' => 'COOLDOWN',
                    'mensaje' =>
                        'Espera ' . max(1, $resta)
                        . ' segundo(s) antes de solicitar un nuevo código.',
                ];
            }

            if ((int) ($pendiente['NumReenvios'] ?? 0) >= self::MAX_REENVIOS) {
                return [
                    'ok' => false,
                    'codigo' => 'LIMITE_REENVIOS',
                    'mensaje' =>
                        'Se alcanzó el límite de reenvíos. Intenta más tarde o contacta al consultorio.',
                ];
            }
        }

        $correo = (string) ($usuario['CorreoUsu'] ?? '');
        $nombre = trim(
            ((string) ($usuario['NombrePer'] ?? '')) . ' '
            . ((string) ($usuario['ApPatPer'] ?? ''))
        );

        $resultado = $this->crearYEnviar($clvUsu, $correo, $nombre, false);
        $this->guardarContextoSesion($clvUsu, $correo);

        return $resultado;
    }

    /**
     * @return array{ok: bool, mensaje: string, codigo?: string, usuario?: array<string, mixed>}
     */
    public function validarCodigo(string $codigoPlano): array
    {
        $ctx = $this->obtenerContextoSesion();
        if ($ctx === null) {
            return [
                'ok' => false,
                'codigo' => 'SIN_CONTEXTO',
                'mensaje' => 'No hay una verificación pendiente. Inicia sesión o regístrate.',
            ];
        }

        $codigoPlano = preg_replace('/\D+/', '', $codigoPlano) ?? '';
        if (!preg_match('/^\d{6}$/', $codigoPlano)) {
            return [
                'ok' => false,
                'codigo' => 'FORMATO',
                'mensaje' => 'Ingresa el código de 6 dígitos.',
            ];
        }

        $clvUsu = (string) $ctx['ClvUsu'];

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "SELECT *
                 FROM verificacion_correo
                 WHERE ClvUsu = :u
                   AND Estado = 'PENDIENTE'
                 ORDER BY IdVerificacion DESC
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['u' => $clvUsu]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fila) {
                throw new RuntimeException(
                    'No hay un código vigente. Solicita uno nuevo.'
                );
            }

            $expira = strtotime((string) ($fila['FechaExpiracion'] ?? ''));
            if ($expira === false || $expira <= time()) {
                $this->db->prepare(
                    "UPDATE verificacion_correo
                     SET Estado = 'EXPIRADA'
                     WHERE IdVerificacion = :id"
                )->execute(['id' => (int) $fila['IdVerificacion']]);
                $this->db->commit();

                return [
                    'ok' => false,
                    'codigo' => 'EXPIRADO',
                    'mensaje' => 'El código ha expirado. Solicita uno nuevo.',
                ];
            }

            $intentos = (int) ($fila['Intentos'] ?? 0);
            if ($intentos >= self::MAX_INTENTOS) {
                $this->db->prepare(
                    "UPDATE verificacion_correo
                     SET Estado = 'REVOCADA'
                     WHERE IdVerificacion = :id"
                )->execute(['id' => (int) $fila['IdVerificacion']]);
                $this->db->commit();

                return [
                    'ok' => false,
                    'codigo' => 'INTENTOS',
                    'mensaje' =>
                        'Se agotaron los intentos. Solicita un código nuevo.',
                ];
            }

            if (!password_verify($codigoPlano, (string) $fila['CodigoHash'])) {
                $nuevo = $intentos + 1;
                $estado = $nuevo >= self::MAX_INTENTOS ? 'REVOCADA' : 'PENDIENTE';
                $this->db->prepare(
                    "UPDATE verificacion_correo
                     SET Intentos = :i, Estado = :e
                     WHERE IdVerificacion = :id"
                )->execute([
                    'i' => $nuevo,
                    'e' => $estado,
                    'id' => (int) $fila['IdVerificacion'],
                ]);
                $this->db->commit();

                if ($estado === 'REVOCADA') {
                    return [
                        'ok' => false,
                        'codigo' => 'INTENTOS',
                        'mensaje' =>
                            'Se agotaron los intentos. Solicita un código nuevo.',
                    ];
                }

                return [
                    'ok' => false,
                    'codigo' => 'INVALIDO',
                    'mensaje' =>
                        'El código no es válido. Te quedan '
                        . (self::MAX_INTENTOS - $nuevo)
                        . ' intento(s).',
                ];
            }

            $this->db->prepare(
                "UPDATE verificacion_correo
                 SET Estado = 'USADA', FechaUso = NOW()
                 WHERE IdVerificacion = :id"
            )->execute(['id' => (int) $fila['IdVerificacion']]);

            $this->db->prepare(
                "UPDATE verificacion_correo
                 SET Estado = 'REVOCADA'
                 WHERE ClvUsu = :u
                   AND Estado = 'PENDIENTE'
                   AND IdVerificacion <> :id"
            )->execute([
                'u' => $clvUsu,
                'id' => (int) $fila['IdVerificacion'],
            ]);

            $upd = $this->db->prepare(
                "UPDATE usuario
                 SET CorreoVerificado = 1,
                     FechaVerificacionCorreo = NOW()
                 WHERE ClvUsu = :u"
            );
            $upd->execute(['u' => $clvUsu]);

            $this->db->commit();
        } catch (RuntimeException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'codigo' => 'ERROR',
                'mensaje' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'codigo' => 'ERROR',
                'mensaje' => 'No fue posible verificar el código. Intenta nuevamente.',
            ];
        }

        $correoCtx = (string) ($ctx['correo'] ?? '');
        $usuario = $correoCtx !== ''
            ? $this->usuarioModel->buscarPorCorreo($correoCtx)
            : null;

        if (!is_array($usuario) || (string) ($usuario['ClvUsu'] ?? '') === '') {
            return [
                'ok' => false,
                'codigo' => 'USUARIO',
                'mensaje' => 'La verificación se registró, pero no fue posible iniciar sesión.',
            ];
        }

        $this->limpiarContextoSesion();

        return [
            'ok' => true,
            'mensaje' => 'Correo verificado correctamente.',
            'usuario' => $usuario,
        ];
    }

    /**
     * Marca correo verificado (activación por enlace / cambio de correo).
     */
    public function marcarCorreoVerificado(string $clvUsu): void
    {
        if (!$this->columnaExiste('usuario', 'CorreoVerificado')) {
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE usuario
             SET CorreoVerificado = 1,
                 FechaVerificacionCorreo = NOW()
             WHERE ClvUsu = :u"
        );
        $stmt->execute(['u' => trim($clvUsu)]);
    }

    /**
     * @return array{ClvUsu: string, correo: string, correo_mascarado: string, FechaInicio: string}|null
     */
    public function obtenerContextoSesion(): ?array
    {
        $ctx = Session::get(self::SESION_CLAVE);
        if (!is_array($ctx)) {
            return null;
        }

        $clvUsu = trim((string) ($ctx['ClvUsu'] ?? ''));
        if ($clvUsu === '') {
            return null;
        }

        return [
            'ClvUsu' => $clvUsu,
            'correo' => (string) ($ctx['correo'] ?? ''),
            'correo_mascarado' => (string) ($ctx['correo_mascarado'] ?? ''),
            'FechaInicio' => (string) ($ctx['FechaInicio'] ?? ''),
        ];
    }

    public function limpiarContextoSesion(): void
    {
        Session::remove(self::SESION_CLAVE);
    }

    public static function enmascararCorreo(string $correo): string
    {
        $correo = strtolower(trim($correo));
        $partes = explode('@', $correo, 2);
        if (count($partes) !== 2) {
            return '***';
        }

        $local = $partes[0];
        $dominio = $partes[1];
        $visible = mb_substr($local, 0, min(3, mb_strlen($local)));

        return $visible . '***@' . $dominio;
    }

    public function segundosCooldownRestantes(string $clvUsu): int
    {
        $pendiente = $this->obtenerPendienteVigente($clvUsu);
        if ($pendiente === null) {
            return 0;
        }

        $ultimo = strtotime((string) ($pendiente['FechaUltimoEnvio'] ?? ''));
        if ($ultimo === false) {
            return 0;
        }

        $resta = self::COOLDOWN_SEGUNDOS - (time() - $ultimo);

        return max(0, $resta);
    }

    /**
     * @return array{ok: bool, mensaje: string, codigo?: string, enviado?: bool}
     */
    private function crearYEnviar(
        string $clvUsu,
        string $correo,
        string $nombreDestino,
        bool $esPrimerEnvio
    ): array {
        $codigoPlano = (string) random_int(100000, 999999);
        $hash = password_hash($codigoPlano, PASSWORD_DEFAULT);
        $ahora = date('Y-m-d H:i:s');
        $expira = date(
            'Y-m-d H:i:s',
            time() + (self::EXPIRACION_MINUTOS * 60)
        );

        $numReenvios = 0;
        if (!$esPrimerEnvio) {
            $prev = $this->obtenerPendienteVigente($clvUsu);
            $numReenvios = $prev !== null
                ? ((int) ($prev['NumReenvios'] ?? 0) + 1)
                : 1;
        }

        try {
            $this->db->beginTransaction();

            $this->db->prepare(
                "UPDATE verificacion_correo
                 SET Estado = 'REVOCADA'
                 WHERE ClvUsu = :u
                   AND Estado = 'PENDIENTE'"
            )->execute(['u' => $clvUsu]);

            $this->db->prepare(
                "INSERT INTO verificacion_correo (
                    ClvUsu, CodigoHash, FechaCreacion, FechaExpiracion,
                    Intentos, NumReenvios, Estado, FechaUltimoEnvio
                 ) VALUES (
                    :u, :hash, :cre, :exp, 0, :reenvios, 'PENDIENTE', :envio
                 )"
            )->execute([
                'u' => $clvUsu,
                'hash' => $hash,
                'cre' => $ahora,
                'exp' => $expira,
                'reenvios' => $numReenvios,
                'envio' => $ahora,
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'codigo' => 'BD',
                'mensaje' => 'No fue posible generar el código de verificación.',
            ];
        }

        $dryRun = (string) Config::get(
            'MAIL_VERIFICACION_DRY_RUN',
            '0'
        ) === '1';

        $enviado = false;
        try {
            $nombreCons = $this->resolverNombreConsultorio();
            (new MailService())->enviarCodigoVerificacionCorreo(
                $correo,
                $nombreDestino !== '' ? $nombreDestino : 'Usuario',
                $codigoPlano,
                $nombreCons
            );
            $enviado = true;
        } catch (Throwable $e) {
            error_log(
                'VerificacionCorreo: fallo SMTP controlado (sin credenciales).'
            );
        }

        $base = [
            'ok' => true,
            'enviado' => $enviado || $dryRun,
            'mensaje' => ($enviado || $dryRun)
                ? (
                    'Te enviamos un código de 6 dígitos a tu correo. '
                    . 'Vence en ' . self::EXPIRACION_MINUTOS . ' minutos.'
                )
                : (
                    'Tu cuenta fue creada, pero no pudimos enviar el correo ahora. '
                    . 'Usa “Reenviar código” en unos momentos.'
                ),
        ];

        // Solo pruebas CLI con dry-run: permite validar sin SMTP real.
        if ($dryRun && PHP_SAPI === 'cli') {
            $base['_codigo_prueba'] = $codigoPlano;
        }

        return $base;
    }

    private function guardarContextoSesion(string $clvUsu, string $correo): void
    {
        Session::set(self::SESION_CLAVE, [
            'ClvUsu' => trim($clvUsu),
            'correo' => strtolower(trim($correo)),
            'correo_mascarado' => self::enmascararCorreo($correo),
            'FechaInicio' => date('c'),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerPendienteVigente(string $clvUsu): ?array
    {
        if (!$this->tablaExiste('verificacion_correo')) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT *
             FROM verificacion_correo
             WHERE ClvUsu = :u
               AND Estado = 'PENDIENTE'
               AND FechaExpiracion > NOW()
             ORDER BY IdVerificacion DESC
             LIMIT 1"
        );
        $stmt->execute(['u' => $clvUsu]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    private function resolverNombreConsultorio(): string
    {
        try {
            $estado = (new InstalacionConsultorioService())->resolver();
            $nombre = trim((string) ($estado['consultorio']['NombreCons'] ?? ''));
            if ($nombre !== '') {
                return $nombre;
            }
        } catch (Throwable $e) {
        }

        return 'Consultorio';
    }

    private function tablaExiste(string $tabla): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $tabla]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnaExiste(string $tabla, string $columna): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
               AND COLUMN_NAME = :c'
        );
        $stmt->execute(['t' => $tabla, 'c' => $columna]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
