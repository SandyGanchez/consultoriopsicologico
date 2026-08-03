<?php

namespace App\Models;

use App\Core\Model;
use App\Services\ClaveService;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class SeguimientoSesion extends Model
{
    public function existeParaCita(string $clvCita): bool
    {
        return $this->obtenerClavePorCita($clvCita) !== null;
    }

    public function obtenerClavePorCita(string $clvCita): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT ClvSeg
             FROM seguimiento_sesion
             WHERE ClvCita = :clvCita
             LIMIT 1"
        );
        $stmt->execute(['clvCita' => $clvCita]);
        $clvSeg = $stmt->fetchColumn();

        return is_string($clvSeg) && $clvSeg !== ''
            ? $clvSeg
            : null;
    }

    public function listarPorHistorial(string $clvHist): array
    {
        $sql = "SELECT
                    s.ClvSeg,
                    s.NumeroSesion,
                    s.FechaRegistroSeg,
                    s.HoraInicioReal,
                    s.HoraFinReal,
                    s.ObjetivoSesion,
                    s.TemaAbordado,
                    s.ObservacionesSeg,
                    s.EstatusSeg,
                    s.ClvHist,
                    s.ClvCita,
                    s.ClvPsi,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.EstadoCita,
                    srv.NombreServicio,
                    (
                        SELECT d.DiagnosticoActual
                        FROM diagnostico_seguimiento d
                        WHERE d.ClvSeg = (s.ClvSeg COLLATE utf8mb4_unicode_ci)
                        ORDER BY d.FechaDiagSeg DESC
                        LIMIT 1
                    ) AS DiagnosticoActual,
                    (
                        SELECT e.AvancesSeg
                        FROM evolucion_sesion e
                        WHERE e.ClvSeg = (s.ClvSeg COLLATE utf8mb4_unicode_ci)
                        LIMIT 1
                    ) AS AvancesSeg
                FROM seguimiento_sesion s
                INNER JOIN cita c
                    ON c.ClvCita = (s.ClvCita COLLATE utf8mb4_unicode_ci)
                INNER JOIN servicios srv
                    ON srv.ClvServ = c.ClvServ
                WHERE (s.ClvHist COLLATE utf8mb4_unicode_ci) = :clvHist
                ORDER BY
                    c.FechaCita DESC,
                    c.HraInicioCita DESC,
                    s.NumeroSesion DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvHist' => $clvHist]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerPorClave(string $clvSeg): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM seguimiento_sesion
             WHERE ClvSeg = :clvSeg
             LIMIT 1"
        );
        $stmt->execute(['clvSeg' => $clvSeg]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function obtenerCompleto(string $clvSeg): ?array
    {
        $seguimiento = $this->obtenerPorClave($clvSeg);

        if (!$seguimiento) {
            return null;
        }

        $sqlCita = "SELECT
                        c.ClvCita,
                        c.FechaCita,
                        c.HraInicioCita,
                        c.HraFinCita,
                        c.EstadoCita,
                        s.NombreServicio
                    FROM cita c
                    INNER JOIN servicios s
                        ON s.ClvServ = c.ClvServ
                    WHERE c.ClvCita = :clvCita
                    LIMIT 1";
        $stmtCita = $this->db->prepare($sqlCita);
        $stmtCita->execute([
            'clvCita' => $seguimiento['ClvCita']
        ]);
        $cita = $stmtCita->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmtEvo = $this->db->prepare(
            "SELECT * FROM evolucion_sesion
             WHERE ClvSeg = :clvSeg LIMIT 1"
        );
        $stmtEvo->execute(['clvSeg' => $clvSeg]);
        $evolucion = $stmtEvo->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmtDiag = $this->db->prepare(
            "SELECT * FROM diagnostico_seguimiento
             WHERE ClvSeg = :clvSeg
             ORDER BY FechaDiagSeg DESC
             LIMIT 1"
        );
        $stmtDiag->execute(['clvSeg' => $clvSeg]);
        $diagnostico = $stmtDiag->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmtRec = $this->db->prepare(
            "SELECT * FROM recomendacion_sesion
             WHERE ClvSeg = :clvSeg
             ORDER BY ClvRecSeg ASC"
        );
        $stmtRec->execute(['clvSeg' => $clvSeg]);
        $recomendaciones = $stmtRec->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'seguimiento' => $seguimiento,
            'cita' => $cita,
            'evolucion' => $evolucion,
            'diagnostico' => $diagnostico,
            'recomendaciones' => $recomendaciones
        ];
    }

    public function listarCitasAsistidasPendientes(
        string $clvPac,
        string $clvPsi,
        string $clvCons
    ): array {
        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.EstadoCita,
                    c.ClvPac,
                    c.ClvPsi,
                    c.ClvCons,
                    s.NombreServicio
                FROM cita c
                INNER JOIN servicios s
                    ON s.ClvServ = c.ClvServ
                WHERE c.ClvPac = :clvPac
                  AND c.ClvPsi = :clvPsi
                  AND c.ClvCons = :clvCons
                  AND c.EstadoCita = 'ASISTIDA'
                  AND NOT EXISTS (
                        SELECT 1
                        FROM seguimiento_sesion ss
                        WHERE (ss.ClvCita COLLATE utf8mb4_unicode_ci) = c.ClvCita
                  )
                ORDER BY
                    c.FechaCita DESC,
                    c.HraInicioCita DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPac' => $clvPac,
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, mensaje: string, clvSeg?: string}
     */
    public function crearCompleto(array $payload): array
    {
        $clvHist = (string) ($payload['ClvHist'] ?? '');
        $clvPsi = (string) ($payload['ClvPsi'] ?? '');
        $clvCita = (string) ($payload['ClvCita'] ?? '');

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }

            $stmtHist = $this->db->prepare(
                "SELECT ClvHist, ClvPsi, ClvPac, ClvCons
                 FROM historial_clinico
                 WHERE ClvHist = :clvHist
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmtHist->execute(['clvHist' => $clvHist]);
            $historial = $stmtHist->fetch(PDO::FETCH_ASSOC);

            if (!$historial) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'mensaje' => 'No se encontró el expediente clínico.'
                ];
            }

            if ((string) $historial['ClvPsi'] !== $clvPsi) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'mensaje' =>
                        'Este expediente clínico se encuentra asignado a otro especialista.'
                ];
            }

            $stmtCita = $this->db->prepare(
                "SELECT ClvCita, ClvPac, ClvPsi, ClvCons, EstadoCita
                 FROM cita
                 WHERE ClvCita = :clvCita
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmtCita->execute(['clvCita' => $clvCita]);
            $cita = $stmtCita->fetch(PDO::FETCH_ASSOC);

            if (
                !$cita ||
                ($cita['EstadoCita'] ?? '') !== 'ASISTIDA' ||
                (string) $cita['ClvPac'] !== (string) $historial['ClvPac'] ||
                (string) $cita['ClvPsi'] !== $clvPsi ||
                (string) $cita['ClvCons'] !== (string) $historial['ClvCons']
            ) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'mensaje' =>
                        'La cita seleccionada no está disponible para registrar seguimiento.'
                ];
            }

            $stmtDup = $this->db->prepare(
                "SELECT ClvSeg
                 FROM seguimiento_sesion
                 WHERE ClvCita = :clvCita
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmtDup->execute(['clvCita' => $clvCita]);

            if ($stmtDup->fetchColumn()) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'mensaje' =>
                        'Esta sesión ya cuenta con un seguimiento registrado.'
                ];
            }

            $stmtNum = $this->db->prepare(
                "SELECT COALESCE(MAX(NumeroSesion), 0)
                 FROM seguimiento_sesion
                 WHERE ClvHist = :clvHist
                 FOR UPDATE"
            );
            $stmtNum->execute(['clvHist' => $clvHist]);
            $numeroSesion = ((int) $stmtNum->fetchColumn()) + 1;

            $clvSeg = ClaveService::generar(
                'seguimiento_sesion',
                'ClvSeg',
                'SEG'
            );

            $sqlSeg = "INSERT INTO seguimiento_sesion (
                            ClvSeg, NumeroSesion, HoraInicioReal, HoraFinReal,
                            ObjetivoSesion, TemaAbordado, DesarrolloSesion,
                            TecnicasAplicadas, RespuestaPaciente, EstadoEmocional,
                            ObservacionesSeg, AcuerdosSeg, TareasAsignadas,
                            RecomendacionesSeg, ProximaAccion, EstatusSeg,
                            ClvHist, ClvCita, ClvPsi
                       ) VALUES (
                            :ClvSeg, :NumeroSesion, :HoraInicioReal, :HoraFinReal,
                            :ObjetivoSesion, :TemaAbordado, :DesarrolloSesion,
                            :TecnicasAplicadas, :RespuestaPaciente, :EstadoEmocional,
                            :ObservacionesSeg, :AcuerdosSeg, :TareasAsignadas,
                            :RecomendacionesSeg, :ProximaAccion, :EstatusSeg,
                            :ClvHist, :ClvCita, :ClvPsi
                       )";

            $map = $this->mapSeguimiento($payload, $clvHist, $clvCita, $clvPsi);
            $map['ClvSeg'] = $clvSeg;
            $map['NumeroSesion'] = $numeroSesion;

            if (!$this->db->prepare($sqlSeg)->execute($map)) {
                throw new RuntimeException('No se pudo insertar el seguimiento.');
            }

            $this->insertarEvolucion($clvSeg, $payload['evolucion'] ?? []);
            $this->insertarDiagnostico(
                $clvSeg,
                $clvPsi,
                $payload['diagnostico'] ?? []
            );

            foreach ($payload['recomendaciones'] ?? [] as $rec) {
                if (!is_array($rec)) {
                    continue;
                }
                if (trim((string) ($rec['DescripcionRec'] ?? '')) === '') {
                    continue;
                }
                $this->insertarRecomendacion($clvSeg, $rec);
            }

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' =>
                    'El seguimiento de la sesión se guardó correctamente.',
                'clvSeg' => $clvSeg
            ];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return [
                    'ok' => false,
                    'mensaje' =>
                        'Esta sesión ya cuenta con un seguimiento registrado.'
                ];
            }

            return [
                'ok' => false,
                'mensaje' =>
                    'No fue posible guardar el seguimiento. Intenta nuevamente.'
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' =>
                    'No fue posible guardar el seguimiento. Intenta nuevamente.'
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, mensaje: string}
     */
    public function actualizarCompleto(
        string $clvSeg,
        string $clvPsi,
        array $payload
    ): array {
        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }

            $stmt = $this->db->prepare(
                "SELECT s.*, h.ClvPac, h.ClvCons, h.ClvPsi AS HistPsi
                 FROM seguimiento_sesion s
                 INNER JOIN historial_clinico h
                    ON h.ClvHist = (s.ClvHist COLLATE utf8mb4_unicode_ci)
                 WHERE s.ClvSeg = :clvSeg
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['clvSeg' => $clvSeg]);
            $seg = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$seg) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'mensaje' =>
                        'No tienes autorización para acceder a este seguimiento.'
                ];
            }

            if (
                (string) ($seg['ClvPsi'] ?? '') !== $clvPsi ||
                (string) ($seg['HistPsi'] ?? '') !== $clvPsi
            ) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'mensaje' =>
                        'No tienes autorización para acceder a este seguimiento.'
                ];
            }

            $sql = "UPDATE seguimiento_sesion SET
                        HoraInicioReal = :HoraInicioReal,
                        HoraFinReal = :HoraFinReal,
                        ObjetivoSesion = :ObjetivoSesion,
                        TemaAbordado = :TemaAbordado,
                        DesarrolloSesion = :DesarrolloSesion,
                        TecnicasAplicadas = :TecnicasAplicadas,
                        RespuestaPaciente = :RespuestaPaciente,
                        EstadoEmocional = :EstadoEmocional,
                        ObservacionesSeg = :ObservacionesSeg,
                        AcuerdosSeg = :AcuerdosSeg,
                        TareasAsignadas = :TareasAsignadas,
                        RecomendacionesSeg = :RecomendacionesSeg,
                        ProximaAccion = :ProximaAccion,
                        EstatusSeg = :EstatusSeg
                    WHERE ClvSeg = :ClvSeg
                      AND ClvPsi = :ClvPsi";

            $map = $this->mapSeguimiento(
                $payload,
                (string) $seg['ClvHist'],
                (string) $seg['ClvCita'],
                $clvPsi
            );
            unset(
                $map['ClvHist'],
                $map['ClvCita'],
                $map['NumeroSesion']
            );
            $map['ClvSeg'] = $clvSeg;
            $map['ClvPsi'] = $clvPsi;

            $this->db->prepare($sql)->execute($map);

            $this->upsertEvolucion($clvSeg, $payload['evolucion'] ?? []);
            $this->upsertDiagnostico(
                $clvSeg,
                $clvPsi,
                $payload['diagnostico'] ?? []
            );
            $this->sincronizarRecomendaciones(
                $clvSeg,
                $payload['recomendaciones'] ?? []
            );

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' =>
                    'El seguimiento de la sesión se guardó correctamente.'
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' =>
                    'No fue posible actualizar el seguimiento. Intenta nuevamente.'
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function mapSeguimiento(
        array $payload,
        string $clvHist,
        string $clvCita,
        string $clvPsi
    ): array {
        $estatus = strtoupper(
            trim((string) ($payload['EstatusSeg'] ?? 'FINALIZADO'))
        );
        $estatusOk = [
            'BORRADOR',
            'FINALIZADO',
            'CORREGIDO',
            'ANULADO'
        ];

        if (!in_array($estatus, $estatusOk, true)) {
            $estatus = 'FINALIZADO';
        }

        return [
            'HoraInicioReal' => $this->nullableTime(
                $payload['HoraInicioReal'] ?? null
            ),
            'HoraFinReal' => $this->nullableTime(
                $payload['HoraFinReal'] ?? null
            ),
            'ObjetivoSesion' => $this->nullableText(
                $payload['ObjetivoSesion'] ?? null
            ),
            'TemaAbordado' => $this->nullableText(
                $payload['TemaAbordado'] ?? null
            ),
            'DesarrolloSesion' => $this->nullableText(
                $payload['DesarrolloSesion'] ?? null
            ),
            'TecnicasAplicadas' => $this->nullableText(
                $payload['TecnicasAplicadas'] ?? null
            ),
            'RespuestaPaciente' => $this->nullableText(
                $payload['RespuestaPaciente'] ?? null
            ),
            'EstadoEmocional' => $this->nullableText(
                $payload['EstadoEmocional'] ?? null,
                100
            ),
            'ObservacionesSeg' => $this->nullableText(
                $payload['ObservacionesSeg'] ?? null
            ),
            'AcuerdosSeg' => $this->nullableText(
                $payload['AcuerdosSeg'] ?? null
            ),
            'TareasAsignadas' => $this->nullableText(
                $payload['TareasAsignadas'] ?? null
            ),
            'RecomendacionesSeg' => $this->nullableText(
                $payload['RecomendacionesSeg'] ?? null
            ),
            'ProximaAccion' => $this->nullableText(
                $payload['ProximaAccion'] ?? null
            ),
            'EstatusSeg' => $estatus,
            'ClvHist' => $clvHist,
            'ClvCita' => $clvCita,
            'ClvPsi' => $clvPsi
        ];
    }

    /** @param array<string, mixed> $data */
    private function insertarEvolucion(string $clvSeg, array $data): void
    {
        $clv = ClaveService::generar(
            'evolucion_sesion',
            'ClvEvolucion',
            'EVO'
        );

        $sql = "INSERT INTO evolucion_sesion (
                    ClvEvolucion, AvancesSeg, DificultadesSeg, RetrocesosSeg,
                    CumplimientoTareas, CambiosConductuales, CambiosEmocionales,
                    FactoresRiesgo, FactoresProtectores, PronosticoActual, ClvSeg
                ) VALUES (
                    :ClvEvolucion, :AvancesSeg, :DificultadesSeg, :RetrocesosSeg,
                    :CumplimientoTareas, :CambiosConductuales, :CambiosEmocionales,
                    :FactoresRiesgo, :FactoresProtectores, :PronosticoActual, :ClvSeg
                )";

        $map = $this->mapEvolucion($data);
        $map['ClvEvolucion'] = $clv;
        $map['ClvSeg'] = $clvSeg;
        $this->db->prepare($sql)->execute($map);
    }

    /** @param array<string, mixed> $data */
    private function upsertEvolucion(string $clvSeg, array $data): void
    {
        $stmt = $this->db->prepare(
            "SELECT ClvEvolucion FROM evolucion_sesion
             WHERE ClvSeg = :clvSeg LIMIT 1"
        );
        $stmt->execute(['clvSeg' => $clvSeg]);
        $clv = $stmt->fetchColumn();

        if (!$clv) {
            $this->insertarEvolucion($clvSeg, $data);

            return;
        }

        $sql = "UPDATE evolucion_sesion SET
                    AvancesSeg = :AvancesSeg,
                    DificultadesSeg = :DificultadesSeg,
                    RetrocesosSeg = :RetrocesosSeg,
                    CumplimientoTareas = :CumplimientoTareas,
                    CambiosConductuales = :CambiosConductuales,
                    CambiosEmocionales = :CambiosEmocionales,
                    FactoresRiesgo = :FactoresRiesgo,
                    FactoresProtectores = :FactoresProtectores,
                    PronosticoActual = :PronosticoActual
                WHERE ClvSeg = :ClvSeg";
        $map = $this->mapEvolucion($data);
        $map['ClvSeg'] = $clvSeg;
        $this->db->prepare($sql)->execute($map);
    }

    /** @param array<string, mixed> $data */
    private function insertarDiagnostico(
        string $clvSeg,
        string $clvPsi,
        array $data
    ): void {
        $diagnostico = trim((string) ($data['DiagnosticoActual'] ?? ''));

        if ($diagnostico === '') {
            return;
        }

        $clv = ClaveService::generar(
            'diagnostico_seguimiento',
            'ClvDiagSeg',
            'DSG'
        );

        $sql = "INSERT INTO diagnostico_seguimiento (
                    ClvDiagSeg, TipoCambioDiag, DiagnosticoAnterior,
                    DiagnosticoActual, CodigoDiagnostico, SistemaClasificacion,
                    JustificacionCambio, ClvSeg, ClvPsi
                ) VALUES (
                    :ClvDiagSeg, :TipoCambioDiag, :DiagnosticoAnterior,
                    :DiagnosticoActual, :CodigoDiagnostico, :SistemaClasificacion,
                    :JustificacionCambio, :ClvSeg, :ClvPsi
                )";

        $map = $this->mapDiagnostico($data);
        $map['ClvDiagSeg'] = $clv;
        $map['ClvSeg'] = $clvSeg;
        $map['ClvPsi'] = $clvPsi;
        $this->db->prepare($sql)->execute($map);
    }

    /** @param array<string, mixed> $data */
    private function upsertDiagnostico(
        string $clvSeg,
        string $clvPsi,
        array $data
    ): void {
        $diagnostico = trim((string) ($data['DiagnosticoActual'] ?? ''));

        if ($diagnostico === '') {
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT ClvDiagSeg FROM diagnostico_seguimiento
             WHERE ClvSeg = :clvSeg
             ORDER BY FechaDiagSeg DESC
             LIMIT 1"
        );
        $stmt->execute(['clvSeg' => $clvSeg]);
        $clv = $stmt->fetchColumn();

        if (!$clv) {
            $this->insertarDiagnostico($clvSeg, $clvPsi, $data);

            return;
        }

        $sql = "UPDATE diagnostico_seguimiento SET
                    TipoCambioDiag = :TipoCambioDiag,
                    DiagnosticoAnterior = :DiagnosticoAnterior,
                    DiagnosticoActual = :DiagnosticoActual,
                    CodigoDiagnostico = :CodigoDiagnostico,
                    SistemaClasificacion = :SistemaClasificacion,
                    JustificacionCambio = :JustificacionCambio
                WHERE ClvDiagSeg = :ClvDiagSeg
                  AND ClvSeg = :ClvSeg";
        $map = $this->mapDiagnostico($data);
        $map['ClvDiagSeg'] = $clv;
        $map['ClvSeg'] = $clvSeg;
        $this->db->prepare($sql)->execute($map);
    }

    /** @param array<string, mixed> $data */
    private function insertarRecomendacion(string $clvSeg, array $data): void
    {
        $tipo = strtoupper(trim((string) ($data['TipoRecomendacion'] ?? '')));
        $tipos = [
            'TAREA',
            'EJERCICIO',
            'LECTURA',
            'HABITO',
            'CANALIZACION',
            'ESTUDIO_COMPLEMENTARIO',
            'OTRA'
        ];

        if (!in_array($tipo, $tipos, true)) {
            throw new RuntimeException(
                'Tipo de recomendación no válido.'
            );
        }

        $descripcion = trim((string) ($data['DescripcionRec'] ?? ''));

        if ($descripcion === '') {
            throw new RuntimeException(
                'La descripción de la recomendación es obligatoria.'
            );
        }

        $clv = ClaveService::generar(
            'recomendacion_sesion',
            'ClvRecSeg',
            'RCS'
        );

        $sql = "INSERT INTO recomendacion_sesion (
                    ClvRecSeg, TipoRecomendacion, DescripcionRec,
                    FechaLimite, Cumplida, FechaCumplimiento, ClvSeg
                ) VALUES (
                    :ClvRecSeg, :TipoRecomendacion, :DescripcionRec,
                    :FechaLimite, :Cumplida, :FechaCumplimiento, :ClvSeg
                )";

        $this->db->prepare($sql)->execute([
            'ClvRecSeg' => $clv,
            'TipoRecomendacion' => $tipo,
            'DescripcionRec' => $descripcion,
            'FechaLimite' => $this->nullableDate($data['FechaLimite'] ?? null),
            'Cumplida' => !empty($data['Cumplida']) ? 1 : 0,
            'FechaCumplimiento' => $this->nullableDate(
                $data['FechaCumplimiento'] ?? null
            ),
            'ClvSeg' => $clvSeg
        ]);
    }

    /**
     * Actualiza recomendaciones existentes e inserta nuevas.
     * No elimina físicamente filas omitidas (sin estatus de baja en BD).
     *
     * @param list<array<string, mixed>> $recomendaciones
     */
    private function sincronizarRecomendaciones(
        string $clvSeg,
        array $recomendaciones
    ): void {
        foreach ($recomendaciones as $rec) {
            if (!is_array($rec)) {
                continue;
            }

            $descripcion = trim((string) ($rec['DescripcionRec'] ?? ''));
            $clvRec = trim((string) ($rec['ClvRecSeg'] ?? ''));

            if ($descripcion === '' && $clvRec === '') {
                continue;
            }

            if ($descripcion === '') {
                continue;
            }

            if ($clvRec !== '') {
                $tipo = strtoupper(
                    trim((string) ($rec['TipoRecomendacion'] ?? 'OTRA'))
                );
                $tipos = [
                    'TAREA',
                    'EJERCICIO',
                    'LECTURA',
                    'HABITO',
                    'CANALIZACION',
                    'ESTUDIO_COMPLEMENTARIO',
                    'OTRA'
                ];

                if (!in_array($tipo, $tipos, true)) {
                    $tipo = 'OTRA';
                }

                $sql = "UPDATE recomendacion_sesion SET
                            TipoRecomendacion = :TipoRecomendacion,
                            DescripcionRec = :DescripcionRec,
                            FechaLimite = :FechaLimite,
                            Cumplida = :Cumplida,
                            FechaCumplimiento = :FechaCumplimiento
                        WHERE ClvRecSeg = :ClvRecSeg
                          AND ClvSeg = :ClvSeg";
                $this->db->prepare($sql)->execute([
                    'TipoRecomendacion' => $tipo,
                    'DescripcionRec' => $descripcion,
                    'FechaLimite' => $this->nullableDate(
                        $rec['FechaLimite'] ?? null
                    ),
                    'Cumplida' => !empty($rec['Cumplida']) ? 1 : 0,
                    'FechaCumplimiento' => $this->nullableDate(
                        $rec['FechaCumplimiento'] ?? null
                    ),
                    'ClvRecSeg' => $clvRec,
                    'ClvSeg' => $clvSeg
                ]);
            } else {
                $this->insertarRecomendacion($clvSeg, $rec);
            }
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function mapEvolucion(array $data): array
    {
        $cumplimiento = strtoupper(
            trim((string) ($data['CumplimientoTareas'] ?? ''))
        );
        $ok = ['COMPLETO', 'PARCIAL', 'NO_REALIZADO', 'NO_APLICA'];

        return [
            'AvancesSeg' => $this->nullableText($data['AvancesSeg'] ?? null),
            'DificultadesSeg' => $this->nullableText(
                $data['DificultadesSeg'] ?? null
            ),
            'RetrocesosSeg' => $this->nullableText(
                $data['RetrocesosSeg'] ?? null
            ),
            'CumplimientoTareas' => in_array($cumplimiento, $ok, true)
                ? $cumplimiento
                : null,
            'CambiosConductuales' => $this->nullableText(
                $data['CambiosConductuales'] ?? null
            ),
            'CambiosEmocionales' => $this->nullableText(
                $data['CambiosEmocionales'] ?? null
            ),
            'FactoresRiesgo' => $this->nullableText(
                $data['FactoresRiesgo'] ?? null
            ),
            'FactoresProtectores' => $this->nullableText(
                $data['FactoresProtectores'] ?? null
            ),
            'PronosticoActual' => $this->nullableText(
                $data['PronosticoActual'] ?? null
            )
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function mapDiagnostico(array $data): array
    {
        $tipo = strtoupper(trim((string) ($data['TipoCambioDiag'] ?? 'SE_MANTIENE')));
        $tipos = ['SE_MANTIENE', 'MODIFICADO', 'DESCARTADO', 'NUEVO'];

        if (!in_array($tipo, $tipos, true)) {
            $tipo = 'SE_MANTIENE';
        }

        $sistema = strtoupper(
            trim((string) ($data['SistemaClasificacion'] ?? ''))
        );
        $sistemas = ['DSM5', 'CIE10', 'CIE11', 'OTRO'];

        return [
            'TipoCambioDiag' => $tipo,
            'DiagnosticoAnterior' => $this->nullableText(
                $data['DiagnosticoAnterior'] ?? null
            ),
            'DiagnosticoActual' => trim(
                (string) ($data['DiagnosticoActual'] ?? '')
            ),
            'CodigoDiagnostico' => $this->nullableText(
                $data['CodigoDiagnostico'] ?? null,
                20
            ),
            'SistemaClasificacion' => in_array($sistema, $sistemas, true)
                ? $sistema
                : null,
            'JustificacionCambio' => $this->nullableText(
                $data['JustificacionCambio'] ?? null
            )
        ];
    }

    private function nullableText(mixed $value, ?int $max = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return $max !== null ? substr($text, 0, $max) : $text;
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $text);

        return ($dt && $dt->format('Y-m-d') === $text) ? $text : null;
    }

    private function nullableTime(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $text)) {
            return $text . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $text)) {
            return $text;
        }

        return null;
    }
}
