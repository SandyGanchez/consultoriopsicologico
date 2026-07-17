<?php
namespace App\Services;
use App\Config\Config;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use RuntimeException;

class MailService {

public function enviarAccesoPsicologo(
    string $correoDestino,
    string $nombreDestino,
    string $contrasenaTemporal,
    string $urlLogin
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

        $mail->SMTPDebug = SMTP::DEBUG_SERVER;

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

        $mail->setFrom(
            $fromAddress,
            $fromName
        );

        $mail->addAddress(
            $correoDestino,
            $nombreDestino
        );

        $mail->isHTML(true);

        $mail->Subject =
            'Tu cuenta de especialista ha sido creada';

        $nombreSeguro = htmlspecialchars(
            $nombreDestino,
            ENT_QUOTES,
            'UTF-8'
        );

        $correoSeguro = htmlspecialchars(
            $correoDestino,
            ENT_QUOTES,
            'UTF-8'
        );

        $contrasenaSegura = htmlspecialchars(
            $contrasenaTemporal,
            ENT_QUOTES,
            'UTF-8'
        );

        $urlLoginSegura = htmlspecialchars(
            $urlLogin,
            ENT_QUOTES,
            'UTF-8'
        );

        $fromNameSeguro = htmlspecialchars(
            $fromName,
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
        max-width:580px;
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
                {$fromNameSeguro}
            </h2>

        </div>

        <div style="padding:35px 36px;">

            <h3 style="
                margin:0 0 18px;
                color:#465149;
            ">
                Bienvenido al sistema
            </h3>

            <p>
                Hola <strong>{$nombreSeguro}</strong>:
            </p>

            <p style="line-height:1.7;">
                Se ha creado una cuenta de especialista para
                que puedas acceder al sistema del consultorio.
            </p>

            <div style="
                margin:28px 0;
                padding:22px;
                border-radius:18px;
                background:#FDE8D3;
            ">

                <p style="
                    margin:0 0 12px;
                    color:#657166;
                ">
                    <strong>Correo de acceso</strong>
                </p>

                <p style="
                    margin:0 0 22px;
                    font-size:16px;
                    word-break:break-word;
                ">
                    {$correoSeguro}
                </p>

                <p style="
                    margin:0 0 12px;
                    color:#657166;
                ">
                    <strong>Contraseña temporal</strong>
                </p>

                <p style="
                    margin:0;
                    font-size:23px;
                    font-weight:bold;
                    letter-spacing:2px;
                ">
                    {$contrasenaSegura}
                </p>

            </div>

            <p style="
                color:#657166;
                font-size:13px;
                line-height:1.7;
            ">
                Al iniciar sesión por primera vez deberás
                establecer una contraseña nueva.
            </p>

            <div style="
                margin-top:28px;
                text-align:center;
            ">

                <a
                    href="{$urlLoginSegura}"
                    style="
                        display:inline-block;
                        padding:13px 24px;
                        border-radius:14px;
                        background:#99CDD8;
                        color:#465149;
                        font-weight:bold;
                        text-decoration:none;
                    "
                >
                    Iniciar sesión
                </a>

            </div>

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
            "Se creó tu cuenta de especialista.\n\n" .
            "Correo: {$correoDestino}\n" .
            "Contraseña temporal: {$contrasenaTemporal}\n\n" .
            "Inicia sesión en:\n{$urlLogin}\n\n" .
            "En tu primer acceso deberás cambiar la contraseña.";

        $mail->send();

    } catch (MailException $e) {
        error_log(
            'Error PHPMailer: ' .
            $mail->ErrorInfo
        );

        throw new RuntimeException(
            'No se pudo enviar el correo de acceso.'
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
           $mail->SMTPDebug = SMTP::DEBUG_SERVER;

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
}