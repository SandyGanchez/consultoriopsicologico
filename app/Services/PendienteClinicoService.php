<?php

namespace App\Services;

use App\Config\Database;
use App\Helpers\Helper;
use App\Models\Cita;
use App\Models\HistorialClinico;
use App\Models\Paciente;
use App\Models\SeguimientoSesion;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Calcula pendientes clínicos operativos sin persistir notificaciones.
 * No expone contenido clínico (diagnósticos, notas, antecedentes).
 */
class PendienteClinicoService
{
    public const CITA_FUTURA = 'CITA_FUTURA';
    public const REGISTRAR_ASISTENCIA = 'REGISTRAR_ASISTENCIA';
    public const HISTORIA_INICIAL_PENDIENTE = 'HISTORIA_INICIAL_PENDIENTE';
    public const SEGUIMIENTO_PENDIENTE = 'SEGUIMIENTO_PENDIENTE';
    public const DOCUMENTACION_COMPLETA = 'DOCUMENTACION_COMPLETA';
    public const SIN_ACCION_CLINICA = 'SIN_ACCION_CLINICA';

    private PDO $db;
    private Cita $citaModel;
    private HistorialClinico $historialModel;
    private SeguimientoSesion $seguimientoModel;
    private Paciente $pacienteModel;

    public function __construct(
        ?PDO $db = null,
        ?Cita $citaModel = null,
        ?HistorialClinico $historialModel = null,
        ?SeguimientoSesion $seguimientoModel = null,
        ?Paciente $pacienteModel = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->citaModel = $citaModel ?? new Cita();
        $this->historialModel = $historialModel ?? new HistorialClinico();
        $this->seguimientoModel = $seguimientoModel ?? new SeguimientoSesion();
        $this->pacienteModel = $pacienteModel ?? new Paciente();
    }

    public function ahoraMexico(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
    }

