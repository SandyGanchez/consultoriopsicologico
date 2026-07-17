<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Consultorio;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function login()
    {
        if (Session::has('usuario')) {
            $usuario = Session::get('usuario');

            if ($usuario['RolUsu'] === 'PACIENTE') {
                Response::redirect('paciente');
            }

            if ($usuario['RolUsu'] === 'PSICOLOGO') {
                Response::redirect('psicologo');
            }
            if ($usuario['RolUsu'] === 'CONSULTORIO') {
    Response::redirect('consultorio');
}
        }

        $consultorio = new Consultorio();

        $this->view('auth/login', [
            'consultorio' => $consultorio->obtenerInformacion()
        ]);
    }
public function changeTemporaryPassword(): void
{
    if (!Session::has('usuario')) {
        Response::redirect('login');
    }

    $consultorio = new Consultorio();

    $this->view('auth/change-password', [
        'consultorio' => $consultorio->obtenerInformacion()
    ]);
}
public function saveTemporaryPassword(): void
{
    if (!Session::has('usuario')) {
        Response::redirect('login');
    }

    // aquí guardaremos la nueva contraseña
}
    public function register()
    {
        if (Session::has('usuario')) {
            $usuario = Session::get('usuario');

            if ($usuario['RolUsu'] === 'PACIENTE') {
                Response::redirect('paciente');
            }

            if ($usuario['RolUsu'] === 'PSICOLOGO') {
                Response::redirect('psicologo');
            }
        }

        $consultorio = new Consultorio();

        $this->view('auth/register', [
            'consultorio' => $consultorio->obtenerInformacion()
        ]);
    }

    public function autenticar()
    {
        $correo = trim($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($correo === '' || $password === '') {
            Session::set(
                'error',
                'Debe ingresar correo y contraseña.'
            );

            Response::redirect('login');
        }

        $service = new AuthService();

        $usuario = $service->login($correo, $password);

        if (!$usuario) {
            Session::set(
                'error',
                'Correo o contraseña incorrectos.'
            );

            Response::redirect('login');
        }

        Session::set('usuario', $usuario);

        if ($usuario['RolUsu'] === 'PACIENTE') {
            Response::redirect('paciente');
        }

        if ($usuario['RolUsu'] === 'PSICOLOGO') {
            Response::redirect('psicologo');
        }
        if ($usuario['RolUsu'] === 'CONSULTORIO') {
    Response::redirect('consultorio');
}

        Response::redirect('');
    }

    public function guardar()
    {
        $service = new AuthService();

        $service->registrar($_POST);
    }

public function forgotPassword(): void
{
    $consultorio = new Consultorio();

    $this->view('auth/forgot-password', [
        'consultorio' => $consultorio->obtenerInformacion()
    ]);
}
public function sendRecoveryCode(): void
{
    $correo = strtolower(
        trim($_POST['correo'] ?? '')
    );

    if (
        $correo === '' ||
        !filter_var($correo, FILTER_VALIDATE_EMAIL)
    ) {
        Session::set(
            'error',
            'Ingresa un correo electrónico válido.'
        );

        Response::redirect('forgot-password');
    }

    $resultado = (new AuthService())
        ->enviarCodigoRecuperacion($correo);

    if (!$resultado['success']) {
        Session::set(
            'error',
            $resultado['message']
        );

        Response::redirect('forgot-password');
    }

    Session::set('recovery_email', $correo);
Session::set(
    'recovery_id',
    (int) $resultado['recovery_id']
);
    Session::remove('recovery_verified');
   
    Session::remove('recovery_debug_code');

    Session::set(
        'success',
        'Revisa tu correo. Te enviamos un código de verificación.'
    );

    Response::redirect('verify-code');
}
   public function verifyCode(): void
   
{
    if (!Session::has('recovery_email')) {
        Response::redirect('forgot-password');
    }

    $consultorio = new Consultorio();

    $this->view('auth/verify-code', [
        'consultorio' => $consultorio->obtenerInformacion(),
        'correo' => Session::get('recovery_email')
    ]);
}


public function validateRecoveryCode(): void
{
    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set(
            'error',
            'La solicitud no es válida. Vuelve a intentarlo.'
        );

        Response::redirect('verify-code');
    }

    if (
        !Session::has('recovery_email') ||
        !Session::has('recovery_id')
    ) {
        Session::set(
            'error',
            'La solicitud de recuperación expiró. Solicita otro código.'
        );

        Response::redirect('forgot-password');
    }

    $codigo = preg_replace(
        '/\D/',
        '',
        $_POST['codigo'] ?? ''
    );

    if (
        !is_string($codigo) ||
        !preg_match('/^\d{6}$/', $codigo)
    ) {
        Session::set(
            'error',
            'El código debe contener exactamente seis dígitos.'
        );

        Response::redirect('verify-code');
    }

    $service = new AuthService();

    $resultado = $service->validarCodigoRecuperacion(
        Session::get('recovery_email'),
        $codigo,
        (int) Session::get('recovery_id')
    );

    if (!$resultado['success']) {
        Session::set(
            'error',
            $resultado['message']
        );

        Response::redirect('verify-code');
    }

    Session::regenerar();

    Session::set('recovery_verified', true);

    Session::set(
        'recovery_id',
        (int) $resultado['recovery_id']
    );

    Response::redirect('new-password');
}

    public function newPassword(): void
    {
        // Esta ruta es GET: no debe validar un token CSRF enviado por POST.
        if (
            !Session::has('recovery_email') ||
            Session::get('recovery_verified') !== true ||
            !Session::has('recovery_id')
        ) {
            Session::set(
                'error',
                'La sesión de recuperación expiró. Solicita un código nuevo.'
            );

            Response::redirect('forgot-password');
        }

        $consultorio = new Consultorio();

        $this->view('auth/new-password', [
            'consultorio' => $consultorio->obtenerInformacion()
        ]);
    }

    
