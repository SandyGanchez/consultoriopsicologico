<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\HistorialClinico;
use App\Models\Paciente;
use App\Models\SeguimientoSesion;
use DateTimeImmutable;

class ExpedienteClinicoService
{
    private Paciente $pacienteModel;

    private Cita $citaModel;

    private HistorialClinico $historialModel;

    private SeguimientoSesion $seguimientoModel;

    public function __construct()
    {
        $this->pacienteModel = new Paciente();
        $this->citaModel = new Cita();
        $this->historialModel = new HistorialClinico();
        $this->seguimientoModel = new SeguimientoSesion();
    }

    /**
     * Autorización clínica:
     * - Si ya existe historial en el consultorio, solo ClvPsi del historial.
     * - Si aún no existe, puede abrirse para crear con relación por citas.
     *
     * @return array{
     *   ok: bool,
     *   mensaje?: string,
     *   paciente?: array,
     *   historial?: ?array,
     *   completo?: ?array,
     *   citaHabilitadora?: ?array,
     *   puedeCrear?: bool,
     *   puedeEditar?: bool,
     *   puedeRegistrarSeguimiento?: bool,
     *   seguimientos?: array,
     *   citasPendientesSeguimiento?: array,
     *   totalSeguimientos?: int
     * }
     */
    public function obtenerExpediente(
        string $clvPac,
        string $clvPsi,
        string $clvCons
    ): array {
        $historial = $this->historialModel->obtenerPorPacienteConsultorio(
            $clvPac,
            $clvCons
        );

        if ($historial !== null) {
            if (
                (string) ($historial['ClvPsi'] ?? '') !== $clvPsi ||
                (string) ($historial['ClvCons'] ?? '') !== $clvCons
            ) {
                return [
                    'ok' => false,
                    'mensaje' =>
                        'Este expediente clínico se encuentra asignado a otro especialista.'
                ];
            }
        } elseif (
            !$this->pacienteModel->perteneceAPsicologo($clvPac, $clvPsi)
        ) {
            return [
                'ok' => false,
                'mensaje' =>
                    'No tienes autorización para consultar este paciente.'
            ];
        }

        $paciente = $this->pacienteModel->obtenerParaPsicologo(
            $clvPac,
            $clvPsi
        );

        if (!$paciente) {
            return [
                'ok' => false,
                'mensaje' =>
                    'No tienes autorización para consultar este paciente.'
            ];
        }

        $completo = null;
        $puedeEditar = false;
        $seguimientos = [];
        $citasPendientes = [];
        $puedeRegistrarSeguimiento = false;

        if ($historial) {
            $completo = $this->historialModel->obtenerCompleto(
                (string) $historial['ClvHist']
            );
            $puedeEditar = true;
            $seguimientos = $this->seguimientoModel->listarPorHistorial(
                (string) $historial['ClvHist']
            );
            $citasPendientes =
                $this->seguimientoModel->listarCitasAsistidasPendientes(
                    $clvPac,
                    $clvPsi,
                    $clvCons
                );
            $puedeRegistrarSeguimiento = $citasPendientes !== [];
        }

        $citaHabilitadora = $historial === null
            ? $this->citaModel->obtenerPrimeraAsistida(
                $clvPac,
                $clvPsi,
                $clvCons
            )
            : null;

        $puedeCrear = $historial === null && $citaHabilitadora !== null;

        return [
            'ok' => true,
            'paciente' => $paciente,
            'historial' => $historial,
            'completo' => $completo,
            'citaHabilitadora' => $citaHabilitadora,
            'puedeCrear' => $puedeCrear,
            'puedeEditar' => $puedeEditar,
            'puedeRegistrarSeguimiento' => $puedeRegistrarSeguimiento,
            'seguimientos' => $seguimientos,
            'citasPendientesSeguimiento' => $citasPendientes,
            'totalSeguimientos' => count($seguimientos)
        ];
    }