    /**
     * @param array<string, mixed> $cita Filas de cita (mínimo ClvCita, FechaCita, HraInicioCita, EstadoCita, ClvPac, ClvPsi, ClvCons)
     * @return array{
     *   estado: string,
     *   mensaje: string,
     *   etiquetaAccion: string,
     *   rutaAccion: string,
     *   clvCita: string,
     *   clvPac: string,
     *   puedeRegistrarAsistencia: bool,
     *   puedeCrearHistoria: bool,
     *   puedeRegistrarSeguimiento: bool,
     *   rutasSecundarias: array{verPaciente?: string, completarDatos?: string}
     * }
     */
    public function evaluarCita(
        array $cita,
        string $clvPsi,
        string $clvCons,
        ?DateTimeImmutable $ahora = null
    ): array {
        $clvPsi = trim($clvPsi);
        $clvCons = trim($clvCons);
        $ahora = $ahora ?? $this->ahoraMexico();

        $base = [
            'estado' => self::SIN_ACCION_CLINICA,
            'mensaje' => '',
            'etiquetaAccion' => '',
            'rutaAccion' => '',
            'clvCita' => trim((string) ($cita['ClvCita'] ?? '')),
            'clvPac' => trim((string) ($cita['ClvPac'] ?? '')),
            'puedeRegistrarAsistencia' => false,
            'puedeMarcarAsistida' => false,
            'puedeMarcarInasistencia' => false,
            'motivoBloqueoAsistencia' => '',
            'horaInicioLegible' => '',
            'puedeCrearHistoria' => false,
            'puedeRegistrarSeguimiento' => false,
            'rutasSecundarias' => []
        ];

        if (
            $base['clvCita'] === ''
            || $base['clvPac'] === ''
            || (string) ($cita['ClvPsi'] ?? '') !== $clvPsi
            || (string) ($cita['ClvCons'] ?? '') !== $clvCons
        ) {
            return $base;
        }

        $estadoCita = strtoupper(trim((string) ($cita['EstadoCita'] ?? '')));

        if (in_array($estadoCita, ['CANCELADA', 'INASISTENCIA'], true)) {
            return $base;
        }

        $inicio = $this->fechaHoraInicioCita($cita);
        if ($inicio === null) {
            return $base;
        }

        $rutaPaciente = 'psicologo/pacientes/ver/' . rawurlencode($base['clvPac']);
        $base['rutasSecundarias'] = [
            'verPaciente' => $rutaPaciente
        ];

        if ($estadoCita === 'PROGRAMADA') {
            $ventana = (new ResultadoCitaVentanaService())
                ->evaluarIndicadores($cita, $ahora);

            if (
                empty($ventana['puedeMarcarAsistida'])
                && empty($ventana['puedeMarcarInasistencia'])
            ) {
                return array_merge($base, [
                    'estado' => self::CITA_FUTURA,
                    'mensaje' => (string) (
                        $ventana['motivoBloqueoAsistencia']
                        ?: 'Podrás registrar la asistencia cuando comience la cita.'
                    ),
                    'puedeMarcarAsistida' => false,
                    'puedeMarcarInasistencia' => false,
                    'motivoBloqueoAsistencia' => (string) (
                        $ventana['motivoBloqueoAsistencia'] ?? ''
                    ),
                    'horaInicioLegible' => (string) (
                        $ventana['horaInicioLegible'] ?? ''
                    )
                ]);
            }

            $faltantes = (new CompletarInformacionPacienteService())
                ->evaluarFaltantes($base['clvPac'], $clvPsi, $clvCons);

            if (!empty($faltantes['tieneFaltantes'])) {
                $base['rutasSecundarias']['completarDatos'] =
                    $rutaPaciente . '/completar-informacion?retorno=detalle';
            }

            return array_merge($base, [
                'estado' => self::REGISTRAR_ASISTENCIA,
                'mensaje' =>
                    'Esta cita está pendiente de registrar asistencia.',
                'etiquetaAccion' => 'Registrar asistencia',
                'puedeRegistrarAsistencia' => true,
                'puedeMarcarAsistida' => !empty($ventana['puedeMarcarAsistida']),
                'puedeMarcarInasistencia' => !empty(
                    $ventana['puedeMarcarInasistencia']
                ),
                'motivoBloqueoAsistencia' => (string) (
                    $ventana['motivoBloqueoAsistencia'] ?? ''
                ),
                'horaInicioLegible' => (string) (
                    $ventana['horaInicioLegible'] ?? ''
                )
            ]);
        }

        if ($estadoCita !== 'ASISTIDA') {
            return $base;
        }

        if (!$this->pacienteModel->perteneceAlConsultorio($base['clvPac'], $clvCons)) {
            return $base;
        }

        $historial = $this->historialModel->obtenerPorPacienteConsultorio(
            $base['clvPac'],
            $clvCons
        );

        if ($historial === null) {
            $consentimiento = (new PrivacidadService())
                ->validarConsentimientoParaHistoria($base['clvPac']);

            if (empty($consentimiento['ok'])) {
                return array_merge($base, [
                    'estado' => self::SIN_ACCION_CLINICA,
                    'mensaje' => (string) (
                        $consentimiento['mensaje']
                        ?? PrivacidadService::MENSAJE_SIN_CONSENTIMIENTO_HISTORIA
                    )
                ]);
            }

            return array_merge($base, [
                'estado' => self::HISTORIA_INICIAL_PENDIENTE,
                'mensaje' => 'Historia clínica inicial pendiente',
                'etiquetaAccion' => 'Crear historia clínica inicial',
                'rutaAccion' => $rutaPaciente . '/historia/nueva',
                'puedeCrearHistoria' => true
            ]);
        }

        if ((string) ($historial['ClvPsi'] ?? '') !== $clvPsi) {
            return $base;
        }

        $clvHist = (string) ($historial['ClvHist'] ?? '');
        $clvSeg = $this->seguimientoModel->obtenerClavePorCita($base['clvCita']);

        if ($clvSeg !== null) {
            return array_merge($base, [
                'estado' => self::DOCUMENTACION_COMPLETA,
                'mensaje' => 'Documentación clínica de esta cita completa.',
                'etiquetaAccion' => 'Ver seguimiento',
                'rutaAccion' =>
                    'psicologo/expediente/seguimientos/ver/'
                    . rawurlencode($clvSeg)
            ]);
        }

        return array_merge($base, [
            'estado' => self::SEGUIMIENTO_PENDIENTE,
            'mensaje' => 'Seguimiento terapéutico pendiente',
            'etiquetaAccion' => 'Registrar seguimiento',
            'rutaAccion' =>
                'psicologo/expediente/'
                . rawurlencode($clvHist)
                . '/seguimientos/nuevo',
            'puedeRegistrarSeguimiento' => true
        ]);
    }

