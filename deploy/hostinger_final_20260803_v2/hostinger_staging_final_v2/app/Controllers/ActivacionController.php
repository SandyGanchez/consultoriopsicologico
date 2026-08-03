<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Services\ActivacionCuentaService;
use App\Services\PrivacidadService;

class ActivacionController extends Controller
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

        if (!$resultado['valido']) {
            $this->view(
                'auth/activar-cuenta',
                array_merge($identidad, [
                    'titulo' => 'Activar cuenta',
                    'valido' => false,
                    'mensaje' => $resultado['mensaje']
                        ?? ActivacionCuentaService::MENSAJE_TOKEN_INVALIDO,
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
        $tipoActivacion = (string) ($activacion['TipoActivacion'] ?? '');

        if ($tipoActivacion === ActivacionCuentaService::TIPO_RECUPERACION_CONSULTORIO) {
            Response::redirect(
                'restablecer-acceso-consultorio?token=' . rawurlencode($token)
            );
        }

        $nombre = trim(
            ($activacion['NombrePer'] ?? '') . ' ' .
            ($activacion['ApPatPer'] ?? '')
        );

        $requiereConsentimiento =
            $tipoActivacion === ActivacionCuentaService::TIPO_PACIENTE;

        $this->view(
            'auth/activar-cuenta',
            array_merge($identidad, [
                'titulo' => 'Activar cuenta',
                'valido' => true,
                'mensaje' => null,
                'token' => $token,
                'nombre' => $nombre,
                'correoEnmascarado' => $servicio->enmascararCorreo(
                    (string) ($activacion['CorreoUsu'] ?? '')
                ),
                'error' => Session::get('error'),
                'requiereConsentimiento' => $requiereConsentimiento,
                'versionAviso' => (new PrivacidadService())->versionVigente()
            ]),
            'activacion'
        );

        Session::remove('error');
    }

    public function activar(): void
    {
        header('Referrer-Policy: no-referrer');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::redirect('login');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::set(
                'error',
                'La solicitud expiró. Intenta nuevamente.'
            );

            $token = trim((string) ($_POST['token'] ?? ''));
            Response::redirect(
                'activar-cuenta' . ($token !== '' ? '?token=' . rawurlencode($token) : '')
            );
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmacion = (string) ($_POST['confirmar_password'] ?? '');

        $servicio = new ActivacionCuentaService();
        $previo = $servicio->obtenerPorToken($token);
        $tipo = (string) (($previo['activacion']['TipoActivacion'] ?? '') ?: '');

        if ($tipo === ActivacionCuentaService::TIPO_RECUPERACION_CONSULTORIO) {
            Response::redirect(
                'restablecer-acceso-consultorio?token=' . rawurlencode($token)
            );
        }

        $resultado = $servicio->activarCuenta(
            $token,
            $password,
            $confirmacion,
            $_POST
        );

        if (!$resultado['ok']) {
            Session::set('error', $resultado['mensaje']);

            Response::redirect(
                'activar-cuenta?token=' . rawurlencode($token)
            );
        }

        Session::set('success', $resultado['mensaje']);
        Response::redirect('login');
    }
}