    /**
     * @return array{ok: bool, mensaje?: string, paciente?: array, citaHabilitadora?: array}
     */
    public function prepararCreacion(
        string $clvPac,
        string $clvPsi,
        string $clvCons
    ): array {
        $expediente = $this->obtenerExpediente(
            $clvPac,
            $clvPsi,
            $clvCons
        );

        if (!$expediente['ok']) {
            return $expediente;
        }

        if (!empty($expediente['historial'])) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Ya existe una historia clínica inicial para este paciente.'
            ];
        }

        if (empty($expediente['citaHabilitadora'])) {
            return [
                'ok' => false,
                'mensaje' =>
                    'El historial clínico inicial se habilitará después de registrar la primera cita como asistida.'
            ];
        }

        return [
            'ok' => true,
            'paciente' => $expediente['paciente'],
            'citaHabilitadora' => $expediente['citaHabilitadora']
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, mensaje: string, clvHist?: string, clvPac?: string}
     */
    public function guardarHistoriaInicial(
        string $clvPsi,
        string $clvCons,
        array $post
    ): array {
        $clvPac = trim((string) ($post['ClvPac'] ?? ''));
        $clvCita = trim((string) ($post['ClvCita'] ?? ''));

        if ($clvPac === '' || $clvCita === '') {
            return [
                'ok' => false,
                'mensaje' => 'Faltan datos para guardar la historia clínica.'
            ];
        }

        if (
            !$this->pacienteModel->perteneceAPsicologo(
                $clvPac,
                $clvPsi
            )
        ) {
            return [
                'ok' => false,
                'mensaje' =>
                    'No tienes autorización para registrar este expediente.'
            ];
        }

        $cita = $this->citaModel->obtenerAsistidaParaHistoria(
            $clvCita,
            $clvPac,
            $clvPsi,
            $clvCons
        );

        if (!$cita) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Solo una cita asistida válida puede habilitar la historia clínica inicial.'
            ];
        }

        if (
            $this->historialModel->existePorPacienteConsultorio(
                $clvPac,
                $clvCons
            )
        ) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Ya existe una historia clínica inicial para este paciente en el consultorio.'
            ];
        }

        $payload = $this->normalizarPayload($post);
        $payload['ClvPac'] = $clvPac;
        $payload['ClvPsi'] = $clvPsi;
        $payload['ClvCons'] = $clvCons;

        if (
            empty($payload['FechaEntrevistaInicial']) &&
            !empty($cita['FechaCita'])
        ) {
            $payload['FechaEntrevistaInicial'] =
                (string) $cita['FechaCita'] . ' 00:00:00';
        }

        $validacion = $this->validarPayloadClinico($payload);

        if (!$validacion['ok']) {
            return $validacion;
        }

        $resultado = $this->historialModel->crearCompleto($payload);

        if ($resultado['ok']) {
            $resultado['clvPac'] = $clvPac;
        }

        return $resultado;
    }

    /**
     * @return array{ok: bool, mensaje?: string, paciente?: array, completo?: array, historial?: array}
     */
    public function prepararEdicion(
        string $clvHist,
        string $clvPsi,
        string $clvCons
    ): array {
        $historial = $this->historialModel->obtenerPorClave($clvHist);

        if (!$historial) {
            return [
                'ok' => false,
                'mensaje' => 'No se encontró la historia clínica.'
            ];
        }

        if ((string) ($historial['ClvCons'] ?? '') !== $clvCons) {
            return [
                'ok' => false,
                'mensaje' =>
                    'No tienes autorización para editar este expediente.'
            ];
        }

        if ((string) ($historial['ClvPsi'] ?? '') !== $clvPsi) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Este expediente clínico se encuentra asignado a otro especialista.'
            ];
        }

        $clvPac = (string) ($historial['ClvPac'] ?? '');

        $paciente = $this->pacienteModel->obtenerParaPsicologo(
            $clvPac,
            $clvPsi
        );

        $completo = $this->historialModel->obtenerCompleto($clvHist);

        return [
            'ok' => true,
            'paciente' => $paciente ?? [],
            'historial' => $historial,
            'completo' => $completo
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, mensaje: string, clvPac?: string}
     */
    public function actualizarHistoriaInicial(
        string $clvPsi,
        string $clvCons,
        array $post
    ): array {
        $clvHist = trim((string) ($post['ClvHist'] ?? ''));

        if ($clvHist === '') {
            return [
                'ok' => false,
                'mensaje' => 'No se identificó la historia clínica.'
            ];
        }

        $preparacion = $this->prepararEdicion(
            $clvHist,
            $clvPsi,
            $clvCons
        );

        if (!$preparacion['ok']) {
            return [
                'ok' => false,
                'mensaje' => (string) ($preparacion['mensaje'] ??
                    'No autorizado.')
            ];
        }

        $payload = $this->normalizarPayload($post);
        $payload['ClvPsi'] = $clvPsi;

        $validacion = $this->validarPayloadClinico($payload);

        if (!$validacion['ok']) {
            return $validacion;
        }

        $resultado = $this->historialModel->actualizarCompleto(
            $clvHist,
            $payload
        );

        if ($resultado['ok']) {
            $resultado['clvPac'] = (string) (
                $preparacion['historial']['ClvPac'] ?? ''
            );
        }

        return $resultado;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $post): array
    {
        $fecha = trim((string) ($post['FechaEntrevistaInicial'] ?? ''));

        if ($fecha !== '') {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha)
                ?: DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i:s',
                    $fecha
                );

            if ($dt) {
                $fecha = $dt->format('Y-m-d H:i:s');
            } else {
                $fecha = '';
            }
        }

        return [
            'FechaEntrevistaInicial' => $fecha,
            'estado' => is_array($post['estado'] ?? null)
                ? $post['estado']
                : [],
            'antecedentes_patologicos' => array_values(
                array_filter(
                    is_array($post['antecedentes_patologicos'] ?? null)
                        ? $post['antecedentes_patologicos']
                        : [],
                    'is_array'
                )
            ),
            'antecedentes_familiares' => array_values(
                array_filter(
                    is_array($post['antecedentes_familiares'] ?? null)
                        ? $post['antecedentes_familiares']
                        : [],
                    'is_array'
                )
            ),
            'psicoanamnesis' => is_array($post['psicoanamnesis'] ?? null)
                ? $post['psicoanamnesis']
                : [],
            'actitud' => is_array($post['actitud'] ?? null)
                ? $post['actitud']
                : [],
            'vida_social' => is_array($post['vida_social'] ?? null)
                ? $post['vida_social']
                : [],
            'adicciones' => array_values(
                array_filter(
                    is_array($post['adicciones'] ?? null)
                        ? $post['adicciones']
                        : [],
                    'is_array'
                )
            ),
            'examen_mental' => is_array($post['examen_mental'] ?? null)
                ? $post['examen_mental']
                : [],
            'reactivos' => array_values(
                array_filter(
                    is_array($post['reactivos'] ?? null)
                        ? $post['reactivos']
                        : [],
                    'is_array'
                )
            ),
            'apreciacion' => is_array($post['apreciacion'] ?? null)
                ? $post['apreciacion']
                : []
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, mensaje: string}
     */
    private function validarPayloadClinico(array $payload): array
    {
        $motivo = trim(
            (string) (($payload['estado']['MotivoConsulta'] ?? ''))
        );

        if ($motivo === '') {
            return [
                'ok' => false,
                'mensaje' => 'El motivo de consulta es obligatorio.'
            ];
        }

        if (mb_strlen($motivo) > 65000) {
            return [
                'ok' => false,
                'mensaje' =>
                    'El motivo de consulta excede la longitud permitida.'
            ];
        }

        $tiposPat = [];
        foreach ($payload['antecedentes_patologicos'] as $fila) {
            $tipo = trim((string) ($fila['TipoAntecedente'] ?? ''));
            if ($tipo === '') {
                continue;
            }
            if (isset($tiposPat[$tipo])) {
                return [
                    'ok' => false,
                    'mensaje' =>
                        'No puedes repetir el mismo tipo de antecedente patológico.'
                ];
            }
            $tiposPat[$tipo] = true;
        }

        $tiposFam = [];
        foreach ($payload['antecedentes_familiares'] as $fila) {
            $tipo = trim((string) ($fila['TipoAntecedenteFam'] ?? ''));
            if ($tipo === '') {
                continue;
            }
            if (isset($tiposFam[$tipo])) {
                return [
                    'ok' => false,
                    'mensaje' =>
                        'No puedes repetir el mismo tipo de antecedente familiar.'
                ];
            }
            $tiposFam[$tipo] = true;
        }

        foreach ($payload['reactivos'] as $fila) {
            $nombre = trim((string) ($fila['NombreReactivo'] ?? ''));
            $fecha = trim((string) ($fila['FechaAplicacion'] ?? ''));

            if ($nombre === '') {
                continue;
            }

            if ($fecha === '') {
                return [
                    'ok' => false,
                    'mensaje' =>
                        'Cada reactivo debe incluir fecha de aplicación.'
                ];
            }

            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);

            if (!$dt || $dt->format('Y-m-d') !== $fecha) {
                return [
                    'ok' => false,
                    'mensaje' =>
                        'La fecha de aplicación de un reactivo no es válida.'
                ];
            }
        }

        return [
            'ok' => true,
            'mensaje' => ''
        ];
    }

    /**
     * Acciones clínicas posteriores a una cita en agenda del psicólogo.
     *
     * @return array{
     *   accion: string,
     *   etiqueta: string,
     *   ruta: string
     * }
     */
    public function resolverAccionClinicaAgenda(
        string $clvPac,
        string $clvPsi,
        string $clvCons,
        string $clvCita,
        string $estadoCita
    ): array {
        $estadoCita = strtoupper(trim($estadoCita));

        if ($estadoCita === 'INASISTENCIA') {
            return [
                'accion' => 'inasistencia',
                'etiqueta' => 'Inasistencia registrada.',
                'ruta' => ''
            ];
        }

        if ($estadoCita !== 'ASISTIDA') {
            return [
                'accion' => '',
                'etiqueta' => '',
                'ruta' => ''
            ];
        }

        $historial = $this->historialModel->obtenerPorPacienteConsultorio(
            $clvPac,
            $clvCons
        );

        if ($historial === null) {
            return [
                'accion' => 'crear_historia',
                'etiqueta' => 'Crear historia clínica inicial',
                'ruta' =>
                    'psicologo/pacientes/ver/' .
                    rawurlencode($clvPac) .
                    '/historia/nueva'
            ];
        }

        if ((string) ($historial['ClvPsi'] ?? '') !== $clvPsi) {
            return [
                'accion' => '',
                'etiqueta' => '',
                'ruta' => ''
            ];
        }

        $clvHist = (string) ($historial['ClvHist'] ?? '');

        $clvSeg = $this->seguimientoModel->obtenerClavePorCita(
            $clvCita
        );

        if ($clvSeg !== null) {
            return [
                'accion' => 'ver_seguimiento',
                'etiqueta' => 'Ver seguimiento',
                'ruta' =>
                    'psicologo/expediente/seguimientos/ver/' .
                    rawurlencode($clvSeg)
            ];
        }

        return [
            'accion' => 'registrar_seguimiento',
            'etiqueta' => 'Registrar seguimiento de sesión',
            'ruta' =>
                'psicologo/expediente/' .
                rawurlencode($clvHist) .
                '/seguimientos/nuevo'
        ];
    }

    /**
     * @return array{ok: bool, mensaje?: string, historial?: array, paciente?: array, citasPendientes?: array}
     */
    public function prepararNuevoSeguimiento(
        string $clvHist,
        string $clvPsi,
        string $clvCons
    ): array {
        $auth = $this->autorizarHistorialResponsable(
            $clvHist,
            $clvPsi,
            $clvCons
        );

        if (!$auth['ok']) {
            return $auth;
        }

        /** @var array $historial */
        $historial = $auth['historial'];
        $clvPac = (string) $historial['ClvPac'];

        $citasPendientes =
            $this->seguimientoModel->listarCitasAsistidasPendientes(
                $clvPac,
                $clvPsi,
                $clvCons
            );

        if ($citasPendientes === []) {
            return [
                'ok' => false,
                'mensaje' =>
                    'No existen sesiones asistidas pendientes de registrar.'
            ];
        }

        $paciente = $this->pacienteModel->obtenerParaPsicologo(
            $clvPac,
            $clvPsi
        );

        return [
            'ok' => true,
            'historial' => $historial,
            'paciente' => $paciente ?? [],
            'citasPendientes' => $citasPendientes
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, mensaje: string, clvSeg?: string, clvPac?: string, clvHist?: string}
     */
    public function guardarSeguimiento(
        string $clvPsi,
        string $clvCons,
        array $post
    ): array {
        $clvHist = trim((string) ($post['ClvHist'] ?? ''));
        $clvCita = trim((string) ($post['ClvCita'] ?? ''));

        if ($clvHist === '' || $clvCita === '') {
            return [
                'ok' => false,
                'mensaje' =>
                    'La cita seleccionada no está disponible para registrar seguimiento.'
            ];
        }

        $auth = $this->autorizarHistorialResponsable(
            $clvHist,
            $clvPsi,
            $clvCons
        );

        if (!$auth['ok']) {
            return [
                'ok' => false,
                'mensaje' => (string) ($auth['mensaje'] ??
                    'No tienes autorización para acceder a este seguimiento.')
            ];
        }

        $payload = $this->normalizarPayloadSeguimiento($post);
        $validacion = $this->validarPayloadSeguimiento($payload);

        if (!$validacion['ok']) {
            return $validacion;
        }

        $payload['ClvHist'] = $clvHist;
        $payload['ClvCita'] = $clvCita;
        $payload['ClvPsi'] = $clvPsi;

        $resultado = $this->seguimientoModel->crearCompleto($payload);

        if ($resultado['ok']) {
            $resultado['clvPac'] = (string) ($auth['historial']['ClvPac'] ?? '');
            $resultado['clvHist'] = $clvHist;
        }

        return $resultado;
    }

    /**
     * @return array{ok: bool, mensaje?: string, completo?: array, historial?: array, paciente?: array}
     */
    public function obtenerSeguimientoAutorizado(
        string $clvSeg,
        string $clvPsi,
        string $clvCons
    ): array {
        $completo = $this->seguimientoModel->obtenerCompleto($clvSeg);

        if (!$completo) {
            return [
                'ok' => false,
                'mensaje' =>
                    'No tienes autorización para acceder a este seguimiento.'
            ];
        }

        $auth = $this->autorizarHistorialResponsable(
            (string) ($completo['seguimiento']['ClvHist'] ?? ''),
            $clvPsi,
            $clvCons
        );

        if (!$auth['ok']) {
            return [
                'ok' => false,
                'mensaje' =>
                    'No tienes autorización para acceder a este seguimiento.'
            ];
        }

        if (
            (string) ($completo['seguimiento']['ClvPsi'] ?? '') !== $clvPsi
        ) {
            return [
                'ok' => false,
                'mensaje' =>
                    'No tienes autorización para acceder a este seguimiento.'
            ];
        }

        $paciente = $this->pacienteModel->obtenerParaPsicologo(
            (string) ($auth['historial']['ClvPac'] ?? ''),
            $clvPsi
        );

        return [
            'ok' => true,
            'completo' => $completo,
            'historial' => $auth['historial'],
            'paciente' => $paciente ?? []
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, mensaje: string, clvPac?: string, clvHist?: string}
     */
    public function actualizarSeguimiento(
        string $clvPsi,
        string $clvCons,
        array $post
    ): array {
        $clvSeg = trim((string) ($post['ClvSeg'] ?? ''));

        if ($clvSeg === '') {
            return [
                'ok' => false,
                'mensaje' =>
                    'No tienes autorización para acceder a este seguimiento.'
            ];
        }

        $actual = $this->obtenerSeguimientoAutorizado(
            $clvSeg,
            $clvPsi,
            $clvCons
        );

        if (!$actual['ok']) {
            return [
                'ok' => false,
                'mensaje' => (string) ($actual['mensaje'] ??
                    'No tienes autorización para acceder a este seguimiento.')
            ];
        }

        $payload = $this->normalizarPayloadSeguimiento($post);
        $validacion = $this->validarPayloadSeguimiento($payload);

        if (!$validacion['ok']) {
            return $validacion;
        }

        $resultado = $this->seguimientoModel->actualizarCompleto(
            $clvSeg,
            $clvPsi,
            $payload
        );

        if ($resultado['ok']) {
            $resultado['clvPac'] = (string) (
                $actual['historial']['ClvPac'] ?? ''
            );
            $resultado['clvHist'] = (string) (
                $actual['historial']['ClvHist'] ?? ''
            );
        }

        return $resultado;
    }

    /**
     * @return array{ok: bool, mensaje?: string, historial?: array}
     */
    private function autorizarHistorialResponsable(
        string $clvHist,
        string $clvPsi,
        string $clvCons
    ): array {
        $historial = $this->historialModel->obtenerPorClave($clvHist);

        if (!$historial) {
            return [
                'ok' => false,
                'mensaje' => 'No se encontró el expediente clínico.'
            ];
        }

        if ((string) ($historial['ClvCons'] ?? '') !== $clvCons) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Este expediente clínico se encuentra asignado a otro especialista.'
            ];
        }

        if ((string) ($historial['ClvPsi'] ?? '') !== $clvPsi) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Este expediente clínico se encuentra asignado a otro especialista.'
            ];
        }

        return [
            'ok' => true,
            'historial' => $historial
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function normalizarPayloadSeguimiento(array $post): array
    {
        return [
            'HoraInicioReal' => $post['HoraInicioReal'] ?? '',
            'HoraFinReal' => $post['HoraFinReal'] ?? '',
            'ObjetivoSesion' => $post['ObjetivoSesion'] ?? '',
            'TemaAbordado' => $post['TemaAbordado'] ?? '',
            'DesarrolloSesion' => $post['DesarrolloSesion'] ?? '',
            'TecnicasAplicadas' => $post['TecnicasAplicadas'] ?? '',
            'RespuestaPaciente' => $post['RespuestaPaciente'] ?? '',
            'EstadoEmocional' => $post['EstadoEmocional'] ?? '',
            'ObservacionesSeg' => $post['ObservacionesSeg'] ?? '',
            'AcuerdosSeg' => $post['AcuerdosSeg'] ?? '',
            'TareasAsignadas' => $post['TareasAsignadas'] ?? '',
            'RecomendacionesSeg' => $post['RecomendacionesSeg'] ?? '',
            'ProximaAccion' => $post['ProximaAccion'] ?? '',
            'EstatusSeg' => $post['EstatusSeg'] ?? 'FINALIZADO',
            'evolucion' => is_array($post['evolucion'] ?? null)
                ? $post['evolucion']
                : [],
            'diagnostico' => is_array($post['diagnostico'] ?? null)
                ? $post['diagnostico']
                : [],
            'recomendaciones' => array_values(
                array_filter(
                    is_array($post['recomendaciones'] ?? null)
                        ? $post['recomendaciones']
                        : [],
                    'is_array'
                )
            )
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, mensaje: string}
     */
    private function validarPayloadSeguimiento(array $payload): array
    {
        $tema = trim((string) ($payload['TemaAbordado'] ?? ''));
        $objetivo = trim((string) ($payload['ObjetivoSesion'] ?? ''));

        if ($tema === '' && $objetivo === '') {
            return [
                'ok' => false,
                'mensaje' =>
                    'Indica al menos el tema abordado o el objetivo de la sesión.'
            ];
        }

        foreach ($payload['recomendaciones'] as $rec) {
            $desc = trim((string) ($rec['DescripcionRec'] ?? ''));
            $tipo = trim((string) ($rec['TipoRecomendacion'] ?? ''));

            if ($desc === '' && $tipo === '') {
                continue;
            }

            if ($desc === '' || $tipo === '') {
                return [
                    'ok' => false,
                    'mensaje' =>
                        'Cada recomendación debe incluir tipo y descripción.'
                ];
            }
        }

        $diag = trim(
            (string) (($payload['diagnostico']['DiagnosticoActual'] ?? ''))
        );
        $tipoCambio = trim(
            (string) (($payload['diagnostico']['TipoCambioDiag'] ?? ''))
        );

        if ($tipoCambio !== '' && $diag === '') {
            return [
                'ok' => false,
                'mensaje' =>
                    'El diagnóstico actual es obligatorio cuando se indica un cambio diagnóstico.'
            ];
        }

        return [
            'ok' => true,
            'mensaje' => ''
        ];
    }
}