    /**
     * Compatibilidad con agenda: mapea estados normalizados a la forma previa.
     *
     * @return array{accion: string, etiqueta: string, ruta: string, estado: string, mensaje: string}
     */
    public function resolverAccionClinicaAgenda(
        string $clvPac,
        string $clvPsi,
        string $clvCons,
        string $clvCita,
        string $estadoCita,
        ?string $fechaCita = null,
        ?string $hraInicio = null
    ): array {
        $cita = [
            'ClvCita' => $clvCita,
            'ClvPac' => $clvPac,
            'ClvPsi' => $clvPsi,
            'ClvCons' => $clvCons,
            'EstadoCita' => $estadoCita,
            'FechaCita' => $fechaCita ?? '',
            'HraInicioCita' => $hraInicio ?? ''
        ];

        // Si faltan fecha/hora, recuperarlas solo cuando hacen falta para PROGRAMADA.
        if (
            strtoupper(trim($estadoCita)) === 'PROGRAMADA'
            && ($fechaCita === null || $fechaCita === '' || $hraInicio === null || $hraInicio === '')
        ) {
            $cargada = $this->obtenerCitaPropia($clvCita, $clvPsi, $clvCons);
            if ($cargada !== null) {
                $cita = $cargada;
            }
        }

        if (
            strtoupper(trim($estadoCita)) === 'ASISTIDA'
            && ($fechaCita === null || $fechaCita === '')
        ) {
            $cargada = $this->obtenerCitaPropia($clvCita, $clvPsi, $clvCons);
            if ($cargada !== null) {
                $cita = $cargada;
            } else {
                $cita['EstadoCita'] = $estadoCita;
            }
        }

        $eval = $this->evaluarCita($cita, $clvPsi, $clvCons);

        $mapaAccion = match ($eval['estado']) {
            self::REGISTRAR_ASISTENCIA => 'registrar_asistencia',
            self::HISTORIA_INICIAL_PENDIENTE => 'crear_historia',
            self::SEGUIMIENTO_PENDIENTE => 'registrar_seguimiento',
            self::DOCUMENTACION_COMPLETA => 'ver_seguimiento',
            default => ''
        };

        if (strtoupper(trim($estadoCita)) === 'INASISTENCIA') {
            return [
                'accion' => 'inasistencia',
                'etiqueta' => 'Inasistencia registrada.',
                'ruta' => '',
                'estado' => self::SIN_ACCION_CLINICA,
                'mensaje' => 'Inasistencia registrada.'
            ];
        }

        return [
            'accion' => $mapaAccion,
            'etiqueta' => $eval['etiquetaAccion'],
            'ruta' => $eval['rutaAccion'],
            'estado' => $eval['estado'],
            'mensaje' => $eval['mensaje'],
            'puedeRegistrarAsistencia' => $eval['puedeRegistrarAsistencia'],
            'rutasSecundarias' => $eval['rutasSecundarias']
        ];
    }

