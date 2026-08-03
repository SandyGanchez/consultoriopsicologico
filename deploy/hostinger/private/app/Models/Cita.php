<?php

namespace App\Models;

use App\Core\Model;
use App\Services\ClaveService;
use PDO;
use PDOException;
use RuntimeException;

class Cita extends Model
{

    /*
    =====================================
            PRÓXIMA CITA
    =====================================
    */

    public function obtenerProximaCitaPaciente(
        string $clvPac
    ): ?array {
        $proximas = $this->obtenerProximasPorPaciente($clvPac, 1);

        return $proximas[0] ?? null;
    }

    /**
     * Citas PROGRAMADAS futuras del paciente (fecha+hora).
     *
     * @return list<array<string, mixed>>
     */
    public function obtenerProximasPorPaciente(
        string $clvPac,
        int $limite = 5
    ): array {
        $clvPac = trim($clvPac);
        $limite = max(1, min(20, $limite));

        if ($clvPac === '') {
            return [];
        }

        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.DuracionAplicadaMin,
                    c.EstadoCita,
                    c.ClvCons,
                    co.NombreCons,
                    s.NombreServicio,
                    TRIM(CONCAT(
                        COALESCE(per.NombrePer, ''),
                        ' ',
                        COALESCE(per.ApPatPer, ''),
                        ' ',
                        COALESCE(per.ApMatPer, '')
                    )) AS NombrePsicologo
                FROM cita c
                INNER JOIN psicologo p
                    ON c.ClvPsi = p.ClvPsi
                INNER JOIN usuario u
                    ON p.ClvUsu = u.ClvUsu
                INNER JOIN persona per
                    ON u.ClvPer = per.ClvPer
                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ
                INNER JOIN consultorio co
                    ON co.ClvCons = c.ClvCons
                WHERE c.ClvPac = :clvPac
                  AND c.EstadoCita = 'PROGRAMADA'
                  AND TIMESTAMP(c.FechaCita, c.HraInicioCita) >= NOW()
                ORDER BY
                    c.FechaCita ASC,
                    c.HraInicioCita ASC
                LIMIT :limite";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':clvPac', $clvPac);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conteos operativos del paciente en una sola consulta.
     *
     * @return array{
     *   programadasFuturas: int,
     *   asistidas: int,
     *   canceladas: int,
     *   inasistencias: int
     * }
     */
    public function obtenerResumenPorPaciente(string $clvPac): array
    {
        $vacio = [
            'programadasFuturas' => 0,
            'asistidas' => 0,
            'canceladas' => 0,
            'inasistencias' => 0
        ];

        $clvPac = trim($clvPac);

        if ($clvPac === '') {
            return $vacio;
        }

        $sql = "SELECT
                    SUM(
                        CASE
                            WHEN EstadoCita = 'PROGRAMADA'
                             AND TIMESTAMP(FechaCita, HraInicioCita) >= NOW()
                            THEN 1 ELSE 0
                        END
                    ) AS programadasFuturas,
                    SUM(
                        CASE WHEN EstadoCita = 'ASISTIDA' THEN 1 ELSE 0 END
                    ) AS asistidas,
                    SUM(
                        CASE WHEN EstadoCita = 'CANCELADA' THEN 1 ELSE 0 END
                    ) AS canceladas,
                    SUM(
                        CASE WHEN EstadoCita = 'INASISTENCIA' THEN 1 ELSE 0 END
                    ) AS inasistencias
                FROM cita
                WHERE ClvPac = :clvPac";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvPac' => $clvPac]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'programadasFuturas' => (int) ($fila['programadasFuturas'] ?? 0),
            'asistidas' => (int) ($fila['asistidas'] ?? 0),
            'canceladas' => (int) ($fila['canceladas'] ?? 0),
            'inasistencias' => (int) ($fila['inasistencias'] ?? 0)
        ];
    }

