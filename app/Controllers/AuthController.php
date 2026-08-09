<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\Paciente;
use App\Models\Usuario;
use App\Services\AuthService;
use App\Services\InstalacionConsultorioService;
use App\Services\PerfilPacienteService;
use App\Services\VerificacionCorreoService;

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

    /**
     * Datos seguros para vistas públicas de auth con identidad del consultorio
     * (login / registro). Misma fuente que la instalación única.
     *
     * @return array<string, mixed>
     */
    private function datosVistaAuthConsultorio(
        string $titulo,
        string $accionAuthActiva = ''
    ): array {
        $identidad = $this->construirIdentidadConsultorioLogin();
        $consultorioNav = is_array($identidad['consultorio'] ?? null)
            ? $identidad['consultorio']
            : [
                'NombreCons' => 'Acceso al sistema',
                'LogotipoCons' => null,
                'ClvCons' => ''
            ];

        return [
            'titulo' => $titulo,
            'consultorio' => $consultorioNav,
            'identidadConsultorio' => $identidad,
            'identidadPlataforma' => false,
            'esNavbarGlobal' => false,
            'esPortadaPlataforma' => false,
            'accionAuthActiva' => $accionAuthActiva,
            'correoIngresado' => ''
        ];
    }

    /**
     * Datos seguros para la vista de login (consultorio único de la instalación).
     * No exige PublicadoCons: el acceso interno debe funcionar en configuración.
     *
     * @return array<string, mixed>
     */
    private function datosVistaLogin(): array
    {
        $datos = $this->datosVistaAuthConsultorio('Iniciar sesión', 'login');
        $datos['correoIngresado'] = (string) (Session::getFlash('login_correo') ?? '');

        return $datos;
    }

    /**
     * Datos de presentación para registro público (misma identidad que login).
     *
     * @return array<string, mixed>
     */
    private function datosVistaRegistro(): array
    {
        $datos = $this->datosVistaAuthConsultorio('Crear cuenta', 'registro');
        unset($datos['correoIngresado']);

        return $datos;
    }

    /**
     * Misma identidad visual que login/registro (consultorio, no PsicoMatch).
     *
     * @return array<string, mixed>
     */
    private function datosVistaVerificarCorreo(): array
    {
        $datos = $this->datosVistaAuthConsultorio(
            'Verificar correo',
            'verificar-correo'
        );
        unset($datos['correoIngresado']);

        $svc = new VerificacionCorreoService();
        $ctx = $svc->obtenerContextoSesion();

        $datos['correoMascarado'] = is_array($ctx)
            ? (string) ($ctx['correo_mascarado'] ?? '')
            : '';
        $datos['segundosCooldown'] = is_array($ctx)
            ? $svc->segundosCooldownRestantes((string) ($ctx['ClvUsu'] ?? ''))
            : 0;

        return $datos;
    }

    /**
     * @return array{
     *   estado: string,
     *   nombre: string,
     *   slogan: string,
     *   descripcion: string,
     *   telefono: string,
     *   correo: string,
     *   ubicacion: string,
     *   logoUrl: string,
     *   iniciales: string,
     *   mensajeSecundario: string,
     *   consultorio: ?array
     * }
     */
    private function construirIdentidadConsultorioLogin(): array
    {
        $base = [
            'estado' => InstalacionConsultorioService::ESTADO_NINGUNO,
            'nombre' => '',
            'slogan' => '',
            'descripcion' => '',
            'telefono' => '',
            'correo' => '',
            'ubicacion' => '',
            'logoUrl' => '',
            'iniciales' => 'AS',
            'mensajeSecundario' => 'Instalación en proceso de configuración',
            'consultorio' => null
        ];

        $estado = (new InstalacionConsultorioService())->resolver();
        $base['estado'] = (string) ($estado['estado'] ?? InstalacionConsultorioService::ESTADO_NINGUNO);

        if ($base['estado'] === InstalacionConsultorioService::ESTADO_MULTIPLE) {
            $base['mensajeSecundario'] =
                'El acceso está disponible. La identidad institucional requiere revisión administrativa.';
            return $base;
        }

        if ($base['estado'] !== InstalacionConsultorioService::ESTADO_UNICO) {
            return $base;
        }

        $consultorio = is_array($estado['consultorio'] ?? null)
            ? $estado['consultorio']
            : null;

        if ($consultorio === null) {
            return $base;
        }

        $nombre = trim((string) ($consultorio['NombreCons'] ?? ''));
        if ($nombre === '') {
            $nombre = 'Consultorio';
        }

        $municipio = trim((string) ($consultorio['MunicipioDir'] ?? ''));
        $estadoDir = trim((string) ($consultorio['EstadoDir'] ?? ''));
        $ubicacion = trim(implode(', ', array_filter([$municipio, $estadoDir])));

        $logoUrl = Helper::logotipoConsultorioUrl(
            $consultorio['LogotipoCons'] ?? null,
            false
        );

        return [
            'estado' => InstalacionConsultorioService::ESTADO_UNICO,
            'nombre' => $nombre,
            'slogan' => trim((string) ($consultorio['Slogan'] ?? '')),
            'descripcion' => $this->fragmentoTextoInstitucional(
                (string) ($consultorio['Descripcion'] ?? ''),
                220
            ),
            'telefono' => trim((string) ($consultorio['TelefonoCons'] ?? '')),
            'correo' => trim((string) ($consultorio['CorreoElectronico'] ?? '')),
            'ubicacion' => $ubicacion,
            'logoUrl' => $logoUrl,
            'iniciales' => $this->inicialesInstitucionales($nombre),
            'mensajeSecundario' => '',
            'consultorio' => $consultorio
        ];
    }

    private function fragmentoTextoInstitucional(string $texto, int $limite): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');

        if ($texto === '' || mb_strlen($texto) <= $limite) {
            return $texto;
        }

        $corte = mb_substr($texto, 0, $limite);
        $ultimoEspacio = mb_strrpos($corte, ' ');

        if ($ultimoEspacio !== false && $ultimoEspacio > (int) ($limite * 0.6)) {
            $corte = mb_substr($corte, 0, $ultimoEspacio);
        }

        return rtrim($corte, ".,;: ") . '…';
    }

    private function inicialesInstitucionales(string $nombre): string
    {
        $palabras = preg_split('/\s+/u', trim($nombre)) ?: [];
        $iniciales = '';

        foreach (array_slice($palabras, 0, 2) as $palabra) {
            $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
        }

        return $iniciales !== '' ? $iniciales : 'C';
    }

    /**
     * Sincroniza aviso de perfil incompleto tras login (solo paciente).
     */
    private function sincronizarPerfilIncompletoPaciente(array $usuario): void
    {
        $rol = strtoupper(trim((string) ($usuario['RolUsu'] ?? '')));

        if ($rol !== 'PACIENTE') {
            return;
        }

        if ((int) ($usuario['RequiereCambioContrasena'] ?? 0) === 1) {
            return;
        }

        $clvUsu = trim((string) ($usuario['ClvUsu'] ?? ''));

        if ($clvUsu === '') {
            return;
        }

        $paciente = (new Paciente())->obtenerPorUsuario($clvUsu);

        if ($paciente === null) {
            return;
        }

        (new PerfilPacienteService())->sincronizarAvisoPerfilIncompleto(
            (string) ($paciente['ClvPac'] ?? ''),
            $clvUsu
        );
    }

    private function redirigirSegunRol(array $usuario): void
    {
        $rol = strtoupper(trim((string) ($usuario['RolUsu'] ?? '')));

        if ($rol === 'PACIENTE') {
            $this->sincronizarPerfilIncompletoPaciente($usuario);
        }

        if (
            $rol === 'PACIENTE'
            && (int) ($usuario['RequiereCambioContrasena'] ?? 0) !== 1
            && (new \App\Services\PrivacidadService())->pacienteDebeResolverPrivacidad(
                (string) ($usuario['ClvUsu'] ?? '')
            )
        ) {
            Response::redirect('privacidad/consentimiento');
        }

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

        $this->view('auth/login', $this->datosVistaLogin());
    }

    /**
     * Formulario de ayuda en login.
     * Neutro: no confirma existencia del correo.
     */
    public function reportarAyudaCuenta(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('login');
        }

        $mensajeNeutro =
            'Si la información corresponde a una cuenta registrada, la solicitud será '
            . 'revisada por soporte.';

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::set('error', 'La solicitud expiró. Intenta nuevamente.');
            Response::redirect('login');
        }

        $ahora = time();
        $ultimo = (int) (Session::get('ayuda_cuenta_ultimo') ?? 0);

        if ($ultimo > 0 && ($ahora - $ultimo) < 60) {
            Session::set('success', $mensajeNeutro);
            Response::redirect('login');
        }

        Session::set('ayuda_cuenta_ultimo', $ahora);

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ipHash = $ip !== '' ? hash('sha256', $ip) : null;

        $resultado = (new \App\Services\IncidenciaSoporteService())
            ->registrarDesdeLogin($_POST, $ipHash);

        Session::set('success', (string) ($resultado['mensaje'] ?? $mensajeNeutro));
        Response::redirect('login');
    }

