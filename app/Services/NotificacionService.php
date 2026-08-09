<?php

namespace App\Services;

use App\Config\Database;
use App\Models\Cita;
use App\Models\Notificacion;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Notificaciones internas in-app.
 *
 * Limitaciones de BD (sin alterar esquema):
 * - No existe columna ClvCita ni RutaNotif.
 * - MensajeNotif es texto limpio orientado al usuario (sin metadatos).
 * - TipoNotif ENUM real: CITA, CANCELACION, RECORDATORIO,
 *   CUENTA, PSICOLOGO, SISTEMA, OTRA.
 * - ASISTIDA/INASISTENCIA usan TipoNotif = CITA (título/mensaje diferencian).
 *
 * Payloads por destinatario (no reutilizar un arreglo nominal):
 * - Psicólogo: paciente + servicio + fecha + hora
 * - Paciente: psicólogo + servicio + fecha + hora
 * - Consultorio: solo operativo (psicólogo, servicio, fecha, hora);
 *   la consulta no une persona/paciente.
 * - Administrador: solo eventos administrativos reales.
 *
 * notificarResultadoCita() es estricto (lanza excepción) para participar
 * en la misma transacción PDO del UPDATE de asistencia.
 * Otros eventos de cita pueden seguir usando helpers auxiliares.
 */
class NotificacionService
{
    private Notificacion $notificacionModel;

    private const TIPOS_PERMITIDOS = [
        'CITA',
        'CANCELACION',
        'RECORDATORIO',
        'CUENTA',
        'PSICOLOGO',
        'SISTEMA',
        'OTRA'
    ];

    public function __construct()
    {
        $this->notificacionModel = new Notificacion();
    }

    /*
    =====================================
          CREAR PARA USUARIO
    =====================================
    */

    public function crearParaUsuario(
        string $clvUsu,
        string $titulo,
        string $mensaje,
        string $tipo,
        ?string $clvCita = null
    ): bool {
        // Sin columna ClvCita en notificacion: el parámetro se ignora.
        // Idempotencia de asistencia: transición atómica PROGRAMADA → final.
        $clvUsu = trim($clvUsu);
        $titulo = trim($titulo);
        $mensaje = trim($mensaje);
        $tipo = strtoupper(trim($tipo));

        $this->validarDatos($clvUsu, $titulo, $mensaje, $tipo);

        $clave = ClaveService::generar(
            'notificacion',
            'ClvNotif',
            'NOT'
        );

        $creada = $this->notificacionModel->crear([
            'ClvNotif' => $clave,
            'TituloNotif' => $titulo,
            'MensajeNotif' => $mensaje,
            'TipoNotif' => $tipo,
            'ClvUsu' => $clvUsu
        ]);

        if (!$creada) {
            throw new RuntimeException(
                'No fue posible crear la notificación.'
            );
        }

        return true;
    }

    /**
     * Compatibilidad con llamadas genéricas previas.
     */
    public function crear(array $datos): array
    {
        $clvUsuario = trim((string) ($datos['ClvUsu'] ?? ''));
        $titulo = trim((string) ($datos['TituloNotif'] ?? ''));
        $mensaje = trim((string) ($datos['MensajeNotif'] ?? ''));
        $tipo = strtoupper(trim(
            (string) ($datos['TipoNotif'] ?? 'SISTEMA')
        ));

        $this->crearParaUsuario(
            $clvUsuario,
            $titulo,
            $mensaje,
            $tipo
        );

        return [
            'TituloNotif' => $titulo,
            'MensajeNotif' => $mensaje,
            'TipoNotif' => $tipo,
            'ClvUsu' => $clvUsuario
        ];
    }

    /*
    =====================================
         EVENTOS DE CITA (AUXILIARES)
    =====================================
    */

