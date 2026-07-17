<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;
use RuntimeException;

class Psicologo extends Model
{
    public function actualizarPerfil(
    string $clvUsu,
    array $datos,
    ?array $foto = null
): void {
    try {
        $this->db->beginTransaction();

        $perfil = $this->obtenerPerfilPorUsuario(
            $clvUsu
        );

        if (!$perfil) {
            throw new RuntimeException(
                'No se encontró el perfil del especialista.'
            );
        }

        $nombreFoto =
            $perfil['FotoPerfilPer'] ?? null;

        if (
            $foto !== null &&
            isset($foto['error']) &&
            $foto['error'] === UPLOAD_ERR_OK
        ) {
            $nombreFoto =
                $this->guardarFotoPerfil($foto);
        }

        $sqlPersona = "UPDATE persona
                       SET
                            NombrePer = :nombre,
                            ApPatPer = :apellidoPaterno,
                            ApMatPer = :apellidoMaterno,
                            FechaNacimiento = :fechaNacimiento,
                            GeneroPer = :genero,
                            FotoPerfilPer = :foto
                       WHERE ClvPer = :clvPer";

        $stmtPersona =
            $this->db->prepare($sqlPersona);

        $stmtPersona->execute([
            'nombre' => $datos['NombrePer'],
            'apellidoPaterno' =>
                $datos['ApPatPer'],
            'apellidoMaterno' =>
                $datos['ApMatPer'],
            'fechaNacimiento' =>
                $datos['FechaNacimiento'],
            'genero' =>
                $datos['GeneroPer'],
            'foto' =>
                $nombreFoto,
            'clvPer' =>
                $perfil['ClvPer']
        ]);

        $sqlUsuario = "UPDATE usuario
                       SET TelefonoUsu = :telefono
                       WHERE ClvUsu = :clvUsu";

        $stmtUsuario =
            $this->db->prepare($sqlUsuario);

        $stmtUsuario->execute([
            'telefono' =>
                $datos['TelefonoUsu'],
            'clvUsu' =>
                $clvUsu
        ]);

        $this->db->commit();

    } catch (\Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        throw $e;
    }
}
private function guardarFotoPerfil(
    array $foto
): string {
    if (
        !isset(
            $foto['tmp_name'],
            $foto['size'],
            $foto['error']
        )
    ) {
        throw new RuntimeException(
            'La fotografía recibida no es válida.'
        );
    }

    if ($foto['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'Ocurrió un error al subir la fotografía.'
        );
    }

    if ((int) $foto['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException(
            'La fotografía no debe superar 3 MB.'
        );
    }

    $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    $finfo = new \finfo(
        FILEINFO_MIME_TYPE
    );

    $tipoMime = $finfo->file(
        $foto['tmp_name']
    );

    if (
        !is_string($tipoMime) ||
        !isset($tiposPermitidos[$tipoMime])
    ) {
        throw new RuntimeException(
            'La fotografía debe ser JPG, PNG o WEBP.'
        );
    }

    $extension =
        $tiposPermitidos[$tipoMime];

    $nombreArchivo =
        'perfil_' .
        bin2hex(random_bytes(12)) .
        '.' .
        $extension;

    $directorio =
        dirname(__DIR__, 2) .
        DIRECTORY_SEPARATOR .
        'public' .
        DIRECTORY_SEPARATOR .
        'uploads' .
        DIRECTORY_SEPARATOR .
        'perfiles';

    if (
        !is_dir($directorio) &&
        !mkdir($directorio, 0775, true) &&
        !is_dir($directorio)
    ) {
        throw new RuntimeException(
            'No fue posible crear la carpeta de perfiles.'
        );
    }

    $rutaDestino =
        $directorio .
        DIRECTORY_SEPARATOR .
        $nombreArchivo;

    if (
        !move_uploaded_file(
            $foto['tmp_name'],
            $rutaDestino
        )
    ) {
        throw new RuntimeException(
            'No fue posible guardar la fotografía.'
        );
    }

    return $nombreArchivo;
}
    public function listarPorConsultorio(
        string $clvCons
    ): array {
        $sql = "SELECT
                    psi.ClvPsi,
                    psi.CedulaProfesional,
                    psi.EspecialidadPsi,
                    psi.DescripcionProfesional,
                    psi.EstatusPsi,
                    psi.MostrarEnPagina,
                    psi.FechaRegistroPsi,

                    usu.ClvUsu,
                    usu.CorreoUsu,
                    usu.TelefonoUsu,
                    usu.EstadoUsu,

                    per.ClvPer,
                    per.NombrePer,
                    per.ApPatPer,
                    per.ApMatPer,
                    per.FechaNacimiento,
                    per.GeneroPer,

                    CONCAT(
                        per.NombrePer,
                        ' ',
                        per.ApPatPer,
                        ' ',
                        per.ApMatPer
                    ) AS NombreCompleto

                FROM psicologo psi

                INNER JOIN usuario usu
                    ON psi.ClvUsu = usu.ClvUsu

                INNER JOIN persona per
                    ON usu.ClvPer = per.ClvPer

                WHERE psi.ClvCons = :clvCons

                ORDER BY
                    per.NombrePer,
                    per.ApPatPer,
                    per.ApMatPer";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
public function obtenerPorUsuario(
    string $clvUsu
): ?array {
    $sql = "SELECT
                psi.ClvPsi,
                psi.CedulaProfesional,
                psi.EspecialidadPsi,
                psi.DescripcionProfesional,
                psi.EstatusPsi,
                psi.MostrarEnPagina,
                psi.ClvUsu,
                psi.ClvCons
            FROM psicologo psi
            WHERE psi.ClvUsu = :clvUsu
            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvUsu' => $clvUsu
    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resultado ?: null;
}
public function listarServicios(
    string $clvPsi
): array {

    $sql = "SELECT
                ps.ClvPsiServ,
                ps.PrecioServicio,
                ps.DuracionMinutos,
                ps.DescripcionServicio,
                ps.EstatusAsignacion,

                s.NombreServicio

            FROM psicologo_servicio ps

            INNER JOIN servicios s
                ON ps.ClvServ = s.ClvServ

            WHERE ps.ClvPsi = :clvPsi

            ORDER BY
                s.NombreServicio";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvPsi' => $clvPsi
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}
public function obtenerPerfilPorUsuario(
    string $clvUsu
): ?array {
    $sql = "SELECT
                psi.ClvPsi,
                psi.CedulaProfesional,
                psi.EspecialidadPsi,
                psi.DescripcionProfesional,
                psi.MostrarEnPagina,
                psi.EstatusPsi,

                usu.ClvUsu,
                usu.CorreoUsu,
                usu.TelefonoUsu,

                per.ClvPer,
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FechaNacimiento,
                per.GeneroPer,
                per.FotoPerfilPer

            FROM psicologo psi

            INNER JOIN usuario usu
                ON psi.ClvUsu = usu.ClvUsu

            INNER JOIN persona per
                ON usu.ClvPer = per.ClvPer

            WHERE psi.ClvUsu = :clvUsu

            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvUsu' => $clvUsu
    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resultado ?: null;
}

public function obtenerServiciosDisponibles(
    string $clvCons,
    string $clvPsi
): array {
    $sql = "SELECT
                s.ClvServ,
                s.NombreServicio,
                s.Descripcion,
                s.DuracionMinutos,
                s.CostoServicio,
                s.EstatusServicio,

                ps.ClvPsiServ,
                ps.EstatusAsignacion

            FROM servicios s

            LEFT JOIN psicologo_servicio ps
                ON s.ClvServ = ps.ClvServ
               AND ps.ClvPsi = :clvPsi

            WHERE s.ClvCons = :clvCons
              AND s.EstatusServicio = 'ACTIVO'

            ORDER BY
                s.NombreServicio ASC";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvPsi' => $clvPsi,
        'clvCons' => $clvCons
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function obtenerPorClave(
        string $clvPsi,
        string $clvCons
    ): ?array {
        $sql = "SELECT
                    psi.ClvPsi,
                    psi.CedulaProfesional,
                    psi.EspecialidadPsi,
                    psi.DescripcionProfesional,
                    psi.EstatusPsi,
                    psi.MostrarEnPagina,
                    psi.FechaRegistroPsi,
                    psi.ClvCons,

                    usu.ClvUsu,
                    usu.CorreoUsu,
                    usu.TelefonoUsu,
                    usu.EstadoUsu,

                    per.ClvPer,
                    per.NombrePer,
                    per.ApPatPer,
                    per.ApMatPer,
                    per.FechaNacimiento,
                    per.GeneroPer,
                    per.ClvDir

                FROM psicologo psi

                INNER JOIN usuario usu
                    ON psi.ClvUsu = usu.ClvUsu

                INNER JOIN persona per
                    ON usu.ClvPer = per.ClvPer

                WHERE psi.ClvPsi = :clvPsi
                  AND psi.ClvCons = :clvCons

                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public function obtenerActivosPorConsultorio(
        string $clvCons
    ): array {
        $sql = "SELECT
                    psi.ClvPsi,

                    CONCAT(
                        per.NombrePer,
                        ' ',
                        per.ApPatPer,
                        ' ',
                        per.ApMatPer
                    ) AS NombrePsicologo

                FROM psicologo psi

                INNER JOIN usuario usu
                    ON psi.ClvUsu = usu.ClvUsu

                INNER JOIN persona per
                    ON usu.ClvPer = per.ClvPer

                WHERE psi.ClvCons = :clvCons
                  AND psi.EstatusPsi = 'ACTIVO'
                  AND usu.EstadoUsu = 1

                ORDER BY
                    per.NombrePer,
                    per.ApPatPer,
                    per.ApMatPer";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar(
        array $datos,
        string $clvCons
    ): void {
        try {
            $this->db->beginTransaction();

            $clvPer = $this->generarClave(
                'persona',
                'ClvPer',
                'PER'
            );

            $clvUsu = $this->generarClave(
                'usuario',
                'ClvUsu',
                'USU'
            );

            $clvPsi = $this->generarClave(
                'psicologo',
                'ClvPsi',
                'PSI'
            );

            $this->validarCorreoDisponible(
                $datos['correo']
            );

            $this->validarCedulaDisponible(
                $datos['cedulaProfesional']
            );

            $sqlPersona = "INSERT INTO persona (
                                ClvPer,
                                NombrePer,
                                ApPatPer,
                                ApMatPer,
                                FechaNacimiento,
                                GeneroPer,
                                ClvDir
                           ) VALUES (
                                :clvPer,
                                :nombre,
                                :apellidoPaterno,
                                :apellidoMaterno,
                                :fechaNacimiento,
                                :genero,
                                NULL
                           )";

            $stmtPersona =
                $this->db->prepare($sqlPersona);

            $stmtPersona->execute([
                'clvPer' => $clvPer,
                'nombre' => $datos['nombre'],
                'apellidoPaterno' =>
                    $datos['apellidoPaterno'],
                'apellidoMaterno' =>
                    $datos['apellidoMaterno'],
                'fechaNacimiento' =>
                    $datos['fechaNacimiento'],
                'genero' => $datos['genero']
            ]);
$sqlUsuario = "INSERT INTO usuario (
                    ClvUsu,
                    CorreoUsu,
                    TelefonoUsu,
                    ContrasenaUsu,
                    EstadoUsu,
                    RequiereCambioContrasena,
                    RolUsu,
                    ClvPer
               ) VALUES (
                    :clvUsu,
                    :correo,
                    :telefono,
                    :contrasena,
                    1,
                    1,
                    'PSICOLOGO',
                    :clvPer
               )";

$stmtUsuario = $this->db->prepare($sqlUsuario);

$stmtUsuario->execute([
    'clvUsu' => $clvUsu,
    'correo' => $datos['correo'],
    'telefono' => $datos['telefono'],
    'contrasena' => password_hash(
        $datos['contrasenaTemporal'],
        PASSWORD_DEFAULT
    ),
    'clvPer' => $clvPer
]);
            $sqlPsicologo = "INSERT INTO psicologo (
                                ClvPsi,
                                CedulaProfesional,
                                EspecialidadPsi,
                                DescripcionProfesional,
                                EstatusPsi,
                                MostrarEnPagina,
                                ClvUsu,
                                ClvCons
                             ) VALUES (
                                :clvPsi,
                                :cedulaProfesional,
                                :especialidad,
                                :descripcionProfesional,
                                'ACTIVO',
                                :mostrarEnPagina,
                                :clvUsu,
                                :clvCons
                             )";

            $stmtPsicologo =
                $this->db->prepare($sqlPsicologo);

            $stmtPsicologo->execute([
                'clvPsi' => $clvPsi,
                'cedulaProfesional' =>
                    $datos['cedulaProfesional'],
                'especialidad' =>
                    $datos['especialidad'],
                'descripcionProfesional' =>
                    $datos['descripcionProfesional']
                        ?: null,
                'mostrarEnPagina' =>
                    $datos['mostrarEnPagina'],
                'clvUsu' => $clvUsu,
                'clvCons' => $clvCons
            ]);

            $this->db->commit();
        } catch (PDOException | RuntimeException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function validarCorreoDisponible(
        string $correo
    ): void {
        $sql = "SELECT COUNT(*)
                FROM usuario
                WHERE CorreoUsu = :correo";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'correo' => $correo
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                'El correo electrónico ya está registrado.'
            );
        }
    }
public function actualizar(
    string $clvPsi,
    string $clvCons,
    array $datos
): void {
    try {
        $this->db->beginTransaction();

        $psicologo = $this->obtenerPorClave(
            $clvPsi,
            $clvCons
        );

        if (!$psicologo) {
            throw new RuntimeException(
                'El especialista no fue encontrado.'
            );
        }

        $this->validarCorreoDisponibleActualizar(
            $datos['correo'],
            $psicologo['ClvUsu']
        );

        $this->validarCedulaDisponibleActualizar(
            $datos['cedulaProfesional'],
            $clvPsi
        );

        $sqlPersona = "UPDATE persona
                       SET
                            NombrePer = :nombre,
                            ApPatPer = :apellidoPaterno,
                            ApMatPer = :apellidoMaterno,
                            FechaNacimiento = :fechaNacimiento,
                            GeneroPer = :genero
                       WHERE ClvPer = :clvPer";

        $stmtPersona = $this->db->prepare(
            $sqlPersona
        );

        $stmtPersona->execute([
            'nombre' => $datos['nombre'],
            'apellidoPaterno' =>
                $datos['apellidoPaterno'],
            'apellidoMaterno' =>
                $datos['apellidoMaterno'],
            'fechaNacimiento' =>
                $datos['fechaNacimiento'],
            'genero' => $datos['genero'],
            'clvPer' => $psicologo['ClvPer']
        ]);

        $sqlUsuario = "UPDATE usuario
                       SET
                            CorreoUsu = :correo,
                            TelefonoUsu = :telefono
                       WHERE ClvUsu = :clvUsu";

        $stmtUsuario = $this->db->prepare(
            $sqlUsuario
        );

        $stmtUsuario->execute([
            'correo' => $datos['correo'],
            'telefono' => $datos['telefono'],
            'clvUsu' => $psicologo['ClvUsu']
        ]);

        $sqlPsicologo = "UPDATE psicologo
                         SET
                            CedulaProfesional =
                                :cedulaProfesional,
                            EspecialidadPsi =
                                :especialidad,
                            DescripcionProfesional =
                                :descripcionProfesional,
                            MostrarEnPagina =
                                :mostrarEnPagina
                         WHERE ClvPsi = :clvPsi
                           AND ClvCons = :clvCons";

        $stmtPsicologo = $this->db->prepare(
            $sqlPsicologo
        );

        $stmtPsicologo->execute([
            'cedulaProfesional' =>
                $datos['cedulaProfesional'],
            'especialidad' =>
                $datos['especialidad'],
            'descripcionProfesional' =>
                $datos['descripcionProfesional'] !== ''
                    ? $datos['descripcionProfesional']
                    : null,
            'mostrarEnPagina' =>
                $datos['mostrarEnPagina'],
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons
        ]);

        $this->db->commit();
    } catch (\Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        throw $e;
    }
}
public function cambiarEstatus(
    string $clvPsi,
    string $clvCons
): string {
    $psicologo = $this->obtenerPorClave(
        $clvPsi,
        $clvCons
    );

    if (!$psicologo) {
        throw new RuntimeException(
            'El especialista no fue encontrado.'
        );
    }

    $nuevoEstatus =
        $psicologo['EstatusPsi'] === 'ACTIVO'
            ? 'INACTIVO'
            : 'ACTIVO';

    $nuevoEstadoUsuario =
        $nuevoEstatus === 'ACTIVO'
            ? 1
            : 0;

    try {
        $this->db->beginTransaction();

        $sqlPsicologo = "UPDATE psicologo
                         SET EstatusPsi = :estatus
                         WHERE ClvPsi = :clvPsi
                           AND ClvCons = :clvCons";

        $stmtPsicologo = $this->db->prepare(
            $sqlPsicologo
        );

        $stmtPsicologo->execute([
            'estatus' => $nuevoEstatus,
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons
        ]);

        $sqlUsuario = "UPDATE usuario
                       SET EstadoUsu = :estadoUsuario
                       WHERE ClvUsu = :clvUsu";

        $stmtUsuario = $this->db->prepare(
            $sqlUsuario
        );

        $stmtUsuario->execute([
            'estadoUsuario' =>
                $nuevoEstadoUsuario,
            'clvUsu' => $psicologo['ClvUsu']
        ]);

        $this->db->commit();

        return $nuevoEstatus;
    } catch (\Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        throw $e;
    }
}
private function validarCorreoDisponibleActualizar(
    string $correo,
    string $clvUsu
): void {
    $sql = "SELECT COUNT(*)
            FROM usuario
            WHERE CorreoUsu = :correo
              AND ClvUsu <> :clvUsu";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'correo' => $correo,
        'clvUsu' => $clvUsu
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'El correo electrónico ya está registrado.'
        );
    }
}

