<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Services\ActivacionCuentaService;

/**
 * Consumo exclusivo de tokens RECUPERACION_CONSULTORIO.
 * No reutiliza la vista de activación inicial.
 */
class RestablecerAccesoConsultorioController extends Controller
{
    public function mostrar(): void
    {
        header('Referrer-Policy: no-referrer');

        $token = trim((string) ($_GET['token'] ?? ''));
        $servicio = new ActivacionCuentaService();
        $resultado = $servicio->obtenerPorToken($token);

        $identidad = [
            'consultorio' => null,
            'identidadPlataforma' => true,
            'esNavbarGlobal' => true,
            'esPortadaPlataforma' => true
        ];

        $tipo = (string) (($resultado['activacion']['TipoActivacion'] ?? '') ?: '');

        if (
            empty($resultado['valido'])
            || $tipo !== ActivacionCuentaService::TIPO_RECUPERACION_CONSULTORIO
        ) {
            $this->view(
                'auth/restablecer-acceso-consultorio',
                array_merge($identidad, [
                    'titulo' => 'Restablecer acceso',
                    'valido' => false,
                    'mensaje' =>
                        'El enlace de recuperación no es válido o ha expirado.',
                    'token' => '',
                    'nombre' => '',
                    'correoEnmascarado' => '',
                    'error' => null
                ]),
                'activacion'
            );
            return;
        }

        $activacion = $resultado['activacion'];
        $nombre = trim(
            ($activacion['NombrePer'] ?? '') . ' ' .
            ($activacion['ApPatPer'] ?? '')
        );

        $this->view(
            'auth/restablecer-acceso-consultorio',
            array_merge($identidad, [
                'titulo' => 'Restablecer acceso',
                'valido' => true,
                'mensaje' => null,
                'token' => $token,
                'nombre' => $nombre,
                'correoEnmascarado' => $servicio->enmascararCorreo(
                    (string) ($activacion['CorreoUsu'] ?? '')
                ),
                'error' => Session::get('error')
            ]),
            'activacion'
        );

        Session::remove('error');
    }

    public function guardar(): void
    {
        header('Referrer-Policy: no-referrer');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('login');
        }

        $token = trim((string) ($_POST['token'] ?? ''));

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::set('error', 'La solicitud expiró. Intenta nuevamente.');
            Response::redirect(
                'restablecer-acceso-consultorio'
                . ($token !== '' ? '?token=' . rawurlencode($token) : '')
            );
        }

        $servicio = new ActivacionCuentaService();
        $previo = $servicio->obtenerPorToken($token);
        $tipo = (string) (($previo['activacion']['TipoActivacion'] ?? '') ?: '');

        if (
            empty($previo['valido'])
            || $tipo !== ActivacionCuentaService::TIPO_RECUPERACION_CONSULTORIO
        ) {
            Session::set(
                'error',
                'El enlace de recuperación no es válido o ha expirado.'
            );
            Response::redirect('login');
        }

        $resultado = $servicio->activarCuenta(
            $token,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['confirmar_password'] ?? ''),
            []
        );

        if (empty($resultado['ok'])) {
            Session::set(
                'error',
                (string) ($resultado['mensaje'] ?? 'No se pudo restablecer el acceso.')
            );
            Response::redirect(
                'restablecer-acceso-consultorio?token=' . rawurlencode($token)
            );
        }

        Session::set(
            'success',
            'Tu contraseña fue actualizada. Ya puedes iniciar sesión.'
        );
        Response::redirect('login');
    }
}