    public function notificarCitaCreadaPorPaciente(
        string $clvCita
    ): void {
        $payloadPsi = $this->obtenerPayloadParaPsicologo($clvCita);
        $payloadPac = $this->obtenerPayloadParaPaciente($clvCita);

        if ($payloadPsi !== null) {
            $this->intentarCrearParaUsuario(
                $payloadPsi['ClvUsuDestinatario'],
                'Nueva cita agendada',
                "{$payloadPsi['NombrePaciente']} agendó una cita de "
                . "{$payloadPsi['NombreServicio']} para el "
                . "{$payloadPsi['Fecha']} a las {$payloadPsi['Hora']}.",
                'CITA',
                $clvCita,
                'cita_creada_psicologo'
            );
        } else {
            $this->registrarFalloAuxiliar(
                'cita_creada_psicologo_sin_payload',
                $clvCita
            );
        }

        if ($payloadPac !== null) {
            $this->intentarCrearParaUsuario(
                $payloadPac['ClvUsuDestinatario'],
                'Cita confirmada',
                "Tu cita con {$payloadPac['NombrePsicologo']} fue programada "
                . "para el {$payloadPac['Fecha']} a las {$payloadPac['Hora']}.",
                'CITA',
                $clvCita,
                'cita_creada_paciente'
            );
        } else {
            $this->registrarFalloAuxiliar(
                'cita_creada_paciente_sin_payload',
                $clvCita
            );
        }

        $this->notificarNuevaCitaConsultorio($clvCita, 'paciente');
    }

    public function notificarCitaCreadaPorPsicologo(
        string $clvCita
    ): void {
        $payloadPac = $this->obtenerPayloadParaPaciente($clvCita);

        if ($payloadPac !== null) {
            $this->intentarCrearParaUsuario(
                $payloadPac['ClvUsuDestinatario'],
                'Nueva cita programada',
                "{$payloadPac['NombrePsicologo']} programó una cita de "
                . "{$payloadPac['NombreServicio']} para el "
                . "{$payloadPac['Fecha']} a las {$payloadPac['Hora']}.",
                'CITA',
                $clvCita,
                'cita_creada_por_psicologo_paciente'
            );
        } else {
            $this->registrarFalloAuxiliar(
                'cita_creada_por_psicologo_sin_payload',
                $clvCita
            );
        }

        $this->notificarNuevaCitaConsultorio($clvCita, 'psicologo');
    }

    public function notificarCancelacionPaciente(
        string $clvCita
    ): void {
        $payloadPsi = $this->obtenerPayloadParaPsicologo($clvCita);
        $payloadPac = $this->obtenerPayloadParaPaciente($clvCita);

        if ($payloadPsi !== null) {
            $this->intentarCrearParaUsuario(
                $payloadPsi['ClvUsuDestinatario'],
                'Cita cancelada',
                "{$payloadPsi['NombrePaciente']} canceló la cita de "
                . "{$payloadPsi['NombreServicio']} del "
                . "{$payloadPsi['Fecha']} a las {$payloadPsi['Hora']}.",
                'CANCELACION',
                $clvCita,
                'cancelacion_psicologo'
            );
        }

        if ($payloadPac !== null) {
            $this->intentarCrearParaUsuario(
                $payloadPac['ClvUsuDestinatario'],
                'Cancelación confirmada',
                "Tu cita con {$payloadPac['NombrePsicologo']} "
                . 'fue cancelada correctamente.',
                'CANCELACION',
                $clvCita,
                'cancelacion_paciente'
            );
        }

        $this->notificarCancelacionConsultorio($clvCita);
    }

    /**
     * Notificaciones de resultado dentro de la misma transacción del UPDATE.
     * Lanza excepción ante cualquier fallo (para provocar ROLLBACK).
     */
    public function notificarResultadoCita(
        string $clvCita,
        string $resultado
    ): void {
        $resultado = strtoupper(trim($resultado));
        $clvCita = trim($clvCita);

        if (!in_array($resultado, ['ASISTIDA', 'INASISTENCIA'], true)) {
            throw new InvalidArgumentException(
                'El resultado de la cita no es válido.'
            );
        }

        $payloadPac = $this->obtenerPayloadParaPaciente($clvCita);

        if ($payloadPac === null) {
            throw new RuntimeException(
                'No fue posible preparar la notificación del paciente.'
            );
        }

        if ($resultado === 'ASISTIDA') {
            $this->crearParaUsuario(
                $payloadPac['ClvUsuDestinatario'],
                'Asistencia registrada',
                "Tu cita del {$payloadPac['Fecha']} a las "
                . "{$payloadPac['Hora']} fue registrada como asistida.",
                'CITA'
            );
        } else {
            $this->crearParaUsuario(
                $payloadPac['ClvUsuDestinatario'],
                'Inasistencia registrada',
                "Tu cita del {$payloadPac['Fecha']} a las "
                . "{$payloadPac['Hora']} fue registrada como inasistencia.",
                'CITA'
            );
        }

        $payloadPsi = $this->obtenerPayloadParaPsicologo($clvCita);

        if ($payloadPsi === null) {
            throw new RuntimeException(
                'No fue posible preparar la notificación del psicólogo.'
            );
        }

        $nombrePaciente = trim(
            (string) ($payloadPsi['NombrePaciente'] ?? '')
        );
        $nombrePaciente = preg_replace('/\s+/u', ' ', $nombrePaciente) ?? '';
        $nombrePaciente = $nombrePaciente !== '' ? $nombrePaciente : 'el paciente';

        if ($resultado === 'ASISTIDA') {
            $this->crearParaUsuario(
                $payloadPsi['ClvUsuDestinatario'],
                'Asistencia confirmada',
                "Registraste la cita de {$nombrePaciente} como asistida.",
                'CITA'
            );
        } else {
            $this->crearParaUsuario(
                $payloadPsi['ClvUsuDestinatario'],
                'Inasistencia confirmada',
                "Registraste la cita de {$nombrePaciente} como inasistencia.",
                'CITA'
            );
        }

        $this->crearNotificacionesResultadoConsultorio(
            $clvCita,
            $resultado
        );
    }

