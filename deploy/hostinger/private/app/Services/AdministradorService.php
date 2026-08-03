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

public function activarConsultorio(
    string $clvConsultorio
): void {
    try {
        $this->db->beginTransaction();

        $this->validarConsultorioExistente(
            $clvConsultorio
        );

        /*
        =====================================
             ACTIVAR EL CONSULTORIO
        =====================================
        */

        $sqlConsultorio = "
            UPDATE consultorio
            SET EstatusCons = 'ACTIVO'
            WHERE ClvCons = :consultorio
        ";

        $stmt = $this->db->prepare(
            $sqlConsultorio
        );

        $stmt->execute([
            'consultorio' => $clvConsultorio
        ]);

        /*
        =====================================
          ACTIVAR LA CUENTA RESPONSABLE
        =====================================
        */

        $sqlUsuario = "
            UPDATE usuario u

            INNER JOIN consultorio_usuario cu
                ON cu.ClvUsu = u.ClvUsu

            SET u.EstadoUsu = 1

            WHERE cu.ClvCons = :consultorio
              AND cu.EstatusConsUsu = 'ACTIVO'
        ";

        $stmt = $this->db->prepare(
            $sqlUsuario
        );

        $stmt->execute([
            'consultorio' => $clvConsultorio
        ]);

        $this->db->commit();
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        if ($e instanceof RuntimeException) {
            throw $e;
        }

        throw new RuntimeException(
            'No fue posible activar el consultorio: '
            . $e->getMessage(),
            0,
            $e
        );
    }
}
    /*
    =========================================
          DESACTIVAR CONSULTORIO
    =========================================
    */

  public function desactivarConsultorio(
    string $clvConsultorio
): void {
    try {
        $this->db->beginTransaction();

        $this->validarConsultorioExistente(
            $clvConsultorio
        );

        $sqlConsultorio = "
            UPDATE consultorio
            SET EstatusCons = 'INACTIVO'
            WHERE ClvCons = :consultorio
        ";

        $stmt = $this->db->prepare(
            $sqlConsultorio
        );

        $stmt->execute([
            'consultorio' => $clvConsultorio
        ]);

        $sqlUsuario = "
            UPDATE usuario u

            INNER JOIN consultorio_usuario cu
                ON cu.ClvUsu = u.ClvUsu

            SET u.EstadoUsu = 0

            WHERE cu.ClvCons = :consultorio
              AND cu.EstatusConsUsu = 'ACTIVO'
        ";

        $stmt = $this->db->prepare(
            $sqlUsuario
        );

        $stmt->execute([
            'consultorio' => $clvConsultorio
        ]);

        $this->db->commit();
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        if ($e instanceof RuntimeException) {
            throw $e;
        }

        throw new RuntimeException(
            'No fue posible desactivar el consultorio: '
            . $e->getMessage(),
            0,
            $e
        );
    }
}
 /*
=========================================
      RESTABLECER ACCESO
=========================================
*/

public function restablecerAcceso(
    string $clvConsultorio
): array {
    try {
        $this->db->beginTransaction();

        $usuario =
            $this->obtenerUsuarioResponsable(
                $clvConsultorio
            );

        if (!$usuario) {
            throw new RuntimeException(
                'No se encontró la cuenta responsable del consultorio.'
            );
        }

        $hash = password_hash(
            bin2hex(random_bytes(32)),
            PASSWORD_DEFAULT
        );

        if ($hash === false) {
            throw new RuntimeException(
                'No fue posible proteger la cuenta.'
            );
        }

        $sql = "
            UPDATE usuario
            SET
                ContrasenaUsu = :contrasena,
                EstadoUsu = 0,
                RequiereCambioContrasena = 1
            WHERE ClvUsu = :usuario
              AND RolUsu = 'CONSULTORIO'
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'contrasena' => $hash,
            'usuario' => $usuario['ClvUsu']
        ]);

        $this->db->commit();

        return [
            'correo' => $usuario['CorreoUsu'],
            'ClvUsu' => $usuario['ClvUsu'],
            'nombreResponsable' => trim(
                ($usuario['NombrePer'] ?? '') . ' ' .
                ($usuario['ApPatPer'] ?? '')
            )
        ];
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        if ($e instanceof RuntimeException) {
            throw $e;
        }

        throw new RuntimeException(
            'No fue posible restablecer el acceso: '
            . $e->getMessage(),
            0,
            $e
        );
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

}