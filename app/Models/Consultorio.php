<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Consultorio extends Model
{

    public function obtenerInformacion()
    
    {
        
        $sql = "SELECT
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
                    ON c.ClvDir=d.ClvDir
                LIMIT 1";

        return $this->db
            ->query($sql)
            ->fetch(PDO::FETCH_ASSOC);
         
    }

    public function obtenerServicios()
    {

        return $this->db
            ->query("SELECT * FROM servicios ORDER BY NombreServicio")
            ->fetchAll(PDO::FETCH_ASSOC);

    }
public function obtenerServiciosPublicosPsicologo(
    string $clvPsi
): array {
    $sql = "SELECT
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

            ORDER BY s.NombreServicio";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvPsi' => $clvPsi
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    public function obtenerHorarios()
    {

        return $this->db
            ->query("SELECT * FROM horario ORDER BY FIELD(DiaSemana,
            'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo')")
            ->fetchAll(PDO::FETCH_ASSOC);

    }
public function obtenerCaracteristicas()
{

    $sql = "SELECT *
            FROM caracteristica
            WHERE EstadoCar = 1
            ORDER BY OrdenCar";

    return $this->db
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);

}
    public function obtenerRedes()
    {

        return $this->db
            ->query("SELECT * FROM redsocial")
            ->fetchAll(PDO::FETCH_ASSOC);

    }
public function obtenerPorClave(
    string $clvCons
): ?array {
    $sql = "SELECT
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
            LEFT JOIN direccion d
                ON c.ClvDir = d.ClvDir
            WHERE c.ClvCons = :clvCons
            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvCons' => $clvCons
    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resultado ?: null;
}
public function obtenerEspecialistasPublicos(
    string $clvCons
): array {
    $sql = "SELECT
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
                    per.ApMatPer
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
                per.ApMatPer";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvCons' => $clvCons
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}