public function changeTemporaryPassword(): void
{
    if (!Session::has('usuario')) {
        Response::redirect('login');
    }

    $usuario = Session::get('usuario');

    if (!is_array($usuario)) {
        Session::destroy();
        Response::redirect('login');
    }

    if ((int) ($usuario['RequiereCambioContrasena'] ?? 0) !== 1) {
        $this->redirigirSegunRol($usuario);
    }

    $datosVista = $this->datosVistaLogin();
    $datosVista['titulo'] = 'Establecer nueva contraseña';
    unset($datosVista['correoIngresado']);

    $this->view('auth/change-password', $datosVista);
}

public function saveTemporaryPassword(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('cambiar-contrasena');
    }

    if (!Session::has('usuario')) {
        Response::redirect('login');
    }

    $usuario = Session::get('usuario');

    if (!is_array($usuario)) {
        Session::destroy();
        Response::redirect('login');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Response::redirect('cambiar-contrasena');
    }

    if ((int) ($usuario['RequiereCambioContrasena'] ?? 0) !== 1) {
        $this->redirigirSegunRol($usuario);
    }

    $clvUsu = trim((string) ($usuario['ClvUsu'] ?? ''));

    if ($clvUsu === '') {
        Session::destroy();
        Response::redirect('login');
    }

    $password = (string) ($_POST['password'] ?? '');
    $confirmacion = (string) ($_POST['confirmar_password'] ?? '');

    if ($password === '' || $confirmacion === '') {
        Session::set(
            'error',
            'Completa ambos campos de contraseña.'
        );
        Response::redirect('cambiar-contrasena');
    }

    if (
        strlen($password) < 8
        || !preg_match('/[A-Za-z]/', $password)
        || !preg_match('/[0-9]/', $password)
    ) {
        Session::set(
            'error',
            'La contraseña debe tener al menos ocho caracteres, letras y números.'
        );
        Response::redirect('cambiar-contrasena');
    }

    if ($password !== $confirmacion) {
        Session::set(
            'error',
            'Las contraseñas no coinciden.'
        );
        Response::redirect('cambiar-contrasena');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    if (!is_string($hash) || $hash === '') {
        Session::set(
            'error',
            'No fue posible actualizar la contraseña. Intenta nuevamente.'
        );
        Response::redirect('cambiar-contrasena');
    }

    $actualizado = (new Usuario())->actualizarContrasenaYLiberarCambio(
        $clvUsu,
        $hash
    );

    if (!$actualizado) {
        Session::set(
            'error',
            'No fue posible actualizar la contraseña. Intenta nuevamente.'
        );
        Response::redirect('cambiar-contrasena');
    }

    $usuario['RequiereCambioContrasena'] = 0;

    Session::regenerar();
    Session::set('usuario', $usuario);

    $this->redirigirSegunRol($usuario);
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

        $privacidad = new \App\Services\PrivacidadService();
        $datos = $this->datosVistaRegistro();
        $datos['versionAviso'] = $privacidad->versionVigente();

        $this->view('auth/register', $datos);
    }

   public function autenticar(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('login');
        return;
    }

    $correo = trim((string) ($_POST['correo'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Session::setFlash('login_correo', $correo);
        Response::redirect('login');
        return;
    }

    if ($correo === '' || $password === '') {
        Session::set(
            'error',
            'Debe ingresar correo y contraseña.'
        );
        Session::setFlash('login_correo', $correo);

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
        Session::setFlash('login_correo', $correo);

        Response::redirect('login');
        return;
    }

    $usuario = $resultado['usuario'];

    $rol = strtoupper(
        trim((string) ($usuario['RolUsu'] ?? ''))
    );

    // Paciente con correo pendiente: sin sesión autenticada.
    if (
        $rol === 'PACIENTE'
        && array_key_exists('CorreoVerificado', $usuario)
        && (int) ($usuario['CorreoVerificado'] ?? 0) === 0
    ) {
        if (Session::has('usuario')) {
            Session::remove('usuario');
        }

        $nombre = trim(
            ((string) ($usuario['NombrePer'] ?? '')) . ' '
            . ((string) ($usuario['ApPatPer'] ?? ''))
        );

        $envio = (new VerificacionCorreoService())->iniciarDesdeLogin(
            (string) ($usuario['ClvUsu'] ?? ''),
            (string) ($usuario['CorreoUsu'] ?? $correo),
            $nombre
        );

        Session::set(
            'success',
            (string) ($envio['mensaje'] ??
                'Debes verificar tu correo para continuar.')
        );

        Response::redirect('verificar-correo');
        return;
    }

    Session::regenerar();

    Session::set(
        'usuario',
        $usuario
    );

    if ((int) ($usuario['RequiereCambioContrasena'] ?? 0) === 1) {
        Response::redirect('cambiar-contrasena');
        return;
    }

    if ($rol === 'PACIENTE') {
        if (
            (new \App\Services\PrivacidadService())->pacienteDebeResolverPrivacidad(
                (string) ($usuario['ClvUsu'] ?? '')
            )
        ) {
            $this->sincronizarPerfilIncompletoPaciente($usuario);
            Response::redirect('privacidad/consentimiento');
            return;
        }

        $intencion = HomeController::consumirIntencionAgendarDesdeSesion();

        if ($intencion !== null) {
            $this->sincronizarPerfilIncompletoPaciente($usuario);

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
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('registro');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::set(
                'error',
                'La solicitud expiró. Intenta nuevamente.'
            );
            Response::redirect('registro');
        }

        $service = new AuthService();
        $service->registrar($_POST);
    }

    public function mostrarVerificarCorreo(): void
    {
        if (Session::has('usuario')) {
            $usuario = Session::get('usuario');
            $rol = strtoupper(
                trim((string) (is_array($usuario) ? ($usuario['RolUsu'] ?? '') : ''))
            );
            if ($rol !== '') {
                Response::redirect(Helper::rutaPanelPorRol($rol));
                return;
            }
        }

        $svc = new VerificacionCorreoService();
        if ($svc->obtenerContextoSesion() === null) {
            Session::set(
                'error',
                'No hay una verificación pendiente. Inicia sesión o regístrate.'
            );
            Response::redirect('login');
            return;
        }

        $this->view(
            'auth/verificar-correo',
            $this->datosVistaVerificarCorreo()
        );
    }

    public function verificarCorreo(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('verificar-correo');
            return;
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::set(
                'error',
                'La solicitud no es válida. Intenta nuevamente.'
            );
            Response::redirect('verificar-correo');
            return;
        }

        $codigo = trim((string) ($_POST['codigo'] ?? ''));
        $resultado = (new VerificacionCorreoService())->validarCodigo($codigo);

        if (empty($resultado['ok'])) {
            Session::set(
                'error',
                (string) ($resultado['mensaje'] ?? 'No fue posible verificar el código.')
            );
            Response::redirect('verificar-correo');
            return;
        }

        /** @var array<string, mixed> $usuario */
        $usuario = $resultado['usuario'];

        Session::regenerar();
        Session::set('usuario', $usuario);

        Session::set(
            'success',
            (string) ($resultado['mensaje'] ?? 'Correo verificado correctamente.')
        );

        Response::redirect(Helper::rutaPanelPorRol('PACIENTE'));
    }

    public function reenviarCodigoVerificacion(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('verificar-correo');
            return;
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::set(
                'error',
                'La solicitud no es válida. Intenta nuevamente.'
            );
            Response::redirect('verificar-correo');
            return;
        }

        $resultado = (new VerificacionCorreoService())->reenviar();

        if (empty($resultado['ok'])) {
            Session::set(
                'error',
                (string) ($resultado['mensaje'] ?? 'No fue posible reenviar el código.')
            );
            Response::redirect('verificar-correo');
            return;
        }

        Session::set(
            'success',
            (string) ($resultado['mensaje'] ?? 'Te enviamos un nuevo código.')
        );
        Response::redirect('verificar-correo');
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
