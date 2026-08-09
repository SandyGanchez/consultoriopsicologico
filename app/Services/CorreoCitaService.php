<?php

namespace App\Services;

use App\Config\Config;
use App\Config\Database;
use App\Helpers\Helper;
use App\Models\CorreoCita;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Confirmaciones y recordatorios de cita por correo + campana (Fase 3D).
 * Idempotencia: UNIQUE correo_cita (ClvCita, TipoCorreo, RolDestinatario).
 * Campana RECORDATORIO solo en el primer claim del ledger (Intentos = 0).
 */
class CorreoCitaService
{
    public const MAX_INTENTOS = 3;
    public const MINUTOS_PROCESANDO_ABANDONADO = 15;

    public const MOTIVO_MENOS_24H = 'CITA_CREADA_CON_MENOS_DE_24H';
    public const MOTIVO_ACTIVACION = 'ACTIVACION_COMBINADA_PRIMERA_CITA';
    public const MOTIVO_CITA_NO_PROGRAMADA = 'CITA_YA_NO_PROGRAMADA';
    public const MOTIVO_TABLA_AUSENTE = 'TABLA_CORREO_CITA_AUSENTE';

    private PDO $db;
    private CorreoCita $model;
    private MailService $mailService;
    private RecordatorioCitaService $recordatorioService;

    public function __construct(
        ?PDO $db = null,
        ?CorreoCita $model = null,
        ?MailService $mailService = null,
        ?RecordatorioCitaService $recordatorioService = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->model = $model ?? new CorreoCita();
        $this->mailService = $mailService ?? new MailService();
        $this->recordatorioService = $recordatorioService
            ?? new RecordatorioCitaService();
    }

    public function persistenciaDisponible(): bool
    {
        return $this->model->tablaDisponible();
    }

    public function zona(): DateTimeZone
    {
        return new DateTimeZone('America/Mexico_City');
    }

