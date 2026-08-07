<?php

namespace App\Services;

use App\Core\Model;
use App\Models\Usuario;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Gestión administrativa de pacientes por el rol CONSULTORIO.
 * Solo conteos/flags; sin contenido clínico.
 * ClvCons siempre desde contexto autenticado (nunca del cliente).
 */
class GestionPacienteConsultorioService extends Model
{
    private const LIMITE_POR_PAGINA = 20;

    private Usuario $usuarioModel;

    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        $this->usuarioModel = new Usuario($this->db);
    }

    /**
     * Paciente del consultorio si:
     * - paciente.ClvCons = consultorio, o
     * - tiene al menos una cita en el consultorio, o
     * - tiene historial_clinico en el consultorio.
     */
    public function perteneceAlAmbito(
        string $clvPac,
        string $clvCons
    ): bool {
        $sql = "SELECT 1
                FROM paciente pac
                INNER JOIN usuario usu ON usu.ClvUsu = pac.ClvUsu
                WHERE pac.ClvPac = :clvPac
                  AND usu.RolUsu = 'PACIENTE'
                  AND (
                        pac.ClvCons = :clvCons
                     OR EXISTS (
                            SELECT 1 FROM cita c
                            WHERE c.ClvPac = pac.ClvPac
                              AND c.ClvCons = :clvCons2
                     )
                     OR EXISTS (
                            SELECT 1 FROM historial_clinico hc
                            WHERE hc.ClvPac = pac.ClvPac
                              AND hc.ClvCons = :clvCons3
                     )
                  )
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPac' => trim($clvPac),
            'clvCons' => trim($clvCons),
            'clvCons2' => trim($clvCons),
            'clvCons3' => trim($clvCons),
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>
     */
    public function resumenDependencias(
        string $clvPac,
        string $clvCons
    ): array {
        if (!$this->perteneceAlAmbito($clvPac, $clvCons)) {
            throw new RuntimeException(
                'El paciente no pertenece al ámbito de este consultorio.'
            );
        }

        $pac = $this->obtenerPacienteBasico($clvPac);
        $clvPacReal = (string) $pac['ClvPac'];
        $conteos = $this->contarDependenciasCompletas($clvPacReal, $clvCons);
        $tieneActividadHistorica = $this->esActividadHistorica($conteos);
        $exclusivo = $this->esExclusivoDelConsultorio($clvPacReal, $clvCons);
        $pendiente = $this->esPendienteActivacion($pac);
        $estadoUsu = (int) $pac['EstadoUsu'];
        $estadoPac = (int) $pac['EstadoActivoPac'];
        $activo = $estadoUsu === 1 && $estadoPac === 1;

        $puedeEliminar = !$tieneActividadHistorica
            && (int) ($conteos['totalSolicitudesPrivacidad'] ?? 0) === 0;

        return array_merge($conteos, [
            'ClvPac' => $clvPacReal,
            'ClvUsu' => (string) $pac['ClvUsu'],
            'ClvPer' => (string) $pac['ClvPer'],
            'ClvConsPaciente' => (string) ($pac['ClvCons'] ?? ''),
            'EstadoUsu' => $estadoUsu,
            'EstadoActivoPac' => $estadoPac,
            'RequiereCambioContrasena' => (int) ($pac['RequiereCambioContrasena'] ?? 0),
            'FechaRegistroPac' => (string) ($pac['FechaRegistroPac'] ?? ''),
            'NombrePer' => (string) ($pac['NombrePer'] ?? ''),
            'ApPatPer' => (string) ($pac['ApPatPer'] ?? ''),
            'ApMatPer' => (string) ($pac['ApMatPer'] ?? ''),
            'CorreoUsu' => (string) ($pac['CorreoUsu'] ?? ''),
            'TelefonoUsu' => (string) ($pac['TelefonoUsu'] ?? ''),
            'pendienteActivacion' => $pendiente,
            'tieneActividadHistorica' => $tieneActividadHistorica,
            'puedeEliminarFisicamente' => $puedeEliminar,
            'exclusivoDelConsultorio' => $exclusivo,
            'puedeInactivar' => $activo && !$pendiente && $exclusivo,
            'puedeReactivar' => !$activo && !$pendiente && $exclusivo,
            'motivoBloqueoInactivacion' => $exclusivo
                ? ''
                : 'La cuenta del paciente no es exclusiva de este consultorio '
                    . '(EstadoUsu/EstadoActivoPac son globales). '
                    . 'No se implementa baja institucional sin relación explícita.',
            'tieneCitas' => (int) $conteos['totalCitasGlobales'] > 0,
            'tieneExpediente' => (int) $conteos['totalExpedientes'] > 0,
        ]);
    }

    /**
     * @param array{
     *   q?: string,
     *   estado?: string,
     *   actividad?: string,
     *   pagina?: int,
     *   limite?: int
     * } $filtros
     * @return array{items: list<array<string,mixed>>, total: int, pagina: int, limite: int, totalPaginas: int, filtros: array}
     */
    public function listar(string $clvCons, array $filtros = []): array
    {
        $clvCons = trim($clvCons);
        $q = trim((string) ($filtros['q'] ?? ''));
        $estado = strtolower(trim((string) ($filtros['estado'] ?? 'todos')));
        $actividad = strtolower(trim((string) ($filtros['actividad'] ?? 'todos')));
        $limite = (int) ($filtros['limite'] ?? self::LIMITE_POR_PAGINA);
        $limite = max(5, min(50, $limite > 0 ? $limite : self::LIMITE_POR_PAGINA));
        $pagina = max(1, (int) ($filtros['pagina'] ?? 1));

        $ambito = $this->sqlAmbitoConsultorio('pac');
        $where = ["usu.RolUsu = 'PACIENTE'", $ambito];
        $params = [
            'clvCons' => $clvCons,
            'clvCons2' => $clvCons,
            'clvCons3' => $clvCons,
        ];

        if ($q !== '') {
            $where[] = '(
                pac.ClvPac LIKE :q
                OR usu.CorreoUsu LIKE :q
                OR per.NombrePer LIKE :q
                OR per.ApPatPer LIKE :q
                OR per.ApMatPer LIKE :q
                OR CONCAT_WS(\' \', per.NombrePer, per.ApPatPer, per.ApMatPer) LIKE :q
            )';
            $params['q'] = '%' . $q . '%';
        }

        if ($estado === 'activo') {
            $where[] = 'usu.EstadoUsu = 1 AND pac.EstadoActivoPac = 1';
        } elseif ($estado === 'inactivo') {
            $where[] = '(usu.EstadoUsu = 0 OR pac.EstadoActivoPac = 0)';
        }

        if ($actividad === 'sin_actividad') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM cita c0 WHERE c0.ClvPac = pac.ClvPac)';
            $where[] = 'NOT EXISTS (SELECT 1 FROM historial_clinico h0 WHERE h0.ClvPac = pac.ClvPac)';
        } elseif ($actividad === 'con_citas') {
            $where[] = 'EXISTS (
                SELECT 1 FROM cita c1
                WHERE c1.ClvPac = pac.ClvPac AND c1.ClvCons = :actCons
            )';
            $params['actCons'] = $clvCons;
        } elseif ($actividad === 'con_expediente') {
            $where[] = 'EXISTS (
                SELECT 1 FROM historial_clinico h1
                WHERE h1.ClvPac = pac.ClvPac AND h1.ClvCons = :actExp
            )';
            $params['actExp'] = $clvCons;
        }

        $whereSql = implode(' AND ', $where);

        $sqlCount = "SELECT COUNT(*)
                     FROM paciente pac
                     INNER JOIN usuario usu ON usu.ClvUsu = pac.ClvUsu
                     INNER JOIN persona per ON per.ClvPer = usu.ClvPer
                     WHERE {$whereSql}";

        $stmtCount = $this->db->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();
        $totalPaginas = max(1, (int) ceil(max(0, $total) / $limite));
        if ($pagina > $totalPaginas) {
            $pagina = $totalPaginas;
        }
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT
                    pac.ClvPac,
                    pac.ClvUsu,
                    pac.ClvCons,
                    pac.FechaRegistroPac,
                    pac.EstadoActivoPac,
                    usu.CorreoUsu,
                    usu.TelefonoUsu,
                    usu.EstadoUsu,
                    usu.RequiereCambioContrasena,
                    per.NombrePer,
                    per.ApPatPer,
                    per.ApMatPer,
                    (
                        SELECT COUNT(*)
                        FROM cita cg
                        WHERE cg.ClvPac = pac.ClvPac
                    ) AS TotalCitasGlobales,
                    (
                        SELECT COUNT(*)
                        FROM cita cc
                        WHERE cc.ClvPac = pac.ClvPac
                          AND cc.ClvCons = :cntCons
                    ) AS TotalCitasConsultorio,
                    EXISTS (
                        SELECT 1
                        FROM historial_clinico hc
                        WHERE hc.ClvPac = pac.ClvPac
                    ) AS TieneExpediente,
                    EXISTS (
                        SELECT 1
                        FROM historial_clinico hx
                        WHERE hx.ClvPac = pac.ClvPac
                          AND hx.ClvCons <> :cntCons2
                          AND hx.ClvCons IS NOT NULL
                          AND hx.ClvCons <> ''
                    ) AS TieneHistorialOtroCons
                FROM paciente pac
                INNER JOIN usuario usu ON usu.ClvUsu = pac.ClvUsu
                INNER JOIN persona per ON per.ClvPer = usu.ClvPer
                WHERE {$whereSql}
                ORDER BY pac.FechaRegistroPac DESC, pac.ClvPac ASC
                LIMIT {$limite} OFFSET {$offset}";

        $paramsList = $params;
        $paramsList['cntCons'] = $clvCons;
        $paramsList['cntCons2'] = $clvCons;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($paramsList);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($filas as $fila) {
            $totalGlobales = (int) ($fila['TotalCitasGlobales'] ?? 0);
            $totalCons = (int) ($fila['TotalCitasConsultorio'] ?? 0);
            $tieneExpediente = (int) ($fila['TieneExpediente'] ?? 0) === 1;
            $estadoUsu = (int) ($fila['EstadoUsu'] ?? 0);
            $estadoPac = (int) ($fila['EstadoActivoPac'] ?? 0);
            $activo = $estadoUsu === 1 && $estadoPac === 1;
            $pendiente = $estadoUsu === 0
                && (int) ($fila['RequiereCambioContrasena'] ?? 0) === 1;
            $tieneActividad = $totalGlobales > 0 || $tieneExpediente;

            $clvPacItem = (string) $fila['ClvPac'];
            $afiliacion = trim((string) ($fila['ClvCons'] ?? ''));
            $exclusivo = (
                ($afiliacion === '' || strcasecmp($afiliacion, $clvCons) === 0)
                && $totalGlobales === $totalCons
                && (int) ($fila['TieneHistorialOtroCons'] ?? 0) === 0
            );

            $items[] = [
                'ClvPac' => $clvPacItem,
                'ClvUsu' => (string) $fila['ClvUsu'],
                'NombreCompleto' => trim(implode(' ', array_filter([
                    (string) ($fila['NombrePer'] ?? ''),
                    (string) ($fila['ApPatPer'] ?? ''),
                    (string) ($fila['ApMatPer'] ?? ''),
                ]))),
                'CorreoUsu' => (string) ($fila['CorreoUsu'] ?? ''),
                'TelefonoUsu' => (string) ($fila['TelefonoUsu'] ?? ''),
                'EstadoUsu' => $estadoUsu,
                'EstadoActivoPac' => $estadoPac,
                'FechaRegistroPac' => (string) ($fila['FechaRegistroPac'] ?? ''),
                'TotalCitasGlobales' => $totalGlobales,
                'TotalCitasConsultorio' => $totalCons,
                'TieneExpediente' => $tieneExpediente,
                'ExpedienteEtiqueta' => $tieneExpediente ? 'Sí' : 'No',
                'EstadoEtiqueta' => $pendiente
                    ? 'Pendiente activación'
                    : ($activo ? 'Activo' : 'Inactivo'),
                'pendienteActivacion' => $pendiente,
                'puedeEliminarFisicamente' => !$tieneActividad,
                'puedeInactivar' => $activo && !$pendiente && $exclusivo,
                'puedeReactivar' => !$activo && !$pendiente && $exclusivo,
                'exclusivoDelConsultorio' => $exclusivo,
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'pagina' => $pagina,
            'limite' => $limite,
            'totalPaginas' => $totalPaginas,
            'filtros' => [
                'q' => $q,
                'estado' => $estado,
                'actividad' => $actividad,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerFicha(string $clvPac, string $clvCons): array
    {
        return $this->resumenDependencias($clvPac, $clvCons);
    }

    /**
     * Whitelist: NombrePer, ApPatPer, ApMatPer, TelefonoUsu.
     * Correo NO se cambia aquí (CuentaService / verificación del titular).
     *
     * @param array<string, mixed> $datos
     * @return array{ok: bool, mensaje: string, codigo?: string}
     */
    public function actualizarAdministrativo(
        string $clvPac,
        string $clvCons,
        array $datos
    ): array {
        try {
            $this->db->beginTransaction();

            $pac = $this->bloquearPacienteEnAmbito($clvPac, $clvCons);
            $clvPer = (string) $pac['ClvPer'];
            $clvUsu = (string) $pac['ClvUsu'];

            $nombre = trim((string) ($datos['NombrePer'] ?? ''));
            $apPat = trim((string) ($datos['ApPatPer'] ?? ''));
            $apMat = trim((string) ($datos['ApMatPer'] ?? ''));
            $telefono = preg_replace('/\D+/', '', (string) ($datos['TelefonoUsu'] ?? '')) ?? '';

            if ($nombre === '' || $apPat === '') {
                throw new RuntimeException('Nombre y apellido paterno son obligatorios.');
            }

            if (strlen($telefono) < 10 || strlen($telefono) > 15) {
                throw new RuntimeException('Ingresa un teléfono válido (mínimo 10 dígitos).');
            }

            $stmtPer = $this->db->prepare(
                'UPDATE persona
                 SET NombrePer = :nombre,
                     ApPatPer = :apPat,
                     ApMatPer = :apMat
                 WHERE ClvPer = :clvPer'
            );
            $stmtPer->execute([
                'nombre' => mb_substr($nombre, 0, 50),
                'apPat' => mb_substr($apPat, 0, 50),
                'apMat' => mb_substr($apMat, 0, 50),
                'clvPer' => $clvPer,
            ]);

            $stmtUsu = $this->db->prepare(
                'UPDATE usuario
                 SET TelefonoUsu = :telefono
                 WHERE ClvUsu = :clvUsu
                   AND RolUsu = \'PACIENTE\''
            );
            $stmtUsu->execute([
                'telefono' => mb_substr($telefono, 0, 10),
                'clvUsu' => $clvUsu,
            ]);

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' => 'Los datos administrativos se actualizaron correctamente. '
                    . 'El correo no se modifica desde este módulo.',
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'codigo' => 'ERROR',
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    /**
     * Inactivación solo si la cuenta es exclusiva de este consultorio.
     * EstadoUsu y EstadoActivoPac son globales: no se tocan si hay vínculo con otros.
     *
     * @return array{ok: bool, mensaje: string, codigo?: string, advertencia?: string}
     */
    public function inactivar(string $clvPac, string $clvCons): array
    {
        try {
            $this->db->beginTransaction();

            $pac = $this->bloquearPacienteEnAmbito($clvPac, $clvCons);

            if ($this->esPendienteActivacion($pac)) {
                throw new RuntimeException(
                    'La cuenta aún está pendiente de activación.'
                );
            }

            if (!$this->esExclusivoDelConsultorio((string) $pac['ClvPac'], $clvCons)) {
                throw new RuntimeException(
                    'No es seguro inactivar esta cuenta desde el consultorio: '
                    . 'EstadoUsu/EstadoActivoPac son globales y el paciente tiene '
                    . 'relación con otro consultorio. Se requiere una baja institucional '
                    . 'explícita (no implementada; no se creó tabla nueva).'
                );
            }

            if ((int) $pac['EstadoUsu'] === 0 && (int) $pac['EstadoActivoPac'] === 0) {
                throw new RuntimeException('El paciente ya está inactivo.');
            }

            $citasFuturas = $this->contarCitasFuturasProgramadas(
                (string) $pac['ClvPac'],
                $clvCons
            );

            $this->db->prepare(
                'UPDATE paciente SET EstadoActivoPac = 0 WHERE ClvPac = :clvPac'
            )->execute(['clvPac' => (string) $pac['ClvPac']]);

            $this->db->prepare(
                "UPDATE usuario
                 SET EstadoUsu = 0
                 WHERE ClvUsu = :clvUsu AND RolUsu = 'PACIENTE'"
            )->execute(['clvUsu' => (string) $pac['ClvUsu']]);

            $this->db->commit();

            $resultado = [
                'ok' => true,
                'mensaje' =>
                    'El paciente fue inactivado. Se conserva el historial.',
            ];

            if ($citasFuturas > 0) {
                $resultado['advertencia'] =
                    'Tiene ' . $citasFuturas
                    . ' cita(s) futura(s) programada(s). No se cancelaron automáticamente.';
            }

            return $resultado;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'codigo' => 'ERROR',
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, mensaje: string, codigo?: string}
     */
    public function reactivar(string $clvPac, string $clvCons): array
    {
        try {
            $this->db->beginTransaction();

            $pac = $this->bloquearPacienteEnAmbito($clvPac, $clvCons);

            if ($this->esPendienteActivacion($pac)) {
                throw new RuntimeException(
                    'La cuenta aún no ha sido activada por el paciente.'
                );
            }

            if (!$this->esExclusivoDelConsultorio((string) $pac['ClvPac'], $clvCons)) {
                throw new RuntimeException(
                    'No es seguro reactivar esta cuenta desde el consultorio: '
                    . 'la cuenta no es exclusiva de este consultorio.'
                );
            }

            if ((int) $pac['EstadoUsu'] === 1 && (int) $pac['EstadoActivoPac'] === 1) {
                throw new RuntimeException('El paciente ya está activo.');
            }

            $this->db->prepare(
                'UPDATE paciente SET EstadoActivoPac = 1 WHERE ClvPac = :clvPac'
            )->execute(['clvPac' => (string) $pac['ClvPac']]);

            $stmtUsu = $this->db->prepare(
                "UPDATE usuario
                 SET EstadoUsu = 1
                 WHERE ClvUsu = :clvUsu
                   AND RolUsu = 'PACIENTE'
                   AND RequiereCambioContrasena = 0"
            );
            $stmtUsu->execute(['clvUsu' => (string) $pac['ClvUsu']]);

            if ($stmtUsu->rowCount() < 1) {
                throw new RuntimeException(
                    'No fue posible reactivar la cuenta del paciente.'
                );
            }

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' => 'El paciente fue reactivado correctamente.',
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'codigo' => 'ERROR',
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    /**
     * DELETE físico solo sin actividad GLOBAL (cualquier consultorio).
     *
     * @return array{ok: bool, mensaje: string, codigo?: string}
     */
    public function eliminarSinActividad(string $clvPac, string $clvCons): array
    {
        try {
            $this->db->beginTransaction();

            $pac = $this->bloquearPacienteEnAmbito($clvPac, $clvCons);
            $clvPacReal = (string) $pac['ClvPac'];
            $clvUsu = (string) $pac['ClvUsu'];
            $clvPer = (string) $pac['ClvPer'];

            $conteos = $this->contarDependenciasCompletas($clvPacReal, $clvCons);

            if ((int) $conteos['totalCitasGlobales'] > 0) {
                throw new RuntimeException(
                    'Este paciente tiene citas registradas (en cualquier consultorio) '
                    . 'y no puede eliminarse definitivamente.'
                );
            }

            if ((int) $conteos['totalExpedientes'] > 0) {
                throw new RuntimeException(
                    'Este paciente tiene expediente clínico y no puede eliminarse.'
                );
            }

            if ($this->esActividadHistorica($conteos)) {
                throw new RuntimeException(
                    'Este paciente tiene información histórica relacionada y no puede '
                    . 'eliminarse. Usa inactivar si corresponde.'
                );
            }

            if ((int) $conteos['totalSolicitudesPrivacidad'] > 0) {
                throw new RuntimeException(
                    'Este paciente tiene solicitudes de privacidad. No puede eliminarse.'
                );
            }

            $this->eliminarActivacionesUsuario($clvUsu);
            $this->eliminarRecuperacionesUsuario($clvUsu);
            $this->eliminarNotificacionesUsuario($clvUsu);
            $this->eliminarConsentimientosUsuario($clvUsu);
            $this->limpiarInvitacionesComoInvitador($clvUsu);
            $this->limpiarCorreosCitaUsuario($clvUsu);
            $this->limpiarIncidenciasUsuario($clvUsu);

            $stmtPac = $this->db->prepare(
                'DELETE FROM paciente WHERE ClvPac = :clvPac'
            );
            $stmtPac->execute(['clvPac' => $clvPacReal]);

            if ($stmtPac->rowCount() < 1) {
                throw new RuntimeException(
                    'No fue posible eliminar al paciente. Puede que ya haya sido eliminado.'
                );
            }

            if (!$this->usuarioTieneOtrasRelaciones($clvUsu)) {
                $stmtUsu = $this->db->prepare(
                    "DELETE FROM usuario WHERE ClvUsu = :clvUsu AND RolUsu = 'PACIENTE'"
                );
                $stmtUsu->execute(['clvUsu' => $clvUsu]);

                if ($stmtUsu->rowCount() > 0 && !$this->personaTieneOtrosUsuarios($clvPer)) {
                    $clvDir = $this->obtenerClvDirPersona($clvPer);
                    $this->db->prepare(
                        'DELETE FROM persona WHERE ClvPer = :clvPer'
                    )->execute(['clvPer' => $clvPer]);

                    if ($clvDir !== null && $clvDir !== '') {
                        $this->eliminarDireccionSiHuerfana($clvDir);
                    }
                }
            }

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' => 'El registro del paciente fue eliminado correctamente.',
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'codigo' => 'ERROR',
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    private function sqlAmbitoConsultorio(string $alias = 'pac'): string
    {
        return "(
            {$alias}.ClvCons = :clvCons
            OR EXISTS (
                SELECT 1 FROM cita c_amb
                WHERE c_amb.ClvPac = {$alias}.ClvPac
                  AND c_amb.ClvCons = :clvCons2
            )
            OR EXISTS (
                SELECT 1 FROM historial_clinico hc_amb
                WHERE hc_amb.ClvPac = {$alias}.ClvPac
                  AND hc_amb.ClvCons = :clvCons3
            )
        )";
    }

    /**
     * Exclusivo = sin citas en otro ClvCons y afiliación null o igual a este.
     */
    private function esExclusivoDelConsultorio(
        string $clvPac,
        string $clvCons
    ): bool {
        $pac = $this->obtenerPacienteBasico($clvPac);
        $afiliacion = trim((string) ($pac['ClvCons'] ?? ''));

        if ($afiliacion !== '' && strcasecmp($afiliacion, $clvCons) !== 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM cita
             WHERE ClvPac = :clvPac
               AND ClvCons <> :clvCons'
        );
        $stmt->execute([
            'clvPac' => $clvPac,
            'clvCons' => $clvCons,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }

        if ($this->tablaExiste('historial_clinico')) {
            $stmtH = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM historial_clinico
                 WHERE ClvPac = :clvPac
                   AND ClvCons <> :clvCons'
            );
            $stmtH->execute([
                'clvPac' => $clvPac,
                'clvCons' => $clvCons,
            ]);
            if ((int) $stmtH->fetchColumn() > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, int> $conteos
     */
    private function esActividadHistorica(array $conteos): bool
    {
        return (int) ($conteos['totalCitasGlobales'] ?? 0) > 0
            || (int) ($conteos['totalExpedientes'] ?? 0) > 0
            || (int) ($conteos['totalSeguimientos'] ?? 0) > 0
            || (int) ($conteos['totalDiagnosticos'] ?? 0) > 0
            || (int) ($conteos['totalApreciaciones'] ?? 0) > 0
            || (int) ($conteos['totalReactivos'] ?? 0) > 0
            || (int) ($conteos['totalEvoluciones'] ?? 0) > 0
            || (int) ($conteos['totalRecomendaciones'] ?? 0) > 0
            || (int) ($conteos['totalAdicciones'] ?? 0) > 0
            || (int) ($conteos['totalAntecedentes'] ?? 0) > 0
            || (int) ($conteos['totalExamenMental'] ?? 0) > 0
            || (int) ($conteos['totalEstadoPsicologico'] ?? 0) > 0
            || (int) ($conteos['totalPsicoanamnesis'] ?? 0) > 0
            || (int) ($conteos['totalVidaSocial'] ?? 0) > 0;
    }

    /**
     * @return array<string, int>
     */
    private function contarDependenciasCompletas(
        string $clvPac,
        string $clvCons
    ): array {
        $globales = $this->contarTabla('cita', 'ClvPac', $clvPac);

        $stmtCons = $this->db->prepare(
            'SELECT COUNT(*) FROM cita WHERE ClvPac = :p AND ClvCons = :c'
        );
        $stmtCons->execute(['p' => $clvPac, 'c' => $clvCons]);
        $enCons = (int) $stmtCons->fetchColumn();

        return [
            'totalCitasGlobales' => $globales,
            'totalCitasConsultorio' => $enCons,
            'citasFuturasConsultorio' => $this->contarCitasFuturasProgramadas(
                $clvPac,
                $clvCons
            ),
            'totalExpedientes' => $this->contarTabla(
                'historial_clinico',
                'ClvPac',
                $clvPac
            ),
            'totalSeguimientos' => $this->contarViaHistorial(
                'seguimiento_sesion',
                $clvPac
            ),
            'totalDiagnosticos' => $this->contarViaSeguimiento(
                'diagnostico_seguimiento',
                $clvPac
            ),
            'totalApreciaciones' => $this->contarViaHistorial(
                'apreciacion_diagnostica',
                $clvPac
            ),
            'totalReactivos' => $this->contarViaHistorial(
                'reactivo_psicologico',
                $clvPac
            ),
            'totalEvoluciones' => $this->contarViaSeguimiento(
                'evolucion_sesion',
                $clvPac
            ),
            'totalRecomendaciones' => $this->contarViaSeguimiento(
                'recomendacion_sesion',
                $clvPac
            ),
            'totalAdicciones' => $this->contarViaHistorial('adiccion', $clvPac),
            'totalAntecedentes' =>
                $this->contarViaHistorial('antecedente_familiar', $clvPac)
                + $this->contarViaHistorial('antecedente_patologico', $clvPac),
            'totalExamenMental' => $this->contarViaHistorial(
                'examen_mental_inicial',
                $clvPac
            ),
            'totalEstadoPsicologico' => $this->contarViaHistorial(
                'estado_psicologico_inicial',
                $clvPac
            ),
            'totalPsicoanamnesis' => $this->contarViaHistorial(
                'psicoanamnesis_familiar',
                $clvPac
            ),
            'totalVidaSocial' => $this->contarViaHistorial(
                'vida_social_laboral',
                $clvPac
            ),
            'totalSolicitudesPrivacidad' => $this->contarTablaSiExiste(
                'solicitud_privacidad',
                'ClvPac',
                $clvPac
            ),
        ];
    }

    private function contarViaHistorial(string $tabla, string $clvPac): int
    {
        if (!$this->tablaExiste($tabla)) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                FROM {$tabla} t
                INNER JOIN historial_clinico hc
                    ON hc.ClvHist COLLATE utf8mb4_unicode_ci
                     = t.ClvHist COLLATE utf8mb4_unicode_ci
                WHERE hc.ClvPac = :clvPac";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvPac' => $clvPac]);

        return (int) $stmt->fetchColumn();
    }

    private function contarViaSeguimiento(string $tabla, string $clvPac): int
    {
        if (!$this->tablaExiste($tabla)) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                FROM {$tabla} t
                INNER JOIN seguimiento_sesion ss
                    ON ss.ClvSeg COLLATE utf8mb4_unicode_ci
                     = t.ClvSeg COLLATE utf8mb4_unicode_ci
                INNER JOIN historial_clinico hc
                    ON hc.ClvHist COLLATE utf8mb4_unicode_ci
                     = ss.ClvHist COLLATE utf8mb4_unicode_ci
                WHERE hc.ClvPac = :clvPac";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvPac' => $clvPac]);

        return (int) $stmt->fetchColumn();
    }

    private function contarCitasFuturasProgramadas(
        string $clvPac,
        string $clvCons
    ): int {
        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvPac = :clvPac
                  AND ClvCons = :clvCons
                  AND EstadoCita = 'PROGRAMADA'
                  AND (
                        FechaCita > CURDATE()
                     OR (FechaCita = CURDATE() AND HraInicioCita >= CURTIME())
                  )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPac' => $clvPac,
            'clvCons' => $clvCons,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerPacienteBasico(string $clvPac): array
    {
        $sql = "SELECT
                    pac.ClvPac,
                    pac.ClvUsu,
                    pac.ClvCons,
                    pac.FechaRegistroPac,
                    pac.EstadoActivoPac,
                    usu.EstadoUsu,
                    usu.RequiereCambioContrasena,
                    usu.CorreoUsu,
                    usu.TelefonoUsu,
                    usu.ClvPer,
                    per.NombrePer,
                    per.ApPatPer,
                    per.ApMatPer
                FROM paciente pac
                INNER JOIN usuario usu ON usu.ClvUsu = pac.ClvUsu
                INNER JOIN persona per ON per.ClvPer = usu.ClvPer
                WHERE pac.ClvPac = :clvPac
                  AND usu.RolUsu = 'PACIENTE'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvPac' => trim($clvPac)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            throw new RuntimeException('El paciente no fue encontrado.');
        }

        return $fila;
    }

    /**
     * @return array<string, mixed>
     */
    private function bloquearPacienteEnAmbito(
        string $clvPac,
        string $clvCons
    ): array {
        if (!$this->perteneceAlAmbito($clvPac, $clvCons)) {
            throw new RuntimeException(
                'El paciente no pertenece al ámbito de este consultorio.'
            );
        }

        $sql = "SELECT
                    pac.ClvPac,
                    pac.ClvUsu,
                    pac.ClvCons,
                    pac.FechaRegistroPac,
                    pac.EstadoActivoPac,
                    usu.EstadoUsu,
                    usu.RequiereCambioContrasena,
                    usu.CorreoUsu,
                    usu.TelefonoUsu,
                    usu.ClvPer,
                    per.NombrePer,
                    per.ApPatPer,
                    per.ApMatPer
                FROM paciente pac
                INNER JOIN usuario usu ON usu.ClvUsu = pac.ClvUsu
                INNER JOIN persona per ON per.ClvPer = usu.ClvPer
                WHERE pac.ClvPac = :clvPac
                  AND usu.RolUsu = 'PACIENTE'
                LIMIT 1
                FOR UPDATE";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvPac' => trim($clvPac)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            throw new RuntimeException('El paciente no fue encontrado.');
        }

        return $fila;
    }

    private function esPendienteActivacion(array $pac): bool
    {
        return (int) ($pac['EstadoUsu'] ?? 0) === 0
            && (int) ($pac['RequiereCambioContrasena'] ?? 0) === 1;
    }

    private function contarTabla(string $tabla, string $campo, string $valor): int
    {
        $sql = "SELECT COUNT(*) FROM {$tabla} WHERE {$campo} = :valor";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['valor' => $valor]);

        return (int) $stmt->fetchColumn();
    }

    private function contarTablaSiExiste(
        string $tabla,
        string $campo,
        string $valor
    ): int {
        if (!$this->tablaExiste($tabla) || !$this->columnaExiste($tabla, $campo)) {
            return 0;
        }

        return $this->contarTabla($tabla, $campo, $valor);
    }

    private function tablaExiste(string $tabla): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tabla'
        );
        $stmt->execute(['tabla' => $tabla]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnaExiste(string $tabla, string $columna): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tabla
               AND COLUMN_NAME = :columna'
        );
        $stmt->execute([
            'tabla' => $tabla,
            'columna' => $columna,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function eliminarActivacionesUsuario(string $clvUsu): void
    {
        if (!$this->tablaExiste('activacion_cuenta')) {
            return;
        }
        $this->db->prepare(
            'DELETE FROM activacion_cuenta WHERE ClvUsu = :clvUsu'
        )->execute(['clvUsu' => $clvUsu]);
    }

    private function eliminarRecuperacionesUsuario(string $clvUsu): void
    {
        if (!$this->tablaExiste('recuperacion_password')) {
            return;
        }
        $this->db->prepare(
            'DELETE FROM recuperacion_password WHERE ClvUsu = :clvUsu'
        )->execute(['clvUsu' => $clvUsu]);
    }

    private function eliminarNotificacionesUsuario(string $clvUsu): void
    {
        if (!$this->tablaExiste('notificacion')) {
            return;
        }
        $campo = $this->columnaExiste('notificacion', 'ClvUsuDestino')
            ? 'ClvUsuDestino'
            : ($this->columnaExiste('notificacion', 'ClvUsu') ? 'ClvUsu' : null);
        if ($campo === null) {
            return;
        }
        $this->db->prepare(
            "DELETE FROM notificacion WHERE {$campo} = :clvUsu"
        )->execute(['clvUsu' => $clvUsu]);
    }

    private function eliminarConsentimientosUsuario(string $clvUsu): void
    {
        if (!$this->tablaExiste('consentimiento_datos_personales')) {
            return;
        }
        $this->db->prepare(
            'DELETE FROM consentimiento_datos_personales WHERE ClvUsu = :clvUsu'
        )->execute(['clvUsu' => $clvUsu]);
    }

    private function limpiarInvitacionesComoInvitador(string $clvUsu): void
    {
        if (
            !$this->tablaExiste('activacion_cuenta')
            || !$this->columnaExiste('activacion_cuenta', 'ClvUsuInvitador')
        ) {
            return;
        }
        $this->db->prepare(
            'UPDATE activacion_cuenta
             SET ClvUsuInvitador = NULL
             WHERE ClvUsuInvitador = :clvUsu'
        )->execute(['clvUsu' => $clvUsu]);
    }

    private function limpiarCorreosCitaUsuario(string $clvUsu): void
    {
        if (
            !$this->tablaExiste('correo_cita')
            || !$this->columnaExiste('correo_cita', 'ClvUsuDestino')
        ) {
            return;
        }
        $this->db->prepare(
            'DELETE FROM correo_cita WHERE ClvUsuDestino = :clvUsu'
        )->execute(['clvUsu' => $clvUsu]);
    }

    private function limpiarIncidenciasUsuario(string $clvUsu): void
    {
        if (!$this->tablaExiste('incidencia_soporte')) {
            return;
        }
        if ($this->columnaExiste('incidencia_soporte', 'ClvUsuSolicitante')) {
            $this->db->prepare(
                'UPDATE incidencia_soporte
                 SET ClvUsuSolicitante = NULL
                 WHERE ClvUsuSolicitante = :clvUsu'
            )->execute(['clvUsu' => $clvUsu]);
        }
        if ($this->columnaExiste('incidencia_soporte', 'ClvUsuAtencion')) {
            $this->db->prepare(
                'UPDATE incidencia_soporte
                 SET ClvUsuAtencion = NULL
                 WHERE ClvUsuAtencion = :clvUsu'
            )->execute(['clvUsu' => $clvUsu]);
        }
    }

    private function usuarioTieneOtrasRelaciones(string $clvUsu): bool
    {
        foreach (
            [
                ['paciente', 'ClvUsu'],
                ['psicologo', 'ClvUsu'],
                ['consultorio_usuario', 'ClvUsu'],
            ] as [$tabla, $campo]
        ) {
            if ($this->tablaExiste($tabla) && $this->contarTabla($tabla, $campo, $clvUsu) > 0) {
                return true;
            }
        }

        return false;
    }

    private function personaTieneOtrosUsuarios(string $clvPer): bool
    {
        return $this->contarTabla('usuario', 'ClvPer', $clvPer) > 0;
    }

    private function obtenerClvDirPersona(string $clvPer): ?string
    {
        if (!$this->columnaExiste('persona', 'ClvDir')) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT ClvDir FROM persona WHERE ClvPer = :clvPer LIMIT 1'
        );
        $stmt->execute(['clvPer' => $clvPer]);
        $valor = $stmt->fetchColumn();

        return $valor !== false && $valor !== null && (string) $valor !== ''
            ? (string) $valor
            : null;
    }

    private function eliminarDireccionSiHuerfana(string $clvDir): void
    {
        if (!$this->tablaExiste('direccion')) {
            return;
        }
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM persona WHERE ClvDir = :clvDir'
        );
        $stmt->execute(['clvDir' => $clvDir]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }
        $this->db->prepare(
            'DELETE FROM direccion WHERE ClvDir = :clvDir'
        )->execute(['clvDir' => $clvDir]);
    }
}
