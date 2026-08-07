<?php

namespace App\Services;

use App\Core\Model;
use App\Models\HorarioConsultorio;
use App\Services\ClaveService;
use PDO;
use RuntimeException;
use Throwable;

class AdministradorService extends Model
{
    /*
    =========================================
          REGISTRAR CONSULTORIO
    =========================================
    */

   public function registrarConsultorio(
    array $datos
): array {
    $bloqueoObtenido = false;

    try {
        $bloqueoObtenido = $this->adquirirBloqueoAltaUnica();

        if (!$bloqueoObtenido) {
            throw new RuntimeException(
                'No fue posible asegurar la alta única del consultorio. Intenta nuevamente.'
            );
        }

        $this->db->beginTransaction();

        // Doble verificación dentro de la transacción (instalación = un solo consultorio).
        $totalExistente = (int) $this->db
            ->query('SELECT COUNT(*) FROM consultorio')
            ->fetchColumn();

        if ($totalExistente > 0) {
            throw new RuntimeException(
                'Ya existe un consultorio en esta instalación. No es posible registrar otro.'
            );
        }

        $this->validarCorreosDisponibles(
            $datos['correoConsultorio'],
            $datos['correoResponsable']
        );

        $clvDireccion = ClaveService::generar(
            'direccion',
            'ClvDir',
            'DIR'
        );

        $clvPersona = ClaveService::generar(
            'persona',
            'ClvPer',
            'PER'
        );

        $clvConsultorio = ClaveService::generar(
            'consultorio',
            'ClvCons',
            'CON'
        );

        $clvUsuario = ClaveService::generar(
            'usuario',
            'ClvUsu',
            'USU'
        );

        $this->insertarDireccion(
            $clvDireccion,
            $datos
        );

        $this->insertarResponsable(
            $clvPersona,
            $clvDireccion,
            $datos
        );

        $this->insertarConsultorio(
            $clvConsultorio,
            $clvDireccion,
            $datos
        );

        $this->insertarUsuarioConsultorio(
            $clvUsuario,
            $clvPersona,
            $clvConsultorio,
            $datos
        );

        (new HorarioConsultorio())->crearDiasFaltantes(
            $clvConsultorio
        );

        $this->db->commit();

        $nombreResponsable = trim(
            $datos['nombreResponsable']
            . ' '
            . $datos['apellidoPaternoResponsable']
            . ' '
            . $datos['apellidoMaternoResponsable']
        );

        return [
            'ClvCons' => $clvConsultorio,
            'ClvUsu' => $clvUsuario,
            'correo' =>
                $datos['correoResponsable'],
            'nombreResponsable' =>
                $nombreResponsable,
            'nombreConsultorio' =>
                $datos['nombreConsultorio']
        ];
    } catch (\Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        if ($e instanceof \RuntimeException) {
            throw $e;
        }

        throw new \RuntimeException(
            'No fue posible registrar el consultorio: '
            . $e->getMessage(),
            0,
            $e
        );
    } finally {
        if ($bloqueoObtenido) {
            $this->liberarBloqueoAltaUnica();
        }
    }
}
private function insertarConsultorio(
    string $claveConsultorio,
    string $claveDireccion,
    array $datos
): void {
    $sql = "
        INSERT INTO consultorio (
            ClvCons,
            NombreCons,
            Slogan,
            Descripcion,
            TelefonoCons,
            CorreoElectronico,
            LimiteCancHoras,
            EstatusCons,
            PublicadoCons,
            FechaPublicacionCons,
            FechaRegistroCons,
            ClvDir
        ) VALUES (
            :clave,
            :nombre,
            :slogan,
            :descripcion,
            :telefono,
            :correo,
            :limiteCancelacion,
            'ACTIVO',
            0,
            NULL,
            NOW(),
            :direccion
        )
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clave' => $claveConsultorio,

        'nombre' =>
            $datos['nombreConsultorio'],

        'slogan' =>
            $datos['slogan'] !== ''
                ? $datos['slogan']
                : null,

        'descripcion' =>
            $datos['descripcion'] !== ''
                ? $datos['descripcion']
                : null,

        'telefono' =>
            $datos['telefonoConsultorio'],

        'correo' =>
            $datos['correoConsultorio'],

        'limiteCancelacion' =>
            $datos['limiteCancelacion'] !== ''
                ? (int) $datos['limiteCancelacion']
                : 24,

        'direccion' =>
            $claveDireccion
    ]);
}
    /*
    =========================================
          ACTUALIZAR CONSULTORIO
    =========================================
    */

   public function actualizarConsultorio(
    string $clvConsultorio,
    array $datos
): void {
    try {
        $this->db->beginTransaction();

        $registro =
            $this->obtenerDatosRelacionados(
                $clvConsultorio
            );

        if (!$registro) {
            throw new \RuntimeException(
                'El consultorio no existe.'
            );
        }

        $this->validarCorreosDisponibles(
            $datos['correoConsultorio'],
            $datos['correoResponsable'],
            $clvConsultorio,
            $registro['ClvUsu']
        );

        $this->actualizarDireccion(
            $registro['ClvDir'],
            $datos
        );

        $this->actualizarPersona(
            $registro['ClvPer'],
            $datos
        );

        $this->actualizarDatosConsultorio(
            $clvConsultorio,
            $datos
        );

        $this->actualizarUsuarioResponsable(
            $registro['ClvUsu'],
            $datos
        );

        $this->db->commit();
    } catch (\Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        if ($e instanceof \RuntimeException) {
            throw $e;
        }

        throw new \RuntimeException(
            'No fue posible actualizar el consultorio: '
            . $e->getMessage(),
            0,
            $e
        );
    }
}
    /*
=========================================
      ACTIVAR CONSULTORIO
=========================================
*/

