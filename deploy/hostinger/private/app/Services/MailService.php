<?php
namespace App\Services;
use App\Config\Config;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use RuntimeException;

class MailService {

/**
 * Legacy: no enviar contraseñas por correo.
 * Usar ActivacionCuentaService + enviarActivacionPsicologo().
 *
 * @deprecated
 */
public function enviarAccesoPsicologo(
    string $correoDestino,
    string $nombreDestino,
    string $contrasenaTemporal,
    string $urlLogin
): void {
    unset($correoDestino, $nombreDestino, $contrasenaTemporal, $urlLogin);

    throw new RuntimeException(
        'enviarAccesoPsicologo está deshabilitado. '
        . 'Use el flujo de activación por enlace.'
    );
}


/**
 * Legacy: no enviar contraseñas por correo.
 * Usar ActivacionCuentaService + enviarActivacionConsultorio().
 *
 * @deprecated
 */
public function enviarAccesoConsultorio(
    string $correoDestino,
    string $nombreResponsable,
    string $nombreConsultorio,
    string $contrasenaTemporal,
    string $urlLogin
): void {
    unset(
        $correoDestino,
        $nombreResponsable,
        $nombreConsultorio,
        $contrasenaTemporal,
        $urlLogin
    );

    throw new RuntimeException(
        'enviarAccesoConsultorio está deshabilitado. '
        . 'Use el flujo de activación por enlace.'
    );
}

public function enviarActivacionConsultorio(
    string $correoDestino,
    string $nombreDestino,
    string $urlActivacion,
    int $horasExpiracion = 24,
    string $nombreConsultorio = ''
): void {
    $mail = $this->crearMailerBase();
    $fromName = Config::get('MAIL_FROM_NAME', 'PsicoMatch');

    try {
        $mail->addAddress($correoDestino, $nombreDestino);
        $mail->isHTML(true);
        $mail->Subject = 'Activa tu cuenta de consultorio en PsicoMatch';

        $nombreSeguro = htmlspecialchars($nombreDestino, ENT_QUOTES, 'UTF-8');
        $consSeguro = htmlspecialchars($nombreConsultorio, ENT_QUOTES, 'UTF-8');
        $urlSegura = htmlspecialchars($urlActivacion, ENT_QUOTES, 'UTF-8');
        $fromNameSeguro = htmlspecialchars($fromName, ENT_QUOTES, 'UTF-8');
        $horas = max(1, $horasExpiracion);
        $bloqueCons = $consSeguro !== ''
            ? '<p style="line-height:1.7;">Consultorio: <strong>'
                . $consSeguro
                . '</strong></p>'
            : '';

        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:30px 15px;background:#f4f8f6;font-family:Arial,Helvetica,sans-serif;color:#3f4942;">
<div style="max-width:580px;margin:0 auto;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 12px 40px rgba(101,113,102,.15);">
<div style="padding:28px;background:linear-gradient(135deg,#DAEBE3,#99CDD8);text-align:center;">
<h2 style="margin:0;color:#465149;font-size:23px;">{$fromNameSeguro}</h2>
</div>
<div style="padding:35px 36px;">
<p>Hola <strong>{$nombreSeguro}</strong>:</p>
{$bloqueCons}
<p style="line-height:1.7;">
Activa tu cuenta de consultorio estableciendo tu propia contraseña.
El enlace expira en {$horas} horas y solo puede usarse una vez.
</p>
<div style="margin-top:28px;text-align:center;">
<a href="{$urlSegura}" style="display:inline-block;padding:13px 24px;border-radius:14px;background:#99CDD8;color:#465149;font-weight:bold;text-decoration:none;">
Activar mi cuenta
</a>
</div>
</div>
</div>
</body>
</html>
HTML;

        $mail->AltBody =
            "Hola {$nombreDestino}.\n\n" .
            "Activa tu cuenta de consultorio en:\n{$urlActivacion}\n";

        $mail->send();
        $this->registrarUrlActivacionDesarrollo(
            'consultorio',
            $correoDestino,
            $urlActivacion
        );
    } catch (MailException $e) {
        error_log('Error PHPMailer activación consultorio: envío fallido');
        throw new RuntimeException(
            'No se pudo enviar el correo de activación.'
        );
    }
}
    public function enviarCodigoRecuperacion(
        string $correoDestino,
        string $nombreDestino,
        string $codigo
    ): void {
        $host = Config::get('MAIL_HOST');
        $port = (int) Config::get('MAIL_PORT', '587');
        $encryption = strtolower(
            Config::get('MAIL_ENCRYPTION', 'tls')
        );

        $username = Config::get('MAIL_USERNAME');
        $password = Config::get('MAIL_PASSWORD');

        $fromAddress = Config::get(
            'MAIL_FROM_ADDRESS',
            $username
        );

        $fromName = Config::get(
            'MAIL_FROM_NAME',
            'Consultorio Psicológico'
        );

        if (
            !$host ||
            !$username ||
            !$password ||
            !$fromAddress
        ) {
            throw new RuntimeException(
                'La configuración SMTP está incompleta.'
            );
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
           $mail->SMTPDebug = SMTP::DEBUG_OFF;

$mail->Debugoutput = static function (
    string $mensaje,
    int $nivel
): void {
    error_log("SMTP [$nivel]: $mensaje");
};

            $mail->SMTPSecure =
                $encryption === 'ssl'
                    ? PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer::ENCRYPTION_STARTTLS;

            $mail->setFrom($fromAddress, $fromName);

            $mail->addAddress(
                $correoDestino,
                $nombreDestino
            );

            $mail->isHTML(true);

            $mail->Subject =
                'Código de recuperación de contraseña';

            $nombreSeguro = htmlspecialchars(
                $nombreDestino,
                ENT_QUOTES,
                'UTF-8'
            );

            $codigoSeguro = htmlspecialchars(
                $codigo,
                ENT_QUOTES,
                'UTF-8'
            );

            $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
</head>

<body style="
    margin:0;
    padding:30px 15px;
    background:#f4f8f6;
    font-family:Arial,Helvetica,sans-serif;
    color:#3f4942;
">

    <div style="
        max-width:560px;
        margin:0 auto;
        background:#ffffff;
        border-radius:22px;
        overflow:hidden;
        box-shadow:0 12px 40px rgba(101,113,102,.15);
    ">

        <div style="
            padding:28px;
            background:linear-gradient(135deg,#DAEBE3,#99CDD8);
            text-align:center;
        ">

            <h2 style="
                margin:0;
                color:#465149;
                font-size:23px;
            ">
                {$fromName}
            </h2>

        </div>

        <div style="padding:35px 36px;">

            <h3 style="
                margin:0 0 18px;
                color:#465149;
            ">
                Recuperación de contraseña
            </h3>

            <p>
                Hola <strong>{$nombreSeguro}</strong>:
            </p>

            <p style="line-height:1.7;">
                Recibimos una solicitud para recuperar el acceso
                a tu cuenta. Utiliza el siguiente código:
            </p>

            <div style="
                margin:28px 0;
                padding:22px 15px;
                border-radius:18px;
                background:#FDE8D3;
                text-align:center;
            ">

                <span style="
                    color:#657166;
                    font-size:34px;
                    font-weight:bold;
                    letter-spacing:9px;
                ">
                    {$codigoSeguro}
                </span>

            </div>

            <p style="
                color:#657166;
                font-size:13px;
                line-height:1.7;
            ">
                El código vencerá en 10 minutos y solo podrá
                utilizarse una vez.
            </p>

            <p style="
                color:#788279;
                font-size:13px;
                line-height:1.7;
            ">
                Si no solicitaste este cambio, ignora este mensaje.
                Tu contraseña continuará sin modificaciones.
            </p>

        </div>

        <div style="
            padding:20px;
            background:#f7faf8;
            color:#89938c;
            text-align:center;
            font-size:11px;
        ">
            Mensaje automático. No respondas este correo.
        </div>

    </div>

</body>
</html>
HTML;

            $mail->AltBody =
                "Hola {$nombreDestino}.\n\n" .
                "Tu código de recuperación es: {$codigo}\n\n" .
                "El código vence en 10 minutos.\n\n" .
                "Si no solicitaste este cambio, ignora este mensaje.";

            $mail->send();

        } catch (MailException $e) {
            error_log(
                'Error PHPMailer: ' . $mail->ErrorInfo
            );

            throw new RuntimeException(
                'No se pudo enviar el correo de recuperación.'
            );
        }
    }

    public function enviarCodigoCambioCorreo(
        string $correoDestino,
        string $nombreDestino,
        string $codigo
    ): void {
        $mail = $this->crearMailerBase();

        try {
            $mail->addAddress($correoDestino, $nombreDestino);
            $mail->isHTML(true);
            $mail->Subject = 'Código para verificar tu nuevo correo';

            $nombreSeguro = htmlspecialchars(
                $nombreDestino,
                ENT_QUOTES,
                'UTF-8'
            );
            $codigoSeguro = htmlspecialchars(
                $codigo,
                ENT_QUOTES,
                'UTF-8'
            );

            $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:30px 15px;background:#f4f8f6;font-family:Arial,Helvetica,sans-serif;color:#3f4942;">
<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 12px 40px rgba(101,113,102,.15);">
<div style="padding:28px;background:linear-gradient(135deg,#DAEBE3,#99CDD8);text-align:center;">
<h2 style="margin:0;color:#465149;font-size:23px;">PsicoMatch</h2>
</div>
<div style="padding:35px 36px;">
<h3 style="margin:0 0 18px;color:#465149;">Verifica tu nuevo correo</h3>
<p>Hola <strong>{$nombreSeguro}</strong>:</p>
<p style="line-height:1.7;">
Recibimos una solicitud para cambiar el correo de acceso de tu cuenta PsicoMatch.
</p>
<p style="line-height:1.7;">Tu código de verificación es:</p>
<div style="margin:28px 0;padding:22px 15px;border-radius:18px;background:#FDE8D3;text-align:center;">
<span style="color:#657166;font-size:34px;font-weight:bold;letter-spacing:9px;">{$codigoSeguro}</span>
</div>
<p style="color:#657166;font-size:13px;line-height:1.7;">El código vence en 10 minutos.</p>
<p style="color:#788279;font-size:13px;line-height:1.7;">Si no realizaste esta solicitud, ignora este mensaje.</p>
</div>
<div style="padding:20px;background:#f7faf8;color:#89938c;text-align:center;font-size:11px;">
Mensaje automático. No respondas este correo.
</div>
</div>
</body>
</html>
HTML;

            $mail->AltBody =
                "Hola {$nombreDestino}.\n\n" .
                "Recibimos una solicitud para cambiar el correo de acceso de tu cuenta PsicoMatch.\n\n" .
                "Tu código de verificación es: {$codigo}\n\n" .
                "El código vence en 10 minutos.\n\n" .
                "Si no realizaste esta solicitud, ignora este mensaje.";

            $mail->send();
        } catch (MailException $e) {
            error_log('Error PHPMailer cambio correo: envío fallido');

            throw new RuntimeException(
                'No se pudo enviar el correo de verificación.'
            );
        }
    }

    public function enviarAvisoCambioCorreoAnterior(
        string $correoDestino,
        string $nombreDestino
    ): void {
        $mail = $this->crearMailerBase();

        try {
            $mail->addAddress($correoDestino, $nombreDestino);
            $mail->isHTML(true);
            $mail->Subject = 'Tu correo de acceso fue actualizado';

            $nombreSeguro = htmlspecialchars(
                $nombreDestino,
                ENT_QUOTES,
                'UTF-8'
            );

            $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:30px 15px;background:#f4f8f6;font-family:Arial,Helvetica,sans-serif;color:#3f4942;">
<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:22px;overflow:hidden;">
<div style="padding:28px;background:linear-gradient(135deg,#DAEBE3,#FDE8D3);text-align:center;">
<h2 style="margin:0;color:#465149;">PsicoMatch</h2>
</div>
<div style="padding:35px 36px;">
<p>Hola <strong>{$nombreSeguro}</strong>:</p>
<p style="line-height:1.7;">
El correo de acceso de tu cuenta PsicoMatch fue actualizado.
</p>
<p style="color:#788279;font-size:13px;line-height:1.7;">
Si no reconoces este cambio, contacta al consultorio de inmediato.
</p>
</div>
</div>
</body>
</html>
HTML;

            $mail->AltBody =
                "Hola {$nombreDestino}.\n\n" .
                "El correo de acceso de tu cuenta PsicoMatch fue actualizado.\n\n" .
                "Si no reconoces este cambio, contacta al consultorio de inmediato.";

            $mail->send();
        } catch (MailException $e) {
            error_log('Error PHPMailer aviso correo: envío fallido');

            throw new RuntimeException(
                'No se pudo enviar el aviso de seguridad.'
            );
        }
    }

    public function enviarActivacionPsicologo(
        string $correoDestino,
        string $nombreDestino,
        string $urlActivacion,
        int $horasExpiracion = 24
    ): void {
        $mail = $this->crearMailerBase();
        $fromName = Config::get('MAIL_FROM_NAME', 'PsicoMatch');

        try {
            $mail->addAddress($correoDestino, $nombreDestino);
            $mail->isHTML(true);
            $mail->Subject = 'Activa tu cuenta de especialista en PsicoMatch';

            $nombreSeguro = htmlspecialchars(
                $nombreDestino,
                ENT_QUOTES,
                'UTF-8'
            );
            $urlSegura = htmlspecialchars(
                $urlActivacion,
                ENT_QUOTES,
                'UTF-8'
            );
            $fromNameSeguro = htmlspecialchars(
                $fromName,
                ENT_QUOTES,
                'UTF-8'
            );
            $horas = max(1, $horasExpiracion);

            $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:30px 15px;background:#f4f8f6;font-family:Arial,Helvetica,sans-serif;color:#3f4942;">
<div style="max-width:580px;margin:0 auto;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 12px 40px rgba(101,113,102,.15);">
<div style="padding:28px;background:linear-gradient(135deg,#DAEBE3,#99CDD8);text-align:center;">
<h2 style="margin:0;color:#465149;font-size:23px;">{$fromNameSeguro}</h2>
</div>
<div style="padding:35px 36px;">
<p>Hola <strong>{$nombreSeguro}</strong>:</p>
<p style="line-height:1.7;">
Tu cuenta de especialista fue creada. Para activarla, establece tu contraseña
mediante el siguiente enlace. Expira en {$horas} horas y solo puede usarse una vez.
</p>
<div style="margin-top:28px;text-align:center;">
<a href="{$urlSegura}" style="display:inline-block;padding:13px 24px;border-radius:14px;background:#99CDD8;color:#465149;font-weight:bold;text-decoration:none;">
Activar mi cuenta
</a>
</div>
<p style="margin-top:24px;color:#657166;font-size:13px;line-height:1.7;">
Si no esperabas este correo, ignóralo.
</p>
</div>
<div style="padding:20px;background:#f7faf8;color:#89938c;text-align:center;font-size:11px;">
Mensaje automático. No respondas este correo.
</div>
</div>
</body>
</html>
HTML;

            $mail->AltBody =
                "Hola {$nombreDestino}.\n\n" .
                "Activa tu cuenta de especialista en:\n{$urlActivacion}\n\n" .
                "El enlace expira en {$horas} horas.";

            $mail->send();
            $this->registrarUrlActivacionDesarrollo(
                'psicologo',
                $correoDestino,
                $urlActivacion
            );
        } catch (MailException $e) {
            error_log('Error PHPMailer activación psicólogo: envío fallido');

            throw new RuntimeException(
                'No se pudo enviar el correo de activación.'
            );
        }
    }

    /**
     * @param array<string, mixed> $contexto
     */
    public function enviarActivacionPacienteConCita(
        array $contexto,
        string $urlActivacion,
        int $horasExpiracion = 24
    ): void {
        $mail = $this->crearMailerBase();
        $fromName = Config::get('MAIL_FROM_NAME', 'PsicoMatch');

        $correoDestino = (string) ($contexto['CorreoUsu'] ?? '');
        $nombrePaciente = trim((string) ($contexto['NombrePaciente'] ?? ''));
        $nombrePsicologo = trim((string) ($contexto['NombrePsicologo'] ?? ''));
        $consultorio = trim((string) ($contexto['NombreCons'] ?? ''));
        $servicio = trim((string) ($contexto['NombreServicio'] ?? ''));
        $fecha = (string) ($contexto['FechaCita'] ?? '');
        $hora = substr((string) ($contexto['HraInicioCita'] ?? ''), 0, 5);
        $duracion = (int) ($contexto['DuracionAplicadaMin'] ?? 0);

        try {
            $fechaFmt = $fecha !== ''
                ? date('d/m/Y', strtotime($fecha))
                : '';

            $mail->addAddress($correoDestino, $nombrePaciente);
            $mail->isHTML(true);
            $mail->Subject =
                'Activa tu cuenta y consulta tu cita en PsicoMatch';

            $esc = static function (string $v): string {
                return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
            };

            $nombreSeguro = $esc($nombrePaciente);
            $psiSeguro = $esc($nombrePsicologo);
            $consSeguro = $esc($consultorio);
            $servSeguro = $esc($servicio);
            $fechaSegura = $esc($fechaFmt);
            $horaSegura = $esc($hora);
            $urlSegura = $esc($urlActivacion);
            $fromNameSeguro = $esc($fromName);
            $horas = max(1, $horasExpiracion);

            $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:30px 15px;background:#f4f8f6;font-family:Arial,Helvetica,sans-serif;color:#3f4942;">
<div style="max-width:580px;margin:0 auto;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 12px 40px rgba(101,113,102,.15);">
<div style="padding:28px;background:linear-gradient(135deg,#DAEBE3,#99CDD8);text-align:center;">
<h2 style="margin:0;color:#465149;font-size:23px;">{$fromNameSeguro}</h2>
</div>
<div style="padding:35px 36px;">
<p>Hola <strong>{$nombreSeguro}</strong>:</p>
<p style="line-height:1.7;">
Se registró tu cuenta y tu primera cita.
Activa tu cuenta para consultar o cancelar tu cita desde PsicoMatch.
</p>
<div style="margin:28px 0;padding:22px;border-radius:18px;background:#FDE8D3;">
<p style="margin:0 0 8px;"><strong>Psicólogo:</strong> {$psiSeguro}</p>
<p style="margin:0 0 8px;"><strong>Consultorio:</strong> {$consSeguro}</p>
<p style="margin:0 0 8px;"><strong>Servicio:</strong> {$servSeguro}</p>
<p style="margin:0 0 8px;"><strong>Fecha:</strong> {$fechaSegura}</p>
<p style="margin:0 0 8px;"><strong>Hora:</strong> {$horaSegura}</p>
<p style="margin:0;"><strong>Duración:</strong> {$duracion} minutos</p>
</div>
<p style="color:#657166;font-size:13px;line-height:1.7;">
El enlace de activación expira en {$horas} horas y solo puede usarse una vez.
</p>
<div style="margin-top:28px;text-align:center;">
<a href="{$urlSegura}" style="display:inline-block;padding:13px 24px;border-radius:14px;background:#99CDD8;color:#465149;font-weight:bold;text-decoration:none;">
Activar mi cuenta
</a>
</div>
</div>
<div style="padding:20px;background:#f7faf8;color:#89938c;text-align:center;font-size:11px;">
Mensaje automático. No respondas este correo.
</div>
</div>
</body>
</html>
HTML;

            $mail->AltBody =
                "Hola {$nombrePaciente}.\n\n" .
                "Tu cita: {$servicio} con {$nombrePsicologo}\n" .
                "Consultorio: {$consultorio}\n" .
                "Fecha: {$fechaFmt} {$hora}\n" .
                "Duración: {$duracion} minutos\n\n" .
                "Activa tu cuenta en:\n{$urlActivacion}\n";

            $mail->send();
        } catch (MailException $e) {
            error_log('Error PHPMailer activación paciente: envío fallido');

            throw new RuntimeException(
                'No se pudo enviar el correo de activación.'
            );
        }
    }

    private function registrarUrlActivacionDesarrollo(
        string $tipo,
        string $correoDestino,
        string $urlActivacion
    ): void {
        // Nunca en producción. Tampoco registrar el token completo fuera de development.
        if (strtolower((string) Config::get('APP_ENV', '')) !== 'development') {
            return;
        }

        unset($tipo, $correoDestino, $urlActivacion);
    }

    private function crearMailerBase(): PHPMailer
    {
        $host = Config::get('MAIL_HOST');
        $port = (int) Config::get('MAIL_PORT', '587');
        $encryption = strtolower(
            Config::get('MAIL_ENCRYPTION', 'tls')
        );
        $username = Config::get('MAIL_USERNAME');
        $password = Config::get('MAIL_PASSWORD');
        $fromAddress = Config::get('MAIL_FROM_ADDRESS', $username);
        $fromName = Config::get(
            'MAIL_FROM_NAME',
            'Consultorio Psicológico'
        );

        if (!$host || !$username || !$password || !$fromAddress) {
            throw new RuntimeException(
                'La configuración SMTP está incompleta.'
            );
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->Port = $port;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->SMTPSecure =
            $encryption === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom($fromAddress, $fromName);

        return $mail;
    }
}