    /*
    =====================================
      NOTIFICACIONES CONSULTORIO (ANÓNIMAS)
    =====================================
    */

    /**
     * @param string $origen 'paciente'|'psicologo'
     */
    public function notificarNuevaCitaConsultorio(
        string $clvCita,
        string $origen = 'paciente'
    ): void {
        try {
            $op = $this->obtenerPayloadOperativoConsultorio($clvCita);

            if ($op === null) {
                $this->registrarFalloAuxiliar(
                    'consultorio_nueva_cita_sin_payload',
                    $clvCita
                );
                return;
            }

            if ($origen === 'psicologo') {
                $titulo = 'Nueva cita programada';
                $mensaje =
                    "{$op['NombrePsicologo']} programó una cita de "
                    . "{$op['NombreServicio']} para el {$op['Fecha']} "
                    . "a las {$op['Hora']}.";
            } else {
                $titulo = 'Nueva cita en la agenda';
                $mensaje =
                    "Se agendó una cita de {$op['NombreServicio']} con "
                    . "{$op['NombrePsicologo']} para el {$op['Fecha']} "
                    . "a las {$op['Hora']}.";
            }

            $this->crearParaUsuariosConsultorio(
                $op['ClvCons'],
                $titulo,
                $mensaje,
                'CITA',
                $clvCita
            );
        } catch (\Throwable $e) {
            $this->registrarFalloAuxiliar(
                'consultorio_nueva_cita',
                $clvCita,
                $e
            );
        }
    }

    public function notificarCancelacionConsultorio(
        string $clvCita
    ): void {
        try {
            $op = $this->obtenerPayloadOperativoConsultorio($clvCita);

            if ($op === null) {
                $this->registrarFalloAuxiliar(
                    'consultorio_cancelacion_sin_payload',
                    $clvCita
                );
                return;
            }

            $this->crearParaUsuariosConsultorio(
                $op['ClvCons'],
                'Cita cancelada',
                "Se canceló la cita de {$op['NombreServicio']} con "
                . "{$op['NombrePsicologo']}, programada para el "
                . "{$op['Fecha']} a las {$op['Hora']}.",
                'CANCELACION',
                $clvCita
            );
        } catch (\Throwable $e) {
            $this->registrarFalloAuxiliar(
                'consultorio_cancelacion',
                $clvCita,
                $e
            );
        }
    }

    public function notificarResultadoCitaConsultorio(
        string $clvCita,
        string $resultado
    ): void {
        try {
            $this->crearNotificacionesResultadoConsultorio(
                $clvCita,
                $resultado
            );
        } catch (\Throwable $e) {
            $this->registrarFalloAuxiliar(
                'consultorio_resultado',
                $clvCita,
                $e
            );
        }
    }

