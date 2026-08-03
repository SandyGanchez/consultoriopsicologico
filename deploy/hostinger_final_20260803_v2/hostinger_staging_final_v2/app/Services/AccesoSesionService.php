<?php

namespace App\Services;

use App\Config\Database;
use App\Core\Response;
use App\Core\Session;
use PDO;

/**
 * Validación central de sesión activa (una vez por petición).
 * Evita que una cuenta inactivada en BD siga usando una sesión abierta.
 */
class AccesoSesionService
{
    public const MENSAJE_INACTIVA =
        'Tu cuenta se encuentra inactiva. Comunícate con soporte.';

    /** @var array<string, mixed>|null */
    private static ?array $cacheUsuario = null;

    private static bool $validado = false;

    /**
     * @return array<string, mixed> Usuario fresco desde BD
     */
    public function exigirSesionActiva(string $rolEsperado): array
    {
        $eval = $this->evaluarSesionActiva($rolEsperado);

        if (empty($eval['ok'])) {
            $motivo = (string) ($eval['motivo'] ?? 'login');

            if ($motivo === 'inactiva') {
                $this->cerrarPorInactividad(true);
            }

            if ($motivo === 'vinculo' || $motivo === 'instalacion') {
                Session::set(
                    'error',
                    (string) ($eval['mensaje'] ?? 'No fue posible validar la sesión.')
                );
                Session::destroy();
                Session::regenerar();
                Response::redirect('login');
            }

            Session::destroy();
            Session::regenerar();
            Response::redirect('login');
        }

        /** @var array<string, mixed> $usuario */
        $usuario = $eval['usuario'];
        $sesion = Session::get('usuario');
        $sesion = is_array($sesion) ? $sesion : [];

        Session::set('usuario', array_merge($sesion, [
            'ClvUsu' => $usuario['ClvUsu'],
            'CorreoUsu' => $usuario['CorreoUsu'] ?? ($sesion['CorreoUsu'] ?? ''),
            'RolUsu' => $usuario['RolUsu'],
            'EstadoUsu' => (int) $usuario['EstadoUsu'],
            'RequiereCambioContrasena' => (int) (
                $usuario['RequiereCambioContrasena'] ?? 0
            )
        ]));

        return $usuario;
    }

    /**
     * Evaluación sin redirección (útil para pruebas CLI).
     *
     * @return array{
     *   ok: bool,
     *   motivo?: string,
     *   mensaje?: string,
     *   usuario?: array<string, mixed>
     * }
     */
    public function evaluarSesionActiva(string $rolEsperado): array
    {
        $rolEsperado = strtoupper(trim($rolEsperado));

        if (!Session::has('usuario')) {
            return ['ok' => false, 'motivo' => 'sin_sesion'];
        }

        $sesion = Session::get('usuario');

        if (!is_array($sesion)) {
            return ['ok' => false, 'motivo' => 'sesion_invalida'];
        }

        $clvUsu = trim((string) ($sesion['ClvUsu'] ?? ''));

        if ($clvUsu === '') {
            return ['ok' => false, 'motivo' => 'sesion_invalida'];
        }

        $usuario = $this->obtenerUsuarioFresco($clvUsu);

        if ($usuario === null) {
            return ['ok' => false, 'motivo' => 'usuario_ausente'];
        }

        $rol = strtoupper(trim((string) ($usuario['RolUsu'] ?? '')));

        if ($rol !== $rolEsperado) {
            return ['ok' => false, 'motivo' => 'rol'];
        }

        if ((int) ($usuario['EstadoUsu'] ?? 0) !== 1) {
            return ['ok' => false, 'motivo' => 'inactiva'];
        }

        if ($rol === 'CONSULTORIO') {
            $vinculo = $this->obtenerVinculoConsultorio($clvUsu);

            if ($vinculo === null) {
                return [
                    'ok' => false,
                    'motivo' => 'vinculo',
                    'mensaje' => 'La cuenta no está asociada a un consultorio válido.'
                ];
            }

            $instalacion = (new InstalacionConsultorioService())->resolver();

            if ($instalacion['estado'] === InstalacionConsultorioService::ESTADO_MULTIPLE) {
                return [
                    'ok' => false,
                    'motivo' => 'instalacion',
                    'mensaje' =>
                        'La instalación tiene una configuración inconsistente. Comunícate con soporte.'
                ];
            }

            if (
                $instalacion['estado'] === InstalacionConsultorioService::ESTADO_UNICO
                && strtoupper((string) ($instalacion['consultorio']['ClvCons'] ?? ''))
                    !== strtoupper((string) ($vinculo['ClvCons'] ?? ''))
            ) {
                return [
                    'ok' => false,
                    'motivo' => 'instalacion',
                    'mensaje' =>
                        'La cuenta no corresponde al consultorio de esta instalación.'
                ];
            }

            $usuario['_vinculo_consultorio'] = $vinculo;
        }

        return ['ok' => true, 'usuario' => $usuario];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerUsuarioFresco(string $clvUsu): ?array
    {
        if (
            self::$validado
            && is_array(self::$cacheUsuario)
            && (string) (self::$cacheUsuario['ClvUsu'] ?? '') === $clvUsu
        ) {
            return self::$cacheUsuario;
        }

        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT
                ClvUsu,
                CorreoUsu,
                TelefonoUsu,
                EstadoUsu,
                RequiereCambioContrasena,
                RolUsu,
                ClvPer
             FROM usuario
             WHERE ClvUsu = :u
             LIMIT 1"
        );
        $stmt->execute(['u' => $clvUsu]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        self::$cacheUsuario = $fila ?: null;
        self::$validado = true;

        return self::$cacheUsuario;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerVinculoConsultorio(string $clvUsu): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT
                cu.ClvConsUsu,
                cu.ClvCons,
                cu.ClvUsu,
                cu.EsResponsable,
                cu.EstatusConsUsu,
                c.EstatusCons,
                c.NombreCons
             FROM consultorio_usuario cu
             INNER JOIN consultorio c ON c.ClvCons = cu.ClvCons
             WHERE cu.ClvUsu = :u
               AND cu.EstatusConsUsu = 'ACTIVO'
             LIMIT 1"
        );
        $stmt->execute(['u' => $clvUsu]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    private function cerrarPorInactividad(bool $mensajeInactiva): void
    {
        Session::destroy();
        Session::regenerar();

        if ($mensajeInactiva) {
            Session::set('error', self::MENSAJE_INACTIVA);
        }

        Response::redirect('login');
    }

    public static function resetCache(): void
    {
        self::$cacheUsuario = null;
        self::$validado = false;
    }
}