    /**
     * Estados operativos para refresco en vivo (sin contenido clínico).
     * ClvPsi/ClvCons siempre desde sesión del controlador.
     *
     * @return array{
     *   ok: bool,
     *   ahora: string,
     *   zona: string,
     *   proximaEvaluacionIso: ?string,
     *   citas: list<array<string, mixed>>,
     *   registrarAsistencia: list<array>,
     *   historiasPendientes: list<array>,
     *   seguimientosPendientes: list<array>
     * }
     */
    public function obtenerSnapshotOperativo(
        string $clvPsi,
        string $clvCons,
        ?string $clvCita = null
    ): array {
        $clvPsi = trim($clvPsi);
        $clvCons = trim($clvCons);
        $clvCita = $clvCita !== null ? trim($clvCita) : null;
        $ahora = $this->ahoraMexico();

        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.DuracionAplicadaMin,
                    c.EstadoCita,
                    c.ClvPac,
                    c.ClvPsi,
                    c.ClvCons,
                    CONCAT(
                        TRIM(per.NombrePer), ' ',
                        TRIM(per.ApPatPer), ' ',
                        TRIM(COALESCE(per.ApMatPer, ''))
                    ) AS NombrePaciente,
                    s.NombreServicio
                FROM cita c
                INNER JOIN paciente pac ON pac.ClvPac = c.ClvPac
                INNER JOIN persona per ON per.ClvPer = pac.ClvPer
                LEFT JOIN usuario u ON u.ClvUsu = pac.ClvUsu
                INNER JOIN servicios s ON s.ClvServ = c.ClvServ
                WHERE c.ClvPsi = :clvPsi
                  AND c.ClvCons = :clvCons
                  AND c.EstadoCita IN ('PROGRAMADA', 'ASISTIDA')";

        $params = [
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons
        ];

        if ($clvCita !== null && $clvCita !== '') {
            $sql .= ' AND c.ClvCita = :clvCita';
            $params['clvCita'] = $clvCita;
        } else {
            // Ventana operativa: desde hace 2 días hasta +14 días.
            $sql .= ' AND c.FechaCita BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 DAY)
                      AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)';
        }

        $sql .= ' ORDER BY c.FechaCita ASC, c.HraInicioCita ASC LIMIT 80';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $citas = [];
        $proximaEvaluacion = null;

        foreach ($filas as $fila) {
            $eval = $this->evaluarCita($fila, $clvPsi, $clvCons, $ahora);
            $mapaAccion = match ($eval['estado']) {
                self::REGISTRAR_ASISTENCIA => 'registrar_asistencia',
                self::HISTORIA_INICIAL_PENDIENTE => 'crear_historia',
                self::SEGUIMIENTO_PENDIENTE => 'registrar_seguimiento',
                self::DOCUMENTACION_COMPLETA => 'ver_seguimiento',
                default => ''
            };

            $citas[] = [
                'clvCita' => (string) ($fila['ClvCita'] ?? ''),
                'clvPac' => (string) ($fila['ClvPac'] ?? ''),
                'estadoCita' => strtoupper((string) ($fila['EstadoCita'] ?? '')),
                'fechaCita' => (string) ($fila['FechaCita'] ?? ''),
                'hraInicioCita' => (string) ($fila['HraInicioCita'] ?? ''),
                'nombrePaciente' => trim((string) ($fila['NombrePaciente'] ?? '')),
                'nombreServicio' => trim((string) ($fila['NombreServicio'] ?? '')),
                'estadoClinico' => $eval['estado'],
                'mensajeClinico' => $eval['mensaje'],
                'accionClinica' => $mapaAccion,
                'etiquetaClinica' => $eval['etiquetaAccion'],
                'urlClinica' => $this->urlPublica($eval['rutaAccion']),
                'puedeRegistrarAsistencia' => !empty($eval['puedeRegistrarAsistencia']),
                'puedeMarcarAsistida' => !empty($eval['puedeMarcarAsistida']),
                'puedeMarcarInasistencia' => !empty($eval['puedeMarcarInasistencia']),
                'motivoBloqueoAsistencia' => (string) (
                    $eval['motivoBloqueoAsistencia'] ?? ''
                ),
                'horaInicioLegible' => (string) (
                    $eval['horaInicioLegible'] ?? ''
                ),
                'urlVerPaciente' => $this->urlPublica(
                    (string) ($eval['rutasSecundarias']['verPaciente'] ?? '')
                ),
                'urlCompletarDatos' => $this->urlPublica(
                    (string) ($eval['rutasSecundarias']['completarDatos'] ?? '')
                )
            ];

            if (($fila['EstadoCita'] ?? '') === 'PROGRAMADA') {
                $inicio = $this->fechaHoraInicioCita($fila);
                $fin = (new ResultadoCitaVentanaService())->resolverFin($fila);
                // Reevaluar al inicio y al fin de la sesión.
                foreach ([$inicio, $fin] as $punto) {
                    if (
                        $punto instanceof DateTimeImmutable
                        && $punto > $ahora
                        && (
                            $proximaEvaluacion === null
                            || $punto < $proximaEvaluacion
                        )
                    ) {
                        $proximaEvaluacion = $punto;
                    }
                }
            }
        }