/**
     * Activa solo usuario.EstadoUsu de la cuenta principal CONSULTORIO.
     * No modifica consultorio.EstatusCons ni otras cuentas.
     */
    public function activarCuentaPrincipal(
        string $clvConsultorio
    ): void {
        $cuenta = $this->resolverCuentaPrincipalUnica($clvConsultorio);

        if ((int) ($cuenta['RequiereCambioContrasena'] ?? 0) === 1) {
            throw new RuntimeException(
                'La cuenta aún no completó la activación inicial. '
                . 'Usa reenviar activación.'
            );
        }

        if ((int) ($cuenta['EstadoUsu'] ?? 0) === 1) {
            throw new RuntimeException(
                'La cuenta principal ya está activa.'
            );
        }

        $stmt = $this->db->prepare(
            "UPDATE usuario
             SET EstadoUsu = 1
             WHERE ClvUsu = :u
               AND RolUsu = 'CONSULTORIO'
               AND RequiereCambioContrasena = 0"
        );
        $stmt->execute(['u' => $cuenta['ClvUsu']]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException(
                'No fue posible activar la cuenta. Verifica que ya haya '
                . 'completado la activación inicial.'
            );
        }
    }

    /**
     * Inactiva solo usuario.EstadoUsu de la cuenta principal.
     * No modifica EstatusCons, psicólogos ni pacientes.
     */
    public function inactivarCuentaPrincipal(
        string $clvConsultorio
    ): void {
        $cuenta = $this->resolverCuentaPrincipalUnica($clvConsultorio);

        if ((int) ($cuenta['EstadoUsu'] ?? 0) === 0) {
            throw new RuntimeException(
                'La cuenta principal ya está inactiva.'
            );
        }

        $stmt = $this->db->prepare(
            "UPDATE usuario
             SET EstadoUsu = 0
             WHERE ClvUsu = :u
               AND RolUsu = 'CONSULTORIO'"
        );
        $stmt->execute(['u' => $cuenta['ClvUsu']]);
    }

    /**
     * @deprecated Usar activarCuentaPrincipal (no toca EstatusCons).
     */
    public function activarConsultorio(string $clvConsultorio): void
    {
        $this->activarCuentaPrincipal($clvConsultorio);
    }

    /**
     * @deprecated Usar inactivarCuentaPrincipal (no toca EstatusCons).
     */
    public function desactivarConsultorio(string $clvConsultorio): void
    {
        $this->inactivarCuentaPrincipal($clvConsultorio);
    }

    /**
     * Datos de la cuenta principal para generar recuperación.
     * No modifica contraseña, EstadoUsu ni EstatusCons.
     *
     * @return array{correo: string, ClvUsu: string, nombreResponsable: string}
     */
    public function restablecerAcceso(
        string $clvConsultorio
    ): array {
        $usuario = $this->resolverCuentaPrincipalUnica($clvConsultorio);

        if ((int) ($usuario['RequiereCambioContrasena'] ?? 0) === 1) {
            throw new RuntimeException(
                'La activación inicial aún no está completa. Usa reenviar activación.'
            );
        }

        if ((int) ($usuario['EstadoUsu'] ?? 0) !== 1) {
            throw new RuntimeException(
                'Activa la cuenta antes de enviar un enlace de recuperación.'
            );
        }

        $correo = strtolower(trim((string) ($usuario['CorreoUsu'] ?? '')));

        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'La cuenta no tiene un correo válido para recuperación.'
            );
        }

        return [
            'correo' => $correo,
            'ClvUsu' => (string) $usuario['ClvUsu'],
            'nombreResponsable' => trim(
                ($usuario['NombrePer'] ?? '') . ' ' .
                ($usuario['ApPatPer'] ?? '')
            )
        ];
    }

    /**
     * Resuelve la única cuenta principal CONSULTORIO (EsResponsable=1).
     * Si hay 0 o más de 1, lanza inconsistencia controlada.
     *
     * @return array<string, mixed>
     */
    public function resolverCuentaPrincipalUnica(
        string $clvConsultorio
    ): array {
        $this->validarConsultorioExistente($clvConsultorio);

        $stmt = $this->db->prepare(
            "SELECT
                u.ClvUsu,
                u.CorreoUsu,
                u.TelefonoUsu,
                u.EstadoUsu,
                u.RequiereCambioContrasena,
                p.NombrePer,
                p.ApPatPer,
                p.ApMatPer
             FROM consultorio_usuario cu
             INNER JOIN usuario u ON u.ClvUsu = cu.ClvUsu
             INNER JOIN persona p ON p.ClvPer = u.ClvPer
             WHERE cu.ClvCons = :consultorio
               AND cu.EsResponsable = 1
               AND cu.EstatusConsUsu = 'ACTIVO'
               AND u.RolUsu = 'CONSULTORIO'"
        );
        $stmt->execute(['consultorio' => $clvConsultorio]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($filas) === 0) {
            throw new RuntimeException(
                'No se encontró la cuenta principal del consultorio.'
            );
        }

        if (count($filas) > 1) {
            throw new RuntimeException(
                'Inconsistencia: hay más de una cuenta CONSULTORIO responsable. '
                . 'Corrige la base de datos antes de operar.'
            );
        }

        return $filas[0];
    }

    private function tieneActivacionPendiente(string $clvUsu): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM activacion_cuenta
                 WHERE ClvUsu = :u
                   AND TipoActivacion = 'ALTA_CONSULTORIO'
                   AND Estado = 'PENDIENTE'
                   AND FechaExpiracion > NOW()"
            );
            $stmt->execute(['u' => $clvUsu]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /*
    =========================================
          INSERTAR DIRECCIÓN
    =========================================
    */

    private function insertarDireccion(
        string $clave,
        array $datos
    ): void {
        $sql = "
            INSERT INTO direccion (
                ClvDir,
                PaisDir,
                EstadoDir,
                MunicipioDir,
                ColoniaDir,
                CalleDir,
                CodPostDir,
                NumExtDir,
                NumIntDir
            ) VALUES (
                :clave,
                :pais,
                :estado,
                :municipio,
                :colonia,
                :calle,
                :codigoPostal,
                :numeroExterior,
                :numeroInterior
            )
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clave' => $clave,
            'pais' => $datos['pais'],
            'estado' => $datos['estado'],
            'municipio' => $datos['municipio'],
            'colonia' => $datos['colonia'],

            'calle' =>
                $datos['calle'] !== ''
                    ? $datos['calle']
                    : null,

            'codigoPostal' =>
                $datos['codigoPostal'],

            'numeroExterior' =>
                $datos['numeroExterior'] !== ''
                    ? $datos['numeroExterior']
                    : null,

            'numeroInterior' =>
                $datos['numeroInterior'] !== ''
                    ? $datos['numeroInterior']
                    : null
        ]);
    }

    /*
    =========================================
          INSERTAR RESPONSABLE
    =========================================
    */

