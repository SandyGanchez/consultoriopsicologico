<?php

namespace App\Services;

use App\Config\Database;
use App\Core\Response;
use App\Core\Session;
use App\Models\Paciente;
use App\Models\Persona;
use App\Models\RecuperacionPassword;
use App\Models\Usuario;
use DateTime;
use Exception;
use Throwable;

class AuthService
{
    public function login(
        string $correo,
        string $password
    ): ?array {
        $usuarioModel = new Usuario();

        $usuario = $usuarioModel->buscarPorCorreo(
            trim($correo)
        );

        if (!$usuario) {
            return null;
        }

        if (
            isset($usuario['EstadoUsu']) &&
            (int) $usuario['EstadoUsu'] !== 1
        ) {
            return null;
        }

        if (
            !password_verify(
                $password,
                $usuario['ContrasenaUsu']
            )
        ) {
            return null;
        }

        return $usuario;
    }

    public function registrar(array $datos): void
    {
        $campos = [
            'nombre',
            'apPat',
            'apMat',
            'fechaNacimiento',
            'genero',
            'correo',
            'telefono',
            'password',
            'password2'
        ];

        foreach ($campos as $campo) {
            if (empty(trim($datos[$campo] ?? ''))) {
                Session::set(
                    'error',
                    'Todos los campos obligatorios deben completarse.'
                );

                Response::redirect('registro');
            }
        }

        $fechaNacimiento = DateTime::createFromFormat(
            'Y-m-d',
            $datos['fechaNacimiento']
        );

        $erroresFecha = DateTime::getLastErrors();

        if (
            !$fechaNacimiento ||
            (
                is_array($erroresFecha) &&
                (
                    $erroresFecha['warning_count'] > 0 ||
                    $erroresFecha['error_count'] > 0
                )
            )
        ) {
            Session::set(
                'error',
                'La fecha de nacimiento no es válida.'
            );

            Response::redirect('registro');
        }

        $hoy = new DateTime('today');

        if ($fechaNacimiento > $hoy) {
            Session::set(
                'error',
                'La fecha de nacimiento no puede ser posterior a la fecha actual.'
            );

            Response::redirect('registro');
        }

        $correo = strtolower(trim($datos['correo']));

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            Session::set(
                'error',
                'Ingrese un correo electrónico válido.'
            );

            Response::redirect('registro');
        }

        $telefono = trim($datos['telefono']);

        if (!preg_match('/^[0-9]{10}$/', $telefono)) {
            Session::set(
                'error',
                'El teléfono debe contener exactamente 10 dígitos.'
            );

            Response::redirect('registro');
        }

        if (strlen($datos['password']) < 8) {
            Session::set(
                'error',
                'La contraseña debe tener mínimo 8 caracteres.'
            );

            Response::redirect('registro');
        }

        if (
            !preg_match('/[A-Za-z]/', $datos['password']) ||
            !preg_match('/[0-9]/', $datos['password'])
        ) {
            Session::set(
                'error',
                'La contraseña debe combinar letras y números.'
            );

            Response::redirect('registro');
        }

        if ($datos['password'] !== $datos['password2']) {
            Session::set(
                'error',
                'Las contraseñas no coinciden.'
            );

            Response::redirect('registro');
        }

        $usuarioModel = new Usuario();

        if ($usuarioModel->existeCorreo($correo)) {
            Session::set(
                'error',
                'El correo ya está registrado.'
            );

            Response::redirect('registro');
        }

        $db = Database::connect();

