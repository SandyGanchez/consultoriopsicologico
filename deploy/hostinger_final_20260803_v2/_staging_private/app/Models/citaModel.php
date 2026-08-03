<?php
class CitaModel extends Model {
    public function contarCitasHoy($idCons) {
        $hoy = date('Y-m-d');
        $query = "SELECT COUNT(*) as total FROM cita WHERE fechaCita = :hoy AND clvCons = :idCons AND estadoCita = 'Programada'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':hoy', $hoy);
        $stmt->bindParam(':idCons', $idCons);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function contarPacientesActivos($idCons) {
        $query = "SELECT COUNT(*) as total FROM paciente p 
                  JOIN usuario u ON p.clvPer = u.clvPer 
                  WHERE u.clvCons = :idCons AND p.estadoActivoPac = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idCons', $idCons);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function contarCitasSemana($idCons) {
        $lunes = date('Y-m-d', strtotime('monday this week'));
        $domingo = date('Y-m-d', strtotime('sunday this week'));
        $query = "SELECT COUNT(*) as total FROM cita 
                  WHERE fechaCita BETWEEN :lunes AND :domingo 
                  AND clvCons = :idCons AND estadoCita = 'Programada'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':lunes', $lunes);
        $stmt->bindParam(':domingo', $domingo);
        $stmt->bindParam(':idCons', $idCons);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
        public function obtenerProximasCitas($idCons) {
        $hoy = date('Y-m-d');
        $query = "SELECT c.hraInicioCita, p.nombrePer, p.apPatPer, p.apMatPer, c.tipoConsultaCita, c.estadoCita 
                  FROM cita c 
                  JOIN paciente pa ON c.clvPac = pa.clvPac 
                  JOIN persona p ON pa.clvPer = p.clvPer 
                  WHERE c.clvCons = :idCons AND c.fechaCita >= :hoy 
                  ORDER BY c.fechaCita ASC, c.hraInicioCita ASC LIMIT 5";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idCons', $idCons);
        $stmt->bindParam(':hoy', $hoy);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>