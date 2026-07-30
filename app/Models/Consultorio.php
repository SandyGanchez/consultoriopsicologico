<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Consultorio extends Model
{
    /*
    =========================================
          MÉTODOS DEL ADMINISTRADOR
    =========================================
    */

    public function contarTodos(): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM consultorio
        ";

        $stmt = $this->db->query($sql);

        return (int) $stmt->fetchColumn();
    }

    public function contarPorEstado(
        string $estado
    ): int {
        $sql = "
            SELECT COUNT(*)
            FROM consultorio
            WHERE EstatusCons = :estado
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'estado' => $estado
        ]);

        return (int) $stmt->fetchColumn();
    }

 public function obtenerRecientes(
        int $limite = 5
    ): array {
        $limite = max(1, min($limite, 50));

        $sql = "
            SELECT
                c.ClvCons,
                c.NombreCons,
                c.LogotipoCons,
                c.Slogan,
                c.Descripcion,
                c.TelefonoCons,
                c.CorreoElectronico,
                c.LimiteCancHoras,
                c.EstatusCons,
                c.FechaRegistroCons,

                d.MunicipioDir,
                d.EstadoDir,

                cu.ClvConsUsu,
                cu.ClvUsu,
                cu.EsResponsable,
                cu.EstatusConsUsu,
                cu.FechaAsignacion,

                u.CorreoUsu,
                u.TelefonoUsu,
                u.EstadoUsu,
                u.RolUsu,
                u.RequiereCambioContrasena,

                p.NombrePer,
                p.ApPatPer,
                p.ApMatPer

            FROM consultorio c

            LEFT JOIN direccion d
                ON d.ClvDir = c.ClvDir

            LEFT JOIN consultorio_usuario cu
                ON cu.ClvCons = c.ClvCons
                AND cu.EsResponsable = 1

            LEFT JOIN usuario u
                ON u.ClvUsu = cu.ClvUsu
                AND u.RolUsu = 'CONSULTORIO'

            LEFT JOIN persona p
                ON p.ClvPer = u.ClvPer

            ORDER BY c.FechaRegistroCons DESC

            LIMIT {$limite}
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTodos(): array
    {
        $sql = "
            SELECT
                c.ClvCons,
                c.NombreCons,
                c.LogotipoCons,
                c.FaviconCons,
                c.ImagenPortada,
                c.Slogan,
                c.Descripcion,
                c.TelefonoCons,
                c.CorreoElectronico,
                c.LimiteCancHoras,
                c.EstatusCons,
                c.FechaRegistroCons,

                d.MunicipioDir,
                d.EstadoDir,

                cu.ClvConsUsu,
                cu.ClvUsu,
                cu.EsResponsable,
                cu.EstatusConsUsu,
                cu.FechaAsignacion,

                u.CorreoUsu,
                u.TelefonoUsu,
                u.RolUsu,
                u.EstadoUsu,
                u.RequiereCambioContrasena,

                p.NombrePer,
                p.ApPatPer,
                p.ApMatPer

            FROM consultorio c

            LEFT JOIN direccion d
                ON d.ClvDir = c.ClvDir

            LEFT JOIN consultorio_usuario cu
                ON cu.ClvCons = c.ClvCons
                AND cu.EsResponsable = 1

            LEFT JOIN usuario u
                ON u.ClvUsu = cu.ClvUsu
                AND u.RolUsu = 'CONSULTORIO'

            LEFT JOIN persona p
                ON p.ClvPer = u.ClvPer

            ORDER BY c.FechaRegistroCons DESC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(
        string $id
    ): ?array {
        return $this->obtenerPorClave($id);
    }

    public function existeCorreoConsultorio(
        string $correo,
        ?string $ignorarId = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM consultorio
            WHERE LOWER(CorreoElectronico) =
                  LOWER(:correo)
        ";

        $parametros = [
            'correo' => trim($correo)
        ];

        if ($ignorarId !== null) {
            $sql .= "
                AND ClvCons <> :ignorarId
            ";

            $parametros['ignorarId'] =
                $ignorarId;
        }

        $stmt = $this->db->prepare($sql);

        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existeNombreConsultorio(
        string $nombre,
        ?string $ignorarId = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM consultorio
            WHERE LOWER(TRIM(NombreCons)) =
                  LOWER(TRIM(:nombre))
        ";

        $parametros = [
            'nombre' => trim($nombre)
        ];

        if ($ignorarId !== null) {
            $sql .= "
                AND ClvCons <> :ignorarId
            ";

            $parametros['ignorarId'] = $ignorarId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existeCorreoUsuario(
        string $correo,
        ?string $ignorarUsuario = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM usuario
            WHERE LOWER(CorreoUsu) =
                  LOWER(:correo)
        ";

        $parametros = [
            'correo' => trim($correo)
        ];

        if ($ignorarUsuario !== null) {
            $sql .= "
                AND ClvUsu <> :ignorarUsuario
            ";

            $parametros['ignorarUsuario'] =
                $ignorarUsuario;
        }

        $stmt = $this->db->prepare($sql);

        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn() > 0;
    }

    /*
    =========================================
          INFORMACIÓN DEL CONSULTORIO
    =========================================
    */

    public function obtenerInformacion(): ?array
    {
        $sql = "
            SELECT
                c.*,

                d.PaisDir,
                d.EstadoDir,
                d.MunicipioDir,
                d.ColoniaDir,
                d.CalleDir,
                d.NumExtDir,
                d.NumIntDir,
                d.CodPostDir

            FROM consultorio c

            INNER JOIN direccion d
                ON c.ClvDir = d.ClvDir

            LIMIT 1
        ";

        $resultado = $this->db
            ->query($sql)
            ->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

public function obtenerPorClave(
    string $clvCons
): ?array {
    $clvCons = trim($clvCons);

    $sql = "
        SELECT
            c.*,

            d.PaisDir,
            d.EstadoDir,
            d.MunicipioDir,
            d.ColoniaDir,
            d.CalleDir,
            d.CodPostDir,
            d.NumExtDir,
            d.NumIntDir,

            cu.ClvConsUsu,
            cu.ClvUsu,
            cu.EsResponsable,
            cu.EstatusConsUsu,

            u.CorreoUsu,
            u.TelefonoUsu,
            u.EstadoUsu,
            u.RolUsu,
            u.RequiereCambioContrasena,

            p.NombrePer,
            p.ApPatPer,
            p.ApMatPer,
            p.FechaNacimiento,
            p.GeneroPer

        FROM consultorio c

        LEFT JOIN direccion d
            ON d.ClvDir = c.ClvDir

        LEFT JOIN consultorio_usuario cu
            ON cu.ClvCons = c.ClvCons
            AND cu.EsResponsable = 1
            AND cu.EstatusConsUsu = 'ACTIVO'

        LEFT JOIN usuario u
            ON u.ClvUsu = cu.ClvUsu
            AND u.RolUsu = 'CONSULTORIO'

        LEFT JOIN persona p
            ON p.ClvPer = u.ClvPer

        WHERE c.ClvCons = :clvCons

        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvCons' => $clvCons
    ]);

    $resultado = $stmt->fetch(
        \PDO::FETCH_ASSOC
    );

    return $resultado !== false
        ? $resultado
        : null;
}
    /*
    =========================================
          SERVICIOS
    =========================================
    */

    public function obtenerServicios(): array
    {
        $sql = "
            SELECT *
            FROM servicios
            ORDER BY NombreServicio
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerServiciosPublicosPsicologo(
        string $clvPsi
    ): array {
        $sql = "
            SELECT
                ps.ClvPsiServ,
                ps.PrecioServicio,
                ps.DuracionMinutos,
                ps.DescripcionServicio,

                s.ClvServ,
                s.NombreServicio

            FROM psicologo_servicio ps

            INNER JOIN servicios s
                ON ps.ClvServ = s.ClvServ

            WHERE ps.ClvPsi = :clvPsi
              AND ps.EstatusAsignacion = 'ACTIVA'
              AND s.EstatusServicio = 'ACTIVO'

            ORDER BY s.NombreServicio
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi
        ]);

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    /*
    =========================================
          HORARIOS
    =========================================
    */

    public function obtenerHorarios(): array
    {
        $sql = "
            SELECT *
            FROM horario
            ORDER BY FIELD(
                DiaSemana,
                'Lunes',
                'Martes',
                'Miércoles',
                'Jueves',
                'Viernes',
                'Sábado',
                'Domingo'
            )
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    =========================================
          CARACTERÍSTICAS
    =========================================
    */

    public function obtenerCaracteristicas(): array
    {
        $sql = "
            SELECT *
            FROM caracteristica
            WHERE EstadoCar = 1
            ORDER BY OrdenCar
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    =========================================
          REDES SOCIALES
    =========================================
    */

    public function obtenerRedes(): array
    {
        $sql = "
            SELECT *
            FROM redsocial
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    =========================================
          ESPECIALISTAS PÚBLICOS
    =========================================
    */

    public function obtenerEspecialistasPublicos(
        string $clvCons
    ): array {
        $sql = "
            SELECT
                psi.ClvPsi,
                psi.CedulaProfesional,
                psi.EspecialidadPsi,
                psi.DescripcionProfesional,
                psi.FechaRegistroPsi,

                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FotoPerfilPer,

                usu.CorreoUsu,
                usu.TelefonoUsu,

                CONCAT(
                    per.NombrePer,
                    ' ',
                    per.ApPatPer,
                    ' ',
                    COALESCE(per.ApMatPer, '')
                ) AS NombreCompleto

            FROM psicologo psi

            INNER JOIN usuario usu
                ON psi.ClvUsu = usu.ClvUsu

            INNER JOIN persona per
                ON usu.ClvPer = per.ClvPer

            WHERE psi.ClvCons = :clvCons
              AND psi.EstatusPsi = 'ACTIVO'
              AND psi.MostrarEnPagina = 1
              AND usu.EstadoUsu = 1

            ORDER BY
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }
}