        try {
            $db->beginTransaction();

            $clvPer = ClaveService::generar(
                'persona',
                'ClvPer',
                'P'
            );

            $clvUsu = ClaveService::generar(
                'usuario',
                'ClvUsu',
                'U'
            );

            $clvPac = ClaveService::generar(
                'paciente',
                'ClvPac',
                'PAC'
            );

            (new Persona())->crear([
                'ClvPer' => $clvPer,
                'NombrePer' => trim($datos['nombre']),
                'ApPatPer' => trim($datos['apPat']),
                'ApMatPer' => trim($datos['apMat']),
                'FechaNacimiento' => $datos['fechaNacimiento'],
                'GeneroPer' => $datos['genero']
            ]);

            (new Usuario())->crear([
                'ClvUsu' => $clvUsu,
                'CorreoUsu' => $correo,
                'TelefonoUsu' => $telefono,
                'ContrasenaUsu' => password_hash(
                    $datos['password'],
                    PASSWORD_DEFAULT
                ),
                'ClvPer' => $clvPer
            ]);

            (new Paciente())->crear([
                'ClvPac' => $clvPac,
                'ClvUsu' => $clvUsu
            ]);

            $db->commit();

            $usuario = (new Usuario())->buscarPorCorreo(
                $correo
            );

            Session::set('usuario', $usuario);

            Response::redirect('paciente');

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            Session::set(
                'error',
                'No se pudo crear la cuenta. Intente nuevamente.'
            );

            Response::redirect('registro');
        }
    }

   public function enviarCodigoRecuperacion(
    string $correo
): array {
    $correo = strtolower(trim($correo));

    $usuarioModel = new Usuario();
    $usuario = $usuarioModel->buscarPorCorreo($correo);

    if (
        !$usuario ||
        (int) ($usuario['EstadoUsu'] ?? 0) !== 1
    ) {
        return [
            'success' => false,
            'message' =>
                'No se encontró una cuenta activa con ese correo.'
        ];
    }

    $recuperacionModel = new RecuperacionPassword();

    if (
        !$recuperacionModel->puedeSolicitarCodigo(
            $usuario['ClvUsu'],
            60
        )
    ) {
        return [
            'success' => false,
            'message' =>
                'Espera un minuto antes de solicitar otro código.'
        ];
    }

    if (
        $recuperacionModel->contarSolicitudesRecientes(
            $usuario['ClvUsu']
        ) >= 5
    ) {
        return [
            'success' => false,
            'message' =>
                'Has solicitado demasiados códigos. Intenta nuevamente más tarde.'
        ];
    }

    $codigo = (string) random_int(100000, 999999);

    $codigoHash = password_hash(
        $codigo,
        PASSWORD_DEFAULT
    );

    if ($codigoHash === false) {
        return [
            'success' => false,
            'message' =>
                'No fue posible generar el código de recuperación.'
        ];
    }

  
    $nombreCompleto = trim(
        ($usuario['NombrePer'] ?? '') . ' ' .
        ($usuario['ApPatPer'] ?? '') . ' ' .
        ($usuario['ApMatPer'] ?? '')
    );

    if ($nombreCompleto === '') {
        $nombreCompleto = 'Usuario';
    }

    try {
        $recuperacionModel->invalidarCodigosAnteriores(
            $usuario['ClvUsu']
        );

      $idRec = $recuperacionModel->crear(
    $usuario['ClvUsu'],
    $codigoHash
);

        try {
            (new MailService())->enviarCodigoRecuperacion(
                $correo,
                $nombreCompleto,
                $codigo
            );
        } catch (Throwable $e) {
            /*
             * El usuario no recibió el código, por lo que debe
             * quedar inutilizable.
             */
            $recuperacionModel->marcarComoUtilizado($idRec);

            throw $e;
        }

        return [
            'success' => true,
            'message' =>
                'El código fue enviado correctamente a tu correo.',
            'recovery_id' => $idRec
        ];

    } catch (Throwable $e) {
        error_log(
            'Recuperación de contraseña: ' .
            $e->getMessage()
        );

        return [
            'success' => false,
            'message' =>
                'No fue posible completar la recuperación. Intenta nuevamente.'
        ];
    }
}
public function validarCodigoRecuperacion(
    string $correo,
    string $codigo,
    int $recoveryId
): array {
    $correo = strtolower(trim($correo));

    $codigo = preg_replace(
        '/\D/',
        '',
        $codigo
    );

    if (
        !is_string($codigo) ||
        !preg_match('/^\d{6}$/', $codigo)
    ) {
        return [
            'success' => false,
            'message' => 'El código debe contener seis dígitos.'
        ];
    }

    $usuario = (new Usuario())->buscarPorCorreo($correo);

    if (!$usuario) {
        return [
            'success' => false,
            'message' =>
                'La solicitud de recuperación no es válida.'
        ];
    }

    $recuperacionModel = new RecuperacionPassword();

    /*
     * Busca exactamente el registro generado para esta sesión.
     * Ya no depende de encontrar "el último" código del usuario.
     */
    $recuperacion = $recuperacionModel->obtenerActivoPorId(
        $recoveryId,
        $usuario['ClvUsu']
    );

    if (!$recuperacion) {
        return [
            'success' => false,
            'message' =>
                'El código expiró o ya fue utilizado. Solicita uno nuevo.'
        ];
    }

    $intentos = (int) $recuperacion['Intentos'];

    if ($intentos >= 5) {
        $recuperacionModel->marcarComoUtilizado(
            (int) $recuperacion['IdRec']
        );

        return [
            'success' => false,
            'message' =>
                'Se superó el límite de intentos. Solicita otro código.'
        ];
    }

    if (
        !password_verify(
            $codigo,
            $recuperacion['CodigoHash']
        )
    ) {
        $recuperacionModel->incrementarIntentos(
            (int) $recuperacion['IdRec']
        );

        if ($intentos + 1 >= 5) {
            $recuperacionModel->marcarComoUtilizado(
                (int) $recuperacion['IdRec']
            );
        }

        return [
            'success' => false,
            'message' => 'El código ingresado es incorrecto.'
        ];
    }

    return [
        'success' => true,
        'message' => 'Código validado correctamente.',
        'recovery_id' => (int) $recuperacion['IdRec']
    ];
}
    public function actualizarPasswordRecuperada(
    string $correo,
    string $password,
    int $recoveryId
): array {
    $correo = strtolower(trim($correo));

    $usuarioModel = new Usuario();

    $usuario = $usuarioModel->buscarPorCorreo(
        $correo
    );

    if (!$usuario) {
        return [
            'success' => false,
            'message' =>
                'La solicitud de recuperación no es válida.'
        ];
    }

    $recuperacionModel = new RecuperacionPassword();

    // Usa la misma referencia validada y deja que MySQL compruebe la vigencia.
    // Esto evita desfases de zona horaria entre PHP y MySQL.
    $recuperacion = $recuperacionModel->obtenerActivoPorId(
        $recoveryId,
        $usuario['ClvUsu']
    );

    if (!$recuperacion) {
        return [
            'success' => false,
            'message' =>
                'La solicitud de recuperación expiró o no es válida.'
        ];
    }

   
    if (
        password_verify(
            $password,
            $usuario['ContrasenaUsu']
        )
    ) {
        return [
            'success' => false,
            'message' =>
                'La nueva contraseña debe ser diferente de la anterior.'
        ];
    }

    $db = Database::connect();

    try {
        $db->beginTransaction();

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        if (!$passwordHash) {
            throw new Exception(
                'No se pudo generar el hash.'
            );
        }

        if (
            !$usuarioModel->actualizarPassword(
                $usuario['ClvUsu'],
                $passwordHash
            )
        ) {
            throw new Exception(
                'No se pudo actualizar la contraseña.'
            );
        }

        /*
         * Invalida todos los códigos del usuario.
         */
        $recuperacionModel
            ->invalidarCodigosAnteriores(
                $usuario['ClvUsu']
            );

        $db->commit();

        return [
            'success' => true,
            'message' =>
                'La contraseña fue actualizada correctamente.'
        ];

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        error_log(
            'Cambio de contraseña: ' .
            $e->getMessage()
        );

        return [
            'success' => false,
            'message' =>
                'No fue posible actualizar la contraseña.'
        ];
    }
}}