    /**
     * Últimas citas no programadas (actividad operativa).
     *
     * @return list<array<string, mixed>>
     */
    public function obtenerActividadRecientePorPaciente(
        string $clvPac,
        int $limite = 5
    ): array {
        $clvPac = trim($clvPac);
        $limite = max(1, min(20, $limite));

        if ($clvPac === '') {
            return [];
        }

        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.EstadoCita,
                    s.NombreServicio,
                    TRIM(CONCAT(
                        COALESCE(per.NombrePer, ''),
                        ' ',
                        COALESCE(per.ApPatPer, ''),
                        ' ',
                        COALESCE(per.ApMatPer, '')
                    )) AS NombrePsicologo
                FROM cita c
                INNER JOIN psicologo p
                    ON c.ClvPsi = p.ClvPsi
                INNER JOIN usuario u
                    ON p.ClvUsu = u.ClvUsu
                INNER JOIN persona per
                    ON u.ClvPer = per.ClvPer
                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ
                WHERE c.ClvPac = :clvPac
                  AND c.EstadoCita IN (
                      'ASISTIDA',
                      'CANCELADA',
                      'INASISTENCIA'
                  )
                ORDER BY
                    c.FechaCita DESC,
                    c.HraInicioCita DESC
                LIMIT :limite";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':clvPac', $clvPac);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
=====================================
        MIS CITAS
=====================================
*/

/**
     * Citas PROGRAMADAS del paciente (vigentes y pasadas aún sin resultado).
     *
     * @return list<array<string, mixed>>
     */
    public function obtenerMisCitas(
        string $clvPac
    ): array {
        $clvPac = trim($clvPac);

        if ($clvPac === '') {
            return [];
        }

        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.DuracionAplicadaMin,
                    c.EstadoCita,
                    c.ClvCons,
                    co.LimiteCancHoras,
                    co.NombreCons,
                    TRIM(CONCAT(
                        COALESCE(per.NombrePer, ''),
                        ' ',
                        COALESCE(per.ApPatPer, ''),
                        ' ',
                        COALESCE(per.ApMatPer, '')
                    )) AS NombrePsicologo,
                    s.NombreServicio
                FROM cita c
                INNER JOIN psicologo p
                    ON c.ClvPsi = p.ClvPsi
                INNER JOIN usuario u
                    ON p.ClvUsu = u.ClvUsu
                INNER JOIN persona per
                    ON u.ClvPer = per.ClvPer
                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ
                INNER JOIN consultorio co
                    ON co.ClvCons = c.ClvCons
                WHERE c.ClvPac = :clvPac
                  AND c.EstadoCita = 'PROGRAMADA'
                ORDER BY
                    c.FechaCita ASC,
                    c.HraInicioCita ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPac' => $clvPac
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    =====================================
            HISTORIAL
    =====================================
    */

    /**
     * Historial operativo (ASISTIDA, CANCELADA, INASISTENCIA).
     *
     * @return list<array<string, mixed>>
     */
    public function obtenerHistorial(
        string $clvPac,
        ?string $estado = null,
        int $pagina = 1,
        int $porPagina = 10
    ): array {
        $clvPac = trim($clvPac);
        $estado = $this->normalizarEstadoHistorial($estado);
        $porPagina = max(1, min(50, $porPagina));
        $pagina = max(1, $pagina);
        $offset = ($pagina - 1) * $porPagina;

        if ($clvPac === '') {
            return [];
        }

        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.DuracionAplicadaMin,
                    c.EstadoCita,
                    c.MotivoCancelacion,
                    c.FechaCancelacion,
                    co.NombreCons,
                    TRIM(CONCAT(
                        COALESCE(per.NombrePer, ''),
                        ' ',
                        COALESCE(per.ApPatPer, ''),
                        ' ',
                        COALESCE(per.ApMatPer, '')
                    )) AS NombrePsicologo,
                    s.NombreServicio
                FROM cita c
                INNER JOIN psicologo p
                    ON c.ClvPsi = p.ClvPsi
                INNER JOIN usuario u
                    ON p.ClvUsu = u.ClvUsu
                INNER JOIN persona per
                    ON u.ClvPer = per.ClvPer
                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ
                INNER JOIN consultorio co
                    ON co.ClvCons = c.ClvCons
                WHERE c.ClvPac = :clvPac
                  AND c.EstadoCita <> 'PROGRAMADA'";

        if ($estado !== null) {
            $sql .= " AND c.EstadoCita = :estado";
        }

        $sql .= " ORDER BY
                    c.FechaCita DESC,
                    c.HraInicioCita DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':clvPac', $clvPac);

        if ($estado !== null) {
            $stmt->bindValue(':estado', $estado);
        }

        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarHistorial(
        string $clvPac,
        ?string $estado = null
    ): int {
        $clvPac = trim($clvPac);
        $estado = $this->normalizarEstadoHistorial($estado);

        if ($clvPac === '') {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvPac = :clvPac
                  AND EstadoCita <> 'PROGRAMADA'";

        if ($estado !== null) {
            $sql .= " AND EstadoCita = :estado";
        }

        $stmt = $this->db->prepare($sql);
        $params = ['clvPac' => $clvPac];

        if ($estado !== null) {
            $params['estado'] = $estado;
        }

        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function normalizarEstadoHistorial(?string $estado): ?string
    {
        $estado = strtoupper(trim((string) $estado));

        $permitidos = [
            'ASISTIDA',
            'CANCELADA',
            'INASISTENCIA'
        ];

        if ($estado === '' || $estado === 'TODAS') {
            return null;
        }

        return in_array($estado, $permitidos, true)
            ? $estado
            : null;
    }

    /*
    =====================================
            HORAS OCUPADAS
    =====================================
    */

    public function obtenerHorasOcupadas(

        string $clvPsi,

        string $fecha

    ): array {

        $sql = "SELECT

                    HraInicioCita

                FROM cita

                WHERE

                    ClvPsi=:clvPsi

                    AND FechaCita=:fecha

                    AND EstadoCita='PROGRAMADA'";

        $stmt = $this->db->prepare($sql); 

        $stmt->execute([

            'clvPsi'=>$clvPsi,

            'fecha'=>$fecha

        ]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);

    }

    public function obtenerProgramadasPorPsicologoYFecha(
        string $clvPsi,
        string $fecha
    ): array {
        $sql = "SELECT
                    HraInicioCita,
                    HraFinCita,
                    DuracionAplicadaMin
                FROM cita
                WHERE ClvPsi = :clvPsi
                  AND FechaCita = :fecha
                  AND EstadoCita = 'PROGRAMADA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'fecha' => $fecha
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function beginTransaccion(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function commitTransaccion(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollbackTransaccion(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function bloquearPsicologoParaReserva(
        string $clvPsi
    ): bool {
        $sql = "SELECT ClvPsi
                FROM psicologo
                WHERE ClvPsi = :clvPsi
                FOR UPDATE";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function existeSolapamientoProgramado(
        string $clvPsi,
        string $fecha,
        string $nuevoInicio,
        string $nuevoFin
    ): bool {
        $sql = "SELECT
                    HraInicioCita,
                    HraFinCita,
                    DuracionAplicadaMin
                FROM cita
                WHERE ClvPsi = :clvPsi
                  AND FechaCita = :fecha
                  AND EstadoCita = 'PROGRAMADA'
                  FOR UPDATE";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'fecha' => $fecha
        ]);

        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($citas as $cita) {
            $inicioCita = $this->normalizarHoraCita(
                (string) $cita['HraInicioCita']
            );
            $finCita = $this->normalizarFinCita($cita);

            if (
                $nuevoInicio < $finCita &&
                $nuevoFin > $inicioCita
            ) {
                return true;
            }
        }

        return false;
    }

    public function generarClaveCita(): string
    {
        return ClaveService::generar(
            'cita',
            'ClvCita',
            'CIT'
        );
    }

    public function crearCita(array $datos): void
    {
        $sql = "INSERT INTO cita (
                    ClvCita,
                    FechaCita,
                    HraInicioCita,
                    HraFinCita,
                    DuracionAplicadaMin,
                    CostoAplicado,
                    EstadoCita,
                    ClvPac,
                    ClvPsi,
                    ClvCons,
                    ClvServ
                ) VALUES (
                    :clvCita,
                    :fecha,
                    :inicio,
                    :fin,
                    :duracion,
                    :costo,
                    'PROGRAMADA',
                    :paciente,
                    :psicologo,
                    :consultorio,
                    :servicio
                )";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCita' => $datos['ClvCita'],
            'fecha' => $datos['FechaCita'],
            'inicio' => $datos['HraInicioCita'],
            'fin' => $datos['HraFinCita'],
            'duracion' => $datos['DuracionAplicadaMin'],
            'costo' => $datos['CostoAplicado'],
            'paciente' => $datos['ClvPac'],
            'psicologo' => $datos['ClvPsi'],
            'consultorio' => $datos['ClvCons'],
            'servicio' => $datos['ClvServ']
        ]);
    }

    private function normalizarHoraCita(string $hora): string
    {
        $hora = trim($hora);

        if (strlen($hora) === 5) {
            return $hora . ':00';
        }

        return substr($hora, 0, 8);
    }

    private function normalizarFinCita(array $cita): string
    {
        $fin = trim((string) ($cita['HraFinCita'] ?? ''));

        if ($fin !== '' && $fin !== '00:00:00') {
            return $this->normalizarHoraCita($fin);
        }

        $inicio = $this->normalizarHoraCita(
            (string) $cita['HraInicioCita']
        );
        $duracion = (int) ($cita['DuracionAplicadaMin'] ?? 0);

        if ($duracion <= 0) {
            $duracion = 60;
        }

        $base = \DateTimeImmutable::createFromFormat(
            'H:i:s',
            $inicio
        );

        if (!$base) {
            return $inicio;
        }

        return $base
            ->add(
                new \DateInterval('PT' . $duracion . 'M')
            )
            ->format('H:i:s');
    }

    /*
=====================================
      VALIDAR HORARIO OCUPADO
=====================================
*/

public function existeCitaEnHorario(

    string $clvPsi,

    string $fecha,

    string $hora

): bool {

    $sql = "SELECT COUNT(*)

            FROM cita

            WHERE

                ClvPsi = :clvPsi

                AND FechaCita = :fecha

                AND HraInicioCita = :hora

                AND EstadoCita = 'PROGRAMADA'";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([

        'clvPsi' => $clvPsi,

        'fecha' => $fecha,

        'hora' => $hora

    ]);

    return (int)$stmt->fetchColumn() > 0;

}

    /*
    =====================================
            PACIENTE TIENE CITA
    =====================================
    */

public function pacienteTieneCita(

    string $clvPac,

    string $fecha,

    string $hora

): bool {

    $sql="SELECT COUNT(*)

          FROM cita

          WHERE

            ClvPac=:paciente

            AND FechaCita=:fecha

            AND HraInicioCita=:hora

            AND EstadoCita='PROGRAMADA'";

    $stmt=$this->db->prepare($sql);

    $stmt->execute([

        'paciente'=>$clvPac,

        'fecha'=>$fecha,

        'hora'=>$hora

    ]);

    return (int)$stmt->fetchColumn()>0;

}

    /*
    =====================================
            GUARDAR CITA
    =====================================
    */

public function guardar(array $datos): void
{

    if (
        $this->pacienteTieneCita(
            $datos['paciente'],
            $datos['fecha'],
            $datos['inicio']
        )
    ) {

        throw new RuntimeException(
            'Ya tienes una cita registrada en ese horario.'
        );

    }

    if (
        $this->existeCitaEnHorario(
            $datos['psicologo'],
            $datos['fecha'],
            $datos['inicio']
        )
    ) {

        throw new RuntimeException(
            'Ese horario ya fue ocupado.'
        );

    }

    $sql = "INSERT INTO cita(

                ClvCita,
                FechaCita,
                HraInicioCita,
                HraFinCita,
                DuracionAplicadaMin,
                CostoAplicado,
                EstadoCita,
                ClvPac,
                ClvPsi,
                ClvCons,
                ClvServ

            )

            VALUES(

                :clv,
                :fecha,
                :inicio,
                :fin,
                :duracion,
                :costo,
                'PROGRAMADA',
                :paciente,
                :psicologo,
                :consultorio,
                :servicio

            )";

    try {

        $stmt = $this->db->prepare($sql);

        $stmt->execute([

            'clv'          => $this->generarClave(),
            'fecha'        => $datos['fecha'],
            'inicio'       => $datos['inicio'],
            'fin'          => $datos['fin'],
            'duracion'     => $datos['duracion'],
            'costo'        => $datos['costo'],
            'paciente'     => $datos['paciente'],
            'psicologo'    => $datos['psicologo'],
            'consultorio'  => $datos['consultorio'],
            'servicio'     => $datos['servicio']

        ]);

    } catch (\Throwable $e) {

        throw new RuntimeException(
            'No fue posible registrar la cita.'
        );

    }

}

