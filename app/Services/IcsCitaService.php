<?php

namespace App\Services;

use App\Config\Database;
use App\Helpers\Helper;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * Genera archivos .ics de cita (UID estable = ClvCita). Sin tabla nueva.
 */
class IcsCitaService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @return array{contenido: string, filename: string}|null
     */
    public function generarParaCita(string $clvCita): ?array
    {
        $clvCita = trim($clvCita);
        if ($clvCita === '' || !preg_match('/^[A-Za-z0-9]{1,10}$/', $clvCita)) {
            return null;
        }

        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.DuracionAplicadaMin,
                    cons.NombreCons,
                    s.NombreServicio,
                    TRIM(CONCAT(
                        COALESCE(psiPer.NombrePer, ''), ' ',
                        COALESCE(psiPer.ApPatPer, ''), ' ',
                        COALESCE(psiPer.ApMatPer, '')
                    )) AS NombrePsicologo,
                    TRIM(CONCAT(
                        COALESCE(pacPer.NombrePer, ''), ' ',
                        COALESCE(pacPer.ApPatPer, ''), ' ',
                        COALESCE(pacPer.ApMatPer, '')
                    )) AS NombrePaciente,
                    d.CalleDir,
                    d.NumExtDir,
                    d.ColoniaDir,
                    d.MunicipioDir,
                    d.EstadoDir,
                    d.CodPostDir
                FROM cita c
                INNER JOIN paciente pac ON pac.ClvPac = c.ClvPac
                INNER JOIN persona pacPer ON pacPer.ClvPer = pac.ClvPer
                INNER JOIN psicologo psi ON psi.ClvPsi = c.ClvPsi
                INNER JOIN usuario psiUsu ON psiUsu.ClvUsu = psi.ClvUsu
                INNER JOIN persona psiPer ON psiPer.ClvPer = psiUsu.ClvPer
                INNER JOIN consultorio cons ON cons.ClvCons = c.ClvCons
                INNER JOIN servicios s ON s.ClvServ = c.ClvServ
                LEFT JOIN direccion d ON d.ClvDir = cons.ClvDir
                WHERE c.ClvCita = :c
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['c' => $clvCita]);
        $cita = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cita) {
            return null;
        }

        $zona = new DateTimeZone('America/Mexico_City');
        $fecha = trim((string) ($cita['FechaCita'] ?? ''));
        $horaIni = $this->normalizarHora((string) ($cita['HraInicioCita'] ?? ''));
        $horaFin = $this->normalizarHora((string) ($cita['HraFinCita'] ?? ''));

        if ($fecha === '' || $horaIni === '') {
            throw new RuntimeException('La cita no tiene fecha/hora válidas para el calendario.');
        }

        $inicio = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . $horaIni,
            $zona
        );
        if (!$inicio) {
            throw new RuntimeException('No se pudo interpretar el inicio de la cita.');
        }

        if ($horaFin !== '') {
            $fin = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $fecha . ' ' . $horaFin,
                $zona
            );
        } else {
            $fin = null;
        }

        if (!$fin) {
            $mins = max(15, (int) ($cita['DuracionAplicadaMin'] ?? 60));
            $fin = $inicio->modify('+' . $mins . ' minutes');
        }

        $uid = $this->escaparTexto($clvCita) . '@psicomatch';
        $summary = $this->escaparTexto(
            'Cita: ' . trim((string) ($cita['NombreServicio'] ?? 'Sesión'))
            . ' — ' . trim((string) ($cita['NombrePsicologo'] ?? ''))
        );
        $description = $this->escaparTexto(
            'Paciente: ' . trim((string) ($cita['NombrePaciente'] ?? '')) . "\\n"
            . 'Especialista: ' . trim((string) ($cita['NombrePsicologo'] ?? '')) . "\\n"
            . 'Servicio: ' . trim((string) ($cita['NombreServicio'] ?? '')) . "\\n"
            . 'Consultorio: ' . trim((string) ($cita['NombreCons'] ?? ''))
        );
        $location = $this->escaparTexto($this->armarDireccion($cita));
        $stamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Ymd\THis\Z');
        $dtStart = $inicio->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $dtEnd = $fin->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $url = Helper::baseUrl('paciente/cita-detalle?cita=' . rawurlencode($clvCita));

        $ics = "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//PsicoMatch//Cita//ES\r\n"
            . "CALSCALE:GREGORIAN\r\n"
            . "METHOD:PUBLISH\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:{$uid}\r\n"
            . "DTSTAMP:{$stamp}\r\n"
            . "DTSTART:{$dtStart}\r\n"
            . "DTEND:{$dtEnd}\r\n"
            . "SUMMARY:{$summary}\r\n"
            . "DESCRIPTION:{$description}\r\n"
            . "LOCATION:{$location}\r\n"
            . "URL:{$url}\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n";

        return [
            'contenido' => $ics,
            'filename' => 'cita-' . preg_replace('/[^A-Za-z0-9_-]/', '', $clvCita) . '.ics',
        ];
    }

    private function normalizarHora(string $hora): string
    {
        $hora = trim($hora);
        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return $hora . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}/', $hora)) {
            return substr($hora, 0, 8);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $cita
     */
    private function armarDireccion(array $cita): string
    {
        $partes = array_filter([
            trim((string) ($cita['CalleDir'] ?? '')),
            trim((string) ($cita['NumExtDir'] ?? '')) !== ''
                ? 'No. ' . trim((string) $cita['NumExtDir'])
                : '',
            trim((string) ($cita['ColoniaDir'] ?? '')),
            trim((string) ($cita['MunicipioDir'] ?? '')),
            trim((string) ($cita['EstadoDir'] ?? '')),
            trim((string) ($cita['CodPostDir'] ?? '')),
            trim((string) ($cita['NombreCons'] ?? '')),
        ], static fn ($v) => $v !== '');

        return implode(', ', $partes);
    }

    private function escaparTexto(string $texto): string
    {
        $texto = str_replace(["\r\n", "\n", "\r"], '\\n', $texto);
        $texto = str_replace(['\\', ',', ';'], ['\\\\', '\\,', '\\;'], $texto);

        return $texto;
    }
}