        // Si el snapshot es de una sola cita, conservar la próxima PROGRAMADA global.
        if ($clvCita !== null && $clvCita !== '') {
            $proximaEvaluacion = $this->obtenerProximaProgramadaInicio(
                $clvPsi,
                $clvCons,
                $ahora
            ) ?? $proximaEvaluacion;
        }

        $agregados = $this->listarPendientesOperativos($clvPsi, $clvCons);

        return [
            'ok' => true,
            'ahora' => $ahora->format(DateTimeImmutable::ATOM),
            'zona' => 'America/Mexico_City',
            'proximaEvaluacionIso' => $proximaEvaluacion?->format(
                DateTimeImmutable::ATOM
            ),
            'citas' => $citas,
            'registrarAsistencia' => $this->mapearAsistenciaOperativa(
                $agregados['registrarAsistencia']
            ),
            'historiasPendientes' => $this->mapearHistoriaOperativa(
                $agregados['historiasPendientes']
            ),
            'seguimientosPendientes' => $this->mapearSeguimientoOperativo(
                $agregados['seguimientosPendientes']
            )
        ];
    }

    private function obtenerProximaProgramadaInicio(
        string $clvPsi,
        string $clvCons,
        DateTimeImmutable $ahora
    ): ?DateTimeImmutable {
        $stmt = $this->db->prepare(
            "SELECT FechaCita, HraInicioCita
             FROM cita
             WHERE ClvPsi = :clvPsi
               AND ClvCons = :clvCons
               AND EstadoCita = 'PROGRAMADA'
               AND TIMESTAMP(FechaCita, HraInicioCita) > :ahora
             ORDER BY FechaCita ASC, HraInicioCita ASC
             LIMIT 1"
        );
        $stmt->execute([
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons,
            'ahora' => $ahora->format('Y-m-d H:i:s')
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->fechaHoraInicioCita($fila) : null;
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return list<array<string, string>>
     */
    private function mapearAsistenciaOperativa(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $clvPac = (string) ($fila['ClvPac'] ?? '');
            $out[] = [
                'clvCita' => (string) ($fila['ClvCita'] ?? ''),
                'clvPac' => $clvPac,
                'nombrePaciente' => trim((string) ($fila['NombrePaciente'] ?? '')),
                'fechaCita' => (string) ($fila['FechaCita'] ?? ''),
                'hraInicioCita' => (string) ($fila['HraInicioCita'] ?? ''),
                'urlAgenda' => $this->urlPublica('psicologo/agenda'),
                'urlVerPaciente' => $this->urlPublica(
                    'psicologo/pacientes/ver/' . rawurlencode($clvPac)
                )
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return list<array<string, string>>
     */
    private function mapearHistoriaOperativa(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $clvPac = (string) ($fila['ClvPac'] ?? '');
            $out[] = [
                'clvPac' => $clvPac,
                'nombrePaciente' => trim((string) ($fila['NombrePaciente'] ?? '')),
                'urlHistoria' => $this->urlPublica(
                    'psicologo/pacientes/ver/'
                    . rawurlencode($clvPac)
                    . '/historia/nueva'
                )
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return list<array<string, string>>
     */
    private function mapearSeguimientoOperativo(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $clvHist = (string) ($fila['ClvHist'] ?? '');
            $out[] = [
                'clvCita' => (string) ($fila['ClvCita'] ?? ''),
                'clvPac' => (string) ($fila['ClvPac'] ?? ''),
                'clvHist' => $clvHist,
                'nombrePaciente' => trim((string) ($fila['NombrePaciente'] ?? '')),
                'fechaCita' => (string) ($fila['FechaCita'] ?? ''),
                'urlSeguimiento' => $this->urlPublica(
                    'psicologo/expediente/'
                    . rawurlencode($clvHist)
                    . '/seguimientos/nuevo'
                )
            ];
        }

        return $out;
    }

    /**
     * Pendientes agregados para dashboard (consulta, sin inserts).
     *
     * @return array{
     *   registrarAsistencia: list<array>,
     *   historiasPendientes: list<array>,
     *   seguimientosPendientes: list<array>
     * }
     */
    public function listarPendientesOperativos(
        string $clvPsi,
        string $clvCons
    ): array {
        $clvPsi = trim($clvPsi);
        $clvCons = trim($clvCons);
        $ahora = $this->ahoraMexico();
        $ahoraSql = $ahora->format('Y-m-d H:i:s');

        $asistencia = $this->listarCitasParaRegistrarAsistencia(
            $clvPsi,
            $clvCons,
            $ahoraSql
        );

        $historias = $this->listarPacientesHistoriaPendiente($clvPsi, $clvCons);
        $seguimientos = $this->listarCitasSeguimientoPendiente($clvPsi, $clvCons);

        return [
            'registrarAsistencia' => $asistencia,
            'historiasPendientes' => $historias,
            'seguimientosPendientes' => $seguimientos
        ];
    }

    /**
     * Resumen por paciente (detalle / expedientes), sin contenido clínico.
     *
     * @return array{
     *   ok: bool,
     *   mensaje?: string,
     *   infoPersonalIncompleta: bool,
     *   historiaPendiente: bool,
     *   seguimientoPendiente: bool,
     *   citasRegistrarAsistencia: list<array>,
     *   citasSeguimientoPendiente: list<array>,
     *   faltantes?: array
     * }
     */
    public function evaluarPaciente(
        string $clvPac,
        string $clvPsi,
        string $clvCons
    ): array {
        $clvPac = trim($clvPac);
        $clvPsi = trim($clvPsi);
        $clvCons = trim($clvCons);

        if (
            !$this->pacienteModel->perteneceAPsicologo($clvPac, $clvPsi)
            || !$this->pacienteModel->perteneceAlConsultorio($clvPac, $clvCons)
        ) {
            return [
                'ok' => false,
                'mensaje' => 'No tienes autorización para consultar este paciente.',
                'infoPersonalIncompleta' => false,
                'historiaPendiente' => false,
                'seguimientoPendiente' => false,
                'citasRegistrarAsistencia' => [],
                'citasSeguimientoPendiente' => []
            ];
        }

        $info = (new CompletarInformacionPacienteService())->evaluarFaltantes(
            $clvPac,
            $clvPsi,
            $clvCons
        );

        $ahoraSql = $this->ahoraMexico()->format('Y-m-d H:i:s');
        $citasAsistencia = $this->listarCitasParaRegistrarAsistencia(
            $clvPsi,
            $clvCons,
            $ahoraSql,
            $clvPac
        );

        $historial = $this->historialModel->obtenerPorPacienteConsultorio(
            $clvPac,
            $clvCons
        );

        $historiaPendiente = false;
        if (
            $historial === null
            && $this->citaModel->obtenerPrimeraAsistida($clvPac, $clvPsi, $clvCons) !== null
        ) {
            $consentimiento = (new PrivacidadService())
                ->validarConsentimientoParaHistoria($clvPac);
            $historiaPendiente = !empty($consentimiento['ok']);
        }

        $citasSeg = [];
        if (
            $historial !== null
            && (string) ($historial['ClvPsi'] ?? '') === $clvPsi
        ) {
            $citasSeg = $this->seguimientoModel->listarCitasAsistidasPendientes(
                $clvPac,
                $clvPsi,
                $clvCons
            );
        }

        return [
            'ok' => true,
            'infoPersonalIncompleta' => !empty($info['tieneFaltantes']),
            'faltantes' => $info['faltantes'] ?? [
                'persona' => [],
                'direccion' => []
            ],
            'datosPersonales' => $info['paciente'] ?? null,
            'historiaPendiente' => $historiaPendiente,
            'seguimientoPendiente' => $citasSeg !== [],
            'citasRegistrarAsistencia' => $citasAsistencia,
            'citasSeguimientoPendiente' => $citasSeg,
            'clvHist' => $historial !== null
                ? (string) ($historial['ClvHist'] ?? '')
                : ''
        ];
    }

    /**
     * @param array<string, mixed> $cita
     */
    public function fechaHoraInicioCita(array $cita): ?DateTimeImmutable
    {
        $fecha = trim((string) ($cita['FechaCita'] ?? ''));
        $hora = trim((string) ($cita['HraInicioCita'] ?? ''));

        if ($fecha === '' || $hora === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            $hora .= ':00';
        }

        $zona = new DateTimeZone('America/Mexico_City');
        $inicio = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . $hora,
            $zona
        );

        return $inicio instanceof DateTimeImmutable ? $inicio : null;
    }

    public function urlPublica(string $rutaRelativa): string
    {
        return $rutaRelativa !== '' ? Helper::baseUrl($rutaRelativa) : '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarCitasParaRegistrarAsistencia(
        string $clvPsi,
        string $clvCons,
        string $ahoraSql,
        ?string $clvPac = null
    ): array {
        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.DuracionAplicadaMin,
                    c.EstadoCita,
                    c.ClvPac,
                    c.ClvPsi,
                    c.ClvCons,
                    CONCAT(
                        TRIM(per.NombrePer), ' ',
                        TRIM(per.ApPatPer), ' ',
                        TRIM(COALESCE(per.ApMatPer, ''))
                    ) AS NombrePaciente,
                    s.NombreServicio
                FROM cita c
                INNER JOIN paciente pac ON pac.ClvPac = c.ClvPac
                INNER JOIN persona per ON per.ClvPer = pac.ClvPer
                LEFT JOIN usuario u ON u.ClvUsu = pac.ClvUsu
                INNER JOIN servicios s ON s.ClvServ = c.ClvServ
                WHERE c.ClvPsi = :clvPsi
                  AND c.ClvCons = :clvCons
                  AND c.EstadoCita = 'PROGRAMADA'
                  AND c.FechaCita = DATE(:ahoraFecha)
                  AND TIMESTAMP(c.FechaCita, c.HraInicioCita) <= :ahora";

        $params = [
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons,
            'ahora' => $ahoraSql,
            'ahoraFecha' => $ahoraSql
        ];

        if ($clvPac !== null && $clvPac !== '') {
            $sql .= ' AND c.ClvPac = :clvPac';
            $params['clvPac'] = $clvPac;
        }

        $sql .= ' ORDER BY c.FechaCita ASC, c.HraInicioCita ASC LIMIT 30';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $ventana = new ResultadoCitaVentanaService();
        $resultado = [];

        foreach ($filas as $fila) {
            $ind = $ventana->evaluarIndicadores($fila);
            if (
                !empty($ind['puedeMarcarAsistida'])
                || !empty($ind['puedeMarcarInasistencia'])
            ) {
                $resultado[] = $fila;
            }
        }

        return $resultado;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarPacientesHistoriaPendiente(
        string $clvPsi,
        string $clvCons
    ): array {
        $sql = "SELECT DISTINCT
                    pac.ClvPac,
                    CONCAT(
                        TRIM(per.NombrePer), ' ',
                        TRIM(per.ApPatPer), ' ',
                        TRIM(COALESCE(per.ApMatPer, ''))
                    ) AS NombrePaciente,
                    MIN(c.FechaCita) AS FechaPrimeraAsistida
                FROM cita c
                INNER JOIN paciente pac
                    ON pac.ClvPac = c.ClvPac
                   AND pac.ClvCons = c.ClvCons
                INNER JOIN persona per ON per.ClvPer = pac.ClvPer
                LEFT JOIN usuario u ON u.ClvUsu = pac.ClvUsu
                WHERE c.ClvPsi = :clvPsi
                  AND c.ClvCons = :clvCons
                  AND c.EstadoCita = 'ASISTIDA'
                  AND NOT EXISTS (
                        SELECT 1
                        FROM historial_clinico h
                        WHERE h.ClvPac = c.ClvPac
                          AND h.ClvCons = c.ClvCons
                  )
                GROUP BY pac.ClvPac, per.NombrePer, per.ApPatPer, per.ApMatPer
                ORDER BY FechaPrimeraAsistida ASC
                LIMIT 40";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons
        ]);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $resultado = [];

        foreach ($filas as $fila) {
            $clvPac = (string) ($fila['ClvPac'] ?? '');
            $consentimiento = (new PrivacidadService())
                ->validarConsentimientoParaHistoria($clvPac);

            if (empty($consentimiento['ok'])) {
                continue;
            }

            $resultado[] = $fila;
        }

        return $resultado;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarCitasSeguimientoPendiente(
        string $clvPsi,
        string $clvCons
    ): array {
        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.ClvPac,
                    h.ClvHist,
                    CONCAT(
                        TRIM(per.NombrePer), ' ',
                        TRIM(per.ApPatPer), ' ',
                        TRIM(COALESCE(per.ApMatPer, ''))
                    ) AS NombrePaciente,
                    s.NombreServicio
                FROM cita c
                INNER JOIN historial_clinico h
                    ON h.ClvPac = c.ClvPac
                   AND h.ClvCons = c.ClvCons
                   AND h.ClvPsi = c.ClvPsi
                INNER JOIN paciente pac ON pac.ClvPac = c.ClvPac
                INNER JOIN persona per ON per.ClvPer = pac.ClvPer
                LEFT JOIN usuario u ON u.ClvUsu = pac.ClvUsu
                INNER JOIN servicios s ON s.ClvServ = c.ClvServ
                WHERE c.ClvPsi = :clvPsi
                  AND c.ClvCons = :clvCons
                  AND c.EstadoCita = 'ASISTIDA'
                  AND NOT EXISTS (
                        SELECT 1
                        FROM seguimiento_sesion ss
                        WHERE (ss.ClvCita COLLATE utf8mb4_unicode_ci) = c.ClvCita
                  )
                ORDER BY c.FechaCita DESC, c.HraInicioCita DESC
                LIMIT 40";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return ?array<string, mixed>
     */
    private function obtenerCitaPropia(
        string $clvCita,
        string $clvPsi,
        string $clvCons
    ): ?array {
        $stmt = $this->db->prepare(
            "SELECT ClvCita, FechaCita, HraInicioCita, EstadoCita,
                    ClvPac, ClvPsi, ClvCons
             FROM cita
             WHERE ClvCita = :clvCita
               AND ClvPsi = :clvPsi
               AND ClvCons = :clvCons
             LIMIT 1"
        );
        $stmt->execute([
            'clvCita' => $clvCita,
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
