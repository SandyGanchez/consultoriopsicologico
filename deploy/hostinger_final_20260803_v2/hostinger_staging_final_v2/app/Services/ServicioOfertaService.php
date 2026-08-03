<?php

namespace App\Services;

use App\Config\Database;
use App\Models\PsicologoServicio;
use PDO;
use RuntimeException;

/**
 * Incorpora servicios institucionales a psicologo_servicio.
 *
 * PrecioServicio y DuracionMinutos son NOT NULL en la BD real: se usa 0 como
 * valor técnico inicial. EstatusAsignacion = INACTIVA hasta que el especialista
 * configure precio/duración válidos y active su oferta.
 */
class ServicioOfertaService
{
    private PDO $db;
    private PsicologoServicio $relacionModel;

    public function __construct(
        ?PDO $db = null,
        ?PsicologoServicio $relacionModel = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->relacionModel = $relacionModel ?? new PsicologoServicio();
    }

    /**
     * Crea relaciones pendientes para todos los psicólogos del consultorio
     * (activos e inactivos).
     *
     * @return int Filas nuevas insertadas
     */
    public function incorporarServicioAPsicologos(
        string $clvServ,
        string $clvCons
    ): int {
        $clvServ = trim($clvServ);
        $clvCons = trim($clvCons);

        if ($clvServ === '' || $clvCons === '') {
            throw new RuntimeException('Servicio o consultorio no válido.');
        }

        $psicologos = $this->listarClavesPsicologosConsultorio($clvCons);
        if ($psicologos === []) {
            return 0;
        }

        $siguiente = $this->siguienteNumeroClavePsiServ();
        $insertadas = 0;

        foreach ($psicologos as $clvPsi) {
            if ($this->relacionModel->existeRelacion($clvPsi, $clvServ)) {
                continue;
            }

            $clvPsiServ = 'PS' . str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
            $siguiente++;

            $ok = $this->relacionModel->crearRelacionPendiente(
                $clvPsiServ,
                $clvPsi,
                $clvServ,
                0.0,
                0
            );

            if ($ok) {
                $insertadas++;
            }
        }

        return $insertadas;
    }

    /**
     * Crea relaciones pendientes con todos los servicios institucionales activos.
     *
     * @return int Filas nuevas insertadas
     */
    public function incorporarServiciosActivosAPsicologo(
        string $clvPsi,
        string $clvCons
    ): int {
        $clvPsi = trim($clvPsi);
        $clvCons = trim($clvCons);

        if ($clvPsi === '' || $clvCons === '') {
            throw new RuntimeException('Psicólogo o consultorio no válido.');
        }

        $servicios = $this->listarServiciosActivosConsultorio($clvCons);
        if ($servicios === []) {
            return 0;
        }

        $siguiente = $this->siguienteNumeroClavePsiServ();
        $insertadas = 0;

        foreach ($servicios as $clvServ) {
            if ($clvServ === '') {
                continue;
            }

            if ($this->relacionModel->existeRelacion($clvPsi, $clvServ)) {
                continue;
            }

            $clvPsiServ = 'PS' . str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
            $siguiente++;

            $ok = $this->relacionModel->crearRelacionPendiente(
                $clvPsiServ,
                $clvPsi,
                $clvServ,
                0.0,
                0
            );

            if ($ok) {
                $insertadas++;
            }
        }

        return $insertadas;
    }

    public static function precioEsValido(float $precio): bool
    {
        return $precio > 0 && $precio <= 99999999.99;
    }

    public static function duracionEsValida(int $duracion): bool
    {
        return $duracion > 0 && $duracion <= 480;
    }

    /**
     * @return list<string>
     */
    private function listarClavesPsicologosConsultorio(string $clvCons): array
    {
        $sql = "SELECT ClvPsi
                FROM psicologo
                WHERE ClvCons = :clvCons
                ORDER BY ClvPsi ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCons' => $clvCons]);

        return array_values(array_map(
            static fn ($row): string => (string) $row['ClvPsi'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        ));
    }

    /**
     * @return list<string>
     */
    private function listarServiciosActivosConsultorio(string $clvCons): array
    {
        $sql = "SELECT ClvServ
                FROM servicios
                WHERE ClvCons = :clvCons
                  AND EstatusServicio = 'ACTIVO'
                ORDER BY ClvServ ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCons' => $clvCons]);

        return array_values(array_map(
            static fn ($row): string => (string) $row['ClvServ'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        ));
    }

    private function siguienteNumeroClavePsiServ(): int
    {
        $sql = "SELECT MAX(CAST(SUBSTRING(ClvPsiServ, 3) AS UNSIGNED))
                FROM psicologo_servicio
                WHERE ClvPsiServ LIKE 'PS%'";

        $max = (int) $this->db->query($sql)->fetchColumn();

        return $max + 1;
    }
}