private function validarCedulaDisponibleActualizar(
    string $cedulaProfesional,
    string $clvPsi
): void {
    $sql = "SELECT COUNT(*)
            FROM psicologo
            WHERE CedulaProfesional = :cedula
              AND ClvPsi <> :clvPsi";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'cedula' => $cedulaProfesional,
        'clvPsi' => $clvPsi
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'La cédula profesional ya está registrada.'
        );
    }
}
    private function validarCedulaDisponible(
        string $cedulaProfesional
    ): void {
        $sql = "SELECT COUNT(*)
                FROM psicologo
                WHERE CedulaProfesional = :cedula";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'cedula' => $cedulaProfesional
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                'La cédula profesional ya está registrada.'
            );
        }
    }

    private function generarClave(
        string $tabla,
        string $campo,
        string $prefijo
    ): string {
        $sql = "SELECT MAX(
                    CAST(
                        SUBSTRING($campo, 4)
                        AS UNSIGNED
                    )
                )
                FROM $tabla";

        $stmt = $this->db->query($sql);

        $ultimoNumero =
            (int) $stmt->fetchColumn();

        $nuevoNumero =
            $ultimoNumero + 1;

        return $prefijo .
            str_pad(
                (string) $nuevoNumero,
                3,
                '0',
                STR_PAD_LEFT
            );
    }
}