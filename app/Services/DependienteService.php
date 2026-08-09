<?php

namespace App\Services;

use App\Config\Database;
use App\Models\Paciente;
use App\Models\PacienteResponsable;
use App\Models\Persona;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Alta/edición de pacientes dependientes (sin usuario) a cargo de un PACIENTE.
 * Prefijo persona: PER (misma convención que invitaciones; no migrar P* históricos).
 */
class DependienteService
{
    private PDO $db;
    private Persona $personaModel;
    private Paciente $pacienteModel;
    private PacienteResponsable $relacionModel;
    private PrivacidadService $privacidad;
    private EdadService $edadService;
    private InstalacionConsultorioService $instalacion;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->personaModel = new Persona();
        $this->pacienteModel = new Paciente();
        $this->relacionModel = new PacienteResponsable();
        $this->privacidad = new PrivacidadService();
        $this->edadService = new EdadService();
        $this->instalacion = new InstalacionConsultorioService();
    }

    /**
     * @return array{ok: bool, mensaje: string, idRelacion?: int, clvPac?: string}
     */
    public function crear(string $clvUsuResponsable, array $datos): array
    {
        $clvUsuResponsable = trim($clvUsuResponsable);
        if ($clvUsuResponsable === '') {
            return ['ok' => false, 'mensaje' => 'Sesión no válida.'];
        }

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $apPat = trim((string) ($datos['apPat'] ?? ''));
        $apMat = trim((string) ($datos['apMat'] ?? ''));
        $fechaNac = trim((string) ($datos['fechaNacimiento'] ?? ''));
        $genero = trim((string) ($datos['genero'] ?? ''));
        $parentesco = trim((string) ($datos['parentesco'] ?? ''));
        $esTutor = !empty($datos['EsTutorLegal']) || !empty($datos['esTutorLegal']);

        if ($nombre === '' || $apPat === '' || $parentesco === '') {
            return [
                'ok' => false,
                'mensaje' => 'Nombre, apellido paterno y parentesco son obligatorios.',
            ];
        }

        if (!in_array($genero, ['Masculino', 'Femenino', 'Otro'], true)) {
            return [
                'ok' => false,
                'mensaje' => 'Selecciona un género válido.',
            ];
        }

        // Política general: permite menores y adultos; no reutiliza >=18 del registro.
        $edad = $this->edadService->validarFechaNacimiento($fechaNac, 'general');
        if (empty($edad['ok'])) {
            return [
                'ok' => false,
                'mensaje' => (string) ($edad['mensaje'] ?? 'Fecha de nacimiento inválida.'),
            ];
        }

        $anios = (int) ($edad['edad'] ?? 0);
        if ($anios < 18 && !$esTutor) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Para registrar a un menor debes declarar que eres su tutor legal '
                    . '(declaración del usuario; no verifica la tutela jurídicamente).',
            ];
        }

        $checkboxes = $this->privacidad->validarCheckboxesConsentimiento($datos);
        if (empty($checkboxes['ok'])) {
            return [
                'ok' => false,
                'mensaje' => (string) ($checkboxes['mensaje'] ??
                    'Debes aceptar el Aviso de Privacidad y el consentimiento.'),
            ];
        }

        $clvCons = $this->instalacion->claveUnicaONull();

        $propia = false;
        try {
            $propia = !$this->db->inTransaction();
            if ($propia) {
                $this->db->beginTransaction();
            }

            $clvPer = ClaveService::generar('persona', 'ClvPer', 'PER');
            $clvPac = ClaveService::generar('paciente', 'ClvPac', 'PAC');

            $this->personaModel->crear([
                'ClvPer' => $clvPer,
                'NombrePer' => $nombre,
                'ApPatPer' => $apPat,
                'ApMatPer' => $apMat,
                'FechaNacimiento' => $fechaNac,
                'GeneroPer' => $genero,
            ]);

            $this->pacienteModel->crear([
                'ClvPac' => $clvPac,
                'ClvPer' => $clvPer,
                'ClvUsu' => null,
                'ClvCons' => $clvCons,
            ]);

            $idRelacion = $this->relacionModel->crearRelacion([
                'ClvPac' => $clvPac,
                'ClvUsuResponsable' => $clvUsuResponsable,
                'Parentesco' => $parentesco,
                'EsTutorLegal' => $esTutor ? 1 : 0,
                'PuedeAgendar' => 1,
            ]);

            $consent = $this->privacidad->registrarConsentimientoDependiente(
                $clvUsuResponsable,
                $clvPac,
                $idRelacion,
                $datos
            );

            if (empty($consent['ok'])) {
                throw new RuntimeException(
                    (string) ($consent['mensaje'] ??
                        'No se pudo registrar el consentimiento.')
                );
            }

            if ($propia) {
                $this->db->commit();
            }

            return [
                'ok' => true,
                'mensaje' => 'Dependiente registrado correctamente.',
                'idRelacion' => $idRelacion,
                'clvPac' => $clvPac,
            ];
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' => 'No se pudo registrar al dependiente. Intenta nuevamente.',
            ];
        }
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function editar(
        string $clvUsuResponsable,
        int $idRelacion,
        array $datos
    ): array {
        $clvUsuResponsable = trim($clvUsuResponsable);
        if (!$this->relacionModel->perteneceAResponsable($idRelacion, $clvUsuResponsable)) {
            return [
                'ok' => false,
                'mensaje' => 'No tienes permiso para modificar esta relación.',
            ];
        }

        $rel = $this->relacionModel->obtenerRelacion($idRelacion);
        if ($rel === null) {
            return ['ok' => false, 'mensaje' => 'Relación no encontrada.'];
        }

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $apPat = trim((string) ($datos['apPat'] ?? ''));
        $apMat = trim((string) ($datos['apMat'] ?? ''));
        $fechaNac = trim((string) ($datos['fechaNacimiento'] ?? ''));
        $genero = trim((string) ($datos['genero'] ?? ''));
        $parentesco = trim((string) ($datos['parentesco'] ?? ''));
        $esTutor = !empty($datos['EsTutorLegal']) || !empty($datos['esTutorLegal']);

        if ($nombre === '' || $apPat === '' || $parentesco === '') {
            return [
                'ok' => false,
                'mensaje' => 'Nombre, apellido paterno y parentesco son obligatorios.',
            ];
        }

        if (!in_array($genero, ['Masculino', 'Femenino', 'Otro'], true)) {
            return [
                'ok' => false,
                'mensaje' => 'Selecciona un género válido.',
            ];
        }

        $edad = $this->edadService->validarFechaNacimiento($fechaNac, 'general');
        if (empty($edad['ok'])) {
            return [
                'ok' => false,
                'mensaje' => (string) ($edad['mensaje'] ?? 'Fecha de nacimiento inválida.'),
            ];
        }

        if ((int) ($edad['edad'] ?? 0) < 18 && !$esTutor) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Para un menor debes declarar tutoría legal (declaración del usuario).',
            ];
        }

        $pac = $this->pacienteModel->obtenerPorClaveBasico((string) $rel['ClvPac']);
        if ($pac === null || empty($pac['ClvPer'])) {
            return ['ok' => false, 'mensaje' => 'Paciente no encontrado.'];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "UPDATE persona
                 SET NombrePer = :n,
                     ApPatPer = :ap,
                     ApMatPer = :am,
                     FechaNacimiento = :fn,
                     GeneroPer = :g
                 WHERE ClvPer = :per"
            );
            $stmt->execute([
                'n' => $nombre,
                'ap' => $apPat,
                'am' => $apMat,
                'fn' => $fechaNac,
                'g' => $genero,
                'per' => (string) $pac['ClvPer'],
            ]);

            $okRel = $this->relacionModel->actualizar(
                $idRelacion,
                $clvUsuResponsable,
                [
                    'Parentesco' => $parentesco,
                    'EsTutorLegal' => $esTutor ? 1 : 0,
                ]
            );

            if (!$okRel) {
                throw new RuntimeException('No se pudo actualizar la relación.');
            }

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' => 'Datos del dependiente actualizados.',
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' => 'No se pudo guardar la edición.',
            ];
        }
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function cambiarEstado(
        string $clvUsuResponsable,
        int $idRelacion,
        string $estado
    ): array {
        $ok = $this->relacionModel->cambiarEstado(
            $idRelacion,
            $clvUsuResponsable,
            $estado
        );

        if (!$ok) {
            return [
                'ok' => false,
                'mensaje' => 'No fue posible cambiar el estado de la relación.',
            ];
        }

        return [
            'ok' => true,
            'mensaje' => $estado === 'ACTIVA'
                ? 'Relación reactivada.'
                : 'Relación inactivada. El paciente y su expediente no se eliminan.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(string $clvUsuResponsable): array
    {
        $filas = $this->relacionModel->obtenerPorResponsable($clvUsuResponsable, false);
        foreach ($filas as &$f) {
            $fn = (string) ($f['FechaNacimiento'] ?? '');
            $edad = $fn !== ''
                ? $this->edadService->validarFechaNacimiento($fn, 'general')
                : ['ok' => false];
            $f['Edad'] = !empty($edad['ok']) ? (int) $edad['edad'] : null;
            $f['NombreCompleto'] = trim(
                ((string) ($f['NombrePer'] ?? '')) . ' '
                . ((string) ($f['ApPatPer'] ?? '')) . ' '
                . ((string) ($f['ApMatPer'] ?? ''))
            );
        }
        unset($f);

        return $filas;
    }

    /**
     * Dependientes ACTIVA + PuedeAgendar=1 para el selector de agendar.
     *
     * @return list<array<string, mixed>>
     */
    public function listarParaAgendar(string $clvUsuResponsable): array
    {
        $out = [];
        foreach ($this->listar($clvUsuResponsable) as $f) {
            if (
                strtoupper((string) ($f['EstadoRelacion'] ?? '')) !== 'ACTIVA'
                || (int) ($f['PuedeAgendar'] ?? 0) !== 1
            ) {
                continue;
            }
            $out[] = $f;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function relacionParaAgendar(
        string $clvUsuResponsable,
        string $clvPac
    ): ?array {
        return $this->relacionModel->obtenerParaAgendar(
            $clvPac,
            $clvUsuResponsable
        );
    }
}