public function updateRecoveredPassword(): void
{
    if (
        !Session::validarCsrf(
            $_POST['csrf_token'] ?? null
        )
    ) {
        Session::set(
            'error',
            'La solicitud expiró. Intenta nuevamente.'
        );

        Response::redirect('new-password');
    }

    if (
        !Session::has('recovery_email') ||
        Session::get('recovery_verified') !== true ||
        !Session::has('recovery_id')
    ) {
        Session::set(
            'error',
            'La sesión de recuperación expiró. Solicita otro código.'
        );

        Response::redirect('forgot-password');
    }


    $password = $_POST['password'] ?? '';
    $confirmacion = $_POST['confirmar_password'] ?? '';

    if ($password === '' || $confirmacion === '') {
        Session::set(
            'error',
            'Completa ambos campos de contraseña.'
        );

        Response::redirect('new-password');
    }

    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Za-z]/', $password) ||
        !preg_match('/[0-9]/', $password)
    ) {
        Session::set(
            'error',
            'La contraseña debe tener al menos ocho caracteres, letras y números.'
        );

        Response::redirect('new-password');
    }

    if ($password !== $confirmacion) {
        Session::set(
            'error',
            'Las contraseñas no coinciden.'
        );

        Response::redirect('new-password');
    }

    $resultado = (new AuthService())
        ->actualizarPasswordRecuperada(
            Session::get('recovery_email'),
            $password,
            (int) Session::get('recovery_id')
        );

    if (!$resultado['success']) {
        Session::set(
            'error',
            $resultado['message']
        );

        Response::redirect('new-password');
    }

    Session::remove('recovery_email');
    Session::remove('recovery_verified');
    Session::remove('recovery_id');
    Session::remove('recovery_debug_code');
    Session::remove('csrf_token');

    Session::regenerar();

    Session::set(
        'success',
        'Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.'
    );

    Response::redirect('login');
}
    public function logout(): void
    {
        Session::destroy();

        Response::redirect('');
    }
}
