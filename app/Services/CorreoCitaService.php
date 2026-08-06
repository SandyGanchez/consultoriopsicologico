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

        $clvUsuPac = (string) ($contexto['ClvUsuPaciente'] ?? '');
        $clvUsuPsi = (string) ($contexto['ClvUsuPsicologo'] ?? '');

        if ($clvUsuPac === '' || $clvUsuPsi === '') {
            throw new RuntimeException(
                'No fue posible resolver los destinatarios de la cita.'
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

        if ($omitirPaciente) {
            $this->model->insertarIdempotente([
                'ClvCita' => $clvCita,
                'ClvUsuDestino' => $clvUsuPac,
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
                'ClvUsuDestino' => $clvUsuPac,
                'TipoCorreo' => 'CONFIRMACION',
                'RolDestinatario' => 'PACIENTE',
                'FechaProgramada' => $ahora->format('Y-m-d H:i:s'),
                'EstadoCorreo' => 'PENDIENTE'
            ]);
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

        foreach (['PACIENTE' => $clvUsuPac, 'PSICOLOGO' => $clvUsuPsi] as $rol => $clvUsu) {
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
            'psicologo' => true
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

            if ($rol === 'PSICOLOGO' && !$ok) {
                $resultado['psicologo'] = false;
            }
        }

        if (!$resultado['paciente']) {
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

        if ($tipo === 'CONFIRMACION' && $rol === 'PSICOLOGO') {
            $this->mailService->enviarConfirmacionCitaPsicologo($contexto);
            return;
        }

        if ($tipo === 'RECORDATORIO_24H' && $rol === 'PACIENTE') {
            $this->mailService->enviarRecordatorioCitaPaciente($contexto);
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
                    cons.LimiteCancHoras,
                    d.PaisDir,
                    d.EstadoDir,
                    d.MunicipioDir,
                    d.ColoniaDir,
                    d.CalleDir,
                    d.CodPostDir,
                    d.NumExtDir,
                    d.NumIntDir,
                    s.NombreServicio
                FROM cita c
                INNER JOIN paciente pac ON pac.ClvPac = c.ClvPac
                INNER JOIN usuario pacUsu ON pacUsu.ClvUsu = pac.ClvUsu
                INNER JOIN persona pacPer ON pacPer.ClvPer = pacUsu.ClvPer
                INNER JOIN psicologo psi ON psi.ClvPsi = c.ClvPsi
                INNER JOIN usuario psiUsu ON psiUsu.ClvUsu = psi.ClvUsu
                INNER JOIN persona psiPer ON psiPer.ClvPer = psiUsu.ClvPer
                INNER JOIN consultorio cons ON cons.ClvCons = c.ClvCons
                LEFT JOIN direccion d ON d.ClvDir = cons.ClvDir
                INNER JOIN servicios s ON s.ClvServ = c.ClvServ
                WHERE c.ClvCita = :clvCita
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCita' => $clvCita]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['UrlLogin'] = Helper::baseUrl('login');
        $row['UrlAgendaPsicologo'] = Helper::baseUrl('psicologo/agenda');
        $row['UrlMisCitas'] = Helper::baseUrl('paciente/mis-citas');
        $row['DireccionCompleta'] = $this->formatearDireccion($row);
        $row['TextoCancelacion'] = Helper::textoPoliticaCancelacionPublica(
            (int) ($row['LimiteCancHoras'] ?? 0)
        );

        return $row;
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
