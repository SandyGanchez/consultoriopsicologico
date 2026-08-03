<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\PrivacidadService;

class PrivacidadController extends Controller
{
    public function avisoDePrivacidad(): void
    {
        $servicio = new PrivacidadService();
        $aviso = $servicio->prepararAvisoIntegral();

        if (!$aviso['publicar']) {
            http_response_code(503);
            $this->view(
                'privacidad/aviso-no-disponible',
                [
                    'titulo' => 'Aviso de Privacidad',
                    'mensaje' => (string) ($aviso['mensaje'] ?? ''),
                    'faltantes' => $aviso['responsable']['faltantes'] ?? [],
                    'version' => $aviso['version'],
                    'fecha' => $aviso['fecha']
                ],
                'auth'
            );
            return;
        }

        $this->view(
            'privacidad/aviso-integral',
            [
                'titulo' => 'Aviso de Privacidad Integral',
                'responsable' => $aviso['responsable'],
                'version' => $aviso['version'],
                'fecha' => $aviso['fecha'],
                'contenido' => (string) ($aviso['contenido'] ?? ''),
                'mensaje' => (string) ($aviso['mensaje'] ?? ''),
                'esDesarrollo' => $servicio->esDesarrollo(),
                'completo' => (bool) ($aviso['responsable']['completo'] ?? false)
            ],
            'auth'
        );
    }

    public function consentimiento(): void
    {
        $usuario = $this->exigirPacienteSesion();
        $servicio = new PrivacidadService();
        $gate = $servicio->evaluarGatePaciente((string) $usuario['ClvUsu']);

        if (($gate['estado'] ?? '') === 'vigente') {
            Response::redirect('paciente');
        }

        if (($gate['estado'] ?? '') === 'tablas_ausentes') {
            Response::redirect('paciente');
        }

        $aviso = $servicio->prepararAvisoIntegral();

        $this->view(
            'privacidad/consentimiento',
            [
                'titulo' => 'Consentimiento de privacidad',
                'usuario' => $usuario,
                'version' => $servicio->versionVigente(),
                'fecha' => $servicio->fechaAviso(),
                'responsable' => $aviso['responsable'] ?? [],
                'estadoGate' => (string) ($gate['estado'] ?? 'requiere_aceptacion'),
                'mensajeGate' => (string) ($gate['mensaje'] ?? ''),
                'puedeAceptar' => !empty($gate['puede_aceptar']),
                'error' => Session::get('error')
            ],
            'auth'
        );

        Session::remove('error');
    }

    public function guardarConsentimiento(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('privacidad/consentimiento');
        }

        $usuario = $this->exigirPacienteSesion();

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::set(
                'error',
                'La solicitud expiró. Intenta nuevamente.'
            );
            Response::redirect('privacidad/consentimiento');
        }

        $servicio = new PrivacidadService();

        if ($servicio->tieneConsentimientoVigente((string) $usuario['ClvUsu'])) {
            Response::redirect('paciente');
        }

        $resultado = $servicio->registrarConsentimiento(
            (string) $usuario['ClvUsu'],
            'REACEPTACION',
            $_POST,
            $servicio->obtenerFechaNacimientoPorUsuario(
                (string) $usuario['ClvUsu']
            )
        );

        if (!$resultado['ok']) {
            Session::set(
                'error',
                (string) ($resultado['mensaje'] ?? 'No se pudo guardar el consentimiento.')
            );
            Response::redirect('privacidad/consentimiento');
        }

        Session::set(
            'success',
            'Tu consentimiento fue registrado correctamente.'
        );
        Response::redirect('paciente');
    }

    /**
     * Canal in-app de solicitudes ARCO retirado.
     * No crea registros en solicitud_privacidad.
     */
    public function solicitarDerechos(): void
    {
        if (Session::has('usuario')) {
            $usuario = Session::get('usuario');
            if (
                is_array($usuario)
                && strtoupper((string) ($usuario['RolUsu'] ?? '')) === 'PACIENTE'
            ) {
                Session::set(
                    'error',
                    'Las solicitudes relacionadas con datos personales se reciben directamente por '
                    . 'los medios indicados en el Aviso de Privacidad.'
                );
                Response::redirect('paciente/configuracion#privacidad');
            }
        }

        Session::set(
            'error',
            'Las solicitudes relacionadas con datos personales se reciben directamente por '
            . 'los medios indicados en el Aviso de Privacidad.'
        );
        Response::redirect('aviso-de-privacidad');
    }

    /**
     * @return array<string, mixed>
     */
    private function exigirPacienteSesion(): array
    {
        if (!Session::has('usuario')) {
            Response::redirect('login');
        }

        $usuario = Session::get('usuario');

        if (!is_array($usuario)) {
            Session::destroy();
            Response::redirect('login');
        }

        if (strtoupper((string) ($usuario['RolUsu'] ?? '')) !== 'PACIENTE') {
            Response::redirect(Helper::rutaPanelPorRol(
                (string) ($usuario['RolUsu'] ?? '')
            ));
        }

        if ((int) ($usuario['EstadoUsu'] ?? 0) !== 1) {
            Session::destroy();
            Response::redirect('login');
        }

        return $usuario;
    }
}
