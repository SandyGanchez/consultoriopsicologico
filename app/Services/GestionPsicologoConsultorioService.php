<?php

namespace App\Services;

use App\Core\Model;
use App\Models\ActivacionCuenta;
use App\Models\Psicologo;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Ciclo de vida administrativo del especialista desde el consultorio:
 * resumen de dependencias, eliminación física sin actividad,
 * desactivación con historial, reactivación.
 */
class GestionPsicologoConsultorioService extends Model
{
    private Psicologo $psicologoModel;
    private ActivacionCuenta $activacionModel;

    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        $this->psicologoModel = new Psicologo($this->db);
        $this->activacionModel = new ActivacionCuenta($this->db);
    }

    /**
     * Resumen seguro de dependencias (solo conteos, sin contenido clínico).
     * ClvCons debe venir de sesión (llamador autenticado).
     *
     * @return array{
     *   ClvPsi: string,
     *   ClvUsu: string,
     *   ClvPer: string,
     *   EstatusPsi: string,
     *   EstadoUsu: int,
     *   pendienteActivacion: bool,
     *   totalCitas: int,
     *   citasFuturas: int,
     *   citasHistoricas: int,
     *   pacientesRelacionados: int,
     *   expedientes: int,
     *   seguimientos: int,
     *   apreciaciones: int,
     *   diagnosticos: int,
     *   reactivos: int,
     *   disponibilidades: int,
     *   serviciosAsignados: int,
     *   redes: int,
     *   sugerencias: int,
     *   horarios: int,
     *   correosCita: int,
     *   tieneActividadHistorica: bool,
     *   puedeEliminarFisicamente: bool,
     *   puedeDesactivar: bool,
     *   puedeReactivar: bool,
     *   urlCitasPendientes: string
     * }
     */
    public function resumenDependencias(
        string $clvPsi,
        string $clvCons
    ): array {
        $psi = $this->obtenerPsicologo($clvPsi, $clvCons);
        $clvPsiReal = (string) $psi['ClvPsi'];
        $conteos = $this->contarDependenciasCompletas($clvPsiReal);
        $pendiente = $this->esPendienteActivacion($psi);
        $estatus = (string) $psi['EstatusPsi'];

        $tieneActividadHistorica = $this->esActividadHistorica($conteos);

        $puedeEliminarFisicamente = !$tieneActividadHistorica;

        $puedeDesactivar =
            !$pendiente
            && $estatus === 'ACTIVO'
            && (int) $conteos['citasFuturas'] === 0
            && $tieneActividadHistorica;

        $puedeReactivar =
            !$pendiente
            && $estatus === 'INACTIVO';

        return array_merge($conteos, [
            'ClvPsi' => $clvPsiReal,
            'ClvUsu' => (string) $psi['ClvUsu'],
            'ClvPer' => (string) $psi['ClvPer'],
            'EstatusPsi' => $estatus,
            'EstadoUsu' => (int) $psi['EstadoUsu'],
            'pendienteActivacion' => $pendiente,
            'tieneActividadHistorica' => $tieneActividadHistorica,
            'puedeEliminarFisicamente' => $puedeEliminarFisicamente,
            'puedeDesactivar' => $puedeDesactivar,
            'puedeReactivar' => $puedeReactivar,
            'urlCitasPendientes' =>
                'consultorio/agenda?psicologo=' . rawurlencode($clvPsiReal),
            // compat aliases usados por vistas previas
            'citas' => (int) $conteos['totalCitas'],
            'citasFuturasProgramadas' => (int) $conteos['citasFuturas'],
            'historial' => (int) $conteos['expedientes'],
            'tieneActividad' => $tieneActividadHistorica,
        ]);
    }

    /**
     * Alias de compatibilidad.
     *
     * @return array<string, mixed>
     */
    public function evaluarEstado(
        string $clvPsi,
        string $clvCons
    ): array {
        return $this->resumenDependencias($clvPsi, $clvCons);
    }

    /**
     * Alias semántico: alta pendiente sin actividad.
     *
     * @return array{ok: bool, mensaje: string, codigo?: string}
     */
    public function cancelarRegistroPendiente(
        string $clvPsi,
        string $clvCons
    ): array {
        return $this->eliminarRegistroSinActividad($clvPsi, $clvCons);
    }

    /**
     * Eliminación física solo sin actividad histórica.
     * Revalida bajo transacción + SELECT FOR UPDATE.
     *
     * @return array{ok: bool, mensaje: string, codigo?: string}
     */
    public function eliminarRegistroSinActividad(
        string $clvPsi,
        string $clvCons
    ): array {
        try {
            $this->db->beginTransaction();

            $psi = $this->bloquearPsicologo($clvPsi, $clvCons);
            $clvPsiReal = (string) $psi['ClvPsi'];
            $conteos = $this->contarDependenciasCompletas($clvPsiReal);

            if ($this->esActividadHistorica($conteos)) {
                throw new RuntimeException(
                    'Este especialista tiene información histórica relacionada y no puede '
                    . 'eliminarse definitivamente. Usa desactivar si corresponde.'
                );
            }

            if ((int) $conteos['citasFuturas'] > 0) {
                throw new RuntimeException(
                    'No puedes eliminar ni desactivar este especialista porque tiene '
                    . (int) $conteos['citasFuturas']
                    . ' cita(s) futura(s) programada(s). Resuelve primero esas citas.'
                );
            }

            $clvUsu = (string) $psi['ClvUsu'];
            $clvPer = (string) $psi['ClvPer'];

            $this->activacionModel->revocarPendientes(
                $clvUsu,
                ActivacionCuentaService::TIPO_PSICOLOGO
            );

            $this->eliminarActivacionesUsuario($clvUsu);
            $this->eliminarRecuperacionesUsuario($clvUsu);
            $this->eliminarNotificacionesUsuario($clvUsu);
            $this->eliminarConsentimientosUsuario($clvUsu);
            $this->limpiarInvitacionesComoInvitador($clvUsu);
            $this->eliminarAsignacionesIniciales($clvPsiReal);
            $this->eliminarDisponibilidades($clvPsiReal);
            $this->eliminarHorarios($clvPsiReal);
            $this->eliminarRedesPsicologo($clvPsiReal);
            $this->eliminarSugerenciasServicio($clvPsiReal);

            $stmtPsi = $this->db->prepare(
                'DELETE FROM psicologo
                 WHERE ClvPsi = :clvPsi
                   AND ClvCons = :clvCons'
            );
            $stmtPsi->execute([
                'clvPsi' => $clvPsiReal,
                'clvCons' => $clvCons,
            ]);

            if ($stmtPsi->rowCount() < 1) {
                throw new RuntimeException(
                    'No fue posible eliminar al especialista. Puede que ya haya sido eliminado.'
                );
            }

            if (!$this->usuarioTieneOtrasRelaciones($clvUsu, $clvPsiReal)) {
                $stmtUsu = $this->db->prepare(
                    'DELETE FROM usuario WHERE ClvUsu = :clvUsu'
                );
                $stmtUsu->execute(['clvUsu' => $clvUsu]);

                if (!$this->personaTieneOtrosUsuarios($clvPer)) {
                    $stmtPer = $this->db->prepare(
                        'DELETE FROM persona WHERE ClvPer = :clvPer'
                    );
                    $stmtPer->execute(['clvPer' => $clvPer]);
                }
            }

            $this->db->commit();

            $pendiente = $this->esPendienteActivacion($psi);

            return [
                'ok' => true,
                'mensaje' => $pendiente
                    ? 'El registro pendiente del especialista fue eliminado correctamente.'
                    : 'El registro del especialista fue eliminado correctamente.',
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
     * Baja lógica: conserva historial. Bloquea si hay citas futuras PROGRAMADA.
     *
     * @return array{ok: bool, mensaje: string, codigo?: string}
     */
    public function desactivar(
        string $clvPsi,
        string $clvCons
    ): array {
        try {
            $this->db->beginTransaction();

            $psi = $this->bloquearPsicologo($clvPsi, $clvCons);

            if ($this->esPendienteActivacion($psi)) {
                throw new RuntimeException(
                    'El especialista aún no ha activado su cuenta. Cancela el registro pendiente o espera la activación.'
                );
            }

            if ((string) $psi['EstatusPsi'] === 'INACTIVO') {
                throw new RuntimeException(
                    'El especialista ya está inactivo.'
                );
            }

            $conteos = $this->contarDependenciasCompletas(
                (string) $psi['ClvPsi']
            );

            if ((int) $conteos['citasFuturas'] > 0) {
                throw new RuntimeException(
                    'No puedes eliminar ni desactivar este especialista porque tiene '
                    . (int) $conteos['citasFuturas']
                    . ' cita(s) futura(s) programada(s). Resuelve primero esas citas.'
                );
            }

            $stmt = $this->db->prepare(
                "UPDATE psicologo
                 SET
                    EstatusPsi = 'INACTIVO',
                    MostrarEnPagina = 0
                 WHERE ClvPsi = :clvPsi
                   AND ClvCons = :clvCons"
            );
            $stmt->execute([
                'clvPsi' => (string) $psi['ClvPsi'],
                'clvCons' => $clvCons,
            ]);

            $stmtUsu = $this->db->prepare(
                'UPDATE usuario
                 SET EstadoUsu = 0
                 WHERE ClvUsu = :clvUsu'
            );
            $stmtUsu->execute([
                'clvUsu' => (string) $psi['ClvUsu'],
            ]);

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' =>
                    'El registro se conservará por historial, pero dejará de estar activo.',
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
     * Reactiva especialista. No publica ni reactiva servicios/disponibilidad.
     *
     * @return array{ok: bool, mensaje: string, codigo?: string}
     */
    public function reactivar(
        string $clvPsi,
        string $clvCons
    ): array {
        try {
            $this->db->beginTransaction();

            $psi = $this->bloquearPsicologo($clvPsi, $clvCons);

            if ($this->esPendienteActivacion($psi)) {
                throw new RuntimeException(
                    'El especialista aún no ha activado su cuenta.'
                );
            }

            if ((string) $psi['EstatusPsi'] === 'ACTIVO') {
                throw new RuntimeException(
                    'El especialista ya está activo.'
                );
            }

            $stmt = $this->db->prepare(
                "UPDATE psicologo
                 SET EstatusPsi = 'ACTIVO'
                 WHERE ClvPsi = :clvPsi
                   AND ClvCons = :clvCons"
            );
            $stmt->execute([
                'clvPsi' => (string) $psi['ClvPsi'],
                'clvCons' => $clvCons,
            ]);

            $stmtUsu = $this->db->prepare(
                'UPDATE usuario
                 SET EstadoUsu = 1
                 WHERE ClvUsu = :clvUsu
                   AND RequiereCambioContrasena = 0'
            );
            $stmtUsu->execute([
                'clvUsu' => (string) $psi['ClvUsu'],
            ]);

            if ($stmtUsu->rowCount() < 1) {
                throw new RuntimeException(
                    'No fue posible reactivar la cuenta del especialista.'
                );
            }

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' =>
                    'El especialista fue reactivado. Revisa su disponibilidad, servicios y '
                    . 'visibilidad pública.',
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
     * @param array<string, int> $conteos
     */
    private function esActividadHistorica(array $conteos): bool
    {
        return (int) ($conteos['totalCitas'] ?? 0) > 0
            || (int) ($conteos['expedientes'] ?? 0) > 0
            || (int) ($conteos['seguimientos'] ?? 0) > 0
            || (int) ($conteos['diagnosticos'] ?? 0) > 0
            || (int) ($conteos['apreciaciones'] ?? 0) > 0
            || (int) ($conteos['reactivos'] ?? 0) > 0;
    }

    /**
     * @return array<string, int>
     */
    private function contarDependenciasCompletas(string $clvPsi): array
    {
        $totalCitas = $this->contarTabla('cita', 'ClvPsi', $clvPsi);
        $citasFuturas = $this->contarCitasFuturasProgramadas($clvPsi);
        $citasHistoricas = max(0, $totalCitas - $citasFuturas);

        $pacientes = 0;
        $stmtPac = $this->db->prepare(
            'SELECT COUNT(DISTINCT ClvPac)
             FROM cita
             WHERE ClvPsi = :clvPsi'
        );
        $stmtPac->execute(['clvPsi' => $clvPsi]);
        $pacientes = (int) $stmtPac->fetchColumn();

        $correosCita = 0;
        if ($this->tablaExiste('correo_cita') && $totalCitas > 0) {
            $stmtCorreo = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM correo_cita cc
                 INNER JOIN cita c ON c.ClvCita = cc.ClvCita
                 WHERE c.ClvPsi = :clvPsi'
            );
            $stmtCorreo->execute(['clvPsi' => $clvPsi]);
            $correosCita = (int) $stmtCorreo->fetchColumn();
        }

        return [
            'totalCitas' => $totalCitas,
            'citasFuturas' => $citasFuturas,
            'citasHistoricas' => $citasHistoricas,
            'pacientesRelacionados' => $pacientes,
            'expedientes' => $this->contarTabla(
                'historial_clinico',
                'ClvPsi',
                $clvPsi
            ),
            'seguimientos' => $this->contarTabla(
                'seguimiento_sesion',
                'ClvPsi',
                $clvPsi
            ),
            'apreciaciones' => $this->contarTablaSiExiste(
                'apreciacion_diagnostica',
                'ClvPsi',
                $clvPsi
            ),
            'diagnosticos' => $this->contarTabla(
                'diagnostico_seguimiento',
                'ClvPsi',
                $clvPsi
            ),
            'reactivos' => $this->contarTablaSiExiste(
                'reactivo_psicologico',
                'ClvPsi',
                $clvPsi
            ),
            'disponibilidades' => $this->contarTablaSiExiste(
                'disponibilidad_psicologo',
                'ClvPsi',
                $clvPsi
            ),
            'serviciosAsignados' => $this->contarTablaSiExiste(
                'psicologo_servicio',
                'ClvPsi',
                $clvPsi
            ),
            'redes' => $this->contarTablaSiExiste(
                'red_social_psicologo',
                'ClvPsi',
                $clvPsi
            ),
            'sugerencias' => $this->contarTablaSiExiste(
                'sugerencia_servicio',
                'ClvPsi',
                $clvPsi
            ),
            'horarios' => $this->contarTablaSiExiste(
                'horario',
                'ClvPsi',
                $clvPsi
            ),
            'correosCita' => $correosCita,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerPsicologo(
        string $clvPsi,
        string $clvCons,
        bool $bloquear = false
    ): array {
        $sql = "SELECT
                    psi.ClvPsi,
                    psi.ClvUsu,
                    psi.ClvCons,
                    psi.EstatusPsi,
                    psi.MostrarEnPagina,
                    usu.EstadoUsu,
                    usu.RequiereCambioContrasena,
                    usu.ClvPer
                FROM psicologo psi
                INNER JOIN usuario usu ON usu.ClvUsu = psi.ClvUsu
                WHERE psi.ClvPsi = :clvPsi
                  AND psi.ClvCons = :clvCons
                LIMIT 1";

        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPsi' => trim($clvPsi),
            'clvCons' => trim($clvCons),
        ]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            throw new RuntimeException(
                'El especialista no fue encontrado en este consultorio.'
            );
        }

        return $fila;
    }

    /**
     * @return array<string, mixed>
     */
    private function bloquearPsicologo(
        string $clvPsi,
        string $clvCons
    ): array {
        return $this->obtenerPsicologo($clvPsi, $clvCons, true);
    }

    private function esPendienteActivacion(array $psi): bool
    {
        return (int) ($psi['EstadoUsu'] ?? 0) === 0
            && (int) ($psi['RequiereCambioContrasena'] ?? 0) === 1;
    }

    private function contarCitasFuturasProgramadas(string $clvPsi): int
    {
        $sql = "SELECT COUNT(*)
                FROM cita
                WHERE ClvPsi = :clvPsi
                  AND EstadoCita = 'PROGRAMADA'
                  AND (
                        FechaCita > CURDATE()
                     OR (FechaCita = CURDATE() AND HraInicioCita >= CURTIME())
                  )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvPsi' => $clvPsi]);

        return (int) $stmt->fetchColumn();
    }

    private function contarTabla(
        string $tabla,
        string $campo,
        string $valor
    ): int {
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
        if (!$this->tablaExiste($tabla)) {
            return 0;
        }

        if (!$this->columnaExiste($tabla, $campo)) {
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
        $stmt = $this->db->prepare(
            'DELETE FROM activacion_cuenta WHERE ClvUsu = :clvUsu'
        );
        $stmt->execute(['clvUsu' => $clvUsu]);
    }

    private function eliminarRecuperacionesUsuario(string $clvUsu): void
    {
        if (!$this->tablaExiste('recuperacion_password')) {
            return;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM recuperacion_password WHERE ClvUsu = :clvUsu'
        );
        $stmt->execute(['clvUsu' => $clvUsu]);
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

        $stmt = $this->db->prepare(
            "DELETE FROM notificacion WHERE {$campo} = :clvUsu"
        );
        $stmt->execute(['clvUsu' => $clvUsu]);
    }

    private function eliminarConsentimientosUsuario(string $clvUsu): void
    {
        if (!$this->tablaExiste('consentimiento_datos_personales')) {
            return;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM consentimiento_datos_personales WHERE ClvUsu = :clvUsu'
        );
        $stmt->execute(['clvUsu' => $clvUsu]);
    }

    private function limpiarInvitacionesComoInvitador(string $clvUsu): void
    {
        if (!$this->columnaExiste('activacion_cuenta', 'ClvUsuInvitador')) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE activacion_cuenta
             SET ClvUsuInvitador = NULL
             WHERE ClvUsuInvitador = :clvUsu'
        );
        $stmt->execute(['clvUsu' => $clvUsu]);
    }

    private function eliminarAsignacionesIniciales(string $clvPsi): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM psicologo_servicio WHERE ClvPsi = :clvPsi'
        );
        $stmt->execute(['clvPsi' => $clvPsi]);
    }

    private function eliminarDisponibilidades(string $clvPsi): void
    {
        if (!$this->tablaExiste('disponibilidad_psicologo')) {
            return;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM disponibilidad_psicologo WHERE ClvPsi = :clvPsi'
        );
        $stmt->execute(['clvPsi' => $clvPsi]);
    }

    private function eliminarHorarios(string $clvPsi): void
    {
        if (!$this->tablaExiste('horario')) {
            return;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM horario WHERE ClvPsi = :clvPsi'
        );
        $stmt->execute(['clvPsi' => $clvPsi]);
    }

    private function eliminarRedesPsicologo(string $clvPsi): void
    {
        if (!$this->tablaExiste('red_social_psicologo')) {
            return;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM red_social_psicologo WHERE ClvPsi = :clvPsi'
        );
        $stmt->execute(['clvPsi' => $clvPsi]);
    }

    private function eliminarSugerenciasServicio(string $clvPsi): void
    {
        if (!$this->tablaExiste('sugerencia_servicio')) {
            return;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM sugerencia_servicio WHERE ClvPsi = :clvPsi'
        );
        $stmt->execute(['clvPsi' => $clvPsi]);
    }

    private function usuarioTieneOtrasRelaciones(
        string $clvUsu,
        string $clvPsiExcluido
    ): bool {
        $checks = [
            ['psicologo', 'ClvUsu', $clvUsu, 'ClvPsi', $clvPsiExcluido],
            ['paciente', 'ClvUsu', $clvUsu, null, null],
            ['consultorio_usuario', 'ClvUsu', $clvUsu, null, null],
        ];

        foreach ($checks as [$tabla, $campo, $valor, $excluirCampo, $excluirValor]) {
            if (!$this->tablaExiste($tabla)) {
                continue;
            }

            $sql = "SELECT COUNT(*) FROM {$tabla} WHERE {$campo} = :valor";
            $params = ['valor' => $valor];

            if ($excluirCampo !== null && $excluirValor !== null) {
                $sql .= " AND {$excluirCampo} <> :excluir";
                $params['excluir'] = $excluirValor;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }
        }

        return false;
    }

    private function personaTieneOtrosUsuarios(string $clvPer): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM usuario WHERE ClvPer = :clvPer'
        );
        $stmt->execute(['clvPer' => $clvPer]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