private function insertarResponsable(
    string $clavePersona,
    string $claveDireccion,
    array $datos
): void {
    $sql = "
        INSERT INTO persona (
            ClvPer,
            NombrePer,
            ApPatPer,
            ApMatPer,
            FechaNacimiento,
            GeneroPer,
            ClvDir
        ) VALUES (
            :clave,
            :nombre,
            :apellidoPaterno,
            :apellidoMaterno,
            :fechaNacimiento,
            :genero,
            :direccion
        )
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clave' => $clavePersona,

        'nombre' =>
            $datos['nombreResponsable'],

        'apellidoPaterno' =>
            $datos['apellidoPaternoResponsable'],

        'apellidoMaterno' =>
            $datos['apellidoMaternoResponsable'],

        'fechaNacimiento' =>
            $datos['fechaNacimientoResponsable'],

        'genero' =>
            $datos['generoResponsable'],

        'direccion' =>
            $claveDireccion
    ]);
}

 /*
=========================================
      INSERTAR USUARIO CONSULTORIO
=========================================
*/

private function insertarUsuarioConsultorio(
    string $claveUsuario,
    string $clavePersona,
    string $claveConsultorio,
    array $datos
): void {
    /*
    =====================================
          CREAR CUENTA PENDIENTE
    =====================================
    */

    $contrasenaHash = password_hash(
        bin2hex(random_bytes(32)),
        PASSWORD_DEFAULT
    );

    if ($contrasenaHash === false) {
        throw new RuntimeException(
            'No fue posible proteger la cuenta del responsable.'
        );
    }

    $sqlUsuario = "
        INSERT INTO usuario (
            ClvUsu,
            CorreoUsu,
            TelefonoUsu,
            ContrasenaUsu,
            EstadoUsu,
            ClvPer,
            RolUsu,
            RequiereCambioContrasena
        ) VALUES (
            :clave,
            :correo,
            :telefono,
            :contrasena,
            0,
            :persona,
            'CONSULTORIO',
            1
        )
    ";

    $stmt = $this->db->prepare($sqlUsuario);

    $stmt->execute([
        'clave' => $claveUsuario,
        'correo' => $datos['correoResponsable'],
        'telefono' => $datos['telefonoResponsable'],
        'contrasena' => $contrasenaHash,
        'persona' => $clavePersona
    ]);

    /*
    =====================================
       RELACIONAR USUARIO Y CONSULTORIO
    =====================================
    */

    $claveRelacion = ClaveService::generar(
        'consultorio_usuario',
        'ClvConsUsu',
        'CU'
    );

    $sqlRelacion = "
        INSERT INTO consultorio_usuario (
            ClvConsUsu,
            ClvCons,
            ClvUsu,
            EsResponsable,
            EstatusConsUsu,
            FechaAsignacion
        ) VALUES (
            :claveRelacion,
            :consultorio,
            :usuario,
            1,
            'ACTIVO',
            NOW()
        )
    ";

    $stmt = $this->db->prepare($sqlRelacion);

    $stmt->execute([
        'claveRelacion' => $claveRelacion,
        'consultorio' => $claveConsultorio,
        'usuario' => $claveUsuario
    ]);
}
/*
=========================================
      OBTENER DATOS RELACIONADOS
=========================================
*/

