<?php

namespace App\Services;

use App\Config\Database;
use App\Helpers\Helper;
use App\Models\ActivacionCuenta;
use App\Models\Cita;
use App\Models\Paciente;
use App\Models\Persona;
use App\Models\Psicologo;
use App\Models\Usuario;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

class ActivacionCuentaService
{
    public const TIPO_PSICOLOGO = 'ALTA_PSICOLOGO';
    public const TIPO_PACIENTE = 'ALTA_PACIENTE';
    public const TIPO_CONSULTORIO = 'ALTA_CONSULTORIO';
    /** Requiere migración 2026_08_03_tipo_recuperacion_consultorio.sql */
    public const TIPO_RECUPERACION_CONSULTORIO = 'RECUPERACION_CONSULTORIO';

    public const MENSAJE_TOKEN_INVALIDO =
        'El enlace de activación no es válido o ha expirado.';

    private const EXPIRACION_HORAS = 24;
    private const MAX_REENVIOS = 3;
    private const COOLDOWN_SEGUNDOS = 60;
    private const MAX_INTENTOS_ACTIVACION = 8;

    private PDO $db;
    private ActivacionCuenta $activacionModel;
    private Usuario $usuarioModel;
    private Psicologo $psicologoModel;
    private Paciente $pacienteModel;
    private Persona $personaModel;
    private Cita $citaModel;
    private MailService $mailService;
    private AgendaService $agendaService;
    private NotificacionService $notificacionService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->activacionModel = new ActivacionCuenta();
        $this->usuarioModel = new Usuario();
        $this->psicologoModel = new Psicologo();
        $this->pacienteModel = new Paciente();
        $this->personaModel = new Persona();
        $this->citaModel = new Cita();
        $this->mailService = new MailService();
        $this->agendaService = new AgendaService();
        $this->notificacionService = new NotificacionService();
    }

    /**
     * Alta transaccional de psicólogo + invitación.
     *
     * @return array{
     *   ok: bool,
     *   codigo?: string,
     *   mensaje: string,
     *   correoEnviado?: bool,
     *   clvUsu?: string,
     *   clvPsi?: string,
     *   permitirReenvio?: bool
     * }
     */
    public function crearInvitacionPsicologo(
        array $datos,
        string $clvCons,
        string $clvUsuInvitador
    ): array {
        $correo = strtolower(trim((string) ($datos['correo'] ?? '')));
        $duplicado = $this->analizarCorreoPsicologo(
            $correo,
            $clvCons
        );

        if ($duplicado['codigo'] === 'ACTIVO') {
            return [
                'ok' => false,
                'codigo' => 'CORREO_EXISTENTE',
                'mensaje' =>
                    'Ya existe una cuenta registrada con este correo.'
            ];
        }

        if ($duplicado['codigo'] === 'PENDIENTE_MISMO') {
            return [
                'ok' => false,
                'codigo' => 'PENDIENTE_MISMO',
                'mensaje' =>
                    'Este especialista ya tiene una invitación pendiente.',
                'clvUsu' => $duplicado['clvUsu'] ?? null,
                'permitirReenvio' => true
            ];
        }

        if ($duplicado['codigo'] === 'OTRO') {
            return [
                'ok' => false,
                'codigo' => 'CORREO_NO_DISPONIBLE',
                'mensaje' =>
                    'No fue posible registrar este correo como una cuenta nueva.'
            ];
        }

        $tokenPlano = null;

        try {
            $this->db->beginTransaction();

            $resultado = $this->psicologoModel->guardarPendienteActivacion(
                $datos,
                $clvCons
            );

            $tokenPlano = $this->crearRegistroActivacion(
                $resultado['ClvUsu'],
                self::TIPO_PSICOLOGO,
                $clvUsuInvitador
            );

            $this->db->commit();
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return [
                    'ok' => false,
                    'codigo' => 'COLISION',
                    'mensaje' =>
                        'No fue posible registrar este correo como una cuenta nueva.'
                ];
            }

            throw new RuntimeException(
                'No fue posible registrar al especialista.'
            );
        } catch (RuntimeException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'codigo' => 'VALIDACION',
                'mensaje' => $e->getMessage()
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw new RuntimeException(
                'No fue posible registrar al especialista.'
            );
        }

        $correoEnviado = $this->enviarCorreoActivacionPsicologo(
            $correo,
            trim(
                ($datos['nombre'] ?? '') . ' ' .
                ($datos['apellidoPaterno'] ?? '')
            ),
            (string) $tokenPlano
        );

        return [
            'ok' => true,
            'codigo' => 'CREADO',
            'clvUsu' => $resultado['ClvUsu'],
            'clvPsi' => $resultado['ClvPsi'],
            'correoEnviado' => $correoEnviado,
            'permitirReenvio' => !$correoEnviado,
            'mensaje' => $correoEnviado
                ? 'El especialista se registró correctamente. Se envió el enlace de activación.'
                : 'El psicólogo fue registrado, pero no se pudo enviar el enlace.'
        ];
    }

    /**
     * Alta transaccional paciente + primera cita + invitación.
     *
     * @return array<string, mixed>
     */
    public function crearInvitacionPaciente(
        array $datosPaciente,
        array $datosCita,
        string $clvPsi,
        string $clvCons,
        string $clvUsuInvitador
    ): array {
        $correo = strtolower(trim((string) ($datosPaciente['correo'] ?? '')));
        $analisis = $this->analizarCorreoPaciente(
            $correo,
            $clvPsi
        );

        if ($analisis['codigo'] === 'ACTIVO_RELACIONADO') {
            return [
                'ok' => false,
                'codigo' => 'ACTIVO_RELACIONADO',
                'mensaje' =>
                    'Este paciente ya está registrado. Puedes agendarle una nueva cita desde el flujo habitual.',
                'clvPac' => $analisis['clvPac'] ?? null
            ];
        }

        if ($analisis['codigo'] === 'PENDIENTE_MISMO') {
            return [
                'ok' => false,
                'codigo' => 'PENDIENTE_MISMO',
                'mensaje' =>
                    'Este paciente tiene una activación pendiente.',
                'clvUsu' => $analisis['clvUsu'] ?? null,
                'clvPac' => $analisis['clvPac'] ?? null,
                'permitirReenvio' => true
            ];
        }

        if ($analisis['codigo'] === 'EXISTENTE_SIN_RELACION') {
            return [
                'ok' => false,
                'codigo' => 'CORREO_NO_DISPONIBLE',
                'mensaje' =>
                    'No fue posible registrar este correo como una cuenta nueva.'
            ];
        }

        $tokenPlano = null;
        $clvCita = null;
        $clvPac = null;
        $clvUsu = null;
        $datosReserva = null;

        try {
            $this->db->beginTransaction();

            if (
                !$this->citaModel->bloquearPsicologoParaReserva($clvPsi)
            ) {
                throw new RuntimeException(
                    'No fue posible validar tu disponibilidad.'
                );
            }

            $validacion = $this->agendaService->validarEspacioReserva(
                $clvPsi,
                (string) $datosCita['servicio'],
                (string) $datosCita['fecha'],
                (string) $datosCita['hora']
            );

            if (!$validacion['ok']) {
                throw new RuntimeException(
                    (string) $validacion['mensaje']
                );
            }

            $datosReserva = $validacion['datos'];

            if (($datosReserva['ClvPsi'] ?? '') !== $clvPsi) {
                throw new RuntimeException(
                    'No fue posible validar la cita.'
                );
            }

            if (
                $this->citaModel->existeSolapamientoProgramado(
                    $datosReserva['ClvPsi'],
                    $datosReserva['FechaCita'],
                    $datosReserva['HraInicioCita'],
                    $datosReserva['HraFinCita']
                )
            ) {
                throw new RuntimeException(
                    'El horario seleccionado ya no está disponible.'
                );
            }

            $claves = $this->crearPacientePendiente(
                $datosPaciente,
                $clvCons
            );

            $clvUsu = $claves['ClvUsu'];
            $clvPac = $claves['ClvPac'];

            $clvCita = $this->citaModel->generarClaveCita();
            $datosReserva['ClvCita'] = $clvCita;
            $datosReserva['ClvPac'] = $clvPac;

            $this->citaModel->crearCita($datosReserva);

            $tokenPlano = $this->crearRegistroActivacion(
                $clvUsu,
                self::TIPO_PACIENTE,
                $clvUsuInvitador
            );

            // Confirmación al paciente = correo de activación combinado (OMITIDO en correo_cita).
            // Confirmación al psicólogo + recordatorio 24h cuando aplique.
            $correoCitaService = new CorreoCitaService($this->db);
            if ($correoCitaService->persistenciaDisponible()) {
                $correoCitaService->prepararParaCitaNueva(
                    (string) $clvCita,
                    [
                        'omitirConfirmacionPaciente' => true,
                        'motivoOmitirConfirmacionPaciente' =>
                            CorreoCitaService::MOTIVO_ACTIVACION
                    ]
                );
            }

            $this->db->commit();
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return [
                    'ok' => false,
                    'codigo' => 'COLISION',
                    'mensaje' =>
                        'No fue posible registrar este correo como una cuenta nueva.'
                ];
            }

            return [
                'ok' => false,
                'codigo' => 'ERROR_GUARDADO',
                'mensaje' => 'No fue posible registrar al paciente.'
            ];
        } catch (RuntimeException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'codigo' => 'VALIDACION',
                'mensaje' => $e->getMessage()
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'codigo' => 'ERROR_INTERNO',
                'mensaje' => 'No fue posible registrar al paciente.'
            ];
        }

        $contextoCorreo = $this->obtenerContextoCorreoPaciente(
            (string) $clvCita
        );

        $correoEnviado = false;

        if ($contextoCorreo !== null && is_string($tokenPlano)) {
            $correoEnviado = $this->enviarCorreoActivacionPaciente(
                $contextoCorreo,
                $tokenPlano
            );
        }

        // Confirmación genérica solo al psicólogo (paciente ya cubierto por activación).
        try {
            $correoCitaPost = new CorreoCitaService();
            if ($correoCitaPost->persistenciaDisponible()) {
                $correoCitaPost->procesarConfirmacionesInmediatas(
                    (string) $clvCita
                );
            }
        } catch (\Throwable $e) {
            // Auxiliar: no revierte la cita ni la activación.
        }

        try {
            $this->notificacionService
                ->notificarCitaCreadaPorPsicologo((string) $clvCita);
        } catch (\Throwable $e) {
            // Auxiliar: la cita ya quedó confirmada.
        }

        return [
            'ok' => true,
            'codigo' => 'CREADO',
            'clvUsu' => $clvUsu,
            'clvPac' => $clvPac,
            'clvCita' => $clvCita,
            'correoEnviado' => $correoEnviado,
            'permitirReenvio' => !$correoEnviado,
            'mensaje' => $correoEnviado
                ? 'El paciente y su primera cita se registraron. Se envió el enlace de activación.'
                : 'El paciente y la cita se registraron, pero no se pudo enviar el enlace.'
        ];
    }

    /**
     * @return array{
     *   valido: bool,
     *   mensaje?: string,
     *   activacion?: array<string, mixed>
     * }
     */
    public function obtenerPorToken(string $token): array
    {
        $token = trim($token);

        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return [
                'valido' => false,
                'mensaje' => self::MENSAJE_TOKEN_INVALIDO
            ];
        }

        $hash = hash('sha256', $token);
        $activacion = $this->activacionModel->obtenerPorTokenHash($hash);

        if (
            !$activacion ||
            !hash_equals(
                (string) ($activacion['TokenHash'] ?? ''),
                $hash
            )
        ) {
            return [
                'valido' => false,
                'mensaje' => self::MENSAJE_TOKEN_INVALIDO
            ];
        }

        if (($activacion['Estado'] ?? '') !== 'PENDIENTE') {
            return [
                'valido' => false,
                'mensaje' => self::MENSAJE_TOKEN_INVALIDO
            ];
        }

        if ($this->estaExpirada($activacion)) {
            $this->activacionModel->marcarExpirada(
                (int) $activacion['IdActivacion']
            );

            return [
                'valido' => false,
                'mensaje' => self::MENSAJE_TOKEN_INVALIDO
            ];
        }

        if ((int) ($activacion['EstadoUsu'] ?? 0) === 1) {
            return [
                'valido' => false,
                'mensaje' => self::MENSAJE_TOKEN_INVALIDO
            ];
        }

        return [
            'valido' => true,
            'activacion' => $activacion
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function activarCuenta(
        string $token,
        string $password,
        string $confirmacion,
        array $consentimientoPost = []
    ): array {
        if (!$this->registrarIntentoActivacion($token)) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Demasiados intentos. Solicita un nuevo enlace de activación.'
            ];
        }

        $validacionPassword = $this->validarPassword(
            $password,
            $confirmacion
        );

        if (!$validacionPassword['ok']) {
            return $validacionPassword;
        }

        $hash = hash('sha256', trim($token));
        $tipo = null;
        $clvUsu = null;
        $clvCons = null;
        $clvPsiInvitado = null;
        $clvUsuInvitador = null;
        $nombreActivado = '';

        try {
            $this->db->beginTransaction();

            $activacion = $this->activacionModel->obtenerPorTokenHash(
                $hash,
                true
            );

            if (
                !$activacion ||
                !hash_equals(
                    (string) ($activacion['TokenHash'] ?? ''),
                    $hash
                ) ||
                ($activacion['Estado'] ?? '') !== 'PENDIENTE'
            ) {
                throw new RuntimeException(self::MENSAJE_TOKEN_INVALIDO);
            }

            if ($this->estaExpirada($activacion)) {
                $this->activacionModel->marcarExpirada(
                    (int) $activacion['IdActivacion']
                );

                throw new RuntimeException(self::MENSAJE_TOKEN_INVALIDO);
            }

            $clvUsu = (string) $activacion['ClvUsu'];
            $tipo = (string) $activacion['TipoActivacion'];
            $rol = (string) ($activacion['RolUsu'] ?? '');
            $clvUsuInvitador = trim(
                (string) ($activacion['ClvUsuInvitador'] ?? '')
            );
            $nombreActivado = trim(
                ($activacion['NombrePer'] ?? '') . ' ' .
                ($activacion['ApPatPer'] ?? '')
            );

            $esRecuperacionConsultorio =
                $tipo === self::TIPO_RECUPERACION_CONSULTORIO;

            if (
                ($tipo === self::TIPO_PSICOLOGO && $rol !== 'PSICOLOGO') ||
                ($tipo === self::TIPO_PACIENTE && $rol !== 'PACIENTE') ||
                ($tipo === self::TIPO_CONSULTORIO && $rol !== 'CONSULTORIO') ||
                ($esRecuperacionConsultorio && $rol !== 'CONSULTORIO')
            ) {
                throw new RuntimeException(self::MENSAJE_TOKEN_INVALIDO);
            }

            $usuarioBloqueado = $this->usuarioModel->bloquearPorClave(
                $clvUsu
            );

            if (!$usuarioBloqueado) {
                throw new RuntimeException(self::MENSAJE_TOKEN_INVALIDO);
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            if ($esRecuperacionConsultorio) {
                // Recuperación: cuenta ya activada; solo cambia contraseña.
                if ((int) ($usuarioBloqueado['EstadoUsu'] ?? 0) !== 1) {
                    throw new RuntimeException(self::MENSAJE_TOKEN_INVALIDO);
                }

                if (!$this->esCuentaPrincipalConsultorio($clvUsu)) {
                    throw new RuntimeException(self::MENSAJE_TOKEN_INVALIDO);
                }

                $this->usuarioModel->actualizarContrasenaYLiberarCambio(
                    $clvUsu,
                    $passwordHash
                );
            } else {
                if ((int) ($usuarioBloqueado['EstadoUsu'] ?? 0) === 1) {
                    throw new RuntimeException(self::MENSAJE_TOKEN_INVALIDO);
                }

                $this->usuarioModel->activarConPassword(
                    $clvUsu,
                    $passwordHash
                );
            }

            if ($tipo === self::TIPO_PSICOLOGO) {
                $psi = $this->psicologoModel->activarTrasInvitacion(
                    $clvUsu
                );
                $clvCons = $psi['ClvCons'] ?? null;
                $clvPsiInvitado = $psi['ClvPsi'] ?? null;
            }

            if ($tipo === self::TIPO_PACIENTE) {
                $privacidad = new PrivacidadService();
                $fechaNacimiento = (string) ($activacion['FechaNacimiento'] ?? '');

                $consentimiento = $privacidad->registrarConsentimiento(
                    $clvUsu,
                    'ACTIVACION',
                    $consentimientoPost,
                    $fechaNacimiento
                );

                if (empty($consentimiento['ok'])) {
                    throw new RuntimeException(
                        (string) ($consentimiento['mensaje'] ??
                            'No se pudo registrar el consentimiento.')
                    );
                }
            }

            $this->activacionModel->marcarUsada(
                (int) $activacion['IdActivacion']
            );

            $this->activacionModel->revocarPendientes(
                $clvUsu,
                $tipo,
                (int) $activacion['IdActivacion']
            );

            $this->db->commit();
        } catch (RuntimeException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' => $e->getMessage()
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_TOKEN_INVALIDO
            ];
        }

        try {
            if ($tipo === self::TIPO_PSICOLOGO && is_string($clvCons)) {
                $this->notificacionService
                    ->notificarEspecialistaActivoConsultorio(
                        $clvCons,
                        $nombreActivado
                    );
            }

            if (
                $tipo === self::TIPO_PACIENTE &&
                is_string($clvUsu)
            ) {
                $this->notificacionService
                    ->notificarPacienteActivoAPsicologo(
                        $clvUsu,
                        $clvUsuInvitador !== '' ? $clvUsuInvitador : null
                    );
            }
        } catch (\Throwable $e) {
            // Auxiliar.
        }

        unset($clvPsiInvitado);

        return [
            'ok' => true,
            'mensaje' =>
                'Tu cuenta fue activada correctamente. Ya puedes iniciar sesión.'
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   mensaje: string,
     *   correoEnviado?: bool
     * }
     */
    public function reenviarActivacion(
        string $clvUsu,
        string $actorClvUsu,
        string $tipo
    ): array {
        $clvUsu = trim($clvUsu);
        $actorClvUsu = trim($actorClvUsu);
        $tipo = strtoupper(trim($tipo));

        if (
            !in_array(
                $tipo,
                [
                    self::TIPO_PSICOLOGO,
                    self::TIPO_PACIENTE,
                    self::TIPO_CONSULTORIO
                ],
                true
            )
        ) {
            return [
                'ok' => false,
                'mensaje' => 'No fue posible reenviar el enlace.'
            ];
        }

        $usuario = $this->usuarioModel->obtenerPorClave($clvUsu);

        if (!$usuario || (int) ($usuario['EstadoUsu'] ?? 0) === 1) {
            return [
                'ok' => false,
                'mensaje' =>
                    'No hay una activación pendiente para reenviar.'
            ];
        }

        $ultimaUsada = $this->activacionModel->obtenerUltimaPorUsuario(
            $clvUsu,
            $tipo
        );

        if (
            $ultimaUsada &&
            ($ultimaUsada['Estado'] ?? '') === 'USADA' &&
            (int) ($usuario['EstadoUsu'] ?? 0) === 1
        ) {
            return [
                'ok' => false,
                'mensaje' =>
                    'La cuenta ya está activa. No es necesario reenviar el enlace.'
            ];
        }

        if (!$this->actorPuedeReenviar($tipo, $clvUsu, $actorClvUsu)) {
            return [
                'ok' => false,
                'mensaje' => 'No tienes permiso para reenviar este enlace.'
            ];
        }

        $ultima = $this->activacionModel->obtenerUltimaPorUsuario(
            $clvUsu,
            $tipo
        );

        if ($ultima) {
            $reenvios = (int) ($ultima['NumReenvios'] ?? 0);

            if ($reenvios >= self::MAX_REENVIOS) {
                return [
                    'ok' => false,
                    'mensaje' =>
                        'Se alcanzó el límite de reenvíos para esta invitación.'
                ];
            }

            if (!empty($ultima['FechaUltimoEnvio'])) {
                try {
                    $ultimo = new DateTimeImmutable(
                        (string) $ultima['FechaUltimoEnvio']
                    );
                    $ahora = new DateTimeImmutable('now');

                    if (
                        ($ahora->getTimestamp() - $ultimo->getTimestamp())
                        < self::COOLDOWN_SEGUNDOS
                    ) {
                        return [
                            'ok' => false,
                            'mensaje' =>
                                'Espera un momento antes de reenviar el enlace.'
                        ];
                    }
                } catch (\Throwable $e) {
                    // Continuar.
                }
            }
        }

        $tokenPlano = null;
        $numReenvios = $ultima
            ? ((int) ($ultima['NumReenvios'] ?? 0) + 1)
            : 1;

        try {
            $this->db->beginTransaction();

            $this->activacionModel->revocarPendientes($clvUsu, $tipo);

            $tokenPlano = $this->crearRegistroActivacion(
                $clvUsu,
                $tipo,
                $actorClvUsu,
                $numReenvios
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' => 'No fue posible reenviar el enlace.'
            ];
        }

        $nombre = trim(
            ($usuario['NombrePer'] ?? '') . ' ' .
            ($usuario['ApPatPer'] ?? '')
        );

        if ($nombre === '') {
            $detalle = $this->usuarioModel->buscarPorCorreo(
                (string) $usuario['CorreoUsu']
            );
            $nombre = trim(
                ($detalle['NombrePer'] ?? '') . ' ' .
                ($detalle['ApPatPer'] ?? '')
            );
        }

        $correoEnviado = false;

        if ($tipo === self::TIPO_PSICOLOGO) {
            $correoEnviado = $this->enviarCorreoActivacionPsicologo(
                (string) $usuario['CorreoUsu'],
                $nombre !== '' ? $nombre : 'Especialista',
                (string) $tokenPlano
            );
        } elseif ($tipo === self::TIPO_CONSULTORIO) {
            $correoEnviado = $this->enviarCorreoActivacionConsultorio(
                (string) $usuario['CorreoUsu'],
                $nombre !== '' ? $nombre : 'Responsable',
                (string) $tokenPlano
            );
        } else {
            $contexto = $this->obtenerContextoCorreoPacientePorUsuario(
                $clvUsu
            );

            if ($contexto !== null) {
                $correoEnviado = $this->enviarCorreoActivacionPaciente(
                    $contexto,
                    (string) $tokenPlano
                );
            }
        }

        return [
            'ok' => $correoEnviado,
            'correoEnviado' => $correoEnviado,
            'mensaje' => $correoEnviado
                ? 'Se reenvió el enlace de activación.'
                : 'No se pudo enviar el enlace. Intenta nuevamente.'
        ];
    }

    /**
     * @param list<array<string, mixed>> $psicologos
     * @return list<array<string, mixed>>
     */
    public function enriquecerEstadosPsicologos(array $psicologos): array
    {
        $claves = [];

        foreach ($psicologos as $psi) {
            if (!empty($psi['ClvUsu'])) {
                $claves[] = (string) $psi['ClvUsu'];
            }
        }

        $activaciones = $this->activacionModel->mapearUltimasPorUsuarios(
            $claves,
            self::TIPO_PSICOLOGO
        );

        foreach ($psicologos as &$psi) {
            $clvUsu = (string) ($psi['ClvUsu'] ?? '');
            $estadoUsu = (int) ($psi['EstadoUsu'] ?? 0);
            $estatusPsi = (string) ($psi['EstatusPsi'] ?? '');
            $activacion = $activaciones[$clvUsu] ?? null;

            $estadoVisual = 'INACTIVO';
            $etiqueta = 'Inactivo';
            $puedeReenviar = false;

            if ($estadoUsu === 1 && $estatusPsi === 'ACTIVO') {
                $estadoVisual = 'ACTIVO';
                $etiqueta = 'Activo';
            } elseif ($estadoUsu === 1) {
                $estadoVisual = 'INACTIVO';
                $etiqueta = 'Inactivo';
            } elseif ($activacion) {
                $pendienteVigente =
                    ($activacion['Estado'] ?? '') === 'PENDIENTE'
                    && !$this->estaExpirada($activacion);

                if ($pendienteVigente) {
                    $estadoVisual = 'PENDIENTE_ACTIVACION';
                    $etiqueta = 'Pendiente de activación';
                    $puedeReenviar =
                        (int) ($activacion['NumReenvios'] ?? 0)
                        < self::MAX_REENVIOS;
                } else {
                    $estadoVisual = 'ACTIVACION_VENCIDA';
                    $etiqueta = 'Activación vencida';
                    $puedeReenviar =
                        (int) ($activacion['NumReenvios'] ?? 0)
                        < self::MAX_REENVIOS;
                }
            } else {
                $estadoVisual = 'PENDIENTE_ACTIVACION';
                $etiqueta = 'Pendiente de activación';
                $puedeReenviar = true;
            }

            $psi['EstadoActivacion'] = $estadoVisual;
            $psi['EstadoActivacionEtiqueta'] = $etiqueta;
            $psi['PuedeReenviarActivacion'] = $puedeReenviar;
        }

        unset($psi);

        return $psicologos;
    }

    public function enmascararCorreo(string $correo): string
    {
        $correo = strtolower(trim($correo));
        $partes = explode('@', $correo, 2);

        if (count($partes) !== 2) {
            return '***';
        }

        $local = $partes[0];
        $dominio = $partes[1];
        $longitud = mb_strlen($local);

        if ($longitud <= 2) {
            $localMask = str_repeat('*', $longitud);
        } else {
            $localMask =
                mb_substr($local, 0, 1) .
                str_repeat('*', max(1, $longitud - 2)) .
                mb_substr($local, -1);
        }

        return $localMask . '@' . $dominio;
    }

    private function crearRegistroActivacion(
        string $clvUsu,
        string $tipo,
        string $clvUsuInvitador,
        int $numReenvios = 0
    ): string {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $ahora = new DateTimeImmutable(
            'now',
            new DateTimeZone('America/Mexico_City')
        );
        $expira = $ahora->modify('+' . self::EXPIRACION_HORAS . ' hours');

        $this->activacionModel->revocarPendientes($clvUsu, $tipo);

        $this->activacionModel->crear([
            'ClvUsu' => $clvUsu,
            'TokenHash' => $hash,
            'TipoActivacion' => $tipo,
            'ClvUsuInvitador' => $clvUsuInvitador !== ''
                ? $clvUsuInvitador
                : null,
            'FechaExpiracion' => $expira->format('Y-m-d H:i:s'),
            'FechaUltimoEnvio' => $ahora->format('Y-m-d H:i:s'),
            'NumReenvios' => $numReenvios
        ]);

        return $token;
    }

    /**
     * @return array{ClvUsu: string, ClvPac: string}
     */
    private function crearPacientePendiente(
        array $datos,
        string $clvCons
    ): array {
        $clvPer = ClaveService::generar('persona', 'ClvPer', 'PER');
        $clvUsu = ClaveService::generar('usuario', 'ClvUsu', 'USU');
        $clvPac = ClaveService::generar('paciente', 'ClvPac', 'PAC');

        $this->personaModel->crear([
            'ClvPer' => $clvPer,
            'NombrePer' => trim((string) $datos['nombre']),
            'ApPatPer' => trim((string) $datos['apellidoPaterno']),
            'ApMatPer' => trim((string) ($datos['apellidoMaterno'] ?? '')),
            'FechaNacimiento' => trim((string) $datos['fechaNacimiento']),
            'GeneroPer' => trim((string) $datos['genero'])
        ]);

        $hashDesconocido = password_hash(
            bin2hex(random_bytes(32)),
            PASSWORD_DEFAULT
        );

        $this->usuarioModel->crearPendienteActivacion([
            'ClvUsu' => $clvUsu,
            'CorreoUsu' => strtolower(trim((string) $datos['correo'])),
            'TelefonoUsu' => preg_replace(
                '/\D+/',
                '',
                (string) ($datos['telefono'] ?? '')
            ),
            'ContrasenaUsu' => $hashDesconocido,
            'RolUsu' => 'PACIENTE',
            'ClvPer' => $clvPer
        ]);

        $this->pacienteModel->crear([
            'ClvPac' => $clvPac,
            'ClvUsu' => $clvUsu,
            'ClvCons' => $clvCons
        ]);

        return [
            'ClvUsu' => $clvUsu,
            'ClvPac' => $clvPac
        ];
    }

    /**
     * @return array{codigo: string, clvUsu?: string}
     */
    private function analizarCorreoPsicologo(
        string $correo,
        string $clvCons
    ): array {
        if ($correo === '') {
            return ['codigo' => 'OTRO'];
        }

        $usuario = $this->usuarioModel->buscarPorCorreo($correo);

        if (!$usuario) {
            return ['codigo' => 'LIBRE'];
        }

        $psi = $this->psicologoModel->obtenerPorUsuario(
            (string) $usuario['ClvUsu']
        );

        if (
            $psi &&
            ($psi['ClvCons'] ?? '') === $clvCons &&
            (int) ($usuario['EstadoUsu'] ?? 0) === 0
        ) {
            return [
                'codigo' => 'PENDIENTE_MISMO',
                'clvUsu' => (string) $usuario['ClvUsu']
            ];
        }

        if ((int) ($usuario['EstadoUsu'] ?? 0) === 1) {
            return ['codigo' => 'ACTIVO'];
        }

        return ['codigo' => 'OTRO'];
    }

    /**
     * @return array{codigo: string, clvUsu?: string, clvPac?: string}
     */
    private function analizarCorreoPaciente(
        string $correo,
        string $clvPsi
    ): array {
        if ($correo === '') {
            return ['codigo' => 'EXISTENTE_SIN_RELACION'];
        }

        $usuario = $this->usuarioModel->buscarPorCorreo($correo);

        if (!$usuario) {
            return ['codigo' => 'LIBRE'];
        }

        if (($usuario['RolUsu'] ?? '') !== 'PACIENTE') {
            return ['codigo' => 'EXISTENTE_SIN_RELACION'];
        }

        $paciente = $this->pacienteModel->obtenerPorUsuario(
            (string) $usuario['ClvUsu']
        );

        if (!$paciente) {
            return ['codigo' => 'EXISTENTE_SIN_RELACION'];
        }

        $relacionado = $this->citaModel->pacientePerteneceAPsicologo(
            (string) $paciente['ClvPac'],
            $clvPsi
        );

        if (
            $relacionado &&
            (int) ($usuario['EstadoUsu'] ?? 0) === 0
        ) {
            return [
                'codigo' => 'PENDIENTE_MISMO',
                'clvUsu' => (string) $usuario['ClvUsu'],
                'clvPac' => (string) $paciente['ClvPac']
            ];
        }

        if (
            $relacionado &&
            (int) ($usuario['EstadoUsu'] ?? 0) === 1
        ) {
            return [
                'codigo' => 'ACTIVO_RELACIONADO',
                'clvPac' => (string) $paciente['ClvPac']
            ];
        }

        return ['codigo' => 'EXISTENTE_SIN_RELACION'];
    }

    private function actorPuedeReenviar(
        string $tipo,
        string $clvUsuObjetivo,
        string $actorClvUsu
    ): bool {
        $actor = $this->usuarioModel->obtenerPorClave($actorClvUsu);

        if (!$actor || (int) ($actor['EstadoUsu'] ?? 0) !== 1) {
            return false;
        }

        if ($tipo === self::TIPO_CONSULTORIO) {
            return ($actor['RolUsu'] ?? '') === 'ADMINISTRADOR';
        }

        if ($tipo === self::TIPO_PSICOLOGO) {
            if (($actor['RolUsu'] ?? '') !== 'CONSULTORIO') {
                return false;
            }

            $psi = $this->psicologoModel->obtenerPorUsuario($clvUsuObjetivo);

            if (!$psi) {
                return false;
            }

            $sql = "SELECT COUNT(*)
                    FROM consultorio_usuario
                    WHERE ClvUsu = :actor
                      AND ClvCons = :cons
                      AND EstatusConsUsu = 'ACTIVO'";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'actor' => $actorClvUsu,
                'cons' => $psi['ClvCons']
            ]);

            return (int) $stmt->fetchColumn() > 0;
        }

        if (($actor['RolUsu'] ?? '') !== 'PSICOLOGO') {
            return false;
        }

        $paciente = $this->pacienteModel->obtenerPorUsuario($clvUsuObjetivo);

        if (!$paciente) {
            return false;
        }

        $psiActor = $this->psicologoModel->obtenerPorUsuario($actorClvUsu);

        if (!$psiActor) {
            return false;
        }

        $relacionado = $this->citaModel->pacientePerteneceAPsicologo(
            (string) $paciente['ClvPac'],
            (string) $psiActor['ClvPsi']
        );

        if ($relacionado) {
            return true;
        }

        $ultima = $this->activacionModel->obtenerUltimaPorUsuario(
            $clvUsuObjetivo,
            self::TIPO_PACIENTE
        );

        return $ultima
            && (string) ($ultima['ClvUsuInvitador'] ?? '') === $actorClvUsu;
    }

    /**
     * ¿El enum TipoActivacion ya incluye RECUPERACION_CONSULTORIO?
     */
    public function soportaRecuperacionConsultorio(): bool
    {
        try {
            $col = $this->db->query(
                "SHOW COLUMNS FROM activacion_cuenta LIKE 'TipoActivacion'"
            )->fetch(PDO::FETCH_ASSOC);

            $type = (string) ($col['Type'] ?? '');

            return stripos($type, 'RECUPERACION_CONSULTORIO') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reenvío de activación inicial (ALTA_CONSULTORIO).
     * No aplica a cuentas ya activas (usar recuperación).
     *
     * @return array{ok: bool, mensaje: string, correoEnviado?: bool}
     */
    public function crearInvitacionConsultorioExistente(
        string $clvUsu,
        string $clvUsuInvitador,
        string $nombreDestino,
        string $nombreConsultorio = ''
    ): array {
        $usuario = $this->usuarioModel->obtenerPorClave($clvUsu);

        if (
            !$usuario ||
            ($usuario['RolUsu'] ?? '') !== 'CONSULTORIO'
        ) {
            return [
                'ok' => false,
                'mensaje' => 'No se encontró la cuenta del consultorio.'
            ];
        }

        if ((int) ($usuario['EstadoUsu'] ?? 0) === 1
            && (int) ($usuario['RequiereCambioContrasena'] ?? 0) === 0
        ) {
            return [
                'ok' => false,
                'mensaje' =>
                    'La cuenta ya está activa. Usa restablecimiento de acceso.'
            ];
        }

        try {
            $this->db->beginTransaction();
            $tokenPlano = $this->crearRegistroActivacion(
                $clvUsu,
                self::TIPO_CONSULTORIO,
                $clvUsuInvitador
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' => 'No fue posible generar el enlace de activación.'
            ];
        }

        $correoEnviado = $this->enviarCorreoActivacionConsultorio(
            (string) $usuario['CorreoUsu'],
            $nombreDestino !== '' ? $nombreDestino : 'Responsable',
            (string) $tokenPlano,
            $nombreConsultorio
        );

        return [
            'ok' => true,
            'correoEnviado' => $correoEnviado,
            'mensaje' => $correoEnviado
                ? 'Se envió el enlace de activación al correo del responsable.'
                : 'La cuenta quedó pendiente, pero no se pudo enviar el enlace.'
        ];
    }

    /**
     * Recuperación administrativa: no cambia EstadoUsu ni EstatusCons.
     * Solo invalida tokens pendientes RECUPERACION_CONSULTORIO.
     *
     * @return array{ok: bool, mensaje: string, correoEnviado?: bool}
     */
    public function crearRecuperacionConsultorio(
        string $clvUsu,
        string $clvUsuInvitador,
        string $nombreDestino,
        string $nombreConsultorio = ''
    ): array {
        if (!$this->soportaRecuperacionConsultorio()) {
            return [
                'ok' => false,
                'mensaje' =>
                    'La recuperación administrativa requiere aplicar la migración '
                    . 'propuesta de TipoActivacion (RECUPERACION_CONSULTORIO). '
                    . 'No se generó enlace ni se modificó la cuenta.'
            ];
        }

        $tokenPlano = null;
        $correoDestino = '';

        try {
            $this->db->beginTransaction();

            $usuario = $this->usuarioModel->bloquearPorClave($clvUsu);

            if (
                !$usuario ||
                ($usuario['RolUsu'] ?? '') !== 'CONSULTORIO'
            ) {
                throw new RuntimeException(
                    'No se encontró la cuenta del consultorio.'
                );
            }

            if ((int) ($usuario['RequiereCambioContrasena'] ?? 0) === 1) {
                throw new RuntimeException(
                    'La activación inicial aún no está completa. Usa reenviar activación.'
                );
            }

            if ((int) ($usuario['EstadoUsu'] ?? 0) !== 1) {
                throw new RuntimeException(
                    'Activa la cuenta antes de enviar un enlace de recuperación.'
                );
            }

            $correoDestino = strtolower(trim((string) ($usuario['CorreoUsu'] ?? '')));

            if ($correoDestino === '' || !filter_var($correoDestino, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(
                    'La cuenta no tiene un correo válido para recuperación.'
                );
            }

            // Solo invalida RECUPERACION_CONSULTORIO (no ALTA_*).
            $tokenPlano = $this->crearRegistroActivacion(
                $clvUsu,
                self::TIPO_RECUPERACION_CONSULTORIO,
                $clvUsuInvitador
            );

            $this->db->commit();
        } catch (RuntimeException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' => $e->getMessage()
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' => 'No fue posible generar el enlace de recuperación.'
            ];
        }

        $correoEnviado = $this->enviarCorreoRecuperacionConsultorio(
            $correoDestino,
            $nombreDestino !== '' ? $nombreDestino : 'Responsable',
            (string) $tokenPlano,
            $nombreConsultorio
        );

        return [
            'ok' => true,
            'correoEnviado' => $correoEnviado,
            'mensaje' => $correoEnviado
                ? 'Se envió el enlace de recuperación al correo registrado.'
                : 'El enlace se generó, pero no se pudo enviar el correo.'
        ];
    }

    private function enviarCorreoRecuperacionConsultorio(
        string $correo,
        string $nombre,
        string $token,
        string $nombreConsultorio = ''
    ): bool {
        try {
            $url = Helper::baseUrl(
                'restablecer-acceso-consultorio?token=' . rawurlencode($token)
            );

            $this->mailService->enviarRecuperacionConsultorio(
                $correo,
                $nombre,
                $url,
                self::EXPIRACION_HORAS,
                $nombreConsultorio
            );

            return true;
        } catch (\Throwable $e) {
            error_log('Error envío recuperación consultorio (sin secretos)');
            return false;
        }
    }

    private function enviarCorreoActivacionConsultorio(
        string $correo,
        string $nombre,
        string $token,
        string $nombreConsultorio = ''
    ): bool {
        try {
            $url = Helper::baseUrl(
                'activar-cuenta?token=' . rawurlencode($token)
            );

            $this->mailService->enviarActivacionConsultorio(
                $correo,
                $nombre,
                $url,
                self::EXPIRACION_HORAS,
                $nombreConsultorio
            );

            return true;
        } catch (\Throwable $e) {
            error_log(
                'Fallo SMTP activación consultorio: envío no completado.'
            );

            return false;
        }
    }

    private function estaExpirada(array $activacion): bool
    {
        $expira = (string) ($activacion['FechaExpiracion'] ?? '');

        if ($expira === '') {
            return true;
        }

        try {
            $fecha = new DateTimeImmutable($expira);
            $ahora = new DateTimeImmutable('now');

            return $fecha <= $ahora;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function esCuentaPrincipalConsultorio(string $clvUsu): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM consultorio_usuario cu
             INNER JOIN usuario u ON u.ClvUsu = cu.ClvUsu
             WHERE cu.ClvUsu = :u
               AND cu.EsResponsable = 1
               AND cu.EstatusConsUsu = 'ACTIVO'
               AND u.RolUsu = 'CONSULTORIO'"
        );
        $stmt->execute(['u' => $clvUsu]);

        return (int) $stmt->fetchColumn() === 1;
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    private function validarPassword(
        string $password,
        string $confirmacion
    ): array {
        if ($password === '' || $confirmacion === '') {
            return [
                'ok' => false,
                'mensaje' => 'Completa ambos campos de contraseña.'
            ];
        }

        if (
            strlen($password) < 8 ||
            !preg_match('/[A-Za-z]/', $password) ||
            !preg_match('/[0-9]/', $password)
        ) {
            return [
                'ok' => false,
                'mensaje' =>
                    'La contraseña debe tener al menos ocho caracteres, letras y números.'
            ];
        }

        if ($password !== $confirmacion) {
            return [
                'ok' => false,
                'mensaje' => 'Las contraseñas no coinciden.'
            ];
        }

        return [
            'ok' => true,
            'mensaje' => ''
        ];
    }

    private function registrarIntentoActivacion(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }

        $clave = 'activacion_intentos_' . substr(
            hash('sha256', $token),
            0,
            16
        );

        $intentos = (int) ($_SESSION[$clave] ?? 0) + 1;
        $_SESSION[$clave] = $intentos;

        return $intentos <= self::MAX_INTENTOS_ACTIVACION;
    }

    private function enviarCorreoActivacionPsicologo(
        string $correo,
        string $nombre,
        string $token
    ): bool {
        try {
            $url = Helper::baseUrl(
                'activar-cuenta?token=' . rawurlencode($token)
            );

            $this->mailService->enviarActivacionPsicologo(
                $correo,
                $nombre,
                $url,
                self::EXPIRACION_HORAS
            );

            return true;
        } catch (\Throwable $e) {
            error_log(
                'Fallo SMTP activación psicólogo: envío no completado.'
            );

            return false;
        }
    }

    /**
     * @param array<string, mixed> $contexto
     */
    private function enviarCorreoActivacionPaciente(
        array $contexto,
        string $token
    ): bool {
        try {
            $url = Helper::baseUrl(
                'activar-cuenta?token=' . rawurlencode($token)
            );

            $this->mailService->enviarActivacionPacienteConCita(
                $contexto,
                $url,
                self::EXPIRACION_HORAS
            );

            return true;
        } catch (\Throwable $e) {
            error_log(
                'Fallo SMTP activación paciente: envío no completado.'
            );

            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerContextoCorreoPaciente(
        string $clvCita
    ): ?array {
        $sql = "SELECT
                    c.FechaCita,
                    c.HraInicioCita,
                    c.DuracionAplicadaMin,
                    s.NombreServicio,
                    co.NombreCons,
                    u.CorreoUsu,
                    TRIM(CONCAT(
                        COALESCE(perp.NombrePer, ''),
                        ' ',
                        COALESCE(perp.ApPatPer, ''),
                        ' ',
                        COALESCE(perp.ApMatPer, '')
                    )) AS NombrePaciente,
                    TRIM(CONCAT(
                        COALESCE(perpsi.NombrePer, ''),
                        ' ',
                        COALESCE(perpsi.ApPatPer, ''),
                        ' ',
                        COALESCE(perpsi.ApMatPer, '')
                    )) AS NombrePsicologo
                FROM cita c
                INNER JOIN servicios s ON s.ClvServ = c.ClvServ
                INNER JOIN consultorio co ON co.ClvCons = c.ClvCons
                INNER JOIN paciente pac ON pac.ClvPac = c.ClvPac
                INNER JOIN usuario u ON u.ClvUsu = pac.ClvUsu
                INNER JOIN persona perp ON perp.ClvPer = u.ClvPer
                INNER JOIN psicologo psi ON psi.ClvPsi = c.ClvPsi
                INNER JOIN usuario upsi ON upsi.ClvUsu = psi.ClvUsu
                INNER JOIN persona perpsi ON perpsi.ClvPer = upsi.ClvPer
                WHERE c.ClvCita = :cita
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cita' => $clvCita]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerContextoCorreoPacientePorUsuario(
        string $clvUsu
    ): ?array {
        $sql = "SELECT c.ClvCita
                FROM cita c
                INNER JOIN paciente p ON p.ClvPac = c.ClvPac
                WHERE p.ClvUsu = :clvUsu
                ORDER BY c.FechaRegistroCita DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvUsu' => $clvUsu]);
        $clvCita = $stmt->fetchColumn();

        if (!is_string($clvCita) || $clvCita === '') {
            return null;
        }

        return $this->obtenerContextoCorreoPaciente($clvCita);
    }
}