    /**
     * Avisos operativos al consultorio (estrictos, sin capturar).
     */
    private function crearNotificacionesResultadoConsultorio(
        string $clvCita,
        string $resultado
    ): void {
        $resultado = strtoupper(trim($resultado));

        if (
            !in_array(
                $resultado,
                ['ASISTIDA', 'INASISTENCIA'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'El resultado de la cita no es válido.'
            );
        }

        $op = $this->obtenerPayloadOperativoConsultorio($clvCita);

        if ($op === null) {
            throw new RuntimeException(
                'No fue posible preparar el aviso del consultorio.'
            );
        }

        if ($resultado === 'ASISTIDA') {
            $titulo = 'Asistencia registrada';
            $mensaje =
                "{$op['NombrePsicologo']} registró como asistida la cita de "
                . "{$op['NombreServicio']} del {$op['Fecha']} "
                . "a las {$op['Hora']}.";
        } else {
            $titulo = 'Inasistencia registrada';
            $mensaje =
                "{$op['NombrePsicologo']} registró una inasistencia en la cita de "
                . "{$op['NombreServicio']} del {$op['Fecha']} "
                . "a las {$op['Hora']}.";
        }

        $destinatarios = $this->obtenerUsuariosConsultorioActivos(
            (string) $op['ClvCons']
        );

        foreach ($destinatarios as $clvUsu) {
            $this->crearParaUsuario(
                $clvUsu,
                $titulo,
                $mensaje,
                'CITA'
            );
        }
    }

    /*
    =====================================
         NOTIFICACIONES ADMINISTRADOR
    =====================================
    */

    public function notificarEspecialistaActivoConsultorio(
        string $clvCons,
        string $nombreEspecialista = ''
    ): void {
        try {
            $nombre = trim($nombreEspecialista);
            $mensaje = $nombre !== ''
                ? "{$nombre} activó su cuenta."
                : 'Un especialista activó su cuenta.';

            $this->crearParaUsuariosConsultorio(
                $clvCons,
                'Especialista activado',
                $mensaje,
                'PSICOLOGO',
                ''
            );
        } catch (\Throwable $e) {
            $this->registrarFalloAuxiliar(
                'especialista_activado_consultorio',
                $clvCons,
                $e
            );
        }
    }

    public function notificarPacienteActivoAPsicologo(
        string $clvUsuPaciente,
        ?string $clvUsuInvitador = null
    ): void {
        try {
            $destinatario = trim((string) $clvUsuInvitador);

            if ($destinatario === '') {
                $sql = "SELECT
                            COALESCE(
                                a.ClvUsuInvitador,
                                psi.ClvUsu
                            ) AS ClvUsuPsi
                        FROM paciente pac
                        INNER JOIN cita c
                            ON c.ClvPac = pac.ClvPac
                        INNER JOIN psicologo psi
                            ON psi.ClvPsi = c.ClvPsi
                        LEFT JOIN activacion_cuenta a
                            ON a.ClvUsu = pac.ClvUsu
                           AND a.TipoActivacion = 'ALTA_PACIENTE'
                           AND a.Estado = 'USADA'
                        WHERE pac.ClvUsu = :clvUsu
                        ORDER BY
                            c.FechaRegistroCita ASC,
                            a.IdActivacion DESC
                        LIMIT 1";

                $stmt = Database::connect()->prepare($sql);
                $stmt->execute(['clvUsu' => trim($clvUsuPaciente)]);
                $fila = $stmt->fetch(PDO::FETCH_ASSOC);
                $destinatario = (string) ($fila['ClvUsuPsi'] ?? '');
            }

            if ($destinatario === '') {
                return;
            }

            $this->intentarCrearParaUsuario(
                $destinatario,
                'Paciente activado',
                'El paciente invitado activó su cuenta.',
                'CUENTA',
                '',
                'paciente_activado_psicologo'
            );
        } catch (\Throwable $e) {
            $this->registrarFalloAuxiliar(
                'paciente_activado_psicologo',
                $clvUsuPaciente,
                $e
            );
        }
    }

    public function notificarAdministradoresSistema(
        string $titulo,
        string $mensaje,
        string $tipo = 'SISTEMA'
    ): void {
        try {
            $tipo = strtoupper(trim($tipo));

            if (
                !in_array(
                    $tipo,
                    ['SISTEMA', 'CUENTA', 'PSICOLOGO', 'OTRA'],
                    true
                )
            ) {
                $tipo = 'SISTEMA';
            }

            foreach ($this->obtenerAdministradoresActivos() as $clvUsu) {
                $this->crearParaUsuario(
                    $clvUsu,
                    $titulo,
                    $mensaje,
                    $tipo
                );
            }
        } catch (\Throwable $e) {
            $this->registrarFalloAuxiliar(
                'administrador_sistema',
                'N/A',
                $e
            );
        }
    }

    /**
     * Notificación operativa a usuarios CONSULTORIO activos (sin cita).
     */
    public function notificarConsultorioSistema(
        string $clvCons,
        string $titulo,
        string $mensaje,
        string $tipo = 'SISTEMA'
    ): void {
        try {
            $clvCons = trim($clvCons);
            $titulo = trim($titulo);
            $mensaje = trim($mensaje);
            $tipo = strtoupper(trim($tipo));

            if ($clvCons === '' || $titulo === '' || $mensaje === '') {
                return;
            }

            if (
                !in_array(
                    $tipo,
                    ['SISTEMA', 'CUENTA', 'PSICOLOGO', 'OTRA'],
                    true
                )
            ) {
                $tipo = 'SISTEMA';
            }

            foreach ($this->obtenerUsuariosConsultorioActivos($clvCons) as $clvUsu) {
                $this->crearParaUsuario(
                    $clvUsu,
                    $titulo,
                    $mensaje,
                    $tipo
                );
            }
        } catch (\Throwable $e) {
            $this->registrarFalloAuxiliar(
                'consultorio_sistema',
                trim($clvCons) !== '' ? trim($clvCons) : 'N/A',
                $e
            );
        }
    }

    public function notificarAdministradoresNuevoConsultorio(
        string $nombreConsultorio
    ): void {
        $nombre = trim($nombreConsultorio);

        if ($nombre === '') {
            $nombre = 'un consultorio';
        }

        $this->notificarAdministradoresSistema(
            'Nuevo consultorio registrado',
            "Se registró el consultorio \"{$nombre}\" en la plataforma.",
            'SISTEMA'
        );
    }

    public function notificarAdministradoresCambioEstatusConsultorio(
        string $nombreConsultorio,
        string $nuevoEstatus
    ): void {
        $nombre = trim($nombreConsultorio) ?: 'Un consultorio';
        $estatus = strtoupper(trim($nuevoEstatus));

        if ($estatus === 'ACTIVO') {
            $titulo = 'Consultorio activado';
            $mensaje =
                "El consultorio \"{$nombre}\" fue activado.";
        } elseif ($estatus === 'INACTIVO') {
            $titulo = 'Consultorio desactivado';
            $mensaje =
                "El consultorio \"{$nombre}\" fue desactivado.";
        } else {
            $titulo = 'Cambio de estatus de consultorio';
            $mensaje =
                "El consultorio \"{$nombre}\" cambió a estatus {$estatus}.";
        }

        $this->notificarAdministradoresSistema(
            $titulo,
            $mensaje,
            'SISTEMA'
        );
    }

    public function notificarAdministradoresAccesoRestablecido(
        string $nombreConsultorio
    ): void {
        $nombre = trim($nombreConsultorio) ?: 'un consultorio';

        $this->notificarAdministradoresSistema(
            'Acceso de consultorio restablecido',
            "Se restableció el acceso de la cuenta responsable del consultorio \"{$nombre}\".",
            'CUENTA'
        );
    }

    /*
    =====================================
              CONSULTAS
    =====================================
    */

    public function listarPorUsuario(
        string $clvUsuario,
        int $limite = 50,
        int $offset = 0
    ): array {
        $clvUsuario = trim($clvUsuario);

        if ($clvUsuario === '') {
            throw new InvalidArgumentException(
                'La clave del usuario es obligatoria.'
            );
        }

        return $this->notificacionModel->listarPorUsuario(
            $clvUsuario,
            $limite,
            $offset
        );
    }

    public function obtenerRecientes(
        string $clvUsuario,
        int $limite = 5
    ): array {
        $clvUsuario = trim($clvUsuario);

        if ($clvUsuario === '') {
            throw new InvalidArgumentException(
                'La clave del usuario es obligatoria.'
            );
        }

        return $this->notificacionModel
            ->listarRecientesPorUsuario(
                $clvUsuario,
                $limite
            );
    }

    public function contarNoLeidas(string $clvUsuario): int
    {
        $clvUsuario = trim($clvUsuario);

        if ($clvUsuario === '') {
            return 0;
        }

        return $this->notificacionModel->contarNoLeidas(
            $clvUsuario
        );
    }

    public function obtenerPorClave(
        string $clave,
        string $clvUsuario
    ): ?array {
        return $this->notificacionModel
            ->obtenerPorClaveYUsuario(
                trim($clave),
                trim($clvUsuario)
            );
    }

    public function marcarComoLeida(
        string $clave,
        string $clvUsuario
    ): bool {
        $clave = trim($clave);
        $clvUsuario = trim($clvUsuario);

        if ($clave === '' || $clvUsuario === '') {
            throw new InvalidArgumentException(
                'La notificación y el usuario son obligatorios.'
            );
        }

        $notificacion = $this->notificacionModel
            ->obtenerPorClaveYUsuario($clave, $clvUsuario);

        if (!$notificacion) {
            throw new RuntimeException(
                'La notificación no existe o no pertenece al usuario.'
            );
        }

        if ((int) ($notificacion['LeidaNotif'] ?? 0) === 1) {
            return true;
        }

        return $this->notificacionModel->marcarLeida(
            $clave,
            $clvUsuario
        );
    }

    public function marcarTodasComoLeidas(string $clvUsuario): int
    {
        $clvUsuario = trim($clvUsuario);

        if ($clvUsuario === '') {
            throw new InvalidArgumentException(
                'La clave del usuario es obligatoria.'
            );
        }

        return $this->notificacionModel->marcarTodasLeidas(
            $clvUsuario
        );
    }

    public function eliminar(
        string $clave,
        string $clvUsuario
    ): bool {
        $clave = trim($clave);
        $clvUsuario = trim($clvUsuario);

        if ($clave === '' || $clvUsuario === '') {
            throw new InvalidArgumentException(
                'La notificación y el usuario son obligatorios.'
            );
        }

        $notificacion = $this->notificacionModel
            ->obtenerPorClaveYUsuario($clave, $clvUsuario);

        if (!$notificacion) {
            throw new RuntimeException(
                'La notificación no existe o no pertenece al usuario.'
            );
        }

        return $this->notificacionModel->eliminar(
            $clave,
            $clvUsuario
        );
    }

    /*
    =====================================
              PRIVADOS
    =====================================
    */

    /**
     * Payload exclusivo para el psicólogo (incluye paciente).
     *
     * @return array{
     *   ClvUsuDestinatario: string,
     *   NombrePaciente: string,
     *   NombreServicio: string,
     *   Fecha: string,
     *   Hora: string
     * }|null
     */
    private function obtenerPayloadParaPsicologo(string $clvCita): ?array
    {
        $clvCita = trim($clvCita);

        if ($clvCita === '') {
            return null;
        }

        $sql = "
            SELECT
                c.FechaCita,
                c.HraInicioCita,
                psi.ClvUsu AS ClvUsuPsicologo,
                COALESCE(s.NombreServicio, 'servicio') AS NombreServicio,
                TRIM(CONCAT(
                    COALESCE(perPac.NombrePer, ''),
                    ' ',
                    COALESCE(perPac.ApPatPer, '')
                )) AS NombrePaciente
            FROM cita c
            INNER JOIN paciente pac
                ON pac.ClvPac = c.ClvPac
            INNER JOIN persona perPac
                ON perPac.ClvPer = pac.ClvPer
            INNER JOIN psicologo psi
                ON psi.ClvPsi = c.ClvPsi
            LEFT JOIN servicios s
                ON s.ClvServ = c.ClvServ
            WHERE c.ClvCita = :clvCita
            LIMIT 1
        ";

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute(['clvCita' => $clvCita]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $destinatario = trim((string) ($fila['ClvUsuPsicologo'] ?? ''));

        if ($destinatario === '') {
            return null;
        }

        return [
            'ClvUsuDestinatario' => $destinatario,
            'NombrePaciente' => trim(
                (string) ($fila['NombrePaciente'] ?? '')
            ) ?: 'Un paciente',
            'NombreServicio' => trim(
                (string) ($fila['NombreServicio'] ?? '')
            ) ?: 'servicio',
            'Fecha' => $this->formatearFecha(
                (string) ($fila['FechaCita'] ?? '')
            ),
            'Hora' => $this->formatearHora(
                (string) ($fila['HraInicioCita'] ?? '')
            )
        ];
    }

    /**
     * Payload exclusivo para el paciente (sin ClvPac expuesto al mensaje).
     *
     * @return array{
     *   ClvUsuDestinatario: string,
     *   NombrePsicologo: string,
     *   NombreServicio: string,
     *   Fecha: string,
     *   Hora: string
     * }|null
     */
    private function obtenerPayloadParaPaciente(string $clvCita): ?array
    {
        $clvCita = trim($clvCita);

        if ($clvCita === '') {
            return null;
        }

        $sql = "
            SELECT
                c.FechaCita,
                c.HraInicioCita,
                pac.ClvUsu AS ClvUsuPaciente,
                " . ((new Cita())->columnasResponsableDisponibles()
                    ? 'c.ClvUsuCreador,'
                    : 'NULL AS ClvUsuCreador,') . "
                COALESCE(s.NombreServicio, 'servicio') AS NombreServicio,
                TRIM(CONCAT(
                    COALESCE(perPsi.NombrePer, ''),
                    ' ',
                    COALESCE(perPsi.ApPatPer, '')
                )) AS NombrePsicologo
            FROM cita c
            INNER JOIN paciente pac
                ON pac.ClvPac = c.ClvPac
            INNER JOIN psicologo psi
                ON psi.ClvPsi = c.ClvPsi
            INNER JOIN usuario usuPsi
                ON usuPsi.ClvUsu = psi.ClvUsu
            INNER JOIN persona perPsi
                ON perPsi.ClvPer = usuPsi.ClvPer
            LEFT JOIN servicios s
                ON s.ClvServ = c.ClvServ
            WHERE c.ClvCita = :clvCita
            LIMIT 1
        ";

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute(['clvCita' => $clvCita]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $destinatario = trim((string) ($fila['ClvUsuPaciente'] ?? ''));
        if ($destinatario === '') {
            $destinatario = trim((string) ($fila['ClvUsuCreador'] ?? ''));
        }

        if ($destinatario === '') {
            return null;
        }

        return [
            'ClvUsuDestinatario' => $destinatario,
            'NombrePsicologo' => trim(
                (string) ($fila['NombrePsicologo'] ?? '')
            ) ?: 'tu especialista',
            'NombreServicio' => trim(
                (string) ($fila['NombreServicio'] ?? '')
            ) ?: 'servicio',
            'Fecha' => $this->formatearFecha(
                (string) ($fila['FechaCita'] ?? '')
            ),
            'Hora' => $this->formatearHora(
                (string) ($fila['HraInicioCita'] ?? '')
            )
        ];
    }

    /**
     * Payload operativo del consultorio: sin paciente ni datos nominales.
     * No hace JOIN con paciente/persona del paciente.
     *
     * @return array{
     *   ClvCons: string,
     *   NombrePsicologo: string,
     *   NombreServicio: string,
     *   Fecha: string,
     *   Hora: string,
     *   EstadoCita: string
     * }|null
     */
    private function obtenerPayloadOperativoConsultorio(
        string $clvCita
    ): ?array {
        $clvCita = trim($clvCita);

        if ($clvCita === '') {
            return null;
        }

        $sql = "
            SELECT
                c.ClvCons,
                c.FechaCita,
                c.HraInicioCita,
                c.EstadoCita,
                COALESCE(s.NombreServicio, 'servicio') AS NombreServicio,
                TRIM(CONCAT(
                    COALESCE(perPsi.NombrePer, ''),
                    ' ',
                    COALESCE(perPsi.ApPatPer, '')
                )) AS NombrePsicologo
            FROM cita c
            INNER JOIN psicologo psi
                ON psi.ClvPsi = c.ClvPsi
            INNER JOIN usuario usuPsi
                ON usuPsi.ClvUsu = psi.ClvUsu
            INNER JOIN persona perPsi
                ON perPsi.ClvPer = usuPsi.ClvPer
            LEFT JOIN servicios s
                ON s.ClvServ = c.ClvServ
            WHERE c.ClvCita = :clvCita
            LIMIT 1
        ";

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute(['clvCita' => $clvCita]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $clvCons = trim((string) ($fila['ClvCons'] ?? ''));

        if ($clvCons === '') {
            return null;
        }

        return [
            'ClvCons' => $clvCons,
            'NombrePsicologo' => trim(
                (string) ($fila['NombrePsicologo'] ?? '')
            ) ?: 'el especialista',
            'NombreServicio' => trim(
                (string) ($fila['NombreServicio'] ?? '')
            ) ?: 'servicio',
            'Fecha' => $this->formatearFecha(
                (string) ($fila['FechaCita'] ?? '')
            ),
            'Hora' => $this->formatearHora(
                (string) ($fila['HraInicioCita'] ?? '')
            ),
            'EstadoCita' => trim(
                (string) ($fila['EstadoCita'] ?? '')
            )
        ];
    }

    /**
     * Usuarios CONSULTORIO activos asociados al consultorio.
     *
     * @return list<string>
     */
    private function obtenerUsuariosConsultorioActivos(
        string $clvCons
    ): array {
        $clvCons = trim($clvCons);

        if ($clvCons === '') {
            return [];
        }

        $sql = "
            SELECT DISTINCT u.ClvUsu
            FROM consultorio_usuario cu
            INNER JOIN usuario u
                ON u.ClvUsu = cu.ClvUsu
            WHERE cu.ClvCons = :clvCons
              AND cu.EstatusConsUsu = 'ACTIVO'
              AND u.RolUsu = 'CONSULTORIO'
              AND u.EstadoUsu = 1
        ";

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute(['clvCons' => $clvCons]);

        $claves = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $clv = trim((string) ($fila['ClvUsu'] ?? ''));

            if ($clv !== '' && !isset($claves[$clv])) {
                $claves[$clv] = $clv;
            }
        }

        return array_values($claves);
    }

    /**
     * @return list<string>
     */
    private function obtenerAdministradoresActivos(): array
    {
        $sql = "
            SELECT ClvUsu
            FROM usuario
            WHERE RolUsu = 'ADMINISTRADOR'
              AND EstadoUsu = 1
        ";

        $stmt = Database::connect()->query($sql);
        $filas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $claves = [];

        foreach ($filas as $fila) {
            $clv = trim((string) ($fila['ClvUsu'] ?? ''));

            if ($clv !== '' && !isset($claves[$clv])) {
                $claves[$clv] = $clv;
            }
        }

        return array_values($claves);
    }

    private function crearParaUsuariosConsultorio(
        string $clvCons,
        string $titulo,
        string $mensaje,
        string $tipo,
        string $clvCita
    ): void {
        $destinatarios = $this->obtenerUsuariosConsultorioActivos(
            $clvCons
        );

        foreach ($destinatarios as $clvUsu) {
            $this->intentarCrearParaUsuario(
                $clvUsu,
                $titulo,
                $mensaje,
                $tipo,
                $clvCita,
                'consultorio_destinatario'
            );
        }
    }

    private function intentarCrearParaUsuario(
        string $clvUsu,
        string $titulo,
        string $mensaje,
        string $tipo,
        string $clvCita,
        string $evento
    ): void {
        try {
            $this->crearParaUsuario(
                $clvUsu,
                $titulo,
                $mensaje,
                $tipo,
                $clvCita
            );
        } catch (\Throwable $e) {
            $this->registrarFalloAuxiliar($evento, $clvCita, $e);
        }
    }

    private function registrarFalloAuxiliar(
        string $evento,
        string $clvCita,
        ?\Throwable $e = null
    ): void {
        $clvSegura = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '',
            trim($clvCita)
        ) ?: 'N/A';

        $eventoSeguro = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '',
            $evento
        ) ?: 'evento';

        $tipoError = $e ? $e::class : 'sin_excepcion';

        error_log(
            "Notificacion auxiliar omitida evento={$eventoSeguro} "
            . "ClvCita={$clvSegura} error={$tipoError}"
        );
    }

