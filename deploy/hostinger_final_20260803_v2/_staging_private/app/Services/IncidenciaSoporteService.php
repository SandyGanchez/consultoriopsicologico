<?php

namespace App\Services;

use App\Config\Database;
use App\Models\IncidenciaSoporte;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class IncidenciaSoporteService
{
    public const TIPOS = [
        'AUTENTICACION',
        'CUENTA_BLOQUEADA',
        'ACTIVACION',
        'RECUPERACION',
        'CAMBIO_CORREO',
        'OTRO_ACCESO',
    ];

    public const ESTADOS = [
        'PENDIENTE',
        'EN_PROCESO',
        'RESUELTA',
    ];

    public const ROL_DESTINO_CONSULTORIO = 'CONSULTORIO';
    public const ROL_DESTINO_ADMINISTRADOR = 'ADMINISTRADOR';
    public const NIVEL_PRIMER = 'PRIMER_NIVEL';
    public const NIVEL_ESCALADA = 'ESCALADA';

    private const TRANSICIONES = [
        'PENDIENTE' => ['EN_PROCESO', 'RESUELTA'],
        'EN_PROCESO' => ['RESUELTA'],
        'RESUELTA' => [],
    ];

    private IncidenciaSoporte $model;

    public function __construct()
    {
        $this->model = new IncidenciaSoporte();
    }

    public function moduloDisponible(): bool
    {
        return $this->model->tablaExiste();
    }

    /**
     * Registro público desde login. Siempre respuesta neutra.
     * El rol se resuelve solo desde BD (nunca desde POST).
     *
     * @return array{ok: bool, mensaje: string, id?: int}
     */
    public function registrarDesdeLogin(array $post, ?string $ipHash = null): array
    {
        $mensajeNeutro =
            'Si la información corresponde a una cuenta registrada, la solicitud será '
            . 'revisada por soporte.';

        if (!$this->moduloDisponible()) {
            return ['ok' => true, 'mensaje' => $mensajeNeutro];
        }

        if ($this->excedeRateLimit($ipHash)) {
            error_log('INCIDENCIA_SOPORTE: rate_limit');
            return ['ok' => true, 'mensaje' => $mensajeNeutro];
        }

        try {
            $correo = $this->normalizarCorreo((string) ($post['correo'] ?? ''));
            $tipo = strtoupper(trim((string) ($post['tipo'] ?? '')));
            $descripcion = $this->sanitizarDescripcion(
                (string) ($post['descripcion'] ?? '')
            );

            // No confiar en rol u otros campos de identidad del POST.
            unset(
                $post['rol'],
                $post['RolUsu'],
                $post['RolDestino'],
                $post['ClvUsu'],
                $post['ClvUsuSolicitante']
            );

            if (
                $correo === ''
                || !filter_var($correo, FILTER_VALIDATE_EMAIL)
                || !in_array($tipo, self::TIPOS, true)
                || mb_strlen($descripcion) < 10
                || mb_strlen($descripcion) > 1000
            ) {
                return ['ok' => true, 'mensaje' => $mensajeNeutro];
            }

            $instalacion = (new InstalacionConsultorioService())->resolver();

            if ($instalacion['estado'] !== InstalacionConsultorioService::ESTADO_UNICO) {
                return ['ok' => true, 'mensaje' => $mensajeNeutro];
            }

            $clvCons = strtoupper(trim(
                (string) ($instalacion['consultorio']['ClvCons'] ?? '')
            ));

            if ($clvCons === '') {
                return ['ok' => true, 'mensaje' => $mensajeNeutro];
            }

            if ($this->model->existeDuplicadoReciente($correo, $tipo, $descripcion, 60)) {
                return ['ok' => true, 'mensaje' => $mensajeNeutro];
            }

            $usuario = $this->buscarUsuarioPorCorreo($correo);
            $clvUsu = $usuario['ClvUsu'] ?? null;
            $rolUsu = strtoupper(trim((string) ($usuario['RolUsu'] ?? '')));

            if ($rolUsu === 'ADMINISTRADOR') {
                error_log('INCIDENCIA_SOPORTE: admin_self_help');
                return ['ok' => true, 'mensaje' => $mensajeNeutro];
            }

            if (in_array($rolUsu, ['PACIENTE', 'PSICOLOGO'], true)) {
                $rolDestino = self::ROL_DESTINO_CONSULTORIO;
                $nivel = self::NIVEL_PRIMER;
            } elseif ($rolUsu === 'CONSULTORIO') {
                $rolDestino = self::ROL_DESTINO_ADMINISTRADOR;
                $nivel = self::NIVEL_PRIMER;
            } else {
                // Sin usuario o rol no reconocido → primer nivel consultorio.
                $rolDestino = self::ROL_DESTINO_CONSULTORIO;
                $nivel = self::NIVEL_PRIMER;
                $clvUsu = null;
            }

            $id = $this->model->insertar([
                'ClvCons' => $clvCons,
                'ClvUsuSolicitante' => $clvUsu,
                'CorreoReportado' => $correo,
                'TipoIncidencia' => $tipo,
                'RolDestino' => $rolDestino,
                'NivelAtencion' => $nivel,
                'IdIncidenciaOrigen' => null,
                'Descripcion' => $descripcion,
            ]);

            $this->registrarRateLimit($ipHash);

            $notif = new NotificacionService();
            $titulo = 'Nueva incidencia de acceso';
            $mensaje = 'Se registró la incidencia #' . $id
                . ' (' . $this->etiquetaTipo($tipo) . ').';

            if ($rolDestino === self::ROL_DESTINO_ADMINISTRADOR) {
                $notif->notificarAdministradoresSistema(
                    $titulo,
                    $mensaje,
                    'SISTEMA'
                );
            } else {
                $notif->notificarConsultorioSistema(
                    $clvCons,
                    $titulo,
                    $mensaje,
                    'SISTEMA'
                );
            }

            return ['ok' => true, 'mensaje' => $mensajeNeutro, 'id' => $id];
        } catch (Throwable $e) {
            error_log('INCIDENCIA_SOPORTE: registro_fallido');
            return ['ok' => true, 'mensaje' => $mensajeNeutro];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParaConsultorio(string $clvCons): array
    {
        $this->exigirModulo();

        return $this->model->listarPorDestino(
            $clvCons,
            self::ROL_DESTINO_CONSULTORIO
        );
    }

    public function contarAbiertasConsultorio(string $clvCons): int
    {
        if (!$this->moduloDisponible()) {
            return 0;
        }

        try {
            return $this->model->contarAbiertasPorDestino(
                trim($clvCons),
                self::ROL_DESTINO_CONSULTORIO
            );
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParaAdministrador(): array
    {
        $this->exigirModulo();
        $clvCons = $this->clvConsUnico();

        return $this->model->listarPorDestino(
            $clvCons,
            self::ROL_DESTINO_ADMINISTRADOR
        );
    }

    public function contarAbiertasAdministrador(): int
    {
        if (!$this->moduloDisponible()) {
            return 0;
        }

        try {
            return $this->model->contarAbiertasPorDestino(
                $this->clvConsUnico(),
                self::ROL_DESTINO_ADMINISTRADOR
            );
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Compatibilidad navbar_admin / dashboard: solo destino ADMINISTRADOR.
     *
     * @return list<array<string, mixed>>
     * @deprecated Usar listarParaAdministrador()
     */
    public function listarInstalacion(): array
    {
        return $this->listarParaAdministrador();
    }

    /**
     * Compatibilidad navbar_admin / dashboard: solo destino ADMINISTRADOR.
     *
     * @deprecated Usar contarAbiertasAdministrador()
     */
    public function contarAbiertasInstalacion(): int
    {
        return $this->contarAbiertasAdministrador();
    }

    public function obtenerDetalleConsultorio(int $id, string $clvCons): array
    {
        $this->exigirModulo();
        $row = $this->model->obtenerPorIdYConsultorioDestino(
            $id,
            $clvCons,
            self::ROL_DESTINO_CONSULTORIO
        );

        if (!$row) {
            throw new RuntimeException(
                'La incidencia no está disponible.'
            );
        }

        $row['EscaladaHija'] = $this->model->obtenerEscaladaHija($id);

        return $row;
    }

    public function obtenerDetalleAdministrador(int $id): array
    {
        $this->exigirModulo();
        $clvCons = $this->clvConsUnico();
        $row = $this->model->obtenerPorIdYConsultorioDestino(
            $id,
            $clvCons,
            self::ROL_DESTINO_ADMINISTRADOR
        );

        if (!$row) {
            throw new RuntimeException(
                'La incidencia no está disponible.'
            );
        }

        return $row;
    }

    /**
     * @deprecated Usar obtenerDetalleAdministrador()
     */
    public function obtenerDetalle(int $id): array
    {
        return $this->obtenerDetalleAdministrador($id);
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function cambiarEstadoConsultorio(
        int $id,
        string $nuevoEstado,
        string $observacion,
        string $clvUsuCons,
        string $clvCons
    ): array {
        $this->exigirModulo();

        $nuevoEstado = strtoupper(trim($nuevoEstado));
        $observacion = $this->sanitizarDescripcion($observacion);
        $clvUsuCons = trim($clvUsuCons);
        $clvCons = strtoupper(trim($clvCons));

        if (!in_array($nuevoEstado, ['EN_PROCESO', 'RESUELTA'], true)) {
            throw new InvalidArgumentException('Estado no permitido.');
        }

        if ($nuevoEstado === 'RESUELTA' && mb_strlen($observacion) < 5) {
            throw new InvalidArgumentException(
                'La observación es obligatoria para marcar como resuelta.'
            );
        }

        if ($clvUsuCons === '' || $clvCons === '') {
            throw new InvalidArgumentException('Sesión de consultorio no válida.');
        }

        $db = Database::connect();
        $db->beginTransaction();

        try {
            $actual = $this->model->bloquearParaActualizar($id, $clvCons);

            if (!$actual) {
                throw new RuntimeException(
                    'La incidencia no está disponible.'
                );
            }

            if (
                strtoupper(trim((string) ($actual['RolDestino'] ?? '')))
                !== self::ROL_DESTINO_CONSULTORIO
            ) {
                throw new RuntimeException(
                    'La incidencia no está disponible.'
                );
            }

            $estadoActual = (string) ($actual['EstadoIncidencia'] ?? '');
            $permitidos = self::TRANSICIONES[$estadoActual] ?? [];

            if (!in_array($nuevoEstado, $permitidos, true)) {
                throw new InvalidArgumentException(
                    'La transición de estado no está permitida.'
                );
            }

            $obs = $nuevoEstado === 'RESUELTA'
                ? $observacion
                : (trim((string) ($actual['ObservacionConsultorio'] ?? '')) !== ''
                    ? (string) $actual['ObservacionConsultorio']
                    : ($observacion !== '' ? $observacion : null));

            $this->model->actualizarEstado([
                'IdIncidencia' => $id,
                'ClvCons' => $clvCons,
                'EstadoIncidencia' => $nuevoEstado,
                'ObservacionConsultorio' => $obs,
                'ClvUsuAtencion' => $clvUsuCons,
                'FechaResolucion' => $nuevoEstado === 'RESUELTA'
                    ? date('Y-m-d H:i:s')
                    : null,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Sin notificación clínica ni a sí mismos al actualizar estado.

        return [
            'ok' => true,
            'mensaje' => 'La incidencia fue actualizada correctamente.',
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function cambiarEstadoAdministrador(
        int $id,
        string $nuevoEstado,
        string $observacion,
        string $clvUsuAdmin
    ): array {
        $this->exigirModulo();

        $nuevoEstado = strtoupper(trim($nuevoEstado));
        $observacion = $this->sanitizarDescripcion($observacion);
        $clvUsuAdmin = trim($clvUsuAdmin);
        $clvCons = $this->clvConsUnico();

        if (!in_array($nuevoEstado, ['EN_PROCESO', 'RESUELTA'], true)) {
            throw new InvalidArgumentException('Estado no permitido.');
        }

        if ($nuevoEstado === 'RESUELTA' && mb_strlen($observacion) < 5) {
            throw new InvalidArgumentException(
                'La observación es obligatoria para marcar como resuelta.'
            );
        }

        if ($clvUsuAdmin === '') {
            throw new InvalidArgumentException('Administrador no válido.');
        }

        $db = Database::connect();
        $db->beginTransaction();
        $nivelAtencion = self::NIVEL_PRIMER;
        $idOrigen = null;

        try {
            $actual = $this->model->bloquearParaActualizar($id, $clvCons);

            if (!$actual) {
                throw new RuntimeException(
                    'La incidencia no está disponible.'
                );
            }

            if (
                strtoupper(trim((string) ($actual['RolDestino'] ?? '')))
                !== self::ROL_DESTINO_ADMINISTRADOR
            ) {
                throw new RuntimeException(
                    'La incidencia no está disponible.'
                );
            }

            $nivelAtencion = strtoupper(trim(
                (string) ($actual['NivelAtencion'] ?? self::NIVEL_PRIMER)
            ));
            $idOrigen = isset($actual['IdIncidenciaOrigen'])
                ? (int) $actual['IdIncidenciaOrigen']
                : null;

            $estadoActual = (string) ($actual['EstadoIncidencia'] ?? '');
            $permitidos = self::TRANSICIONES[$estadoActual] ?? [];

            if (!in_array($nuevoEstado, $permitidos, true)) {
                throw new InvalidArgumentException(
                    'La transición de estado no está permitida.'
                );
            }

            $obs = $nuevoEstado === 'RESUELTA'
                ? $observacion
                : (trim((string) ($actual['ObservacionAdministrador'] ?? '')) !== ''
                    ? (string) $actual['ObservacionAdministrador']
                    : ($observacion !== '' ? $observacion : null));

            $this->model->actualizarEstado([
                'IdIncidencia' => $id,
                'ClvCons' => $clvCons,
                'EstadoIncidencia' => $nuevoEstado,
                'ObservacionAdministrador' => $obs,
                'ClvUsuAtencion' => $clvUsuAdmin,
                'FechaResolucion' => $nuevoEstado === 'RESUELTA'
                    ? date('Y-m-d H:i:s')
                    : null,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if (
            $nuevoEstado === 'RESUELTA'
            && $nivelAtencion === self::NIVEL_ESCALADA
        ) {
            $folioOrigen = $idOrigen > 0 ? $idOrigen : $id;
            (new NotificacionService())->notificarConsultorioSistema(
                $clvCons,
                'Incidencia escalada atendida',
                'Tu incidencia escalada #' . $folioOrigen
                . ' fue atendida.',
                'SISTEMA'
            );
        }

        return [
            'ok' => true,
            'mensaje' => 'La incidencia fue actualizada correctamente.',
        ];
    }

    /**
     * @deprecated Usar cambiarEstadoAdministrador()
     * @return array{ok: bool, mensaje: string}
     */
    public function cambiarEstado(
        int $id,
        string $nuevoEstado,
        string $observacion,
        string $clvUsuAdmin
    ): array {
        return $this->cambiarEstadoAdministrador(
            $id,
            $nuevoEstado,
            $observacion,
            $clvUsuAdmin
        );
    }

    /**
     * Escala una incidencia de primer nivel (consultorio) a soporte técnico.
     *
     * @return array{ok: bool, mensaje: string, id_hija: int}
     */
    public function escalarAAdministrador(
        int $idOrigen,
        string $descripcionTecnica,
        string $clvUsuCons,
        string $clvCons
    ): array {
        $this->exigirModulo();

        $descripcionTecnica = $this->sanitizarDescripcion($descripcionTecnica);
        $clvUsuCons = trim($clvUsuCons);
        $clvCons = strtoupper(trim($clvCons));

        if (
            mb_strlen($descripcionTecnica) < 10
            || mb_strlen($descripcionTecnica) > 1000
        ) {
            throw new InvalidArgumentException(
                'Describe el problema técnico (entre 10 y 1000 caracteres).'
            );
        }

        if ($clvUsuCons === '' || $clvCons === '') {
            throw new InvalidArgumentException('Sesión de consultorio no válida.');
        }

        $db = Database::connect();
        $db->beginTransaction();
        $idHija = 0;

        try {
            $actual = $this->model->bloquearParaActualizar($idOrigen, $clvCons);

            if (!$actual) {
                throw new RuntimeException(
                    'La incidencia no está disponible.'
                );
            }

            if (
                strtoupper(trim((string) ($actual['RolDestino'] ?? '')))
                !== self::ROL_DESTINO_CONSULTORIO
            ) {
                throw new RuntimeException(
                    'Solo se pueden escalar incidencias de primer nivel del consultorio.'
                );
            }

            $estadoActual = strtoupper(trim(
                (string) ($actual['EstadoIncidencia'] ?? '')
            ));

            if ($estadoActual === 'RESUELTA') {
                throw new InvalidArgumentException(
                    'No se puede escalar una incidencia ya resuelta.'
                );
            }

            if ($this->model->tieneEscaladaActiva($idOrigen)) {
                throw new InvalidArgumentException(
                    'Ya existe una escalada activa para esta incidencia.'
                );
            }

            $idHija = $this->model->insertarEscalada(
                $clvCons,
                isset($actual['ClvUsuSolicitante'])
                    && trim((string) $actual['ClvUsuSolicitante']) !== ''
                    ? (string) $actual['ClvUsuSolicitante']
                    : null,
                (string) ($actual['CorreoReportado'] ?? ''),
                (string) ($actual['TipoIncidencia'] ?? 'OTRO_ACCESO'),
                $descripcionTecnica,
                $idOrigen
            );

            $this->model->actualizarEstado([
                'IdIncidencia' => $idOrigen,
                'ClvCons' => $clvCons,
                'EstadoIncidencia' => 'EN_PROCESO',
                'ObservacionConsultorio' => 'Escalada a soporte técnico',
                'ClvUsuAtencion' => $clvUsuCons,
                'FechaResolucion' => null,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        (new NotificacionService())->notificarAdministradoresSistema(
            'Incidencia escalada a soporte',
            'Se escaló la incidencia #' . $idOrigen
            . ' (ticket técnico #' . $idHija . ').',
            'SISTEMA'
        );

        return [
            'ok' => true,
            'mensaje' => 'La incidencia fue escalada a soporte técnico.',
            'id_hija' => $idHija,
        ];
    }

    public function ocultarCorreo(string $correo): string
    {
        $correo = strtolower(trim($correo));
        $partes = explode('@', $correo, 2);

        if (count($partes) !== 2) {
            return '***';
        }

        $local = $partes[0];
        $dominio = $partes[1];
        $visible = mb_substr($local, 0, 2);

        return $visible . str_repeat('*', max(3, mb_strlen($local) - 2))
            . '@' . $dominio;
    }

    public function etiquetaTipo(string $tipo): string
    {
        return match (strtoupper(trim($tipo))) {
            'AUTENTICACION' => 'Autenticación',
            'CUENTA_BLOQUEADA' => 'Cuenta bloqueada',
            'ACTIVACION' => 'Activación',
            'RECUPERACION' => 'Recuperación',
            'CAMBIO_CORREO' => 'Cambio de correo',
            'OTRO_ACCESO' => 'Otro acceso',
            default => 'Acceso',
        };
    }

    public function etiquetaNivel(string $nivel): string
    {
        return match (strtoupper(trim($nivel))) {
            self::NIVEL_ESCALADA => 'Escalada',
            default => 'Primer nivel',
        };
    }

    private function exigirModulo(): void
    {
        if (!$this->moduloDisponible()) {
            throw new RuntimeException(
                'El módulo de incidencias aún no está disponible.'
            );
        }
    }

    private function clvConsUnico(): string
    {
        $estado = (new InstalacionConsultorioService())->resolver();

        if ($estado['estado'] !== InstalacionConsultorioService::ESTADO_UNICO) {
            throw new RuntimeException(
                'La instalación no tiene un único consultorio.'
            );
        }

        $clv = strtoupper(trim(
            (string) ($estado['consultorio']['ClvCons'] ?? '')
        ));

        if ($clv === '') {
            throw new RuntimeException(
                'No fue posible identificar el consultorio.'
            );
        }

        return $clv;
    }

    private function normalizarCorreo(string $correo): string
    {
        return strtolower(trim($correo));
    }

    private function sanitizarDescripcion(string $texto): string
    {
        $texto = trim(strip_tags($texto));
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    /**
     * @return array{ClvUsu: string, RolUsu: string}|null
     */
    private function buscarUsuarioPorCorreo(string $correo): ?array
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare(
                "SELECT ClvUsu, RolUsu
                 FROM usuario
                 WHERE LOWER(CorreoUsu) = :correo
                 LIMIT 1"
            );
            $stmt->execute(['correo' => $correo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            return [
                'ClvUsu' => (string) $row['ClvUsu'],
                'RolUsu' => (string) ($row['RolUsu'] ?? ''),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    private function rateLimitKey(?string $ipHash): string
    {
        $base = $ipHash !== null && $ipHash !== ''
            ? $ipHash
            : 'anon';

        return 'incidencia_rl_' . hash('sha256', $base);
    }

    private function excedeRateLimit(?string $ipHash): bool
    {
        $key = $this->rateLimitKey($ipHash);
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $key . '.json';

        if (!is_file($file)) {
            return false;
        }

        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($data)) {
            return false;
        }

        $ventana = (int) ($data['inicio'] ?? 0);
        $count = (int) ($data['count'] ?? 0);
        $ahora = time();

        if ($ventana < ($ahora - 900)) {
            @unlink($file);
            return false;
        }

        // Máx. 5 solicitudes / 15 min por IP hash
        return $count >= 5;
    }

    private function registrarRateLimit(?string $ipHash): void
    {
        $key = $this->rateLimitKey($ipHash);
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $key . '.json';
        $ahora = time();
        $data = ['inicio' => $ahora, 'count' => 1];

        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $prev = is_string($raw) ? json_decode($raw, true) : null;
            if (
                is_array($prev)
                && (int) ($prev['inicio'] ?? 0) >= ($ahora - 900)
            ) {
                $data['inicio'] = (int) $prev['inicio'];
                $data['count'] = (int) ($prev['count'] ?? 0) + 1;
            }
        }

        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