    public function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->zona());
    }

    /**
     * Preparar filas dentro de la transacción abierta del alta de cita.
     *
     * @param array{
     *   omitirConfirmacionPaciente?: bool,
     *   motivoOmitirConfirmacionPaciente?: string
     * } $opciones
     */
    public function prepararParaCitaNueva(
        string $clvCita,
        array $opciones = []
    ): void {
        if (!$this->persistenciaDisponible()) {
            throw new RuntimeException(
                'El control de correos de cita no está disponible.'
            );
        }

        $contexto = $this->obtenerContextoCita($clvCita);
        if ($contexto === null) {
            throw new RuntimeException(
                'No fue posible resolver los destinatarios de la cita.'
            );
        }

        $clvUsuPac = trim((string) ($contexto['ClvUsuPaciente'] ?? ''));
        $clvUsuPsi = trim((string) ($contexto['ClvUsuPsicologo'] ?? ''));
        $clvUsuCreador = trim((string) ($contexto['ClvUsuCreador'] ?? ''));
        $origen = strtoupper(trim((string) ($contexto['OrigenCita'] ?? 'PACIENTE')));
        $correoResponsable = trim((string) ($contexto['CorreoResponsable'] ?? ''));
        $correoPaciente = trim((string) ($contexto['CorreoPaciente'] ?? ''));

        // Psicólogo sigue siendo obligatorio.
        if ($clvUsuPsi === '') {
            throw new RuntimeException(
                'No fue posible resolver el destinatario psicólogo de la cita.'
            );
        }

        $inicio = $this->fechaHoraInicio($contexto);
        if ($inicio === null) {
            throw new RuntimeException(
                'La fecha u hora de la cita no es válida.'
            );
        }

        $ahora = $this->ahora();
        $omitirPaciente = !empty($opciones['omitirConfirmacionPaciente']);

        // RESPONSABLE: NUNCA paciente.ClvUsu del dependiente.
        $esReservaResponsable = $origen === 'RESPONSABLE'
            || ($clvUsuPac === '' && $clvUsuCreador !== '');

        // PACIENTE (propia): destinatario = ClvUsuCreador (fallback legado: ClvUsu paciente).
        $destinoPaciente = $clvUsuCreador !== '' ? $clvUsuCreador : $clvUsuPac;

        if ($esReservaResponsable) {
            if ($clvUsuCreador === '' || $correoResponsable === '') {
                throw new RuntimeException(
                    'No fue posible resolver el correo del responsable de la reserva.'
                );
            }
            if (!$this->rolResponsableSoportado()) {
                throw new RuntimeException(
                    'correo_cita no admite RolDestinatario=RESPONSABLE. '
                    . 'Aplica la migración 20260809_cita_responsable en este entorno.'
                );
            }

            $this->model->insertarIdempotente([
                'ClvCita' => $clvCita,
                'ClvUsuDestino' => $clvUsuCreador,
                'TipoCorreo' => 'CONFIRMACION',
                'RolDestinatario' => 'RESPONSABLE',
                'FechaProgramada' => $ahora->format('Y-m-d H:i:s'),
                'EstadoCorreo' => 'PENDIENTE'
            ]);
        } elseif ($destinoPaciente !== '') {
            $correoDestinoPac = $clvUsuCreador !== ''
                ? ($correoResponsable !== '' ? $correoResponsable : $correoPaciente)
                : $correoPaciente;
            if ($correoDestinoPac === '' && !$omitirPaciente) {
                throw new RuntimeException(
                    'No fue posible resolver el correo del paciente de la cita.'
                );
            }

            if ($omitirPaciente) {
                $this->model->insertarIdempotente([
                    'ClvCita' => $clvCita,
                    'ClvUsuDestino' => $destinoPaciente,
                    'TipoCorreo' => 'CONFIRMACION',
                    'RolDestinatario' => 'PACIENTE',
                    'FechaProgramada' => $ahora->format('Y-m-d H:i:s'),
                    'EstadoCorreo' => 'OMITIDO',
                    'MotivoOmision' => (string) (
                        $opciones['motivoOmitirConfirmacionPaciente']
                        ?? self::MOTIVO_ACTIVACION
                    )
                ]);
            } else {
                $this->model->insertarIdempotente([
                    'ClvCita' => $clvCita,
                    'ClvUsuDestino' => $destinoPaciente,
                    'TipoCorreo' => 'CONFIRMACION',
                    'RolDestinatario' => 'PACIENTE',
                    'FechaProgramada' => $ahora->format('Y-m-d H:i:s'),
                    'EstadoCorreo' => 'PENDIENTE'
                ]);
            }
        }

        $this->model->insertarIdempotente([
            'ClvCita' => $clvCita,
            'ClvUsuDestino' => $clvUsuPsi,
            'TipoCorreo' => 'CONFIRMACION',
            'RolDestinatario' => 'PSICOLOGO',
            'FechaProgramada' => $ahora->format('Y-m-d H:i:s'),
            'EstadoCorreo' => 'PENDIENTE'
        ]);

        // Ventana: ahora >= inicio - horas y ahora < inicio → programar ya.
        // Citas creadas con poca anticipación también quedan PENDIENTE.
        $fechaProgRecordatorio = $this->recordatorioService
            ->fechaProgramadaRecordatorio($inicio, $ahora);

        $destinos = ['PSICOLOGO' => $clvUsuPsi];
        if ($esReservaResponsable && $clvUsuCreador !== '') {
            $destinos = ['RESPONSABLE' => $clvUsuCreador] + $destinos;
        } elseif ($destinoPaciente !== '') {
            $destinos = ['PACIENTE' => $destinoPaciente] + $destinos;
        }

        foreach ($destinos as $rol => $clvUsu) {
            if ($clvUsu === '') {
                continue;
            }
            if ($rol === 'RESPONSABLE' && !$this->rolResponsableSoportado()) {
                continue;
            }

            if ($fechaProgRecordatorio === null) {
                $this->model->insertarIdempotente([
                    'ClvCita' => $clvCita,
                    'ClvUsuDestino' => $clvUsu,
                    'TipoCorreo' => RecordatorioCitaService::TIPO_CORREO,
                    'RolDestinatario' => $rol,
                    'FechaProgramada' => $ahora->format('Y-m-d H:i:s'),
                    'EstadoCorreo' => 'OMITIDO',
                    'MotivoOmision' => RecordatorioCitaService::MOTIVO_CITA_INICIADA
                ]);
                continue;
            }

            $this->model->insertarIdempotente([
                'ClvCita' => $clvCita,
                'ClvUsuDestino' => $clvUsu,
                'TipoCorreo' => RecordatorioCitaService::TIPO_CORREO,
                'RolDestinatario' => $rol,
                'FechaProgramada' => $fechaProgRecordatorio->format('Y-m-d H:i:s'),
                'EstadoCorreo' => 'PENDIENTE'
            ]);
        }
    }

    /**
     * Post-COMMIT: intenta confirmaciones de una cita.
     *
     * @return array{paciente: bool, psicologo: bool, mensajeCorreo?: string}
     */
    public function procesarConfirmacionesInmediatas(string $clvCita): array
    {
        $resultado = [
            'paciente' => true,
            'psicologo' => true,
            'responsable' => true
        ];

        if (!$this->persistenciaDisponible()) {
            return $resultado;
        }

        $filas = $this->model->listarConfirmacionesPendientesPorCita($clvCita);

        foreach ($filas as $fila) {
            $ok = $this->procesarFilaPorId((int) ($fila['IdCorreoCita'] ?? 0));
            $rol = (string) ($fila['RolDestinatario'] ?? '');

            if ($rol === 'PACIENTE' && !$ok) {
                $resultado['paciente'] = false;
            }

            if ($rol === 'RESPONSABLE' && !$ok) {
                $resultado['responsable'] = false;
            }

            if ($rol === 'PSICOLOGO' && !$ok) {
                $resultado['psicologo'] = false;
            }
        }

        if (!$resultado['paciente'] || !$resultado['responsable']) {
            $resultado['mensajeCorreo'] =
                'No fue posible enviar la confirmación por correo en este momento.';
        }

        return $resultado;
    }

    /**
     * Lote para CLI.
     *
     * @return array{
     *   recuperados: int,
     *   reactivados: int,
     *   procesados: int,
     *   enviados: int,
     *   fallidos: int,
     *   omitidos: int
     * }
     */
    public function procesarLote(int $limite = 40): array
    {
        $resumen = [
            'recuperados' => 0,
            'reactivados' => 0,
            'procesados' => 0,
            'enviados' => 0,
            'fallidos' => 0,
            'omitidos' => 0
        ];

        if (!$this->persistenciaDisponible()) {
            return $resumen;
        }

        $resumen['recuperados'] = $this->model->recuperarProcesandoAbandonados(
            self::MINUTOS_PROCESANDO_ABANDONADO
        );

        // Compatibilidad: filas antiguas OMITIDO por <24h vuelven a cola.
        $resumen['reactivados'] = $this->model
            ->reactivarRecordatoriosOmitidosMenosHoras(
                self::MOTIVO_MENOS_24H,
                $this->recordatorioService->horasRecordatorio()
            );

        $filas = $this->model->listarPendientesProcesables(
            $limite,
            self::MAX_INTENTOS
        );

        foreach ($filas as $fila) {
            $id = (int) ($fila['IdCorreoCita'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $resumen['procesados']++;
            $estadoAntes = (string) ($fila['EstadoCorreo'] ?? '');
            $ok = $this->procesarFilaPorId($id);

            $stmt = $this->db->prepare(
                'SELECT EstadoCorreo FROM correo_cita WHERE IdCorreoCita = :id LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $estado = (string) ($stmt->fetchColumn() ?: '');

            if ($estado === 'ENVIADO') {
                $resumen['enviados']++;
            } elseif ($estado === 'OMITIDO') {
                $resumen['omitidos']++;
            } elseif ($estado === 'FALLIDO' || !$ok) {
                $resumen['fallidos']++;
            } elseif ($estadoAntes === 'PENDIENTE' && $estado === '') {
                $resumen['fallidos']++;
            }
        }

        return $resumen;
    }

    public function procesarFilaPorId(int $id): bool
    {
        if ($id <= 0 || !$this->persistenciaDisponible()) {
            return false;
        }

        $propia = !$this->db->inTransaction();
        $fila = null;
        $tipo = '';
        $clvCita = '';
        $contexto = null;

        try {
            if ($propia) {
                $this->db->beginTransaction();
            }

            $fila = $this->model->obtenerPorIdParaUpdate($id);
            if ($fila === null) {
                if ($propia) {
                    $this->db->rollBack();
                }
                return false;
            }

            $estado = (string) ($fila['EstadoCorreo'] ?? '');
            $intentos = (int) ($fila['Intentos'] ?? 0);

            if (!in_array($estado, ['PENDIENTE', 'FALLIDO'], true)) {
                if ($propia) {
                    $this->db->commit();
                }
                return $estado === 'ENVIADO';
            }

            if ($intentos >= self::MAX_INTENTOS && $estado === 'FALLIDO') {
                if ($propia) {
                    $this->db->commit();
                }
                return false;
            }

            $tipo = (string) ($fila['TipoCorreo'] ?? '');
            $clvCita = (string) ($fila['ClvCita'] ?? '');
            $intentosAntes = $intentos;

            if ($tipo === RecordatorioCitaService::TIPO_CORREO) {
                $estadoCita = $this->obtenerEstadoCita($clvCita);
                if ($estadoCita !== 'PROGRAMADA') {
                    $this->model->marcarOmitido(
                        $id,
                        self::MOTIVO_CITA_NO_PROGRAMADA . '_' . ($estadoCita ?: 'DESCONOCIDO')
                    );
                    if ($propia) {
                        $this->db->commit();
                    }
                    return true;
                }

                $contexto = $this->obtenerContextoCita($clvCita);
                if ($contexto === null) {
                    if ($propia) {
                        $this->db->rollBack();
                    }
                    return false;
                }

                $inicio = $this->fechaHoraInicio($contexto);
                if ($inicio === null || $this->ahora() >= $inicio) {
                    $this->model->marcarOmitido(
                        $id,
                        RecordatorioCitaService::MOTIVO_CITA_INICIADA
                    );
                    if ($propia) {
                        $this->db->commit();
                    }
                    return true;
                }

                // Ventana: ahora >= inicio - horas AND ahora < inicio.
                if (
                    !$this->recordatorioService->estaEnVentanaRecordatorio(
                        $inicio,
                        $this->ahora()
                    )
                ) {
                    if ($propia) {
                        $this->db->commit();
                    }
                    return false;
                }
            }

            if (!$this->model->marcarProcesando($id)) {
                if ($propia) {
                    $this->db->rollBack();
                }
                return false;
            }

            // Campana: solo primer claim (Intentos bloqueado antes del +1).
            // Falla → excepción → ROLLBACK (sin claim parcial).
            if (
                $tipo === RecordatorioCitaService::TIPO_CORREO
                && $intentosAntes === 0
            ) {
                if (
                    !is_array($contexto)
                    || !$this->recordatorioService->crearNotificacionCampana(
                        $fila,
                        $contexto
                    )
                ) {
                    throw new RuntimeException(
                        'No fue posible crear la notificación de recordatorio.'
                    );
                }
            }

            if ($propia) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }

        // Fuera de transacción: SMTP (nunca dentro del BEGIN anterior).
        try {
            $contexto = $contexto ?? $this->obtenerContextoCita($clvCita);
            if ($contexto === null) {
                throw new RuntimeException('Contexto de cita no disponible.');
            }

            $rol = (string) ($fila['RolDestinatario'] ?? '');
            $this->enviarSegunTipo($tipo, $rol, $contexto);

            $this->model->marcarEnviado($id);
            return true;
        } catch (Throwable $e) {
            $resumen = $this->resumirError($e);
            $this->model->marcarFallido($id, $resumen);
            return false;
        }
    }

    private function enviarSegunTipo(
        string $tipo,
        string $rol,
        array $contexto
    ): void {
        if ($this->esDryRun()) {
            return;
        }

        if ($tipo === 'CONFIRMACION' && $rol === 'PACIENTE') {
            $this->mailService->enviarConfirmacionCitaPaciente($contexto);
            return;
        }

        if ($tipo === 'CONFIRMACION' && $rol === 'RESPONSABLE') {
            $this->mailService->enviarConfirmacionCitaResponsable($contexto);
            return;
        }

        if ($tipo === 'CONFIRMACION' && $rol === 'PSICOLOGO') {
            $this->mailService->enviarConfirmacionCitaPsicologo($contexto);
            return;
        }

        if ($tipo === 'RECORDATORIO_24H' && $rol === 'PACIENTE') {
            $this->mailService->enviarRecordatorioCitaPaciente($contexto);
            return;
        }

        if ($tipo === 'RECORDATORIO_24H' && $rol === 'RESPONSABLE') {
            $this->mailService->enviarRecordatorioCitaResponsable($contexto);
            return;
        }

        if ($tipo === 'RECORDATORIO_24H' && $rol === 'PSICOLOGO') {
            $this->mailService->enviarRecordatorioCitaPsicologo($contexto);
            return;
        }

        throw new RuntimeException('Tipo o rol de correo no soportado.');
    }

    private function esDryRun(): bool
    {
        $flag = strtolower(trim((string) Config::get('MAIL_CITA_DRY_RUN', '')));

        return in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    private function resumirError(Throwable $e): string
    {
        $msg = trim($e->getMessage());
        $msg = preg_replace('/password[^\s]*/i', '[oculto]', $msg) ?? $msg;
        $msg = preg_replace('/\bSQLSTATE\b[^\s]*/i', 'ERROR_BD', $msg) ?? $msg;

        if (
            str_contains(strtolower($msg), 'smtp')
            || str_contains(strtolower($msg), 'mail')
        ) {
            return 'ENVIO_CORREO_FALLIDO';
        }

        return mb_substr($msg !== '' ? $msg : 'ERROR_DESCONOCIDO', 0, 255);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function obtenerContextoCita(string $clvCita): ?array
    {
        $metaCita = $this->columnasCitaResponsableDisponibles();

        $extraSelect = '';
        $extraJoin = '';
        if ($metaCita) {
            $extraSelect = ",
                    c.ClvUsuCreador,
                    c.OrigenCita,
                    c.IdRelacionResponsable,
                    creadorUsu.CorreoUsu AS CorreoResponsable,
                    creadorUsu.CorreoUsu AS CorreoCreador,
                    CONCAT(
                        TRIM(creadorPer.NombrePer), ' ',
                        TRIM(creadorPer.ApPatPer), ' ',
                        TRIM(COALESCE(creadorPer.ApMatPer, ''))
                    ) AS NombreResponsable,
                    TRIM(rel.Parentesco) AS Parentesco";
            $extraJoin = "
                LEFT JOIN usuario creadorUsu
                    ON creadorUsu.ClvUsu = c.ClvUsuCreador
                LEFT JOIN persona creadorPer
                    ON creadorPer.ClvPer = creadorUsu.ClvPer
                LEFT JOIN paciente_responsable rel
                    ON rel.IdRelacion = c.IdRelacionResponsable";
        }

        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.DuracionAplicadaMin,
                    c.CostoAplicado,
                    c.EstadoCita,
                    c.ClvPac,
                    c.ClvPsi,
                    c.ClvCons,
                    c.ClvServ,
                    pacUsu.ClvUsu AS ClvUsuPaciente,
                    pacUsu.CorreoUsu AS CorreoPaciente,
                    CONCAT(
                        TRIM(pacPer.NombrePer), ' ',
                        TRIM(pacPer.ApPatPer), ' ',
                        TRIM(COALESCE(pacPer.ApMatPer, ''))
                    ) AS NombrePaciente,
                    psiUsu.ClvUsu AS ClvUsuPsicologo,
                    psiUsu.CorreoUsu AS CorreoPsicologo,
                    CONCAT(
                        TRIM(psiPer.NombrePer), ' ',
                        TRIM(psiPer.ApPatPer), ' ',
                        TRIM(COALESCE(psiPer.ApMatPer, ''))
                    ) AS NombrePsicologo,
                    cons.NombreCons,
                    cons.TelefonoCons,
                    cons.CorreoElectronico AS CorreoConsultorio,
                    cons.LogotipoCons,
                    cons.LimiteCancHoras,
                    d.PaisDir,
                    d.EstadoDir,
                    d.MunicipioDir,
                    d.ColoniaDir,
                    d.CalleDir,
                    d.CodPostDir,
                    d.NumExtDir,
                    d.NumIntDir,
                    d.LatitudDir,
                    d.LongitudDir,
                    s.NombreServicio
                    {$extraSelect}
                FROM cita c
                INNER JOIN paciente pac ON pac.ClvPac = c.ClvPac
                INNER JOIN persona pacPer ON pacPer.ClvPer = pac.ClvPer
                LEFT JOIN usuario pacUsu ON pacUsu.ClvUsu = pac.ClvUsu
                INNER JOIN psicologo psi ON psi.ClvPsi = c.ClvPsi
                INNER JOIN usuario psiUsu ON psiUsu.ClvUsu = psi.ClvUsu
                INNER JOIN persona psiPer ON psiPer.ClvPer = psiUsu.ClvPer
                INNER JOIN consultorio cons ON cons.ClvCons = c.ClvCons
                LEFT JOIN direccion d ON d.ClvDir = cons.ClvDir
                INNER JOIN servicios s ON s.ClvServ = c.ClvServ
                {$extraJoin}
                WHERE c.ClvCita = :clvCita
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCita' => $clvCita]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['NombrePaciente'] = trim(preg_replace(
            '/\s+/',
            ' ',
            (string) ($row['NombrePaciente'] ?? '')
        ) ?? '');
        $row['NombrePsicologo'] = trim(preg_replace(
            '/\s+/',
            ' ',
            (string) ($row['NombrePsicologo'] ?? '')
        ) ?? '');
        if (isset($row['NombreResponsable'])) {
            $row['NombreResponsable'] = trim(preg_replace(
                '/\s+/',
                ' ',
                (string) $row['NombreResponsable']
            ) ?? '');
        }

        $row['UrlLogin'] = Helper::baseUrl('login');
        $row['UrlAgendaPsicologo'] = Helper::baseUrl('psicologo/agenda');
        $row['UrlMisCitas'] = Helper::baseUrl('paciente/mis-citas');
        $row['DireccionCompleta'] = $this->formatearDireccion($row);
        $row['TextoCancelacion'] = Helper::textoPoliticaCancelacionPublica(
            (int) ($row['LimiteCancHoras'] ?? 0)
        );
        $row['UrlLogoConsultorio'] = Helper::logotipoConsultorioUrl(
            (string) ($row['LogotipoCons'] ?? ''),
            false
        );
        $row['FechaCitaLarga'] = $this->formatearFechaLargaEs($row);
        $row['HoraInicioFmt'] = substr((string) ($row['HraInicioCita'] ?? ''), 0, 5);
        $row['HoraFinFmt'] = substr((string) ($row['HraFinCita'] ?? ''), 0, 5);
        $row['UrlGoogleCalendar'] = $this->construirUrlGoogleCalendar($row);
        $row['UrlComoLlegar'] = $this->construirUrlComoLlegar($row);
        $row['EsReservaDependiente'] = strtoupper(
            trim((string) ($row['OrigenCita'] ?? 'PACIENTE'))
        ) === 'RESPONSABLE';

        return $row;
    }

    /**
     * @param array<string, mixed> $contexto
     */
    private function formatearFechaLargaEs(array $contexto): string
    {
        $fecha = trim((string) ($contexto['FechaCita'] ?? ''));
        if ($fecha === '') {
            return '';
        }

        $dt = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $fecha,
            $this->zona()
        );
        if (!$dt) {
            return $fecha;
        }

        $dias = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
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
            12 => 'diciembre',
        ];

        $diaSemana = $dias[(int) $dt->format('N')] ?? '';
        $dia = (int) $dt->format('j');
        $mes = $meses[(int) $dt->format('n')] ?? '';
        $anio = $dt->format('Y');

        return "{$diaSemana}, {$dia} de {$mes} de {$anio}";
    }

    /**
     * @param array<string, mixed> $contexto
     */
    private function construirUrlGoogleCalendar(array $contexto): string
    {
        $inicio = $this->fechaHoraInicio($contexto);
        if ($inicio === null) {
            return '';
        }

        $horaFin = trim((string) ($contexto['HraFinCita'] ?? ''));
        if (preg_match('/^\d{2}:\d{2}$/', $horaFin)) {
            $horaFin .= ':00';
        }
        $fin = null;
        if ($horaFin !== '') {
            $fin = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                trim((string) $contexto['FechaCita']) . ' ' . $horaFin,
                $this->zona()
            );
        }
        if (!$fin instanceof DateTimeImmutable) {
            $mins = max(15, (int) ($contexto['DuracionAplicadaMin'] ?? 60));
            $fin = $inicio->modify('+' . $mins . ' minutes');
        }

        $utc = new DateTimeZone('UTC');
        $dates = $inicio->setTimezone($utc)->format('Ymd\THis\Z')
            . '/'
            . $fin->setTimezone($utc)->format('Ymd\THis\Z');

        $titulo = 'Cita: '
            . trim((string) ($contexto['NombreServicio'] ?? 'Sesión'))
            . ' — '
            . trim((string) ($contexto['NombrePsicologo'] ?? ''));

        $detalles = 'Paciente: '
            . trim((string) ($contexto['NombrePaciente'] ?? '')) . "\n"
            . 'Especialista: '
            . trim((string) ($contexto['NombrePsicologo'] ?? '')) . "\n"
            . 'Consultorio: '
            . trim((string) ($contexto['NombreCons'] ?? ''));

        $params = [
            'action' => 'TEMPLATE',
            'text' => $titulo,
            'dates' => $dates,
            'details' => $detalles,
            'location' => trim((string) ($contexto['DireccionCompleta'] ?? '')),
        ];

        return 'https://calendar.google.com/calendar/render?'
            . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, mixed> $contexto
     */
    private function construirUrlComoLlegar(array $contexto): string
    {
        $lat = trim((string) ($contexto['LatitudDir'] ?? ''));
        $lng = trim((string) ($contexto['LongitudDir'] ?? ''));

        if (
            $lat !== ''
            && $lng !== ''
            && is_numeric($lat)
            && is_numeric($lng)
        ) {
            return 'https://www.google.com/maps/search/?api=1&query='
                . rawurlencode($lat . ',' . $lng);
        }

        $dir = trim((string) ($contexto['DireccionCompleta'] ?? ''));
        if ($dir === '') {
            $dir = trim((string) ($contexto['NombreCons'] ?? ''));
        }
        if ($dir === '') {
            return '';
        }

        return 'https://www.google.com/maps/search/?api=1&query='
            . rawurlencode($dir);
    }

    private function columnasCitaResponsableDisponibles(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $schema = (string) $this->db->query('SELECT DATABASE()')->fetchColumn();
        if ($schema === '') {
            $cache = false;
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = 'cita'
               AND COLUMN_NAME IN (
                   'ClvUsuCreador', 'OrigenCita', 'IdRelacionResponsable'
               )"
        );
        $stmt->execute(['schema' => $schema]);
        $cache = (int) $stmt->fetchColumn() === 3;

        return $cache;
    }

    private function rolResponsableSoportado(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $stmt = $this->db->query(
                "SHOW COLUMNS FROM correo_cita LIKE 'RolDestinatario'"
            );
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            $type = (string) ($col['Type'] ?? '');
            $cache = stripos($type, "RESPONSABLE") !== false;
        } catch (Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    /**
     * @param array<string, mixed> $contexto
     */
    private function fechaHoraInicio(array $contexto): ?DateTimeImmutable
    {
        $fecha = trim((string) ($contexto['FechaCita'] ?? ''));
        $hora = trim((string) ($contexto['HraInicioCita'] ?? ''));

        if ($fecha === '' || $hora === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            $hora .= ':00';
        }

        $inicio = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . $hora,
            $this->zona()
        );

        return $inicio instanceof DateTimeImmutable ? $inicio : null;
    }

    private function obtenerEstadoCita(string $clvCita): string
    {
        $stmt = $this->db->prepare(
            'SELECT EstadoCita FROM cita WHERE ClvCita = :c LIMIT 1'
        );
        $stmt->execute(['c' => $clvCita]);

        return strtoupper(trim((string) ($stmt->fetchColumn() ?: '')));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatearDireccion(array $row): string
    {
        $partes = array_filter([
            trim((string) ($row['CalleDir'] ?? '')),
            trim((string) ($row['NumExtDir'] ?? '')) !== ''
                ? 'Ext. ' . trim((string) $row['NumExtDir'])
                : '',
            trim((string) ($row['NumIntDir'] ?? '')) !== ''
                ? 'Int. ' . trim((string) $row['NumIntDir'])
                : '',
            trim((string) ($row['ColoniaDir'] ?? '')),
            trim((string) ($row['MunicipioDir'] ?? '')),
            trim((string) ($row['EstadoDir'] ?? '')),
            trim((string) ($row['CodPostDir'] ?? '')) !== ''
                ? 'C.P. ' . trim((string) $row['CodPostDir'])
                : '',
            trim((string) ($row['PaisDir'] ?? ''))
        ], static fn(string $v): bool => $v !== '');

        return implode(', ', $partes);
    }
}
