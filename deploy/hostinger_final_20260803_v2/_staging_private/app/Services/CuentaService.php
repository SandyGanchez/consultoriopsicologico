<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Usuario;
use PDOException;
use RuntimeException;

class CuentaService
{
    private const SESION_CLAVE = 'cambio_correo_pendiente';
    private const EXPIRACION_SEGUNDOS = 600;
    private const MAX_INTENTOS = 5;
    private const REENVIO_ESPERA_SEGUNDOS = 60;
    private const MAX_REENVIOS = 3;
    /** Segundos tras los cuales envio_en_proceso se considera huérfano. */
    private const ENVIO_HUERFANO_SEGUNDOS = 120;

    private Usuario $usuarioModel;
    private MailService $mailService;

    public function __construct(
        ?Usuario $usuarioModel = null,
        ?MailService $mailService = null
    ) {
        $this->usuarioModel = $usuarioModel ?? new Usuario();
        $this->mailService = $mailService ?? new MailService();
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function cambiarContrasena(
        string $clvUsu,
        string $contrasenaActual,
        string $nuevaContrasena,
        string $confirmarContrasena
    ): array {
        $clvUsu = trim($clvUsu);

        if ($clvUsu === '') {
            return $this->fallo('No se pudo identificar la cuenta.');
        }

        if (
            $contrasenaActual === '' ||
            $nuevaContrasena === '' ||
            $confirmarContrasena === ''
        ) {
            return $this->fallo('Completa todos los campos de seguridad.');
        }

        if ($nuevaContrasena !== $confirmarContrasena) {
            return $this->fallo('Las contraseñas nuevas no coinciden.');
        }

        if (!$this->contrasenaCumpleReglas($nuevaContrasena)) {
            return $this->fallo(
                'La nueva contraseña debe tener al menos 8 caracteres, una letra y un número.'
            );
        }

        $hashActual = $this->usuarioModel->obtenerHashContrasena($clvUsu);

        if (
            $hashActual === null ||
            !password_verify($contrasenaActual, $hashActual)
        ) {
            return $this->fallo('La contraseña actual no es correcta.');
        }

        if (password_verify($nuevaContrasena, $hashActual)) {
            return $this->fallo(
                'La nueva contraseña debe ser diferente de la actual.'
            );
        }

        $nuevoHash = password_hash($nuevaContrasena, PASSWORD_DEFAULT);

        if (!is_string($nuevoHash) || $nuevoHash === '') {
            return $this->fallo('No fue posible actualizar la contraseña.');
        }

        if (
            !$this->usuarioModel->actualizarContrasenaYLiberarCambio(
                $clvUsu,
                $nuevoHash
            )
        ) {
            return $this->fallo('No fue posible actualizar la contraseña.');
        }

        Session::regenerar();

        return $this->ok('Tu contraseña se actualizó correctamente.');
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function actualizarTelefono(
        string $clvUsu,
        string $telefono,
        string $contrasenaActual
    ): array {
        $clvUsu = trim($clvUsu);

        if ($clvUsu === '') {
            return $this->fallo('No se pudo identificar la cuenta.');
        }

        if ($contrasenaActual === '') {
            return $this->fallo(
                'Debes confirmar tu contraseña actual para actualizar el teléfono.'
            );
        }

        $telefonoNormalizado = $this->normalizarTelefono($telefono);

        if (!preg_match('/^[0-9]{10}$/', $telefonoNormalizado)) {
            return $this->fallo('El teléfono debe contener exactamente 10 dígitos.');
        }

        if (!$this->verificarContrasenaActual($clvUsu, $contrasenaActual)) {
            return $this->fallo('La contraseña actual no es correcta.');
        }

        if (!$this->usuarioModel->actualizarTelefono($clvUsu, $telefonoNormalizado)) {
            return $this->fallo('No fue posible actualizar el teléfono.');
        }

        $this->actualizarSesionUsuario([
            'TelefonoUsu' => $telefonoNormalizado
        ]);

        Session::regenerar();

        return $this->ok('Tu número telefónico fue actualizado.');
    }

    /**
     * @return array{ok: bool, codigo: string, mensaje: string, reenviarEn?: int}
     */
    public function solicitarCambioCorreo(
        string $clvUsu,
        string $correoNuevo,
        string $contrasenaActual,
        string $nombreDestino
    ): array {
        $clvUsu = trim($clvUsu);
        $correoNuevo = $this->normalizarCorreo($correoNuevo);

        if ($clvUsu === '') {
            return $this->fallo(
                'No se pudo identificar la cuenta.',
                'CUENTA_INVALIDA'
            );
        }

        if ($contrasenaActual === '') {
            return $this->fallo(
                'Debes confirmar tu contraseña actual para cambiar el correo.',
                'CONTRASENA_REQUERIDA'
            );
        }

        if (!$this->correoEsValido($correoNuevo)) {
            return $this->fallo(
                'El correo ingresado no tiene un formato válido.',
                'CORREO_INVALIDO'
            );
        }

        if (!$this->verificarContrasenaActual($clvUsu, $contrasenaActual)) {
            return $this->fallo(
                'La contraseña actual no es correcta.',
                'CONTRASENA_INCORRECTA'
            );
        }

        $usuario = $this->usuarioModel->obtenerPorClave($clvUsu);

        if (!$usuario) {
            return $this->fallo('No se encontró la cuenta.', 'CUENTA_INVALIDA');
        }

        $correoActual = $this->normalizarCorreo(
            (string) ($usuario['CorreoUsu'] ?? '')
        );

        if ($correoNuevo === $correoActual) {
            return $this->fallo(
                'El nuevo correo debe ser diferente al correo actual.',
                'CORREO_IGUAL'
            );
        }

        if ($this->usuarioModel->existeCorreoExcepto($correoNuevo, $clvUsu)) {
            return $this->fallo(
                'Este correo ya está registrado en otra cuenta.',
                'CORREO_DUPLICADO'
            );
        }

        $bloqueo = $this->evaluarBloqueoSolicitudActiva($clvUsu, $correoNuevo);

        if ($bloqueo !== null) {
            return $bloqueo;
        }

        $ahora = time();
        $solicitudId = bin2hex(random_bytes(16));

        $solicitud = [
            'clv_usu' => $clvUsu,
            'correo_nuevo' => $correoNuevo,
            'correo_actual' => $correoActual,
            'codigo_hash' => '',
            'expira_en' => $ahora + self::EXPIRACION_SEGUNDOS,
            'intentos' => 0,
            'reenvios' => 0,
            'reenviar_desde' => $ahora + self::REENVIO_ESPERA_SEGUNDOS,
            'envio_en_proceso' => true,
            'envio_iniciado_en' => $ahora,
            'verificacion_en_proceso' => false,
            'aviso_enviado' => false,
            'solicitud_id' => $solicitudId
        ];

        // Persistir bloqueo antes de liberar la sesión / llamar SMTP.
        Session::set(self::SESION_CLAVE, $solicitud);
        Session::writeClose();

        $codigo = (string) random_int(100000, 999999);
        $codigoHash = password_hash($codigo, PASSWORD_DEFAULT);

        if (!is_string($codigoHash) || $codigoHash === '') {
            $this->limpiarEnvioSiMismaSolicitud(
                $clvUsu,
                $solicitudId,
                false
            );

            return $this->fallo(
                'No fue posible generar el código de verificación.',
                'CODIGO_NO_GENERADO'
            );
        }

        try {
            // Una sola llamada autorizada; MailService no reintenta.
            $this->mailService->enviarCodigoCambioCorreo(
                $correoNuevo,
                $nombreDestino,
                $codigo
            );
        } catch (\Throwable $e) {
            error_log(
                'cambio_correo_envio_fallido evento=smtp_error sid='
                . substr($solicitudId, 0, 8)
            );

            $this->limpiarEnvioSiMismaSolicitud(
                $clvUsu,
                $solicitudId,
                false
            );

            return $this->fallo(
                'No pudimos enviar el código de verificación. Intenta más tarde.',
                'ENVIO_FALLIDO'
            );
        }

        unset($codigo);

        $expiracion = self::EXPIRACION_SEGUNDOS;
        $esperaReenvio = self::REENVIO_ESPERA_SEGUNDOS;

        $persistido = $this->aplicarPostSmtpSiVigente(
            $clvUsu,
            $solicitudId,
            $correoNuevo,
            static function (array &$actual) use (
                $codigoHash,
                $expiracion,
                $esperaReenvio
            ): void {
                $actual['codigo_hash'] = $codigoHash;
                $actual['envio_en_proceso'] = false;
                $actual['expira_en'] = time() + $expiracion;
                $actual['reenviar_desde'] = time() + $esperaReenvio;
                $actual['intentos'] = 0;
            }
        );

        if ($persistido !== null) {
            return $persistido;
        }

        return $this->ok(
            'Enviamos un código de verificación al nuevo correo.',
            'CODIGO_ENVIADO',
            ['reenviarEn' => self::REENVIO_ESPERA_SEGUNDOS]
        );
    }

    /**
     * @return array{ok: bool, codigo: string, mensaje: string, reenviarEn?: int}
     */
    public function verificarCambioCorreo(
        string $clvUsu,
        string $codigo
    ): array {
        $clvUsu = trim($clvUsu);
        $codigo = trim($codigo);

        if ($clvUsu === '') {
            return $this->fallo(
                'No se pudo identificar la cuenta.',
                'CUENTA_INVALIDA'
            );
        }

        if (!preg_match('/^[0-9]{6}$/', $codigo)) {
            return $this->fallo(
                'El código ingresado no es válido.',
                'CODIGO_INVALIDO'
            );
        }

        $solicitud = $this->obtenerSolicitudCruda($clvUsu);
        $solicitud = $this->sanearEnvioEstancado($solicitud);

        if ($solicitud === null) {
            return $this->fallo(
                'No hay una solicitud de cambio de correo pendiente.',
                'SIN_SOLICITUD'
            );
        }

        if (!empty($solicitud['envio_en_proceso'])) {
            return $this->fallo(
                'El código ya se está enviando.',
                'ENVIO_EN_PROCESO'
            );
        }

        if (!empty($solicitud['verificacion_en_proceso'])) {
            return $this->fallo(
                'La verificación ya está en proceso.',
                'VERIFICACION_EN_PROCESO'
            );
        }

        if (time() > (int) ($solicitud['expira_en'] ?? 0)) {
            Session::remove(self::SESION_CLAVE);

            return $this->fallo(
                'El código ha expirado. Solicita uno nuevo.',
                'CODIGO_EXPIRADO'
            );
        }

        if ((int) ($solicitud['intentos'] ?? 0) >= self::MAX_INTENTOS) {
            Session::remove(self::SESION_CLAVE);

            return $this->fallo(
                'Se superó el número de intentos. Solicita un código nuevo.',
                'LIMITE_INTENTOS'
            );
        }

        $hash = (string) ($solicitud['codigo_hash'] ?? '');

        if ($hash === '' || !password_verify($codigo, $hash)) {
            $solicitud['intentos'] = (int) ($solicitud['intentos'] ?? 0) + 1;

            if ($solicitud['intentos'] >= self::MAX_INTENTOS) {
                Session::remove(self::SESION_CLAVE);

                return $this->fallo(
                    'Se superó el número de intentos. Solicita un código nuevo.',
                    'LIMITE_INTENTOS'
                );
            }

            Session::set(self::SESION_CLAVE, $solicitud);

            return $this->fallo(
                'El código ingresado no es válido.',
                'CODIGO_INVALIDO'
            );
        }

        $correoNuevo = $this->normalizarCorreo(
            (string) ($solicitud['correo_nuevo'] ?? '')
        );
        $correoAnterior = $this->normalizarCorreo(
            (string) ($solicitud['correo_actual'] ?? '')
        );
        $solicitudId = (string) ($solicitud['solicitud_id'] ?? '');
        $avisoYaMarcado = !empty($solicitud['aviso_enviado']);

        // Consumir la solicitud antes del UPDATE/aviso para bloquear POST repetidos.
        $solicitud['verificacion_en_proceso'] = true;
        $solicitud['codigo_hash'] = '';
        Session::set(self::SESION_CLAVE, $solicitud);
        Session::writeClose();

        try {
            $this->usuarioModel->actualizarCorreoVerificado(
                $clvUsu,
                $correoNuevo
            );
        } catch (PDOException $e) {
            Session::start();
            Session::remove(self::SESION_CLAVE);

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return $this->fallo(
                    'Este correo ya está registrado en otra cuenta.',
                    'CORREO_DUPLICADO'
                );
            }

            return $this->fallo(
                'No fue posible actualizar el correo.',
                'ACTUALIZACION_FALLIDA'
            );
        } catch (RuntimeException $e) {
            Session::start();
            Session::remove(self::SESION_CLAVE);

            if ($e->getMessage() === 'CORREO_DUPLICADO') {
                return $this->fallo(
                    'Este correo ya está registrado en otra cuenta.',
                    'CORREO_DUPLICADO'
                );
            }

            return $this->fallo($e->getMessage(), 'ACTUALIZACION_FALLIDA');
        } catch (\Throwable $e) {
            Session::start();
            Session::remove(self::SESION_CLAVE);

            return $this->fallo(
                'No fue posible actualizar el correo.',
                'ACTUALIZACION_FALLIDA'
            );
        }

        Session::start();
        Session::remove(self::SESION_CLAVE);

        $this->actualizarSesionUsuario([
            'CorreoUsu' => $correoNuevo
        ]);

        Session::regenerar();

        if ($correoAnterior !== '' && !$avisoYaMarcado && $solicitudId !== '') {
            $llaveAviso = 'cambio_correo_aviso_' . $solicitudId;

            if (!Session::has($llaveAviso)) {
                Session::set($llaveAviso, 1);

                $usuario = Session::get('usuario');
                $nombre = 'Usuario';

                if (is_array($usuario)) {
                    $nombreTmp = trim(
                        ((string) ($usuario['NombrePer'] ?? '')) . ' ' .
                        ((string) ($usuario['ApPatPer'] ?? ''))
                    );

                    if ($nombreTmp !== '') {
                        $nombre = $nombreTmp;
                    }
                }

                Session::writeClose();

                try {
                    $this->mailService->enviarAvisoCambioCorreoAnterior(
                        $correoAnterior,
                        $nombre
                    );
                } catch (\Throwable $e) {
                    error_log(
                        'cambio_correo_aviso_fallido sid='
                        . substr($solicitudId, 0, 8)
                    );
                }

                Session::start();
                Session::remove($llaveAviso);
            }
        }

        return $this->ok(
            'Tu correo de acceso fue actualizado correctamente.',
            'CORREO_ACTUALIZADO'
        );
    }

    /**
     * @return array{ok: bool, codigo: string, mensaje: string, reenviarEn?: int}
     */
    public function reenviarCodigoCorreo(
        string $clvUsu,
        string $nombreDestino
    ): array {
        $clvUsu = trim($clvUsu);
        $solicitud = $this->obtenerSolicitudCruda($clvUsu);
        $solicitud = $this->sanearEnvioEstancado($solicitud);

        if ($solicitud === null) {
            return $this->fallo(
                'No hay una solicitud de cambio de correo pendiente.',
                'SIN_SOLICITUD'
            );
        }

        if (!empty($solicitud['verificacion_en_proceso'])) {
            return $this->fallo(
                'La verificación ya está en proceso.',
                'VERIFICACION_EN_PROCESO'
            );
        }

        if (!empty($solicitud['envio_en_proceso'])) {
            return $this->fallo(
                'El código ya se está enviando.',
                'ENVIO_EN_PROCESO'
            );
        }

        $ahora = time();
        $reenviarDesde = (int) ($solicitud['reenviar_desde'] ?? 0);

        if ($ahora < $reenviarDesde) {
            return $this->fallo(
                'Ya enviamos un código a este correo. Podrás solicitar otro en unos segundos.',
                'CODIGO_YA_ENVIADO',
                ['reenviarEn' => max(1, $reenviarDesde - $ahora)]
            );
        }

        if ((int) ($solicitud['reenvios'] ?? 0) >= self::MAX_REENVIOS) {
            return $this->fallo(
                'Alcanzaste el límite de reenvíos. Cancela la solicitud e inicia una nueva más tarde.',
                'LIMITE_REENVIOS'
            );
        }

        $hashAnterior = (string) ($solicitud['codigo_hash'] ?? '');
        $reenviosAnteriores = (int) ($solicitud['reenvios'] ?? 0);
        $solicitudId = (string) ($solicitud['solicitud_id'] ?? '');
        $correoPendiente = $this->normalizarCorreo(
            (string) ($solicitud['correo_nuevo'] ?? '')
        );

        if ($solicitudId === '' || $correoPendiente === '') {
            return $this->fallo(
                'La solicitud de cambio de correo ya no es válida.',
                'SOLICITUD_INVALIDA'
            );
        }

        $solicitud['envio_en_proceso'] = true;
        $solicitud['envio_iniciado_en'] = $ahora;
        Session::set(self::SESION_CLAVE, $solicitud);
        Session::writeClose();

        $codigo = (string) random_int(100000, 999999);
        $codigoHash = password_hash($codigo, PASSWORD_DEFAULT);

        if (!is_string($codigoHash) || $codigoHash === '') {
            $this->limpiarEnvioSiMismaSolicitud(
                $clvUsu,
                $solicitudId,
                true,
                $hashAnterior,
                $reenviosAnteriores
            );

            return $this->fallo(
                'No fue posible generar el código de verificación.',
                'CODIGO_NO_GENERADO'
            );
        }

        try {
            $this->mailService->enviarCodigoCambioCorreo(
                $correoPendiente,
                $nombreDestino,
                $codigo
            );
        } catch (\Throwable $e) {
            error_log(
                'cambio_correo_reenvio_fallido evento=smtp_error sid='
                . substr($solicitudId, 0, 8)
            );

            $this->limpiarEnvioSiMismaSolicitud(
                $clvUsu,
                $solicitudId,
                true,
                $hashAnterior,
                $reenviosAnteriores
            );

            return $this->fallo(
                'No pudimos reenviar el código. Intenta más tarde.',
                'ENVIO_FALLIDO'
            );
        }

        unset($codigo);

        $expiracion = self::EXPIRACION_SEGUNDOS;
        $esperaReenvio = self::REENVIO_ESPERA_SEGUNDOS;

        $persistido = $this->aplicarPostSmtpSiVigente(
            $clvUsu,
            $solicitudId,
            $correoPendiente,
            static function (array &$actual) use (
                $codigoHash,
                $reenviosAnteriores,
                $expiracion,
                $esperaReenvio
            ): void {
                // Invalidar hash anterior e incrementar solo tras envío real.
                $actual['codigo_hash'] = $codigoHash;
                $actual['expira_en'] = time() + $expiracion;
                $actual['intentos'] = 0;
                $actual['reenvios'] = $reenviosAnteriores + 1;
                $actual['reenviar_desde'] = time() + $esperaReenvio;
                $actual['envio_en_proceso'] = false;
            }
        );

        if ($persistido !== null) {
            return $persistido;
        }

        return $this->ok(
            'Enviamos un código de verificación al nuevo correo.',
            'CODIGO_REENVIADO',
            ['reenviarEn' => self::REENVIO_ESPERA_SEGUNDOS]
        );
    }

    /**
     * @return array{ok: bool, codigo: string, mensaje: string}
     */
    public function cancelarCambioCorreo(string $clvUsu): array
    {
        $solicitud = $this->obtenerSolicitudCruda(trim($clvUsu));
        $solicitud = $this->sanearEnvioEstancado($solicitud);

        if ($solicitud === null) {
            return $this->fallo(
                'No hay una solicitud de cambio de correo pendiente.',
                'SIN_SOLICITUD'
            );
        }

        if (!empty($solicitud['verificacion_en_proceso'])) {
            return $this->fallo(
                'La verificación ya está en proceso.',
                'VERIFICACION_EN_PROCESO'
            );
        }

        // Permitir cancelar aunque envio_en_proceso=true (otra pestaña).
        // El POST SMTP antiguo no recreará la solicitud si solicitud_id no coincide.
        $sid = substr((string) ($solicitud['solicitud_id'] ?? ''), 0, 8);
        Session::remove(self::SESION_CLAVE);

        error_log(
            'cambio_correo_cancelado evento=cancel sid=' . $sid
        );

        return $this->ok(
            'Se canceló la solicitud de cambio de correo.',
            'SOLICITUD_CANCELADA'
        );
    }

    /**
     * Datos seguros para la vista (sin hash, código ni solicitud_id).
     *
     * @return array<string, mixed>|null
     */
    public function obtenerSolicitudPendienteVista(string $clvUsu): ?array
    {
        $solicitud = $this->obtenerSolicitudCruda(trim($clvUsu));
        $solicitud = $this->sanearEnvioEstancado($solicitud);

        if ($solicitud === null) {
            return null;
        }

        if (
            empty($solicitud['envio_en_proceso'])
            && time() > (int) ($solicitud['expira_en'] ?? 0)
        ) {
            Session::remove(self::SESION_CLAVE);

            return null;
        }

        $ahora = time();
        $expira = (int) ($solicitud['expira_en'] ?? 0);
        $reenvios = (int) ($solicitud['reenvios'] ?? 0);
        $reenviarDesde = (int) ($solicitud['reenviar_desde'] ?? 0);
        $segundosReenvio = max(0, $reenviarDesde - $ahora);
        $envioEnProceso = !empty($solicitud['envio_en_proceso']);

        return [
            'correo_enmascarado' => $this->enmascararCorreo(
                (string) ($solicitud['correo_nuevo'] ?? '')
            ),
            'expira_en' => $expira,
            'segundos_restantes' => max(0, $expira - $ahora),
            'segundos_para_reenviar' => $segundosReenvio,
            'puede_reenviar' =>
                !$envioEnProceso
                && $segundosReenvio === 0
                && $reenvios < self::MAX_REENVIOS
                && (string) ($solicitud['codigo_hash'] ?? '') !== '',
            'reenvios_restantes' => max(0, self::MAX_REENVIOS - $reenvios),
            'envio_en_proceso' => $envioEnProceso
        ];
    }

    public function normalizarTelefono(string $telefono): string
    {
        return preg_replace('/\D+/', '', $telefono) ?? '';
    }

    public function normalizarCorreo(string $correo): string
    {
        return strtolower(trim($correo));
    }

    public function enmascararCorreo(string $correo): string
    {
        $correo = $this->normalizarCorreo($correo);
        $partes = explode('@', $correo, 2);

        if (count($partes) !== 2) {
            return '***';
        }

        $local = $partes[0];
        $dominio = $partes[1];
        $inicial = $local !== '' ? substr($local, 0, 1) : '*';

        return $inicial . '***@' . $dominio;
    }

    private function verificarContrasenaActual(
        string $clvUsu,
        string $contrasenaActual
    ): bool {
        $hash = $this->usuarioModel->obtenerHashContrasena($clvUsu);

        return $hash !== null
            && password_verify($contrasenaActual, $hash);
    }

    private function contrasenaCumpleReglas(string $password): bool
    {
        return strlen($password) >= 8
            && (bool) preg_match('/[A-Za-zÁÉÍÓÚÜáéíóúüÑñ]/u', $password)
            && (bool) preg_match('/[0-9]/', $password);
    }

    private function correoEsValido(string $correo): bool
    {
        return (bool) filter_var($correo, FILTER_VALIDATE_EMAIL)
            && strlen($correo) <= 100;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerSolicitudCruda(string $clvUsu): ?array
    {
        if ($clvUsu === '') {
            return null;
        }

        $solicitud = Session::get(self::SESION_CLAVE);

        if (!is_array($solicitud)) {
            return null;
        }

        if ((string) ($solicitud['clv_usu'] ?? '') !== $clvUsu) {
            return null;
        }

        return $solicitud;
    }

    /**
     * @return array{ok: bool, codigo: string, mensaje: string, reenviarEn?: int}|null
     */
    private function evaluarBloqueoSolicitudActiva(
        string $clvUsu,
        string $correoNuevo
    ): ?array {
        $solicitud = $this->obtenerSolicitudCruda($clvUsu);
        $solicitud = $this->sanearEnvioEstancado($solicitud);

        if ($solicitud === null) {
            return null;
        }

        if (!empty($solicitud['verificacion_en_proceso'])) {
            return $this->fallo(
                'La verificación ya está en proceso.',
                'VERIFICACION_EN_PROCESO'
            );
        }

        if (!empty($solicitud['envio_en_proceso'])) {
            return $this->fallo(
                'El código ya se está enviando.',
                'ENVIO_EN_PROCESO'
            );
        }

        $correoPendiente = $this->normalizarCorreo(
            (string) ($solicitud['correo_nuevo'] ?? '')
        );

        if (
            $correoPendiente !== ''
            && $correoPendiente !== $correoNuevo
        ) {
            return $this->fallo(
                'Ya existe un cambio de correo pendiente. Cancélalo antes de solicitar otro.',
                'SOLICITUD_ACTIVA_OTRO_CORREO'
            );
        }

        $ahora = time();
        $reenviarDesde = (int) ($solicitud['reenviar_desde'] ?? 0);
        $tieneCodigo = (string) ($solicitud['codigo_hash'] ?? '') !== '';

        if ($tieneCodigo && $ahora < $reenviarDesde) {
            return $this->fallo(
                'Ya enviamos un código a este correo. Podrás solicitar otro en unos segundos.',
                'CODIGO_YA_ENVIADO',
                ['reenviarEn' => max(1, $reenviarDesde - $ahora)]
            );
        }

        if (
            $tieneCodigo
            && (int) ($solicitud['reenvios'] ?? 0) >= self::MAX_REENVIOS
        ) {
            return $this->fallo(
                'Alcanzaste el límite de reenvíos. Cancela la solicitud e inicia una nueva más tarde.',
                'LIMITE_REENVIOS'
            );
        }

        // Misma solicitud y cooldown vencido: usar reenvío explícito, no otro "enviar".
        if ($tieneCodigo && $correoPendiente === $correoNuevo) {
            return $this->fallo(
                'Ya existe un código pendiente para este correo. Usa “Reenviar código” o cancela la solicitud.',
                'SOLICITUD_ACTIVA',
                [
                    'reenviarEn' => max(
                        0,
                        $reenviarDesde - $ahora
                    )
                ]
            );
        }

        return null;
    }

    /**
     * Libera envíos marcados en proceso si quedaron estancados.
     *
     * @param array<string, mixed>|null $solicitud
     * @return array<string, mixed>|null
     */
    private function sanearEnvioEstancado(?array $solicitud): ?array
    {
        if ($solicitud === null) {
            return null;
        }

        if (empty($solicitud['envio_en_proceso'])) {
            return $solicitud;
        }

        $iniciado = (int) ($solicitud['envio_iniciado_en'] ?? 0);
        $edad = $iniciado > 0 ? (time() - $iniciado) : self::ENVIO_HUERFANO_SEGUNDOS;

        if ($edad < self::ENVIO_HUERFANO_SEGUNDOS) {
            return $solicitud;
        }

        $sid = substr((string) ($solicitud['solicitud_id'] ?? ''), 0, 8);

        error_log(
            'cambio_correo_bloqueo_huerfano evento=envio_en_proceso_timeout sid='
            . $sid
        );

        // Recuperar sin reenviar automáticamente.
        $solicitud['envio_en_proceso'] = false;

        if ((string) ($solicitud['codigo_hash'] ?? '') !== '') {
            Session::set(self::SESION_CLAVE, $solicitud);
            Session::setFlash('config_toast_tipo', 'info');
            Session::setFlash(
                'config_toast_mensaje',
                'Un envío anterior quedó interrumpido. Puedes verificar el código o reenviarlo cuando el tiempo lo permita.'
            );

            return $solicitud;
        }

        // Envío inicial nunca completó: limpiar para permitir un nuevo intento.
        Session::remove(self::SESION_CLAVE);
        Session::setFlash('config_toast_tipo', 'info');
        Session::setFlash(
            'config_toast_mensaje',
            'El envío anterior no se completó. Puedes intentar nuevamente.'
        );

        return null;
    }

    /**
     * Tras SMTP: reabre sesión y escribe solo si la misma solicitud sigue vigente.
     *
     * @param callable(array<string, mixed>):void $aplicar
     * @return array{ok: bool, codigo: string, mensaje: string}|null
     */
    private function aplicarPostSmtpSiVigente(
        string $clvUsu,
        string $solicitudId,
        string $correoNuevoEsperado,
        callable $aplicar
    ): ?array {
        Session::start();

        $actual = $this->obtenerSolicitudCruda($clvUsu);

        if (!is_array($actual)) {
            error_log(
                'cambio_correo_post_smtp evento=solicitud_ausente sid='
                . substr($solicitudId, 0, 8)
            );

            return $this->fallo(
                'La solicitud de cambio de correo ya no es válida. Si recibiste un código, cancélalo e inicia una nueva solicitud.',
                'SOLICITUD_INVALIDA'
            );
        }

        if ((string) ($actual['solicitud_id'] ?? '') !== $solicitudId) {
            error_log(
                'cambio_correo_post_smtp evento=solicitud_sustituida sid='
                . substr($solicitudId, 0, 8)
            );

            return $this->fallo(
                'La solicitud de cambio de correo ya no es válida.',
                'SOLICITUD_INVALIDA'
            );
        }

        if ((string) ($actual['clv_usu'] ?? '') !== $clvUsu) {
            error_log(
                'cambio_correo_post_smtp evento=clv_distinto sid='
                . substr($solicitudId, 0, 8)
            );

            return $this->fallo(
                'La solicitud de cambio de correo ya no es válida.',
                'SOLICITUD_INVALIDA'
            );
        }

        $correoActualSolicitud = $this->normalizarCorreo(
            (string) ($actual['correo_nuevo'] ?? '')
        );

        if ($correoActualSolicitud !== $this->normalizarCorreo($correoNuevoEsperado)) {
            error_log(
                'cambio_correo_post_smtp evento=correo_distinto sid='
                . substr($solicitudId, 0, 8)
            );

            return $this->fallo(
                'La solicitud de cambio de correo ya no es válida.',
                'SOLICITUD_INVALIDA'
            );
        }

        if (!empty($actual['verificacion_en_proceso'])) {
            return $this->fallo(
                'La verificación ya está en proceso.',
                'VERIFICACION_EN_PROCESO'
            );
        }

        $aplicar($actual);
        Session::set(self::SESION_CLAVE, $actual);

        return null;
    }

    /**
     * Limpia o restaura solo si la solicitud de sesión sigue siendo la misma.
     */
    private function limpiarEnvioSiMismaSolicitud(
        string $clvUsu,
        string $solicitudId,
        bool $esReenvio,
        string $hashAnterior = '',
        int $reenviosAnteriores = 0
    ): void {
        Session::start();

        $actual = $this->obtenerSolicitudCruda($clvUsu);

        if (
            !is_array($actual)
            || (string) ($actual['solicitud_id'] ?? '') !== $solicitudId
        ) {
            // Cancelada o sustituida: no tocar el estado vigente.
            return;
        }

        if ($esReenvio) {
            $actual['envio_en_proceso'] = false;
            $actual['codigo_hash'] = $hashAnterior;
            $actual['reenvios'] = $reenviosAnteriores;
            Session::set(self::SESION_CLAVE, $actual);

            return;
        }

        Session::remove(self::SESION_CLAVE);
    }

    /**
     * @param array<string, mixed> $cambios
     */
    private function actualizarSesionUsuario(array $cambios): void
    {
        $usuario = Session::get('usuario');

        if (!is_array($usuario)) {
            return;
        }

        foreach ($cambios as $clave => $valor) {
            $usuario[$clave] = $valor;
        }

        Session::set('usuario', $usuario);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array{ok: bool, codigo: string, mensaje: string}
     */
    private function ok(
        string $mensaje,
        string $codigo = 'OK',
        array $extra = []
    ): array {
        return array_merge(
            [
                'ok' => true,
                'codigo' => $codigo,
                'mensaje' => $mensaje
            ],
            $extra
        );
    }

    /**
     * @param array<string, mixed> $extra
     * @return array{ok: bool, codigo: string, mensaje: string}
     */
    private function fallo(
        string $mensaje,
        string $codigo = 'ERROR',
        array $extra = []
    ): array {
        return array_merge(
            [
                'ok' => false,
                'codigo' => $codigo,
                'mensaje' => $mensaje
            ],
            $extra
        );
    }
}