    /*
    =====================================
            CANCELAR
    =====================================
    */

    public function evaluarCancelacionPaciente(
        array $cita
    ): array {
        $estado = trim((string) ($cita['EstadoCita'] ?? ''));

        if ($estado !== 'PROGRAMADA') {
            return match ($estado) {
                'CANCELADA' => [
                    'puedeCancelar' => false,
                    'codigo' => 'CANCELADA',
                    'mensaje' => 'Esta cita ya fue cancelada.',
                    'limiteHoras' => null,
                    'fechaHoraLimite' => null,
                    'fechaHoraLimiteTexto' => null
                ],
                'ASISTIDA' => [
                    'puedeCancelar' => false,
                    'codigo' => 'ASISTIDA',
                    'mensaje' => 'Esta cita ya fue registrada como asistida.',
                    'limiteHoras' => null,
                    'fechaHoraLimite' => null,
                    'fechaHoraLimiteTexto' => null
                ],
                'INASISTENCIA' => [
                    'puedeCancelar' => false,
                    'codigo' => 'INASISTENCIA',
                    'mensaje' => 'Esta cita fue registrada como inasistencia.',
                    'limiteHoras' => null,
                    'fechaHoraLimite' => null,
                    'fechaHoraLimiteTexto' => null
                ],
                default => [
                    'puedeCancelar' => false,
                    'codigo' => 'ESTADO_INVALIDO',
                    'mensaje' => 'Esta cita no puede cancelarse.',
                    'limiteHoras' => null,
                    'fechaHoraLimite' => null,
                    'fechaHoraLimiteTexto' => null
                ]
            };
        }

        if (
            !array_key_exists('LimiteCancHoras', $cita)
            || $cita['LimiteCancHoras'] === null
            || $cita['LimiteCancHoras'] === ''
            || !is_numeric($cita['LimiteCancHoras'])
        ) {
            return [
                'puedeCancelar' => false,
                'codigo' => 'POLITICA_NO_CONFIGURADA',
                'mensaje' =>
                    'La política de cancelación del consultorio está pendiente de configurar.',
                'limiteHoras' => null,
                'fechaHoraLimite' => null,
                'fechaHoraLimiteTexto' => null
            ];
        }

        $limiteHoras = (int) $cita['LimiteCancHoras'];

        if ($limiteHoras < 0) {
            return [
                'puedeCancelar' => false,
                'codigo' => 'POLITICA_NO_CONFIGURADA',
                'mensaje' =>
                    'La política de cancelación del consultorio está pendiente de configurar.',
                'limiteHoras' => null,
                'fechaHoraLimite' => null,
                'fechaHoraLimiteTexto' => null
            ];
        }

        $inicioCita = $this->crearFechaHoraCita(
            (string) ($cita['FechaCita'] ?? ''),
            (string) ($cita['HraInicioCita'] ?? '')
        );

        if (!$inicioCita) {
            return [
                'puedeCancelar' => false,
                'codigo' => 'FECHA_INVALIDA',
                'mensaje' => 'No fue posible validar la fecha de la cita.',
                'limiteHoras' => $limiteHoras,
                'fechaHoraLimite' => null,
                'fechaHoraLimiteTexto' => null
            ];
        }

        $zona = $this->zonaHorariaProyecto();
        $ahora = new \DateTimeImmutable('now', $zona);
        $fechaHoraLimite = $inicioCita->modify('-' . $limiteHoras . ' hours');
        $fechaHoraLimiteIso = $fechaHoraLimite->format('Y-m-d H:i:s');
        $fechaHoraLimiteTexto = $this->formatearFechaHoraCancelacion(
            $fechaHoraLimite
        );

        if ($inicioCita <= $ahora) {
            return [
                'puedeCancelar' => false,
                'codigo' => 'CITA_INICIADA',
                'mensaje' =>
                    'Esta cita ya inició o ya pasó, por lo que no puede cancelarse.',
                'limiteHoras' => $limiteHoras,
                'fechaHoraLimite' => $fechaHoraLimiteIso,
                'fechaHoraLimiteTexto' => $fechaHoraLimiteTexto
            ];
        }

        /*
         * Política oficial: permitir solo si
         * fechaHoraActual <= (fechaHoraCita - LimiteCancHoras).
         * Equivale a horasRestantes >= LimiteCancHoras.
         * La igualdad exacta (p. ej. 24:00:00 restantes) sí permite.
         * No usar abs() ni solo diff->h.
         */
        if ($ahora > $fechaHoraLimite) {
            $textoHoras = $limiteHoras === 1
                ? '1 hora'
                : $limiteHoras . ' horas';

            return [
                'puedeCancelar' => false,
                'codigo' => 'PLAZO_INSUFICIENTE',
                'mensaje' =>
                    'Ya no es posible cancelar esta cita. El consultorio requiere al menos '
                    . $textoHoras
                    . ' de anticipación.',
                'limiteHoras' => $limiteHoras,
                'fechaHoraLimite' => $fechaHoraLimiteIso,
                'fechaHoraLimiteTexto' => $fechaHoraLimiteTexto
            ];
        }

        return [
            'puedeCancelar' => true,
            'codigo' => 'PERMITIDA',
            'mensaje' => '',
            'limiteHoras' => $limiteHoras,
            'fechaHoraLimite' => $fechaHoraLimiteIso,
            'fechaHoraLimiteTexto' => $fechaHoraLimiteTexto
        ];
    }

