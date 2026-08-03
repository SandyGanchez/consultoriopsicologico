<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\AuthService;

class AuthController extends Controller
{
    /**
     * Identidad global de PsicoMatch (sin consultorio implícito).
     *
     * @return array<string, mixed>
     */
    private function datosIdentidadVisualPublica(): array
    {
        return [
            'consultorio' => null,
            'identidadPlataforma' => true,
            'esNavbarGlobal' => true,
            'esPortadaPlataforma' => true
        ];
    }

    private function redirigirSegunRol(array $usuario): void
    {
        $rol = strtoupper(trim((string) ($usuario['RolUsu'] ?? '')));

        Response::redirect(Helper::rutaPanelPorRol($rol));
    }

    public function login()
    {
        if (Session::has('usuario')) {
            $usuario = Session::get('usuario');

            if (is_array($usuario)) {
                $this->redirigirSegunRol($usuario);
            }
        }

        $this->view('auth/login', $this->datosIdentidadVisualPublica());
    }

public function changeTemporaryPassword(): void
{
    if (!Session::has('usuario')) {
        Response::redirect('login');
    }

    $this->view(
        'auth/change-password',
        $this->datosIdentidadVisualPublica()
    );
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

        $this->view('auth/register', $this->datosIdentidadVisualPublica());
    }

   public function autenticar(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('login');
        return;
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Response::redirect('login');
        return;
    }

    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($correo === '' || $password === '') {
        Session::set(
            'error',
            'Debe ingresar correo y contraseña.'
        );

        Response::redirect('login');
        return;
    }

    $service = new AuthService();
    $resultado = $service->autenticar($correo, $password);

    if (empty($resultado['ok'])) {
        Session::set(
            'error',
            (string) ($resultado['mensaje'] ?? 'Correo o contraseña incorrectos.')
        );

        Response::redirect('login');
        return;
    }

    $usuario = $resultado['usuario'];

    Session::regenerar();

    Session::set(
        'usuario',
        $usuario
    );

    if ((int) ($usuario['RequiereCambioContrasena'] ?? 0) === 1) {
        Response::redirect('cambiar-contrasena');
        return;
    }

    $rol = strtoupper(
        trim((string) ($usuario['RolUsu'] ?? ''))
    );

    if ($rol === 'PACIENTE') {
        $intencion = HomeController::consumirIntencionAgendarDesdeSesion();

        if ($intencion !== null) {
            $query = [
                'psicologo' => $intencion['psicologo']
            ];

            if ($intencion['servicio'] !== '') {
                $query['servicio'] = $intencion['servicio'];
            }

            Response::redirect(
                'paciente/agendar?' . http_build_query($query)
            );
            return;
        }
    }

    if (!in_array($rol, [
        'ADMINISTRADOR',
        'CONSULTORIO',
        'PSICOLOGO',
        'PACIENTE'
    ], true)) {
        Session::remove('usuario');
        Session::set(
            'error',
            'El rol del usuario no es válido.'
        );
        Response::redirect('login');
        return;
    }

    $this->redirigirSegunRol($usuario);
}

    public function guardar()
    {
        $service = new AuthService();

        $service->registrar($_POST);
    }

public function forgotPassword(): void
{
    $this->view(
        'auth/forgot-password',
        $this->datosIdentidadVisualPublica()
    );
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

    $this->view(
        'auth/verify-code',
        array_merge(
            $this->datosIdentidadVisualPublica(),
            ['correo' => Session::get('recovery_email')]
        )
    );
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

        $this->view(
            'auth/new-password',
            $this->datosIdentidadVisualPublica()
        );
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
        $metodo = strtoupper(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')
        );

        if ($metodo === 'POST') {
            if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
                Response::redirect('');
                return;
            }
        }

        Session::destroy();

        Response::redirect('');
    }
}