private function obtenerDatosRelacionados(
    string $clvConsultorio
): ?array {
    $sql = "
        SELECT
            c.ClvCons,
            c.ClvDir,

            cu.ClvConsUsu,
            cu.ClvUsu,

            u.ClvPer

        FROM consultorio c

        INNER JOIN consultorio_usuario cu
            ON cu.ClvCons = c.ClvCons
            AND cu.EsResponsable = 1
            AND cu.EstatusConsUsu = 'ACTIVO'

        INNER JOIN usuario u
            ON u.ClvUsu = cu.ClvUsu
            AND u.RolUsu = 'CONSULTORIO'

        WHERE c.ClvCons = :consultorio

        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'consultorio' => $clvConsultorio
    ]);

    $resultado = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return $resultado ?: null;
}
/*
=========================================
      OBTENER USUARIO RESPONSABLE
=========================================
*/

private function obtenerUsuarioResponsable(
    string $clvConsultorio
): ?array {
    $sql = "
        SELECT
            u.ClvUsu,
            u.CorreoUsu,
            u.TelefonoUsu,
            u.EstadoUsu,
            u.RequiereCambioContrasena,
            p.NombrePer,
            p.ApPatPer,
            p.ApMatPer

        FROM consultorio_usuario cu

        INNER JOIN usuario u
            ON u.ClvUsu = cu.ClvUsu

        INNER JOIN persona p
            ON p.ClvPer = u.ClvPer

        WHERE cu.ClvCons = :consultorio
          AND cu.EsResponsable = 1
          AND cu.EstatusConsUsu = 'ACTIVO'
          AND u.RolUsu = 'CONSULTORIO'

        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'consultorio' => $clvConsultorio
    ]);

    $resultado = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return $resultado ?: null;
}

    /*
    =========================================
          ACTUALIZAR DIRECCIÓN
    =========================================
    */

    private function actualizarDireccion(
        string $clvDireccion,
        array $datos
    ): void {
        $sql = "
            UPDATE direccion
            SET
                PaisDir = :pais,
                EstadoDir = :estado,
                MunicipioDir = :municipio,
                ColoniaDir = :colonia,
                CalleDir = :calle,
                CodPostDir = :codigoPostal,
                NumExtDir = :numeroExterior,
                NumIntDir = :numeroInterior
            WHERE ClvDir = :direccion
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'pais' => $datos['pais'],
            'estado' => $datos['estado'],
            'municipio' => $datos['municipio'],
            'colonia' => $datos['colonia'],

            'calle' =>
                $datos['calle'] !== ''
                    ? $datos['calle']
                    : null,

            'codigoPostal' =>
                $datos['codigoPostal'],

            'numeroExterior' =>
                $datos['numeroExterior'] !== ''
                    ? $datos['numeroExterior']
                    : null,

            'numeroInterior' =>
                $datos['numeroInterior'] !== ''
                    ? $datos['numeroInterior']
                    : null,

            'direccion' =>
                $clvDireccion
        ]);
    }

    /*
    =========================================
          ACTUALIZAR PERSONA
    =========================================
    */

    private function actualizarPersona(
        string $clvPersona,
        array $datos
    ): void {
        $sql = "
            UPDATE persona
            SET
                NombrePer = :nombre,
                ApPatPer = :apellidoPaterno,
                ApMatPer = :apellidoMaterno,
                FechaNacimiento = :fechaNacimiento,
                GeneroPer = :genero
            WHERE ClvPer = :persona
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'nombre' =>
                $datos['nombreResponsable'],

            'apellidoPaterno' =>
                $datos[
                    'apellidoPaternoResponsable'
                ],

            'apellidoMaterno' =>
                $datos[
                    'apellidoMaternoResponsable'
                ] !== ''
                    ? $datos[
                        'apellidoMaternoResponsable'
                    ]
                    : null,

            'fechaNacimiento' =>
                $datos[
                    'fechaNacimientoResponsable'
                ],

            'genero' =>
                $datos['generoResponsable'],

            'persona' =>
                $clvPersona
        ]);
    }

    /*
=========================================
      ACTUALIZAR DATOS DEL CONSULTORIO
=========================================
*/