    public function cancelarPorPaciente(
        string $clvCita,
        string $clvPac
    ): array {
        $clvCita = trim($clvCita);
        $clvPac = trim($clvPac);

        if ($clvCita === '' || $clvPac === '') {
            return [
                'ok' => false,
                'mensaje' => 'No se recibieron los datos necesarios.'
            ];
        }

        try {
            $this->beginTransaccion();

            $sql = "SELECT
                        c.ClvCita,
                        c.ClvPac,
                        c.ClvCons,
                        c.FechaCita,
                        c.HraInicioCita,
                        c.EstadoCita,
                        co.LimiteCancHoras
                    FROM cita c
                    INNER JOIN consultorio co
                        ON co.ClvCons = c.ClvCons
                    WHERE c.ClvCita = :clvCita
                    FOR UPDATE";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                'clvCita' => $clvCita
            ]);

            $cita = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$cita) {
                $this->rollbackTransaccion();

                return [
                    'ok' => false,
                    'mensaje' => 'La cita no existe.'
                ];
            }

            if (($cita['ClvPac'] ?? '') !== $clvPac) {
                $this->rollbackTransaccion();

                return [
                    'ok' => false,
                    'mensaje' =>
                        'No tienes permiso para cancelar esa cita.'
                ];
            }

            $evaluacion = $this->evaluarCancelacionPaciente($cita);

            if (!$evaluacion['puedeCancelar']) {
                $this->rollbackTransaccion();

                return [
                    'ok' => false,
                    'mensaje' => $evaluacion['mensaje']
                ];
            }

            $sqlActualizar = "UPDATE cita
                              SET
                                  EstadoCita = 'CANCELADA',
                                  FechaCancelacion = NOW()
                              WHERE ClvCita = :clvCita
                                AND ClvPac = :clvPac
                                AND EstadoCita = 'PROGRAMADA'";

            $stmtActualizar = $this->db->prepare($sqlActualizar);

            $stmtActualizar->execute([
                'clvCita' => $clvCita,
                'clvPac' => $clvPac
            ]);

            if ((int) $stmtActualizar->rowCount() !== 1) {
                $this->rollbackTransaccion();

                return [
                    'ok' => false,
                    'mensaje' =>
                        'La cita ya no puede cancelarse.'
                ];
            }

            $this->commitTransaccion();

            return [
                'ok' => true,
                'mensaje' => 'La cita fue cancelada correctamente.'
            ];
        } catch (\Throwable $e) {
            $this->rollbackTransaccion();

            return [
                'ok' => false,
                'mensaje' =>
                    'No fue posible cancelar la cita. Inténtalo nuevamente.'
            ];
        }
    }

    public function cancelar(
    string $clvCita,
    string $motivo
): void {

    $sql = "UPDATE cita

            SET

                EstadoCita='CANCELADA',

                MotivoCancelacion=:motivo,

                FechaCancelacion=NOW()

            WHERE

                ClvCita=:clv";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([

        'motivo' => $motivo,

        'clv' => $clvCita

    ]);

    if ($stmt->rowCount() === 0) {

        throw new RuntimeException(
            'No fue posible cancelar la cita.'
        );

    }

}

    private function zonaHorariaProyecto(): \DateTimeZone
    {
        return new \DateTimeZone('America/Mexico_City');
    }

    private function crearFechaHoraCita(
        string $fecha,
        string $hora
    ): ?\DateTimeImmutable {
        $fecha = trim($fecha);
        $hora = trim($hora);

        if ($fecha === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/', $fecha)) {
            $partes = preg_split('/\s+/', $fecha, 2);
            $fecha = $partes[0] ?? '';
            if ($hora === '' && isset($partes[1])) {
                $hora = $partes[1];
            }
        }

        if ($fecha === '' || $hora === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            $hora .= ':00';
        }

        $hora = substr($hora, 0, 8);

        $fechaHora = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . $hora,
            $this->zonaHorariaProyecto()
        );

        if (
            !$fechaHora ||
            $fechaHora->format('Y-m-d') !== $fecha
        ) {
            return null;
        }

        return $fechaHora;
    }

    private function formatearFechaHoraCancelacion(
        \DateTimeImmutable $fechaHora
    ): string {
        $meses = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre'
        ];

        $dia = (int) $fechaHora->format('j');
        $mes = $meses[(int) $fechaHora->format('n')] ?? $fechaHora->format('m');
        $anio = $fechaHora->format('Y');
        $hora = $fechaHora->format('H:i');

        return "{$dia} de {$mes} de {$anio} a las {$hora}";
    }

    /*
=====================================
        OBTENER UNA CITA
=====================================
*/