    private function formatearFecha(?string $fecha): string
    {
        $fecha = trim((string) $fecha);

        if ($fecha === '') {
            return 'fecha pendiente';
        }

        try {
            $dt = new \DateTimeImmutable(
                $fecha,
                new \DateTimeZone('America/Mexico_City')
            );
        } catch (\Throwable $e) {
            return $fecha;
        }

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

        $dia = (int) $dt->format('j');
        $mes = $meses[(int) $dt->format('n')] ?? $dt->format('m');
        $anio = $dt->format('Y');

        return "{$dia} de {$mes} de {$anio}";
    }

    private function formatearHora(?string $hora): string
    {
        $hora = trim((string) $hora);

        if ($hora === '') {
            return 'hora pendiente';
        }

        if (preg_match('/^\d{2}:\d{2}/', $hora)) {
            return substr($hora, 0, 5);
        }

        return $hora;
    }

    private function validarDatos(
        string $clvUsuario,
        string $titulo,
        string $mensaje,
        string $tipo
    ): void {
        if ($clvUsuario === '') {
            throw new InvalidArgumentException(
                'El usuario destinatario es obligatorio.'
            );
        }

        if ($titulo === '') {
            throw new InvalidArgumentException(
                'El título de la notificación es obligatorio.'
            );
        }

        if (mb_strlen($titulo) > 100) {
            throw new InvalidArgumentException(
                'El título no puede exceder 100 caracteres.'
            );
        }

        if ($mensaje === '') {
            throw new InvalidArgumentException(
                'El mensaje de la notificación es obligatorio.'
            );
        }

        if (!in_array($tipo, self::TIPOS_PERMITIDOS, true)) {
            throw new InvalidArgumentException(
                'El tipo de notificación no es válido.'
            );
        }
    }
}