private function actualizarDatosConsultorio(
    string $clvConsultorio,
    array $datos
): void {
    $sql = "
        UPDATE consultorio
        SET
            NombreCons = :nombre,
            Slogan = :slogan,
            Descripcion = :descripcion,
            TelefonoCons = :telefono,
            CorreoElectronico = :correo,
            LimiteCancHoras = :limite
        WHERE ClvCons = :consultorio
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'nombre' =>
            $datos['nombreConsultorio'],

        'slogan' =>
            $datos['slogan'] !== ''
                ? $datos['slogan']
                : null,

        'descripcion' =>
            $datos['descripcion'] !== ''
                ? $datos['descripcion']
                : null,

        'telefono' =>
            $datos['telefonoConsultorio'],

        'correo' =>
            $datos['correoConsultorio'],

        'limite' =>
            $datos['limiteCancelacion'] !== ''
                ? (int) $datos['limiteCancelacion']
                : null,

        'consultorio' =>
            $clvConsultorio
    ]);
}
    /*
    =========================================
          ACTUALIZAR USUARIO RESPONSABLE
    =========================================
    */

   private function actualizarUsuarioResponsable(
    string $clvUsuario,
    array $datos
): void {
    $sql = "
        UPDATE usuario
        SET
            CorreoUsu = :correo,
            TelefonoUsu = :telefono
        WHERE ClvUsu = :usuario
          AND RolUsu = 'CONSULTORIO'
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'correo' =>
            $datos['correoResponsable'],

        'telefono' =>
            $datos['telefonoResponsable'],

        'usuario' =>
            $clvUsuario
    ]);
}

    /*
    =========================================
          VALIDAR CONSULTORIO EXISTENTE
    =========================================
    */

    private function validarConsultorioExistente(
        string $clvConsultorio
    ): void {
        $sql = "
            SELECT COUNT(*)
            FROM consultorio
            WHERE ClvCons = :consultorio
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'consultorio' =>
                $clvConsultorio
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            throw new RuntimeException(
                'El consultorio solicitado no existe.'
            );
        }
    }

    /*
=========================================
      VALIDAR CORREOS DUPLICADOS
=========================================
*/