public function obtenerPorClave(

    string $clvCita

): ?array {

    $sql = "SELECT *

            FROM cita

            WHERE ClvCita = :clv

            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([

        'clv' => $clvCita

    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resultado ?: null;

}

/*
=====================================
        DETALLE DE CITA
=====================================
*/

public function obtenerDetallePaciente(
    string $clvCita,
    string $clvPac
): ?array {

    $sql = "SELECT

                c.*,

                s.NombreServicio,

                s.CostoServicio,

                s.DuracionMinutos,

                con.NombreCons,
                con.LimiteCancHoras,

                CONCAT(

                    per.NombrePer,

                    ' ',

                    per.ApPatPer,

                    ' ',

                    per.ApMatPer

                ) AS NombrePsicologo

            FROM cita c

            INNER JOIN psicologo p
                ON c.ClvPsi = p.ClvPsi

            INNER JOIN usuario u
                ON p.ClvUsu = u.ClvUsu

            INNER JOIN persona per
                ON u.ClvPer = per.ClvPer

            INNER JOIN servicios s
                ON c.ClvServ = s.ClvServ

            INNER JOIN consultorio con
                ON c.ClvCons = con.ClvCons

            WHERE

                c.ClvCita = :cita

                AND c.ClvPac = :paciente

            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([

        'cita' => $clvCita,

        'paciente' => $clvPac

    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resultado ?: null;
}

    /*
    =====================================
            DASHBOARD PSICÓLOGO
    =====================================
    */

    public function contarCitasHoy(
        string $clvPsi
    ): int {
        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvPsi = :clvPsi
                  AND FechaCita = CURDATE()
                  AND EstadoCita <> 'CANCELADA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function contarPacientesActivos(
        string $clvPsi
    ): int {
        $sql = "SELECT COUNT(DISTINCT ClvPac)
                FROM cita
                WHERE ClvPsi = :clvPsi
                  AND EstadoCita <> 'CANCELADA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function contarCitasSemana(
        string $clvPsi
    ): int {
        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvPsi = :clvPsi
                  AND FechaCita BETWEEN :lunes AND :domingo
                  AND EstadoCita <> 'CANCELADA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'lunes' => date('Y-m-d', strtotime('monday this week')),
            'domingo' => date('Y-m-d', strtotime('sunday this week'))
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function obtenerProximasCitas(
        string $clvPsi,
        int $limite = 5
    ): array {
        $limite = max(1, min($limite, 20));

        $sql = "SELECT
                    c.FechaCita,
                    c.HraInicioCita,
                    c.EstadoCita,

                    CONCAT(
                        perPac.NombrePer,
                        ' ',
                        perPac.ApPatPer,
                        ' ',
                        COALESCE(perPac.ApMatPer, '')
                    ) AS NombrePaciente,

                    s.NombreServicio

                FROM cita c

                INNER JOIN paciente pac
                    ON c.ClvPac = pac.ClvPac

                INNER JOIN usuario usuPac
                    ON pac.ClvUsu = usuPac.ClvUsu

                INNER JOIN persona perPac
                    ON usuPac.ClvPer = perPac.ClvPer

                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ

                WHERE c.ClvPsi = :clvPsi
                  AND c.EstadoCita = 'PROGRAMADA'
                  AND (
                        c.FechaCita > CURDATE()
                        OR (
                            c.FechaCita = CURDATE()
                            AND c.HraInicioCita >= CURTIME()
                        )
                  )

                ORDER BY
                    c.FechaCita ASC,
                    c.HraInicioCita ASC

                LIMIT {$limite}";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    =====================================
            AGENDA / DASHBOARD CONSULTORIO
    =====================================
    */

    public function obtenerPorConsultorio(
        string $clvCons,
        ?string $clvPsi = null,
        ?string $estado = null
    ): array {
        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.EstadoCita,
                    c.NotasCita,
                    c.MotivoCancelacion,
                    c.FechaCancelacion,
                    c.ClvPac,
                    c.ClvPsi,
                    c.ClvCons,
                    c.ClvServ,

                    CONCAT(
                        perPac.NombrePer,
                        ' ',
                        perPac.ApPatPer,
                        ' ',
                        COALESCE(perPac.ApMatPer, '')
                    ) AS NombrePaciente,

                    CONCAT(
                        perPsi.NombrePer,
                        ' ',
                        perPsi.ApPatPer,
                        ' ',
                        COALESCE(perPsi.ApMatPer, '')
                    ) AS NombrePsicologo,

                    s.NombreServicio

                FROM cita c

                INNER JOIN paciente pac
                    ON c.ClvPac = pac.ClvPac

                INNER JOIN usuario usuPac
                    ON pac.ClvUsu = usuPac.ClvUsu

                INNER JOIN persona perPac
                    ON usuPac.ClvPer = perPac.ClvPer

                INNER JOIN psicologo psi
                    ON c.ClvPsi = psi.ClvPsi

                INNER JOIN usuario usuPsi
                    ON psi.ClvUsu = usuPsi.ClvUsu

                INNER JOIN persona perPsi
                    ON usuPsi.ClvPer = perPsi.ClvPer

                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ

                WHERE c.ClvCons = :clvCons";

        $parametros = [
            'clvCons' => $clvCons
        ];

        if ($clvPsi !== null && $clvPsi !== '') {
            $sql .= " AND c.ClvPsi = :clvPsi";
            $parametros['clvPsi'] = $clvPsi;
        }

        if ($estado !== null && $estado !== '') {
            $sql .= " AND c.EstadoCita = :estado";
            $parametros['estado'] = strtoupper($estado);
        }

        $sql .= "
            ORDER BY
                c.FechaCita ASC,
                c.HraInicioCita ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarCitasHoyPorConsultorio(
        string $clvCons
    ): int {
        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvCons = :clvCons
                  AND FechaCita = CURDATE()
                  AND EstadoCita <> 'CANCELADA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function contarCitasProgramadasPorConsultorio(
        string $clvCons
    ): int {
        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvCons = :clvCons
                  AND EstadoCita = 'PROGRAMADA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function contarCitasCanceladasPorConsultorio(
        string $clvCons
    ): int {
        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvCons = :clvCons
                  AND EstadoCita = 'CANCELADA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function contarPacientesPorConsultorio(
        string $clvCons
    ): int {
        $sql = "SELECT COUNT(DISTINCT ClvPac)
                FROM cita
                WHERE ClvCons = :clvCons
                  AND EstadoCita <> 'CANCELADA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function contarCitasSemanaPorConsultorio(
        string $clvCons
    ): int {
        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvCons = :clvCons
                  AND FechaCita BETWEEN :lunes AND :domingo
                  AND EstadoCita <> 'CANCELADA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons,
            'lunes' => date('Y-m-d', strtotime('monday this week')),
            'domingo' => date('Y-m-d', strtotime('sunday this week'))
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function obtenerProximasCitasPorConsultorio(
        string $clvCons,
        int $limite = 5
    ): array {
        $limite = max(1, min($limite, 20));

        $sql = "SELECT
                    c.FechaCita,
                    c.HraInicioCita,
                    c.NotasCita,

                    CONCAT(
                        perPac.NombrePer,
                        ' ',
                        perPac.ApPatPer,
                        ' ',
                        COALESCE(perPac.ApMatPer, '')
                    ) AS NombrePaciente,

                    CONCAT(
                        perPsi.NombrePer,
                        ' ',
                        perPsi.ApPatPer,
                        ' ',
                        COALESCE(perPsi.ApMatPer, '')
                    ) AS NombrePsicologo,

                    s.NombreServicio

                FROM cita c

                INNER JOIN paciente pac
                    ON c.ClvPac = pac.ClvPac

                INNER JOIN usuario usuPac
                    ON pac.ClvUsu = usuPac.ClvUsu

                INNER JOIN persona perPac
                    ON usuPac.ClvPer = perPac.ClvPer

                INNER JOIN psicologo psi
                    ON c.ClvPsi = psi.ClvPsi

                INNER JOIN usuario usuPsi
                    ON psi.ClvUsu = usuPsi.ClvUsu

                INNER JOIN persona perPsi
                    ON usuPsi.ClvPer = perPsi.ClvPer

                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ

                WHERE c.ClvCons = :clvCons
                  AND c.EstadoCita = 'PROGRAMADA'
                  AND (
                        c.FechaCita > CURDATE()
                        OR (
                            c.FechaCita = CURDATE()
                            AND c.HraInicioCita >= CURTIME()
                        )
                  )

                ORDER BY
                    c.FechaCita ASC,
                    c.HraInicioCita ASC

                LIMIT {$limite}";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAgendaOperativaPorConsultorio(
        string $clvCons,
        ?string $clvPsi = null,
        ?string $estado = null
    ): array {
        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.DuracionAplicadaMin,
                    c.EstadoCita,
                    c.ClvPsi,

                    psi.EspecialidadPsi,

                    CONCAT(
                        perPsi.NombrePer,
                        ' ',
                        perPsi.ApPatPer,
                        ' ',
                        COALESCE(perPsi.ApMatPer, '')
                    ) AS NombrePsicologo,

                    s.NombreServicio,

                    co.NombreCons

                FROM cita c

                INNER JOIN psicologo psi
                    ON c.ClvPsi = psi.ClvPsi

                INNER JOIN usuario usuPsi
                    ON psi.ClvUsu = usuPsi.ClvUsu

                INNER JOIN persona perPsi
                    ON usuPsi.ClvPer = perPsi.ClvPer

                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ

                INNER JOIN consultorio co
                    ON c.ClvCons = co.ClvCons

                WHERE c.ClvCons = :clvCons";

        $parametros = [
            'clvCons' => $clvCons
        ];

        if ($clvPsi !== null && $clvPsi !== '') {
            $sql .= " AND c.ClvPsi = :clvPsi";
            $parametros['clvPsi'] = $clvPsi;
        }

        if ($estado !== null && $estado !== '') {
            $sql .= " AND c.EstadoCita = :estado";
            $parametros['estado'] = strtoupper($estado);
        }

        $sql .= "
            ORDER BY
                c.FechaCita ASC,
                c.HraInicioCita ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorPsicologo(
        string $clvPsi,
        ?string $estado = null
    ): array {
        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.DuracionAplicadaMin,
                    c.EstadoCita,
                    c.ClvPac,

                    CONCAT(
                        perPac.NombrePer,
                        ' ',
                        perPac.ApPatPer,
                        ' ',
                        COALESCE(perPac.ApMatPer, '')
                    ) AS NombrePaciente,

                    s.NombreServicio

                FROM cita c

                INNER JOIN paciente pac
                    ON c.ClvPac = pac.ClvPac

                INNER JOIN usuario usuPac
                    ON pac.ClvUsu = usuPac.ClvUsu

                INNER JOIN persona perPac
                    ON usuPac.ClvPer = perPac.ClvPer

                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ

                WHERE c.ClvPsi = :clvPsi";

        $parametros = [
            'clvPsi' => $clvPsi
        ];

        if ($estado !== null && $estado !== '') {
            $sql .= " AND c.EstadoCita = :estado";
            $parametros['estado'] = strtoupper($estado);
        }

        $sql .= "
            ORDER BY
                c.FechaCita ASC,
                c.HraInicioCita ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarCitasAsistidasPorConsultorio(
        string $clvCons
    ): int {
        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvCons = :clvCons
                  AND EstadoCita = 'ASISTIDA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function contarInasistenciasPorConsultorio(
        string $clvCons
    ): int {
        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvCons = :clvCons
                  AND EstadoCita = 'INASISTENCIA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function obtenerConteoCitasPorEspecialistaConsultorio(
        string $clvCons
    ): array {
        $sql = "SELECT
                    c.ClvPsi,

                    CONCAT(
                        perPsi.NombrePer,
                        ' ',
                        perPsi.ApPatPer,
                        ' ',
                        COALESCE(perPsi.ApMatPer, '')
                    ) AS NombrePsicologo,

                    COUNT(*) AS TotalCitas,

                    SUM(
                        CASE
                            WHEN c.EstadoCita = 'PROGRAMADA'
                            THEN 1 ELSE 0
                        END
                    ) AS Programadas,

                    SUM(
                        CASE
                            WHEN c.EstadoCita = 'ASISTIDA'
                            THEN 1 ELSE 0
                        END
                    ) AS Asistidas,

                    SUM(
                        CASE
                            WHEN c.EstadoCita = 'CANCELADA'
                            THEN 1 ELSE 0
                        END
                    ) AS Canceladas,

                    SUM(
                        CASE
                            WHEN c.EstadoCita = 'INASISTENCIA'
                            THEN 1 ELSE 0
                        END
                    ) AS Inasistencias

                FROM cita c

                INNER JOIN psicologo psi
                    ON c.ClvPsi = psi.ClvPsi

                INNER JOIN usuario usuPsi
                    ON psi.ClvUsu = usuPsi.ClvUsu

                INNER JOIN persona perPsi
                    ON usuPsi.ClvPer = perPsi.ClvPer

                WHERE c.ClvCons = :clvCons

                GROUP BY
                    c.ClvPsi,
                    perPsi.NombrePer,
                    perPsi.ApPatPer,
                    perPsi.ApMatPer

                ORDER BY
                    NombrePsicologo ASC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function pacientePerteneceAPsicologo(
        string $clvPac,
        string $clvPsi
    ): bool {
        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvPac = :clvPac
                  AND ClvPsi = :clvPsi";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPac' => $clvPac,
            'clvPsi' => $clvPsi
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /*
    =====================================
            GENERAR CLAVE
    =====================================
    */

    private function generarClave(): string {

        $sql="SELECT

                MAX(

                    CAST(

                        SUBSTRING(ClvCita,4)

                        AS UNSIGNED

                    )

                )

                FROM cita";

        $ultimo=(int)$this->db
            ->query($sql)
            ->fetchColumn();

        return 'CIT'.str_pad(

            (string)($ultimo+1),

            3,

            '0',

            STR_PAD_LEFT

        );

    }

    public function obtenerPrimeraAsistida(
        string $clvPac,
        string $clvPsi,
        string $clvCons
    ): ?array {
        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.EstadoCita,
                    c.ClvPac,
                    c.ClvPsi,
                    c.ClvCons,
                    c.ClvServ,
                    s.NombreServicio
                FROM cita c
                INNER JOIN servicios s
                    ON s.ClvServ = c.ClvServ
                WHERE c.ClvPac = :clvPac
                  AND c.ClvPsi = :clvPsi
                  AND c.ClvCons = :clvCons
                  AND c.EstadoCita = 'ASISTIDA'
                ORDER BY
                    c.FechaCita ASC,
                    c.HraInicioCita ASC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPac' => $clvPac,
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons
        ]);

        $cita = $stmt->fetch(PDO::FETCH_ASSOC);

        return $cita ?: null;
    }

    public function obtenerAsistidaParaHistoria(
        string $clvCita,
        string $clvPac,
        string $clvPsi,
        string $clvCons
    ): ?array {
        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.EstadoCita,
                    c.ClvPac,
                    c.ClvPsi,
                    c.ClvCons,
                    c.ClvServ,
                    s.NombreServicio
                FROM cita c
                INNER JOIN servicios s
                    ON s.ClvServ = c.ClvServ
                WHERE c.ClvCita = :clvCita
                  AND c.ClvPac = :clvPac
                  AND c.ClvPsi = :clvPsi
                  AND c.ClvCons = :clvCons
                  AND c.EstadoCita = 'ASISTIDA'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvCita' => $clvCita,
            'clvPac' => $clvPac,
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons
        ]);

        $cita = $stmt->fetch(PDO::FETCH_ASSOC);

        return $cita ?: null;
    }

    /**
     * Registra ASISTIDA o INASISTENCIA sobre una cita PROGRAMADA propia.
     *
     * @return array{
     *   ok: bool,
     *   codigo?: string,
     *   mensaje: string,
     *   estado?: string,
     *   clvPac?: string,
     *   clvCita?: string
     * }
     */
    public function registrarResultadoPorPsicologo(
        string $clvCita,
        string $clvPsi,
        string $clvCons,
        string $nuevoEstado
    ): array {
        $clvCita = trim($clvCita);
        $clvPsi = trim($clvPsi);
        $clvCons = trim($clvCons);
        $nuevoEstado = strtoupper(trim($nuevoEstado));

        if ($clvCita === '' || $clvPsi === '' || $clvCons === '') {
            return [
                'ok' => false,
                'codigo' => 'DATOS_INVALIDOS',
                'mensaje' =>
                    'No tienes autorización para modificar esta cita.'
            ];
        }

        if (
            !in_array(
                $nuevoEstado,
                ['ASISTIDA', 'INASISTENCIA'],
                true
            )
        ) {
            return [
                'ok' => false,
                'codigo' => 'ACCION_INVALIDA',
                'mensaje' =>
                    'Esta cita ya no puede cambiar de estado.'
            ];
        }

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }

            $stmt = $this->db->prepare(
                "SELECT
                    ClvCita,
                    FechaCita,
                    HraInicioCita,
                    EstadoCita,
                    ClvPsi,
                    ClvCons,
                    ClvPac
                 FROM cita
                 WHERE ClvCita = :clvCita
                 LIMIT 1
                 FOR UPDATE"
            );

            $stmt->execute([
                'clvCita' => $clvCita
            ]);

            $cita = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cita) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'codigo' => 'CITA_NO_ENCONTRADA',
                    'mensaje' =>
                        'No tienes autorización para modificar esta cita.'
                ];
            }

            if (
                (string) ($cita['ClvPsi'] ?? '') !== $clvPsi ||
                (string) ($cita['ClvCons'] ?? '') !== $clvCons
            ) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'codigo' => 'SIN_AUTORIZACION',
                    'mensaje' =>
                        'No tienes autorización para modificar esta cita.'
                ];
            }

            $estadoActual = strtoupper(
                trim((string) ($cita['EstadoCita'] ?? ''))
            );

            if ($estadoActual !== 'PROGRAMADA') {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'codigo' => 'TRANSICION_NO_PERMITIDA',
                    'mensaje' =>
                        'Esta cita ya no puede cambiar de estado.'
                ];
            }

            $zona = new \DateTimeZone('America/Mexico_City');
            $horaInicio = trim((string) ($cita['HraInicioCita'] ?? ''));

            if (preg_match('/^\d{2}:\d{2}$/', $horaInicio)) {
                $horaInicio .= ':00';
            }

            $inicioCita = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                (string) $cita['FechaCita'] . ' ' . $horaInicio,
                $zona
            );

            if (!$inicioCita) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'codigo' => 'FECHA_INVALIDA',
                    'mensaje' =>
                        'No fue posible validar el horario de la cita.'
                ];
            }

            $ahora = new \DateTimeImmutable('now', $zona);

            if ($inicioCita > $ahora) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'codigo' => 'CITA_NO_INICIADA',
                    'mensaje' =>
                        'La asistencia podrá registrarse cuando comience la cita.'
                ];
            }

            $sqlUpdate = "UPDATE cita
                          SET EstadoCita = :nuevoEstado
                          WHERE ClvCita = :clvCita
                            AND ClvPsi = :clvPsi
                            AND ClvCons = :clvCons
                            AND EstadoCita = 'PROGRAMADA'";

            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->execute([
                'nuevoEstado' => $nuevoEstado,
                'clvCita' => $clvCita,
                'clvPsi' => $clvPsi,
                'clvCons' => $clvCons
            ]);

            if ((int) $stmtUpdate->rowCount() !== 1) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'codigo' => 'TRANSICION_NO_PERMITIDA',
                    'mensaje' =>
                        'Esta cita ya no puede cambiar de estado.'
                ];
            }

            $this->db->commit();

            $mensaje = $nuevoEstado === 'ASISTIDA'
                ? 'La cita fue marcada como asistida.'
                : 'La inasistencia fue registrada.';

            return [
                'ok' => true,
                'estado' => $nuevoEstado,
                'mensaje' => $mensaje,
                'clvPac' => (string) ($cita['ClvPac'] ?? ''),
                'clvCita' => $clvCita
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'codigo' => 'ERROR_INTERNO',
                'mensaje' =>
                    'No fue posible actualizar el estado de la cita.'
            ];
        }
    }

}