<?php

namespace App\Models;

use App\Core\Model;
use App\Services\ClaveService;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class HistorialClinico extends Model
{
    public function obtenerPorPacienteConsultorio(
        string $clvPac,
        string $clvCons
    ): ?array {
        $sql = "SELECT *
                FROM historial_clinico
                WHERE ClvPac = :clvPac
                  AND ClvCons = :clvCons
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPac' => $clvPac,
            'clvCons' => $clvCons
        ]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function existePorPacienteConsultorio(
        string $clvPac,
        string $clvCons
    ): bool {
        return $this->obtenerPorPacienteConsultorio(
            $clvPac,
            $clvCons
        ) !== null;
    }

    public function obtenerPorClave(string $clvHist): ?array
    {
        $sql = "SELECT *
                FROM historial_clinico
                WHERE ClvHist = :clvHist
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvHist' => $clvHist]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function obtenerCompleto(string $clvHist): ?array
    {
        $historial = $this->obtenerPorClave($clvHist);

        if (!$historial) {
            return null;
        }

        return [
            'historial' => $historial,
            'estado' => $this->obtenerUno(
                'estado_psicologico_inicial',
                $clvHist
            ),
            'antecedentes_patologicos' => $this->obtenerMuchos(
                'antecedente_patologico',
                $clvHist,
                'ClvAntPat'
            ),
            'antecedentes_familiares' => $this->obtenerMuchos(
                'antecedente_familiar',
                $clvHist,
                'ClvAntFam'
            ),
            'psicoanamnesis' => $this->obtenerUno(
                'psicoanamnesis_familiar',
                $clvHist
            ),
            'actitud' => $this->obtenerUno(
                'actitud_conducta_inicial',
                $clvHist
            ),
            'vida_social' => $this->obtenerUno(
                'vida_social_laboral',
                $clvHist
            ),
            'adicciones' => $this->obtenerMuchos(
                'adiccion',
                $clvHist,
                'ClvAdiccion'
            ),
            'examen_mental' => $this->obtenerUno(
                'examen_mental_inicial',
                $clvHist
            ),
            'reactivos' => $this->obtenerMuchos(
                'reactivo_psicologico',
                $clvHist,
                'ClvReact'
            ),
            'apreciaciones' => $this->obtenerMuchos(
                'apreciacion_diagnostica',
                $clvHist,
                'ClvDiag'
            )
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, mensaje: string, clvHist?: string}
     */
    public function crearCompleto(array $payload): array
    {
        $clvPac = (string) ($payload['ClvPac'] ?? '');
        $clvPsi = (string) ($payload['ClvPsi'] ?? '');
        $clvCons = (string) ($payload['ClvCons'] ?? '');

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }

            $stmtLock = $this->db->prepare(
                "SELECT ClvHist
                 FROM historial_clinico
                 WHERE ClvPac = :clvPac
                   AND ClvCons = :clvCons
                 LIMIT 1
                 FOR UPDATE"
            );

            $stmtLock->execute([
                'clvPac' => $clvPac,
                'clvCons' => $clvCons
            ]);

            if ($stmtLock->fetchColumn()) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'mensaje' =>
                        'Ya existe una historia clínica inicial para este paciente en el consultorio.'
                ];
            }

            $clvHist = ClaveService::generar(
                'historial_clinico',
                'ClvHist',
                'HIST'
            );

            $numeroExpediente = substr(
                $clvCons . '-' . $clvPac,
                0,
                20
            );

            $fechaEntrevista = $payload['FechaEntrevistaInicial'] ?? null;

            $sqlHist = "INSERT INTO historial_clinico (
                            ClvHist,
                            NumeroExpediente,
                            FechaEntrevistaInicial,
                            EstatusHist,
                            ClvPac,
                            ClvPsi,
                            ClvCons
                        ) VALUES (
                            :clvHist,
                            :numero,
                            :fechaEntrevista,
                            'ACTIVO',
                            :clvPac,
                            :clvPsi,
                            :clvCons
                        )";

            $stmtHist = $this->db->prepare($sqlHist);
            $ok = $stmtHist->execute([
                'clvHist' => $clvHist,
                'numero' => $numeroExpediente,
                'fechaEntrevista' => $fechaEntrevista !== ''
                    ? $fechaEntrevista
                    : null,
                'clvPac' => $clvPac,
                'clvPsi' => $clvPsi,
                'clvCons' => $clvCons
            ]);

            if (!$ok) {
                throw new RuntimeException(
                    'No se pudo crear el historial clínico.'
                );
            }

            $this->insertarSecciones($clvHist, $clvPsi, $payload);

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' => 'Historia clínica inicial guardada correctamente.',
                'clvHist' => $clvHist
            ];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return [
                    'ok' => false,
                    'mensaje' =>
                        'Ya existe una historia clínica inicial para este paciente en el consultorio.'
                ];
            }

            return [
                'ok' => false,
                'mensaje' =>
                    'No fue posible guardar la historia clínica. Intenta nuevamente.'
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' =>
                    'No fue posible guardar la historia clínica. Intenta nuevamente.'
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, mensaje: string}
     */
    public function actualizarCompleto(
        string $clvHist,
        array $payload
    ): array {
        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }

            $stmt = $this->db->prepare(
                "SELECT ClvHist, ClvPsi, ClvPac, ClvCons
                 FROM historial_clinico
                 WHERE ClvHist = :clvHist
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['clvHist' => $clvHist]);
            $historial = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$historial) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'mensaje' => 'No se encontró la historia clínica.'
                ];
            }

            $fechaEntrevista = $payload['FechaEntrevistaInicial'] ?? null;

            $upd = $this->db->prepare(
                "UPDATE historial_clinico
                 SET FechaEntrevistaInicial = :fechaEntrevista,
                     FechaActualizacionHist = NOW()
                 WHERE ClvHist = :clvHist"
            );

            $upd->execute([
                'fechaEntrevista' => $fechaEntrevista !== ''
                    ? $fechaEntrevista
                    : null,
                'clvHist' => $clvHist
            ]);

            $this->actualizarSeccionesUnoAUno(
                $clvHist,
                (string) $historial['ClvPsi'],
                $payload
            );
            $this->actualizarOInsertarMultiples(
                $clvHist,
                (string) $historial['ClvPsi'],
                $payload
            );

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' => 'Historia clínica actualizada correctamente.'
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' =>
                    'No fue posible actualizar la historia clínica. Intenta nuevamente.'
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertarSecciones(
        string $clvHist,
        string $clvPsi,
        array $payload
    ): void {
        $estado = $payload['estado'] ?? [];

        if (!is_array($estado) || trim((string) ($estado['MotivoConsulta'] ?? '')) === '') {
            throw new RuntimeException(
                'El motivo de consulta es obligatorio.'
            );
        }

        $this->insertarEstado($clvHist, $estado);

        foreach ($payload['antecedentes_patologicos'] ?? [] as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $this->insertarAntecedentePatologico($clvHist, $fila);
        }

        foreach ($payload['antecedentes_familiares'] ?? [] as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $this->insertarAntecedenteFamiliar($clvHist, $fila);
        }

        if (!empty($payload['psicoanamnesis']) && is_array($payload['psicoanamnesis'])) {
            $this->insertarPsicoanamnesis(
                $clvHist,
                $payload['psicoanamnesis']
            );
        }

        if (!empty($payload['actitud']) && is_array($payload['actitud'])) {
            $this->insertarActitud($clvHist, $payload['actitud']);
        }

        if (!empty($payload['vida_social']) && is_array($payload['vida_social'])) {
            $this->insertarVidaSocial(
                $clvHist,
                $payload['vida_social']
            );
        }

        foreach ($payload['adicciones'] ?? [] as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            if (trim((string) ($fila['TipoAdiccion'] ?? '')) === '') {
                continue;
            }
            $this->insertarAdiccion($clvHist, $fila);
        }

        if (!empty($payload['examen_mental']) && is_array($payload['examen_mental'])) {
            $this->insertarExamenMental(
                $clvHist,
                $payload['examen_mental']
            );
        }

        foreach ($payload['reactivos'] ?? [] as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            if (trim((string) ($fila['NombreReactivo'] ?? '')) === '') {
                continue;
            }
            $this->insertarReactivo($clvHist, $clvPsi, $fila);
        }

        $apreciacion = $payload['apreciacion'] ?? [];

        if (
            is_array($apreciacion) &&
            trim((string) ($apreciacion['DiagnosticoInicial'] ?? '')) !== ''
        ) {
            $this->insertarApreciacion(
                $clvHist,
                $clvPsi,
                $apreciacion
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function actualizarSeccionesUnoAUno(
        string $clvHist,
        string $clvPsi,
        array $payload
    ): void {
        unset($clvPsi);

        if (!empty($payload['estado']) && is_array($payload['estado'])) {
            $estado = $payload['estado'];
            if (trim((string) ($estado['MotivoConsulta'] ?? '')) === '') {
                throw new RuntimeException(
                    'El motivo de consulta es obligatorio.'
                );
            }

            $existente = $this->obtenerUno(
                'estado_psicologico_inicial',
                $clvHist
            );

            if ($existente) {
                $sql = "UPDATE estado_psicologico_inicial SET
                    MotivoConsulta = :MotivoConsulta,
                    SintomasReferidos = :SintomasReferidos,
                    Ansiedad = :Ansiedad,
                    Angustia = :Angustia,
                    AutoestimaBaja = :AutoestimaBaja,
                    Indiferencia = :Indiferencia,
                    Confusion = :Confusion,
                    Descontrol = :Descontrol,
                    Desorientacion = :Desorientacion,
                    Incoherencia = :Incoherencia,
                    Sobrevaloracion = :Sobrevaloracion,
                    OtrosEstados = :OtrosEstados,
                    ObservacionesIniciales = :ObservacionesIniciales
                    WHERE ClvHist = :ClvHist";
                $this->db->prepare($sql)->execute(
                    $this->mapEstado($estado, $clvHist)
                );
            } else {
                $this->insertarEstado($clvHist, $estado);
            }
        }

        if (!empty($payload['psicoanamnesis']) && is_array($payload['psicoanamnesis'])) {
            $existente = $this->obtenerUno(
                'psicoanamnesis_familiar',
                $clvHist
            );
            if ($existente) {
                $sql = "UPDATE psicoanamnesis_familiar SET
                    PadresJuntos = :PadresJuntos,
                    PadreFallecido = :PadreFallecido,
                    MadreFallecida = :MadreFallecida,
                    ConflictoPadre = :ConflictoPadre,
                    ConflictoMadre = :ConflictoMadre,
                    ConflictoOtrosFamiliares = :ConflictoOtrosFamiliares,
                    ActitudPadres = :ActitudPadres,
                    NumeroHermanos = :NumeroHermanos,
                    NumeroHermanosVarones = :NumeroHermanosVarones,
                    NumeroHermanasMujeres = :NumeroHermanasMujeres,
                    RelacionHermanos = :RelacionHermanos,
                    ObservacionesFamiliares = :ObservacionesFamiliares
                    WHERE ClvHist = :ClvHist";
                $this->db->prepare($sql)->execute(
                    $this->mapPsicoanamnesis(
                        $payload['psicoanamnesis'],
                        $clvHist
                    )
                );
            } else {
                $this->insertarPsicoanamnesis(
                    $clvHist,
                    $payload['psicoanamnesis']
                );
            }
        }

        if (!empty($payload['actitud']) && is_array($payload['actitud'])) {
            $existente = $this->obtenerUno(
                'actitud_conducta_inicial',
                $clvHist
            );
            if ($existente) {
                $sql = "UPDATE actitud_conducta_inicial SET
                    Independiente = :Independiente,
                    Dependiente = :Dependiente,
                    Timida = :Timida,
                    Expansiva = :Expansiva,
                    Agresiva = :Agresiva,
                    Controlada = :Controlada,
                    Frustrada = :Frustrada,
                    Deprimida = :Deprimida,
                    Alegre = :Alegre,
                    ConductaPsicopatica = :ConductaPsicopatica,
                    ProblemasConductuales = :ProblemasConductuales,
                    TrabajoPrecoz = :TrabajoPrecoz,
                    FugaHogar = :FugaHogar,
                    EdadFugaHogar = :EdadFugaHogar,
                    SintomasNeuroticos = :SintomasNeuroticos,
                    ProblemasEscolares = :ProblemasEscolares,
                    Otros = :Otros
                    WHERE ClvHist = :ClvHist";
                $this->db->prepare($sql)->execute(
                    $this->mapActitud($payload['actitud'], $clvHist)
                );
            } else {
                $this->insertarActitud($clvHist, $payload['actitud']);
            }
        }

        if (!empty($payload['vida_social']) && is_array($payload['vida_social'])) {
            $existente = $this->obtenerUno(
                'vida_social_laboral',
                $clvHist
            );
            if ($existente) {
                $sql = "UPDATE vida_social_laboral SET
                    CantidadAmigos = :CantidadAmigos,
                    TipoGrupoSocial = :TipoGrupoSocial,
                    EstabilidadLaboral = :EstabilidadLaboral,
                    SatisfaccionLaboral = :SatisfaccionLaboral,
                    AdaptacionLaboral = :AdaptacionLaboral,
                    SituacionLaboral = :SituacionLaboral,
                    ManejoDineroAdecuado = :ManejoDineroAdecuado,
                    ActividadesTiempoLibre = :ActividadesTiempoLibre,
                    ObservacionesVidaSocial = :ObservacionesVidaSocial
                    WHERE ClvHist = :ClvHist";
                $this->db->prepare($sql)->execute(
                    $this->mapVidaSocial(
                        $payload['vida_social'],
                        $clvHist
                    )
                );
            } else {
                $this->insertarVidaSocial(
                    $clvHist,
                    $payload['vida_social']
                );
            }
        }

        if (!empty($payload['examen_mental']) && is_array($payload['examen_mental'])) {
            $existente = $this->obtenerUno(
                'examen_mental_inicial',
                $clvHist
            );
            if ($existente) {
                $sql = "UPDATE examen_mental_inicial SET
                    Conciencia = :Conciencia,
                    Orientacion = :Orientacion,
                    Inteligencia = :Inteligencia,
                    Pensamiento = :Pensamiento,
                    Afectividad = :Afectividad,
                    Atencion = :Atencion,
                    Memoria = :Memoria,
                    Sensopercepcion = :Sensopercepcion,
                    Psicomotricidad = :Psicomotricidad,
                    Habitos = :Habitos,
                    InstintosConservados = :InstintosConservados,
                    Lenguaje = :Lenguaje,
                    ObservacionesExamen = :ObservacionesExamen
                    WHERE ClvHist = :ClvHist";
                $this->db->prepare($sql)->execute(
                    $this->mapExamen(
                        $payload['examen_mental'],
                        $clvHist
                    )
                );
            } else {
                $this->insertarExamenMental(
                    $clvHist,
                    $payload['examen_mental']
                );
            }
        }

        if (
            !empty($payload['apreciacion']) &&
            is_array($payload['apreciacion']) &&
            trim((string) ($payload['apreciacion']['DiagnosticoInicial'] ?? '')) !== ''
        ) {
            $apreciaciones = $this->obtenerMuchos(
                'apreciacion_diagnostica',
                $clvHist,
                'ClvDiag'
            );

            $clvPsiResp = (string) ($payload['ClvPsi'] ?? '');

            if (!empty($apreciaciones[0]['ClvDiag'])) {
                $sql = "UPDATE apreciacion_diagnostica SET
                    ApreciacionPersonalidad = :ApreciacionPersonalidad,
                    DiagnosticoInicial = :DiagnosticoInicial,
                    CodigoDiagnostico = :CodigoDiagnostico,
                    SistemaClasificacion = :SistemaClasificacion,
                    PlanTratamiento = :PlanTratamiento,
                    RecomendacionesIniciales = :RecomendacionesIniciales,
                    PronosticoInicial = :PronosticoInicial,
                    ObservacionesDiagnosticas = :ObservacionesDiagnosticas,
                    EstatusDiagnostico = :EstatusDiagnostico
                    WHERE ClvDiag = :ClvDiag
                      AND ClvHist = :ClvHist";
                $map = $this->mapApreciacion(
                    $payload['apreciacion'],
                    $clvHist,
                    $clvPsiResp
                );
                unset($map['ClvPsi']);
                $map['ClvDiag'] = $apreciaciones[0]['ClvDiag'];
                $this->db->prepare($sql)->execute($map);
            } elseif ($clvPsiResp !== '') {
                $this->insertarApreciacion(
                    $clvHist,
                    $clvPsiResp,
                    $payload['apreciacion']
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function actualizarOInsertarMultiples(
        string $clvHist,
        string $clvPsi,
        array $payload
    ): void {
        foreach ($payload['antecedentes_patologicos'] ?? [] as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $clv = trim((string) ($fila['ClvAntPat'] ?? ''));
            if ($clv !== '') {
                $sql = "UPDATE antecedente_patologico SET
                    TipoAntecedente = :TipoAntecedente,
                    PresentaAntecedente = :PresentaAntecedente,
                    DescripcionAntecedente = :DescripcionAntecedente,
                    TratamientoActual = :TratamientoActual
                    WHERE ClvAntPat = :ClvAntPat
                      AND ClvHist = :ClvHist";
                $this->db->prepare($sql)->execute([
                    'TipoAntecedente' => $fila['TipoAntecedente'] ?? '',
                    'PresentaAntecedente' => !empty($fila['PresentaAntecedente']) ? 1 : 0,
                    'DescripcionAntecedente' => $this->nullableText(
                        $fila['DescripcionAntecedente'] ?? null
                    ),
                    'TratamientoActual' => $this->nullableText(
                        $fila['TratamientoActual'] ?? null
                    ),
                    'ClvAntPat' => $clv,
                    'ClvHist' => $clvHist
                ]);
            } else {
                $this->insertarAntecedentePatologico($clvHist, $fila);
            }
        }

        foreach ($payload['antecedentes_familiares'] ?? [] as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $clv = trim((string) ($fila['ClvAntFam'] ?? ''));
            if ($clv !== '') {
                $sql = "UPDATE antecedente_familiar SET
                    TipoAntecedenteFam = :TipoAntecedenteFam,
                    PresentaAntecedenteFam = :PresentaAntecedenteFam,
                    FamiliarRelacionado = :FamiliarRelacionado,
                    DescripcionAntFam = :DescripcionAntFam
                    WHERE ClvAntFam = :ClvAntFam
                      AND ClvHist = :ClvHist";
                $this->db->prepare($sql)->execute([
                    'TipoAntecedenteFam' => $fila['TipoAntecedenteFam'] ?? '',
                    'PresentaAntecedenteFam' => !empty($fila['PresentaAntecedenteFam']) ? 1 : 0,
                    'FamiliarRelacionado' => $this->nullableText(
                        $fila['FamiliarRelacionado'] ?? null,
                        100
                    ),
                    'DescripcionAntFam' => $this->nullableText(
                        $fila['DescripcionAntFam'] ?? null
                    ),
                    'ClvAntFam' => $clv,
                    'ClvHist' => $clvHist
                ]);
            } else {
                $this->insertarAntecedenteFamiliar($clvHist, $fila);
            }
        }

        foreach ($payload['adicciones'] ?? [] as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            if (trim((string) ($fila['TipoAdiccion'] ?? '')) === '') {
                continue;
            }
            $clv = trim((string) ($fila['ClvAdiccion'] ?? ''));
            if ($clv !== '') {
                $sql = "UPDATE adiccion SET
                    TipoAdiccion = :TipoAdiccion,
                    EdadInicio = :EdadInicio,
                    Frecuencia = :Frecuencia,
                    EstadoConsumo = :EstadoConsumo,
                    ConflictosAsociados = :ConflictosAsociados,
                    TratamientoRecibido = :TratamientoRecibido,
                    DescripcionTratamiento = :DescripcionTratamiento,
                    ObservacionesAdiccion = :ObservacionesAdiccion
                    WHERE ClvAdiccion = :ClvAdiccion
                      AND ClvHist = :ClvHist";
                $map = $this->mapAdiccion($fila, $clvHist);
                $map['ClvAdiccion'] = $clv;
                unset($map['ClvHist']);
                $map['ClvHist'] = $clvHist;
                $this->db->prepare($sql)->execute($map);
            } else {
                $this->insertarAdiccion($clvHist, $fila);
            }
        }

        foreach ($payload['reactivos'] ?? [] as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            if (trim((string) ($fila['NombreReactivo'] ?? '')) === '') {
                continue;
            }
            $clv = trim((string) ($fila['ClvReact'] ?? ''));
            if ($clv !== '') {
                $sql = "UPDATE reactivo_psicologico SET
                    NombreReactivo = :NombreReactivo,
                    FechaAplicacion = :FechaAplicacion,
                    ResultadoReactivo = :ResultadoReactivo,
                    InterpretacionReactivo = :InterpretacionReactivo
                    WHERE ClvReact = :ClvReact
                      AND ClvHist = :ClvHist";
                $this->db->prepare($sql)->execute([
                    'NombreReactivo' => substr(
                        trim((string) $fila['NombreReactivo']),
                        0,
                        100
                    ),
                    'FechaAplicacion' => $fila['FechaAplicacion'] ?? date('Y-m-d'),
                    'ResultadoReactivo' => $this->nullableText(
                        $fila['ResultadoReactivo'] ?? null
                    ),
                    'InterpretacionReactivo' => $this->nullableText(
                        $fila['InterpretacionReactivo'] ?? null
                    ),
                    'ClvReact' => $clv,
                    'ClvHist' => $clvHist
                ]);
            } else {
                $this->insertarReactivo($clvHist, $clvPsi, $fila);
            }
        }
    }

    private function obtenerUno(string $tabla, string $clvHist): ?array
    {
        $permitidas = [
            'estado_psicologico_inicial',
            'psicoanamnesis_familiar',
            'actitud_conducta_inicial',
            'vida_social_laboral',
            'examen_mental_inicial'
        ];

        if (!in_array($tabla, $permitidas, true)) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM {$tabla} WHERE ClvHist = :clvHist LIMIT 1"
        );
        $stmt->execute(['clvHist' => $clvHist]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    private function obtenerMuchos(
        string $tabla,
        string $clvHist,
        string $orden
    ): array {
        $permitidas = [
            'antecedente_patologico' => 'ClvAntPat',
            'antecedente_familiar' => 'ClvAntFam',
            'adiccion' => 'ClvAdiccion',
            'reactivo_psicologico' => 'ClvReact',
            'apreciacion_diagnostica' => 'ClvDiag'
        ];

        if (!isset($permitidas[$tabla])) {
            return [];
        }

        $campoOrden = $permitidas[$tabla];

        $stmt = $this->db->prepare(
            "SELECT * FROM {$tabla}
             WHERE ClvHist = :clvHist
             ORDER BY {$campoOrden} ASC"
        );
        $stmt->execute(['clvHist' => $clvHist]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $estado */
    private function insertarEstado(string $clvHist, array $estado): void
    {
        $clv = ClaveService::generar(
            'estado_psicologico_inicial',
            'ClvEstadoInicial',
            'EIN'
        );

        $sql = "INSERT INTO estado_psicologico_inicial (
                    ClvEstadoInicial, MotivoConsulta, SintomasReferidos,
                    Ansiedad, Angustia, AutoestimaBaja, Indiferencia,
                    Confusion, Descontrol, Desorientacion, Incoherencia,
                    Sobrevaloracion, OtrosEstados, ObservacionesIniciales, ClvHist
                ) VALUES (
                    :ClvEstadoInicial, :MotivoConsulta, :SintomasReferidos,
                    :Ansiedad, :Angustia, :AutoestimaBaja, :Indiferencia,
                    :Confusion, :Descontrol, :Desorientacion, :Incoherencia,
                    :Sobrevaloracion, :OtrosEstados, :ObservacionesIniciales, :ClvHist
                )";

        $map = $this->mapEstado($estado, $clvHist);
        $map['ClvEstadoInicial'] = $clv;
        $this->db->prepare($sql)->execute($map);
    }

    /** @param array<string, mixed> $fila */
    private function insertarAntecedentePatologico(
        string $clvHist,
        array $fila
    ): void {
        $tipo = trim((string) ($fila['TipoAntecedente'] ?? ''));
        if ($tipo === '') {
            return;
        }

        $clv = ClaveService::generar(
            'antecedente_patologico',
            'ClvAntPat',
            'APT'
        );

        $sql = "INSERT INTO antecedente_patologico (
                    ClvAntPat, TipoAntecedente, PresentaAntecedente,
                    DescripcionAntecedente, TratamientoActual, ClvHist
                ) VALUES (
                    :ClvAntPat, :TipoAntecedente, :PresentaAntecedente,
                    :DescripcionAntecedente, :TratamientoActual, :ClvHist
                )";

        $this->db->prepare($sql)->execute([
            'ClvAntPat' => $clv,
            'TipoAntecedente' => $tipo,
            'PresentaAntecedente' => !empty($fila['PresentaAntecedente']) ? 1 : 0,
            'DescripcionAntecedente' => $this->nullableText(
                $fila['DescripcionAntecedente'] ?? null
            ),
            'TratamientoActual' => $this->nullableText(
                $fila['TratamientoActual'] ?? null
            ),
            'ClvHist' => $clvHist
        ]);
    }

    /** @param array<string, mixed> $fila */
    private function insertarAntecedenteFamiliar(
        string $clvHist,
        array $fila
    ): void {
        $tipo = trim((string) ($fila['TipoAntecedenteFam'] ?? ''));
        if ($tipo === '') {
            return;
        }

        $clv = ClaveService::generar(
            'antecedente_familiar',
            'ClvAntFam',
            'AFM'
        );

        $sql = "INSERT INTO antecedente_familiar (
                    ClvAntFam, TipoAntecedenteFam, PresentaAntecedenteFam,
                    FamiliarRelacionado, DescripcionAntFam, ClvHist
                ) VALUES (
                    :ClvAntFam, :TipoAntecedenteFam, :PresentaAntecedenteFam,
                    :FamiliarRelacionado, :DescripcionAntFam, :ClvHist
                )";

        $this->db->prepare($sql)->execute([
            'ClvAntFam' => $clv,
            'TipoAntecedenteFam' => $tipo,
            'PresentaAntecedenteFam' => !empty($fila['PresentaAntecedenteFam']) ? 1 : 0,
            'FamiliarRelacionado' => $this->nullableText(
                $fila['FamiliarRelacionado'] ?? null,
                100
            ),
            'DescripcionAntFam' => $this->nullableText(
                $fila['DescripcionAntFam'] ?? null
            ),
            'ClvHist' => $clvHist
        ]);
    }

    /** @param array<string, mixed> $data */
    private function insertarPsicoanamnesis(
        string $clvHist,
        array $data
    ): void {
        $clv = ClaveService::generar(
            'psicoanamnesis_familiar',
            'ClvPsicoFam',
            'PSF'
        );

        $sql = "INSERT INTO psicoanamnesis_familiar (
                    ClvPsicoFam, PadresJuntos, PadreFallecido, MadreFallecida,
                    ConflictoPadre, ConflictoMadre, ConflictoOtrosFamiliares,
                    ActitudPadres, NumeroHermanos, NumeroHermanosVarones,
                    NumeroHermanasMujeres, RelacionHermanos,
                    ObservacionesFamiliares, ClvHist
                ) VALUES (
                    :ClvPsicoFam, :PadresJuntos, :PadreFallecido, :MadreFallecida,
                    :ConflictoPadre, :ConflictoMadre, :ConflictoOtrosFamiliares,
                    :ActitudPadres, :NumeroHermanos, :NumeroHermanosVarones,
                    :NumeroHermanasMujeres, :RelacionHermanos,
                    :ObservacionesFamiliares, :ClvHist
                )";

        $map = $this->mapPsicoanamnesis($data, $clvHist);
        $map['ClvPsicoFam'] = $clv;
        $this->db->prepare($sql)->execute($map);
    }

    /** @param array<string, mixed> $data */
    private function insertarActitud(string $clvHist, array $data): void
    {
        $clv = ClaveService::generar(
            'actitud_conducta_inicial',
            'ClvActitud',
            'ACT'
        );

        $sql = "INSERT INTO actitud_conducta_inicial (
                    ClvActitud, Independiente, Dependiente, Timida, Expansiva,
                    Agresiva, Controlada, Frustrada, Deprimida, Alegre,
                    ConductaPsicopatica, ProblemasConductuales, TrabajoPrecoz,
                    FugaHogar, EdadFugaHogar, SintomasNeuroticos,
                    ProblemasEscolares, Otros, ClvHist
                ) VALUES (
                    :ClvActitud, :Independiente, :Dependiente, :Timida, :Expansiva,
                    :Agresiva, :Controlada, :Frustrada, :Deprimida, :Alegre,
                    :ConductaPsicopatica, :ProblemasConductuales, :TrabajoPrecoz,
                    :FugaHogar, :EdadFugaHogar, :SintomasNeuroticos,
                    :ProblemasEscolares, :Otros, :ClvHist
                )";

        $map = $this->mapActitud($data, $clvHist);
        $map['ClvActitud'] = $clv;
        $this->db->prepare($sql)->execute($map);
    }

    /** @param array<string, mixed> $data */
    private function insertarVidaSocial(string $clvHist, array $data): void
    {
        $clv = ClaveService::generar(
            'vida_social_laboral',
            'ClvVidaSocial',
            'VSL'
        );

        $sql = "INSERT INTO vida_social_laboral (
                    ClvVidaSocial, CantidadAmigos, TipoGrupoSocial,
                    EstabilidadLaboral, SatisfaccionLaboral, AdaptacionLaboral,
                    SituacionLaboral, ManejoDineroAdecuado,
                    ActividadesTiempoLibre, ObservacionesVidaSocial, ClvHist
                ) VALUES (
                    :ClvVidaSocial, :CantidadAmigos, :TipoGrupoSocial,
                    :EstabilidadLaboral, :SatisfaccionLaboral, :AdaptacionLaboral,
                    :SituacionLaboral, :ManejoDineroAdecuado,
                    :ActividadesTiempoLibre, :ObservacionesVidaSocial, :ClvHist
                )";

        $map = $this->mapVidaSocial($data, $clvHist);
        $map['ClvVidaSocial'] = $clv;
        $this->db->prepare($sql)->execute($map);
    }

    /** @param array<string, mixed> $fila */
    private function insertarAdiccion(string $clvHist, array $fila): void
    {
        $clv = ClaveService::generar(
            'adiccion',
            'ClvAdiccion',
            'ADI'
        );

        $sql = "INSERT INTO adiccion (
                    ClvAdiccion, TipoAdiccion, EdadInicio, Frecuencia,
                    EstadoConsumo, ConflictosAsociados, TratamientoRecibido,
                    DescripcionTratamiento, ObservacionesAdiccion, ClvHist
                ) VALUES (
                    :ClvAdiccion, :TipoAdiccion, :EdadInicio, :Frecuencia,
                    :EstadoConsumo, :ConflictosAsociados, :TratamientoRecibido,
                    :DescripcionTratamiento, :ObservacionesAdiccion, :ClvHist
                )";

        $map = $this->mapAdiccion($fila, $clvHist);
        $map['ClvAdiccion'] = $clv;
        $this->db->prepare($sql)->execute($map);
    }

    /** @param array<string, mixed> $data */
    private function insertarExamenMental(
        string $clvHist,
        array $data
    ): void {
        $clv = ClaveService::generar(
            'examen_mental_inicial',
            'ClvExamenMental',
            'EXM'
        );

        $sql = "INSERT INTO examen_mental_inicial (
                    ClvExamenMental, Conciencia, Orientacion, Inteligencia,
                    Pensamiento, Afectividad, Atencion, Memoria,
                    Sensopercepcion, Psicomotricidad, Habitos,
                    InstintosConservados, Lenguaje, ObservacionesExamen, ClvHist
                ) VALUES (
                    :ClvExamenMental, :Conciencia, :Orientacion, :Inteligencia,
                    :Pensamiento, :Afectividad, :Atencion, :Memoria,
                    :Sensopercepcion, :Psicomotricidad, :Habitos,
                    :InstintosConservados, :Lenguaje, :ObservacionesExamen, :ClvHist
                )";

        $map = $this->mapExamen($data, $clvHist);
        $map['ClvExamenMental'] = $clv;
        $this->db->prepare($sql)->execute($map);
    }

    /** @param array<string, mixed> $fila */
    private function insertarReactivo(
        string $clvHist,
        string $clvPsi,
        array $fila
    ): void {
        $clv = ClaveService::generar(
            'reactivo_psicologico',
            'ClvReact',
            'REA'
        );

        $sql = "INSERT INTO reactivo_psicologico (
                    ClvReact, NombreReactivo, FechaAplicacion,
                    ResultadoReactivo, InterpretacionReactivo, ClvHist, ClvPsi
                ) VALUES (
                    :ClvReact, :NombreReactivo, :FechaAplicacion,
                    :ResultadoReactivo, :InterpretacionReactivo, :ClvHist, :ClvPsi
                )";

        $this->db->prepare($sql)->execute([
            'ClvReact' => $clv,
            'NombreReactivo' => substr(
                trim((string) $fila['NombreReactivo']),
                0,
                100
            ),
            'FechaAplicacion' => $fila['FechaAplicacion'] ?? date('Y-m-d'),
            'ResultadoReactivo' => $this->nullableText(
                $fila['ResultadoReactivo'] ?? null
            ),
            'InterpretacionReactivo' => $this->nullableText(
                $fila['InterpretacionReactivo'] ?? null
            ),
            'ClvHist' => $clvHist,
            'ClvPsi' => $clvPsi
        ]);
    }

    /** @param array<string, mixed> $data */
    private function insertarApreciacion(
        string $clvHist,
        string $clvPsi,
        array $data
    ): void {
        $clv = ClaveService::generar(
            'apreciacion_diagnostica',
            'ClvDiag',
            'DIA'
        );

        $sql = "INSERT INTO apreciacion_diagnostica (
                    ClvDiag, ApreciacionPersonalidad, DiagnosticoInicial,
                    CodigoDiagnostico, SistemaClasificacion, PlanTratamiento,
                    RecomendacionesIniciales, PronosticoInicial,
                    ObservacionesDiagnosticas, EstatusDiagnostico,
                    ClvHist, ClvPsi
                ) VALUES (
                    :ClvDiag, :ApreciacionPersonalidad, :DiagnosticoInicial,
                    :CodigoDiagnostico, :SistemaClasificacion, :PlanTratamiento,
                    :RecomendacionesIniciales, :PronosticoInicial,
                    :ObservacionesDiagnosticas, :EstatusDiagnostico,
                    :ClvHist, :ClvPsi
                )";

        $map = $this->mapApreciacion($data, $clvHist, $clvPsi);
        $map['ClvDiag'] = $clv;
        $this->db->prepare($sql)->execute($map);
    }

    /** @param array<string, mixed> $estado @return array<string, mixed> */
    private function mapEstado(array $estado, string $clvHist): array
    {
        return [
            'MotivoConsulta' => trim((string) ($estado['MotivoConsulta'] ?? '')),
            'SintomasReferidos' => $this->nullableText(
                $estado['SintomasReferidos'] ?? null
            ),
            'Ansiedad' => !empty($estado['Ansiedad']) ? 1 : 0,
            'Angustia' => !empty($estado['Angustia']) ? 1 : 0,
            'AutoestimaBaja' => !empty($estado['AutoestimaBaja']) ? 1 : 0,
            'Indiferencia' => !empty($estado['Indiferencia']) ? 1 : 0,
            'Confusion' => !empty($estado['Confusion']) ? 1 : 0,
            'Descontrol' => !empty($estado['Descontrol']) ? 1 : 0,
            'Desorientacion' => !empty($estado['Desorientacion']) ? 1 : 0,
            'Incoherencia' => !empty($estado['Incoherencia']) ? 1 : 0,
            'Sobrevaloracion' => !empty($estado['Sobrevaloracion']) ? 1 : 0,
            'OtrosEstados' => $this->nullableText(
                $estado['OtrosEstados'] ?? null
            ),
            'ObservacionesIniciales' => $this->nullableText(
                $estado['ObservacionesIniciales'] ?? null
            ),
            'ClvHist' => $clvHist
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function mapPsicoanamnesis(array $data, string $clvHist): array
    {
        return [
            'PadresJuntos' => $this->nullableBool($data['PadresJuntos'] ?? null),
            'PadreFallecido' => !empty($data['PadreFallecido']) ? 1 : 0,
            'MadreFallecida' => !empty($data['MadreFallecida']) ? 1 : 0,
            'ConflictoPadre' => !empty($data['ConflictoPadre']) ? 1 : 0,
            'ConflictoMadre' => !empty($data['ConflictoMadre']) ? 1 : 0,
            'ConflictoOtrosFamiliares' => $this->nullableText(
                $data['ConflictoOtrosFamiliares'] ?? null
            ),
            'ActitudPadres' => $this->nullableEnum(
                $data['ActitudPadres'] ?? null,
                [
                    'AFECTUOSA',
                    'SOBREPROTECTORA',
                    'INDIFERENTE',
                    'HOSTIL',
                    'INEXISTENTE',
                    'OTRA'
                ]
            ),
            'NumeroHermanos' => $this->nullableInt($data['NumeroHermanos'] ?? null),
            'NumeroHermanosVarones' => $this->nullableInt(
                $data['NumeroHermanosVarones'] ?? null
            ),
            'NumeroHermanasMujeres' => $this->nullableInt(
                $data['NumeroHermanasMujeres'] ?? null
            ),
            'RelacionHermanos' => $this->nullableEnum(
                $data['RelacionHermanos'] ?? null,
                [
                    'AFECTUOSA',
                    'SOBREPROTECTORA',
                    'APATICA',
                    'AGRESIVA',
                    'INEXISTENTE',
                    'OTRA'
                ]
            ),
            'ObservacionesFamiliares' => $this->nullableText(
                $data['ObservacionesFamiliares'] ?? null
            ),
            'ClvHist' => $clvHist
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function mapActitud(array $data, string $clvHist): array
    {
        $flags = [
            'Independiente',
            'Dependiente',
            'Timida',
            'Expansiva',
            'Agresiva',
            'Controlada',
            'Frustrada',
            'Deprimida',
            'Alegre',
            'ConductaPsicopatica',
            'ProblemasConductuales',
            'TrabajoPrecoz',
            'FugaHogar',
            'SintomasNeuroticos',
            'ProblemasEscolares'
        ];

        $map = [];
        foreach ($flags as $flag) {
            $map[$flag] = !empty($data[$flag]) ? 1 : 0;
        }

        $map['EdadFugaHogar'] = $this->nullableInt(
            $data['EdadFugaHogar'] ?? null
        );
        $map['Otros'] = $this->nullableText($data['Otros'] ?? null);
        $map['ClvHist'] = $clvHist;

        return $map;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function mapVidaSocial(array $data, string $clvHist): array
    {
        return [
            'CantidadAmigos' => $this->nullableEnum(
                $data['CantidadAmigos'] ?? null,
                ['MUCHOS', 'POCOS', 'NINGUNO']
            ),
            'TipoGrupoSocial' => $this->nullableEnum(
                $data['TipoGrupoSocial'] ?? null,
                ['DISOCIAL', 'MIXTO', 'SANO', 'SIN_GRUPO']
            ),
            'EstabilidadLaboral' => $this->nullableEnum(
                $data['EstabilidadLaboral'] ?? null,
                ['ESTABLE', 'INESTABLE', 'NO_APLICA']
            ),
            'SatisfaccionLaboral' => $this->nullableEnum(
                $data['SatisfaccionLaboral'] ?? null,
                [
                    'SATISFECHO',
                    'PARCIALMENTE_SATISFECHO',
                    'INSATISFECHO',
                    'NO_APLICA'
                ]
            ),
            'AdaptacionLaboral' => $this->nullableEnum(
                $data['AdaptacionLaboral'] ?? null,
                ['ADECUADA', 'REGULAR', 'INADECUADA', 'NO_APLICA']
            ),
            'SituacionLaboral' => $this->nullableEnum(
                $data['SituacionLaboral'] ?? null,
                [
                    'REALIZADO',
                    'FRUSTRADO',
                    'DESEMPLEADO',
                    'DESPEDIDO',
                    'SANCIONADO',
                    'REUBICADO',
                    'REINGRESADO',
                    'NO_APLICA',
                    'OTRO'
                ]
            ),
            'ManejoDineroAdecuado' => $this->nullableBool(
                $data['ManejoDineroAdecuado'] ?? null
            ),
            'ActividadesTiempoLibre' => $this->nullableText(
                $data['ActividadesTiempoLibre'] ?? null
            ),
            'ObservacionesVidaSocial' => $this->nullableText(
                $data['ObservacionesVidaSocial'] ?? null
            ),
            'ClvHist' => $clvHist
        ];
    }

    /** @param array<string, mixed> $fila @return array<string, mixed> */
    private function mapAdiccion(array $fila, string $clvHist): array
    {
        return [
            'TipoAdiccion' => substr(
                trim((string) ($fila['TipoAdiccion'] ?? '')),
                0,
                50
            ),
            'EdadInicio' => $this->nullableInt($fila['EdadInicio'] ?? null),
            'Frecuencia' => $this->nullableEnum(
                $fila['Frecuencia'] ?? null,
                [
                    'FRECUENTE',
                    'POCO_FRECUENTE',
                    'OCASIONAL',
                    'NO_ESPECIFICADA'
                ]
            ),
            'EstadoConsumo' => $this->nullableEnum(
                $fila['EstadoConsumo'] ?? null,
                [
                    'CONTROLADO',
                    'DESCONTROLADO',
                    'EN_ABSTINENCIA',
                    'EN_TRATAMIENTO',
                    'NO_ESPECIFICADO'
                ]
            ),
            'ConflictosAsociados' => $this->nullableText(
                $fila['ConflictosAsociados'] ?? null
            ),
            'TratamientoRecibido' => !empty($fila['TratamientoRecibido']) ? 1 : 0,
            'DescripcionTratamiento' => $this->nullableText(
                $fila['DescripcionTratamiento'] ?? null
            ),
            'ObservacionesAdiccion' => $this->nullableText(
                $fila['ObservacionesAdiccion'] ?? null
            ),
            'ClvHist' => $clvHist
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function mapExamen(array $data, string $clvHist): array
    {
        return [
            'Conciencia' => $this->nullableText($data['Conciencia'] ?? null, 100),
            'Orientacion' => $this->nullableText($data['Orientacion'] ?? null, 100),
            'Inteligencia' => $this->nullableText($data['Inteligencia'] ?? null, 100),
            'Pensamiento' => $this->nullableText($data['Pensamiento'] ?? null),
            'Afectividad' => $this->nullableText($data['Afectividad'] ?? null),
            'Atencion' => $this->nullableText($data['Atencion'] ?? null, 100),
            'Memoria' => $this->nullableText($data['Memoria'] ?? null, 150),
            'Sensopercepcion' => $this->nullableText(
                $data['Sensopercepcion'] ?? null
            ),
            'Psicomotricidad' => $this->nullableText(
                $data['Psicomotricidad'] ?? null
            ),
            'Habitos' => $this->nullableText($data['Habitos'] ?? null),
            'InstintosConservados' => $this->nullableBool(
                $data['InstintosConservados'] ?? null
            ),
            'Lenguaje' => $this->nullableText($data['Lenguaje'] ?? null),
            'ObservacionesExamen' => $this->nullableText(
                $data['ObservacionesExamen'] ?? null
            ),
            'ClvHist' => $clvHist
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function mapApreciacion(
        array $data,
        string $clvHist,
        string $clvPsi
    ): array {
        return [
            'ApreciacionPersonalidad' => $this->nullableText(
                $data['ApreciacionPersonalidad'] ?? null
            ),
            'DiagnosticoInicial' => trim(
                (string) ($data['DiagnosticoInicial'] ?? '')
            ),
            'CodigoDiagnostico' => $this->nullableText(
                $data['CodigoDiagnostico'] ?? null,
                20
            ),
            'SistemaClasificacion' => $this->nullableEnum(
                $data['SistemaClasificacion'] ?? null,
                ['DSM5', 'CIE10', 'CIE11', 'OTRO']
            ),
            'PlanTratamiento' => $this->nullableText(
                $data['PlanTratamiento'] ?? null
            ),
            'RecomendacionesIniciales' => $this->nullableText(
                $data['RecomendacionesIniciales'] ?? null
            ),
            'PronosticoInicial' => $this->nullableText(
                $data['PronosticoInicial'] ?? null
            ),
            'ObservacionesDiagnosticas' => $this->nullableText(
                $data['ObservacionesDiagnosticas'] ?? null
            ),
            'EstatusDiagnostico' => $this->nullableEnum(
                $data['EstatusDiagnostico'] ?? 'VIGENTE',
                ['VIGENTE', 'MODIFICADO', 'DESCARTADO']
            ) ?? 'VIGENTE',
            'ClvHist' => $clvHist,
            'ClvPsi' => $clvPsi
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

        if ($max !== null) {
            return substr($text, 0, $max);
        }

        return $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableBool(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return !empty($value) ? 1 : 0;
    }

    /**
     * @param list<string> $permitidos
     */
    private function nullableEnum(mixed $value, array $permitidos): ?string
    {
        $valor = strtoupper(trim((string) ($value ?? '')));

        if ($valor === '' || !in_array($valor, $permitidos, true)) {
            return null;
        }

        return $valor;
    }
}