private function validarCorreosDisponibles(
    string $correoConsultorio,
    string $correoResponsable,
    ?string $ignorarConsultorio = null,
    ?string $ignorarUsuario = null
): void {
    /*
    =====================================
       CORREO INSTITUCIONAL DEL CONSULTORIO
    =====================================
    */

    $sqlConsultorio = "
        SELECT COUNT(*)
        FROM consultorio
        WHERE LOWER(CorreoElectronico) =
              LOWER(:correo)
    ";

    $parametrosConsultorio = [
        'correo' => trim($correoConsultorio)
    ];

    if ($ignorarConsultorio !== null) {
        $sqlConsultorio .= "
            AND ClvCons <> :consultorio
        ";

        $parametrosConsultorio['consultorio'] =
            $ignorarConsultorio;
    }

    $stmt = $this->db->prepare(
        $sqlConsultorio
    );

    $stmt->execute(
        $parametrosConsultorio
    );

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'El correo institucional ya pertenece a otro consultorio.'
        );
    }


    

    /*
    =====================================
          CORREO DEL RESPONSABLE
    =====================================
    */

    $sqlUsuario = "
        SELECT COUNT(*)
        FROM usuario
        WHERE LOWER(CorreoUsu) =
              LOWER(:correo)
    ";

    $parametrosUsuario = [
        'correo' => trim($correoResponsable)
    ];

    if ($ignorarUsuario !== null) {
        $sqlUsuario .= "
            AND ClvUsu <> :usuario
        ";

        $parametrosUsuario['usuario'] =
            $ignorarUsuario;
    }

    $stmt = $this->db->prepare(
        $sqlUsuario
    );

    $stmt->execute(
        $parametrosUsuario
    );

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'El correo del responsable ya está registrado.'
        );
    }
}

    /**
     * Bloqueo MySQL a nivel de conexión para impedir altas concurrentes.
     */
    private function adquirirBloqueoAltaUnica(): bool
    {
        $stmt = $this->db->query(
            "SELECT GET_LOCK('psicomatch_alta_consultorio_unica', 10)"
        );

        return (int) $stmt->fetchColumn() === 1;
    }

    private function liberarBloqueoAltaUnica(): void
    {
        $this->db->query(
            "SELECT RELEASE_LOCK('psicomatch_alta_consultorio_unica')"
        );
    }

    /**
     * Cambia únicamente EstatusCons (ACTIVO|INACTIVO|BLOQUEADO).
     * No toca RolUsu, contraseñas ni datos clínicos.
     */
    public function cambiarEstatusInstitucional(
        string $clvConsultorio,
        string $nuevoEstatus
    ): void {
        $permitidos = ['ACTIVO', 'INACTIVO', 'BLOQUEADO'];
        $nuevoEstatus = strtoupper(trim($nuevoEstatus));

        if (!in_array($nuevoEstatus, $permitidos, true)) {
            throw new RuntimeException(
                'Estatus institucional no válido.'
            );
        }

        $this->validarConsultorioExistente($clvConsultorio);

        $stmt = $this->db->prepare(
            'UPDATE consultorio
             SET EstatusCons = :estatus
             WHERE ClvCons = :clvCons'
        );
        $stmt->execute([
            'estatus' => $nuevoEstatus,
            'clvCons' => $clvConsultorio,
        ]);
    }

    /**
     * @return array{
     *   puedeEliminar: bool,
     *   psicologos: int,
     *   pacientes: int,
     *   citas: int,
     *   historial: int,
     *   pendienteActivacion: bool
     * }
     */
    public function evaluarActividadConsultorio(string $clvConsultorio): array
    {
        $this->validarConsultorioExistente($clvConsultorio);

        $stmtPsi = $this->db->prepare(
            'SELECT COUNT(*) FROM psicologo WHERE ClvCons = :c'
        );
        $stmtPsi->execute(['c' => $clvConsultorio]);
        $psicologos = (int) $stmtPsi->fetchColumn();

        $stmtPac = $this->db->prepare(
            'SELECT COUNT(*) FROM paciente WHERE ClvCons = :c'
        );
        $stmtPac->execute(['c' => $clvConsultorio]);
        $pacientes = (int) $stmtPac->fetchColumn();

        $stmtCita = $this->db->prepare(
            'SELECT COUNT(*) FROM cita WHERE ClvCons = :c'
        );
        $stmtCita->execute(['c' => $clvConsultorio]);
        $citas = (int) $stmtCita->fetchColumn();

        $stmtHist = $this->db->prepare(
            'SELECT COUNT(*) FROM historial_clinico WHERE ClvCons = :c'
        );
        $stmtHist->execute(['c' => $clvConsultorio]);
        $historial = (int) $stmtHist->fetchColumn();

        $pendiente = false;
        try {
            $cuenta = $this->resolverCuentaPrincipalUnica($clvConsultorio);
            $pendiente =
                (int) ($cuenta['RequiereCambioContrasena'] ?? 0) === 1
                || $this->tieneActivacionPendiente((string) $cuenta['ClvUsu']);
        } catch (Throwable $e) {
            $pendiente = false;
        }

        $puedeEliminar =
            $psicologos === 0
            && $pacientes === 0
            && $citas === 0
            && $historial === 0;

        return [
            'puedeEliminar' => $puedeEliminar,
            'psicologos' => $psicologos,
            'pacientes' => $pacientes,
            'citas' => $citas,
            'historial' => $historial,
            'pendienteActivacion' => $pendiente,
        ];
    }

    /**
     * Eliminación física solo sin actividad. Transaccional.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function eliminarConsultorioSinActividad(string $clvConsultorio): array
    {
        try {
            $this->db->beginTransaction();

            $stmtLock = $this->db->prepare(
                'SELECT ClvCons, ClvDir
                 FROM consultorio
                 WHERE ClvCons = :c
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmtLock->execute(['c' => $clvConsultorio]);
            $cons = $stmtLock->fetch(PDO::FETCH_ASSOC);

            if (!$cons) {
                throw new RuntimeException('El consultorio no existe.');
            }

            $actividad = $this->evaluarActividadConsultorio($clvConsultorio);

            if (!$actividad['puedeEliminar']) {
                throw new RuntimeException(
                    'No se puede eliminar: el consultorio tiene actividad asociada. '
                    . 'Usa inactivar o bloquear.'
                );
            }

            $cuenta = $this->resolverCuentaPrincipalUnica($clvConsultorio);
            $clvUsu = (string) $cuenta['ClvUsu'];
            $clvPer = (string) ($this->obtenerDatosRelacionados($clvConsultorio)['ClvPer'] ?? '');
            $clvDir = (string) $cons['ClvDir'];

            $this->db->prepare(
                'DELETE FROM activacion_cuenta WHERE ClvUsu = :u'
            )->execute(['u' => $clvUsu]);

            if ($this->tablaExiste('recuperacion_password')) {
                $this->db->prepare(
                    'DELETE FROM recuperacion_password WHERE ClvUsu = :u'
                )->execute(['u' => $clvUsu]);
            }

            if ($this->tablaExiste('notificacion')) {
                $this->db->prepare(
                    'DELETE FROM notificacion WHERE ClvUsu = :u'
                )->execute(['u' => $clvUsu]);
            }

            $this->db->prepare(
                'DELETE FROM horario_consultorio WHERE ClvCons = :c'
            )->execute(['c' => $clvConsultorio]);

            if ($this->tablaExiste('caracteristica')) {
                $this->db->prepare(
                    'DELETE FROM caracteristica WHERE ClvCons = :c'
                )->execute(['c' => $clvConsultorio]);
            }

            if ($this->tablaExiste('redsocial')) {
                $this->db->prepare(
                    'DELETE FROM redsocial WHERE ClvCons = :c'
                )->execute(['c' => $clvConsultorio]);
            }

            if ($this->tablaExiste('red_social_consultorio')) {
                $this->db->prepare(
                    'DELETE FROM red_social_consultorio WHERE ClvCons = :c'
                )->execute(['c' => $clvConsultorio]);
            }

            if ($this->tablaExiste('servicios')) {
                $this->db->prepare(
                    'DELETE FROM servicios WHERE ClvCons = :c'
                )->execute(['c' => $clvConsultorio]);
            }

            if ($this->tablaExiste('incidencia_soporte')) {
                $this->db->prepare(
                    'DELETE FROM incidencia_soporte WHERE ClvCons = :c'
                )->execute(['c' => $clvConsultorio]);
            }

            if ($this->tablaExiste('incidencia_acceso')) {
                $this->db->prepare(
                    'DELETE FROM incidencia_acceso WHERE ClvCons = :c'
                )->execute(['c' => $clvConsultorio]);
            }

            $this->db->prepare(
                'DELETE FROM consultorio_usuario WHERE ClvCons = :c'
            )->execute(['c' => $clvConsultorio]);

            $this->db->prepare(
                'DELETE FROM consultorio WHERE ClvCons = :c'
            )->execute(['c' => $clvConsultorio]);

            $this->db->prepare(
                'DELETE FROM usuario WHERE ClvUsu = :u AND RolUsu = \'CONSULTORIO\''
            )->execute(['u' => $clvUsu]);

            if ($clvPer !== '') {
                $this->db->prepare(
                    'DELETE FROM persona WHERE ClvPer = :p'
                )->execute(['p' => $clvPer]);
            }

            if ($clvDir !== '') {
                $stmtOtros = $this->db->prepare(
                    'SELECT (
                        (SELECT COUNT(*) FROM persona WHERE ClvDir = :d1)
                      + (SELECT COUNT(*) FROM consultorio WHERE ClvDir = :d2)
                     )'
                );
                $stmtOtros->execute(['d1' => $clvDir, 'd2' => $clvDir]);
                if ((int) $stmtOtros->fetchColumn() === 0) {
                    $this->db->prepare(
                        'DELETE FROM direccion WHERE ClvDir = :d'
                    )->execute(['d' => $clvDir]);
                }
            }

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' =>
                    'El registro pendiente del consultorio fue eliminado correctamente.',
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    private function tablaExiste(string $tabla): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tabla'
        );
        $stmt->execute(['tabla' => $tabla]);

        return (int) $stmt->fetchColumn() > 0;
    }

}