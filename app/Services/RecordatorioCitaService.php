<?php

namespace App\Services;

use App\Config\Config;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Recordatorios de cita próxima (campana) — Fase 3D.
 * Ledger/idempotencia: correo_cita UNIQUE (ClvCita, TipoCorreo, RolDestinatario)
 * con TipoCorreo = RECORDATORIO_24H. No usa refs ocultas en MensajeNotif.
 */
class RecordatorioCitaService
{
    public const TIPO_CORREO = 'RECORDATORIO_24H';
    public const TIPO_NOTIF = 'RECORDATORIO';
    public const TITULO_PACIENTE = 'Tu cita está próxima';
    public const TITULO_PSICOLOGO = 'Cita próxima';
    public const HORAS_DEFAULT = 24;
    public const HORAS_MIN = 1;
    public const HORAS_MAX = 168;
    public const MOTIVO_CITA_INICIADA = 'CITA_YA_INICIADA';

    private NotificacionService $notificacionService;

    public function __construct(?NotificacionService $notificacionService = null)
    {
        $this->notificacionService = $notificacionService
            ?? new NotificacionService();
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
     * Horas de anticipación desde entorno (no GET/POST).
     */
    public function horasRecordatorio(): int
    {
        $raw = trim((string) Config::get('CITA_RECORDATORIO_HORAS', ''));

        if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
            return self::HORAS_DEFAULT;
        }

        $horas = (int) $raw;

        if ($horas < self::HORAS_MIN || $horas > self::HORAS_MAX) {
            return self::HORAS_DEFAULT;
        }

        return $horas;
    }

    /**
     * FechaProgramada del recordatorio según ventana.
     * Si ya está en ventana: ahora. Si falta más: inicio - horas.
     * null si la cita ya inició.
     */
    public function fechaProgramadaRecordatorio(
        DateTimeImmutable $inicio,
        ?DateTimeImmutable $ahora = null
    ): ?DateTimeImmutable {
        $ahora ??= $this->ahora();

        if ($ahora >= $inicio) {
            return null;
        }

        $umbral = $inicio->modify('-' . $this->horasRecordatorio() . ' hours');

        return $ahora >= $umbral ? $ahora : $umbral;
    }

    public function estaEnVentanaRecordatorio(
        DateTimeImmutable $inicio,
        ?DateTimeImmutable $ahora = null
    ): bool {
        $ahora ??= $this->ahora();

        if ($ahora >= $inicio) {
            return false;
        }

        $umbral = $inicio->modify('-' . $this->horasRecordatorio() . ' hours');

        return $ahora >= $umbral;
    }

    /**
     * @param array<string, mixed> $contexto
     */
    public function construirMensajePaciente(array $contexto): string
    {
        $fecha = $this->formatearFecha($contexto);
        $hora = $this->formatearHora($contexto);
        $especialista = $this->nombreLimpio(
            (string) ($contexto['NombrePsicologo'] ?? ''),
            'tu especialista'
        );

        return 'Recuerda que tienes una cita el '
            . $fecha
            . ' a las '
            . $hora
            . ' con '
            . $especialista
            . '.';
    }

    /**
     * @param array<string, mixed> $contexto
     */
    public function construirMensajePsicologo(array $contexto): string
    {
        $fecha = $this->formatearFecha($contexto);
        $hora = $this->formatearHora($contexto);
        $paciente = $this->nombreLimpio(
            (string) ($contexto['NombrePaciente'] ?? ''),
            'tu paciente'
        );

        return 'Tienes una cita próxima con '
            . $paciente
            . ' el '
            . $fecha
            . ' a las '
            . $hora
            . '.';
    }

    /**
     * Crea la notificación de campana una sola vez (primer claim).
     * Destinatario solo desde ClvUsuDestino del ledger / BD.
     *
     * @param array<string, mixed> $filaCorreo
     * @param array<string, mixed> $contexto
     */
    public function crearNotificacionCampana(
        array $filaCorreo,
        array $contexto
    ): bool {
        $rol = strtoupper(trim((string) ($filaCorreo['RolDestinatario'] ?? '')));
        $clvUsu = trim((string) ($filaCorreo['ClvUsuDestino'] ?? ''));

        if ($clvUsu === '') {
            return false;
        }

        if ($rol === 'PACIENTE') {
            return $this->notificacionService->crearParaUsuario(
                $clvUsu,
                self::TITULO_PACIENTE,
                $this->construirMensajePaciente($contexto),
                self::TIPO_NOTIF
            );
        }

        if ($rol === 'PSICOLOGO') {
            return $this->notificacionService->crearParaUsuario(
                $clvUsu,
                self::TITULO_PSICOLOGO,
                $this->construirMensajePsicologo($contexto),
                self::TIPO_NOTIF
            );
        }

        // No CONSULTORIO ni ADMINISTRADOR en este evento.
        return false;
    }

    /**
     * @param array<string, mixed> $contexto
     */
    public function formatearFecha(array $contexto): string
    {
        $inicio = $this->fechaHoraInicioDesdeContexto($contexto);

        if ($inicio === null) {
            return 'fecha por confirmar';
        }

        return $inicio->format('d/m/Y');
    }

    /**
     * @param array<string, mixed> $contexto
     */
    public function formatearHora(array $contexto): string
    {
        $inicio = $this->fechaHoraInicioDesdeContexto($contexto);

        if ($inicio === null) {
            return 'hora por confirmar';
        }

        return $inicio->format('H:i');
    }

    /**
     * @param array<string, mixed> $contexto
     */
    public function fechaHoraInicioDesdeContexto(array $contexto): ?DateTimeImmutable
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

    private function nombreLimpio(string $nombre, string $fallback): string
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');

        if ($nombre === '') {
            return $fallback;
        }

        // Texto plano para MensajeNotif (sin HTML).
        return mb_substr($nombre, 0, 120);
    }
}
