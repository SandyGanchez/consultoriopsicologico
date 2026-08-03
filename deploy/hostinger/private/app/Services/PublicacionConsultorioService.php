<?php

namespace App\Services;

use App\Config\Database;
use App\Models\Consultorio;
use PDO;
use RuntimeException;

class PublicacionConsultorioService
{
    private PDO $db;
    private Consultorio $consultorioModel;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->consultorioModel = new Consultorio();
    }

    /**
     * @return array{
     *   porcentaje: int,
     *   completados: list<array{clave: string, etiqueta: string}>,
     *   pendientes: list<array{clave: string, etiqueta: string}>,
     *   listo: bool
     * }
     */
    public function calcularProgreso(string $clvCons): array
    {
        $requisitos = $this->evaluarRequisitos($clvCons);
        $completados = [];
        $pendientes = [];

        foreach ($requisitos as $clave => $item) {
            $fila = [
                'clave' => $clave,
                'etiqueta' => $item['etiqueta']
            ];

            if ($item['ok']) {
                $completados[] = $fila;
            } else {
                $pendientes[] = $fila;
            }
        }

        $total = count($requisitos);
        $hechos = count($completados);
        $porcentaje = $total > 0
            ? (int) round(($hechos / $total) * 100)
            : 0;

        return [
            'porcentaje' => $porcentaje,
            'completados' => $completados,
            'pendientes' => $pendientes,
            'listo' => $pendientes === []
        ];
    }

    /**
     * @return array{
     *   codigo: string,
     *   etiqueta: string
     * }
     */
    public function derivarEstadoPagina(
        array $consultorio,
        int $estadoUsuResponsable
    ): array {
        $estatus = strtoupper(
            trim((string) ($consultorio['EstatusCons'] ?? ''))
        );

        if (in_array($estatus, ['INACTIVO', 'BLOQUEADO'], true)) {
            return [
                'codigo' => 'INACTIVO',
                'etiqueta' => 'Inactivo'
            ];
        }

        if ($estadoUsuResponsable !== 1) {
            return [
                'codigo' => 'PENDIENTE_ACTIVACION',
                'etiqueta' => 'Pendiente de activación'
            ];
        }

        $publicado = (int) ($consultorio['PublicadoCons'] ?? 0) === 1;
        $fechaPub = $consultorio['FechaPublicacionCons'] ?? null;

        if ($publicado) {
            return [
                'codigo' => 'PUBLICADO',
                'etiqueta' => 'Publicado'
            ];
        }

        if ($fechaPub !== null && trim((string) $fechaPub) !== '') {
            return [
                'codigo' => 'OCULTO',
                'etiqueta' => 'Oculto'
            ];
        }

        return [
            'codigo' => 'BORRADOR',
            'etiqueta' => 'Borrador'
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string, pendientes?: list<array{clave: string, etiqueta: string}>}
     */
    public function publicar(string $clvCons, string $clvUsuActor): array
    {
        $clvCons = trim($clvCons);

        if (!$this->actorPerteneceAlConsultorio($clvUsuActor, $clvCons)) {
            return [
                'ok' => false,
                'mensaje' => 'No tienes autorización para publicar este consultorio.'
            ];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "SELECT
                    c.ClvCons,
                    c.EstatusCons,
                    c.PublicadoCons,
                    c.FechaPublicacionCons,
                    u.EstadoUsu
                 FROM consultorio c
                 INNER JOIN consultorio_usuario cu
                    ON cu.ClvCons = c.ClvCons
                   AND cu.ClvUsu = :actor
                   AND cu.EstatusConsUsu = 'ACTIVO'
                 INNER JOIN usuario u
                    ON u.ClvUsu = cu.ClvUsu
                 WHERE c.ClvCons = :clvCons
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([
                'actor' => $clvUsuActor,
                'clvCons' => $clvCons
            ]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fila) {
                throw new RuntimeException(
                    'No se encontró el consultorio.'
                );
            }

            if ((int) ($fila['EstadoUsu'] ?? 0) !== 1) {
                throw new RuntimeException(
                    'La cuenta del consultorio debe estar activa.'
                );
            }

            if (($fila['EstatusCons'] ?? '') !== 'ACTIVO') {
                throw new RuntimeException(
                    'El consultorio no está activo administrativamente.'
                );
            }

            $progreso = $this->calcularProgreso($clvCons);

            if (!$progreso['listo']) {
                $this->db->rollBack();

                return [
                    'ok' => false,
                    'mensaje' =>
                        'Completa la configuración mínima antes de publicar.',
                    'pendientes' => $progreso['pendientes']
                ];
            }

            $update = $this->db->prepare(
                "UPDATE consultorio
                 SET
                    PublicadoCons = 1,
                    FechaPublicacionCons = NOW()
                 WHERE ClvCons = :clvCons
                   AND EstatusCons = 'ACTIVO'"
            );
            $update->execute(['clvCons' => $clvCons]);

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' =>
                    'Tu página pública fue publicada correctamente.'
            ];
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
                'mensaje' => 'No fue posible publicar la página.'
            ];
        }
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function ocultar(string $clvCons, string $clvUsuActor): array
    {
        $clvCons = trim($clvCons);

        if (!$this->actorPerteneceAlConsultorio($clvUsuActor, $clvCons)) {
            return [
                'ok' => false,
                'mensaje' => 'No tienes autorización para ocultar este consultorio.'
            ];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "SELECT ClvCons, EstatusCons, PublicadoCons, FechaPublicacionCons
                 FROM consultorio
                 WHERE ClvCons = :clvCons
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['clvCons' => $clvCons]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fila) {
                throw new RuntimeException(
                    'No se encontró el consultorio.'
                );
            }

            $update = $this->db->prepare(
                "UPDATE consultorio
                 SET PublicadoCons = 0
                 WHERE ClvCons = :clvCons"
            );
            $update->execute(['clvCons' => $clvCons]);

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' =>
                    'Tu página pública fue ocultada. Puedes volver a publicarla cuando lo desees.'
            ];
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
                'mensaje' => 'No fue posible ocultar la página.'
            ];
        }
    }

    /**
     * Datos para reutilizar la plantilla home en modo vista previa.
     *
     * @return array<string, mixed>
     */
    public function obtenerDatosVistaPrevia(
        string $clvCons,
        string $busqueda = '',
        string $especialidad = ''
    ): array {
        $consultorio = $this->consultorioModel->obtenerParaVistaPrevia(
            $clvCons
        );

        if (!$consultorio) {
            throw new RuntimeException(
                'No se encontró el consultorio.'
            );
        }

        $servicios = $this->consultorioModel->obtenerServiciosPorClave(
            $clvCons
        );
        $horarios = $this->consultorioModel->obtenerHorariosPorClave(
            $clvCons
        );

        $especialidadesFiltro =
            $this->consultorioModel->listarEspecialidadesPublicas(
                $clvCons,
                false
            );

        if (
            $especialidad !== ''
            && !in_array($especialidad, $especialidadesFiltro, true)
        ) {
            $especialidad = '';
        }

        $filtrosActivos = $busqueda !== '' || $especialidad !== '';

        $especialistas = $this->consultorioModel->buscarEspecialistasPublicos(
            $clvCons,
            $busqueda,
            $especialidad,
            false
        );

        foreach ($especialistas as &$especialista) {
            $especialista['servicios'] =
                $this->consultorioModel->obtenerServiciosPublicosPsicologo(
                    (string) $especialista['ClvPsi']
                );
        }
        unset($especialista);

        $diasAtencion = 0;

        foreach ($horarios as $dia) {
            if (($dia['EstatusHorario'] ?? '') === 'ACTIVO') {
                $diasAtencion++;
            }
        }

        $mapaDisponible = \App\Helpers\Helper::coordenadasPublicasValidas(
            $consultorio['LatitudDir'] ?? null,
            $consultorio['LongitudDir'] ?? null
        );

        return [
            'consultorio' => $consultorio,
            'servicios' => $servicios,
            'horarios' => $horarios,
            'redes' => $this->consultorioModel->obtenerRedesPorClave($clvCons),
            'caracteristicas' => $this->consultorioModel
                ->obtenerCaracteristicasPorClave($clvCons),
            'especialistas' => $especialistas,
            'especialidadesFiltro' => $especialidadesFiltro,
            'busquedaEspecialistas' => $busqueda,
            'filtroEspecialidad' => $especialidad,
            'filtrosActivos' => $filtrosActivos,
            'totalEspecialistas' => count($especialistas),
            'mapaDisponible' => $mapaDisponible,
            'diasAtencion' => $diasAtencion,
            'cargarMapaHome' => $mapaDisponible,
            'modoVistaPrevia' => true
        ];
    }

    /**
     * @return array<string, array{ok: bool, etiqueta: string}>
     */
    private function evaluarRequisitos(string $clvCons): array
    {
        $sql = "SELECT
                    c.NombreCons,
                    c.Descripcion,
                    c.TelefonoCons,
                    c.CorreoElectronico,
                    c.LimiteCancHoras,
                    d.PaisDir,
                    d.EstadoDir,
                    d.MunicipioDir,
                    d.CalleDir,
                    d.CodPostDir,
                    (
                        SELECT COUNT(*)
                        FROM horario_consultorio h
                        WHERE h.ClvCons = c.ClvCons
                          AND h.EstatusHorario = 'ACTIVO'
                    ) AS TotalHorariosActivos,
                    (
                        SELECT COUNT(*)
                        FROM servicios s
                        WHERE s.ClvCons = c.ClvCons
                          AND s.EstatusServicio = 'ACTIVO'
                    ) AS TotalServiciosActivos,
                    (
                        SELECT COUNT(*)
                        FROM psicologo psi
                        INNER JOIN usuario u ON u.ClvUsu = psi.ClvUsu
                        WHERE psi.ClvCons = c.ClvCons
                          AND psi.EstatusPsi = 'ACTIVO'
                          AND u.EstadoUsu = 1
                    ) AS TotalPsicologosActivos
                FROM consultorio c
                LEFT JOIN direccion d ON d.ClvDir = c.ClvDir
                WHERE c.ClvCons = :clvCons
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCons' => trim($clvCons)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $nombre = trim((string) ($fila['NombreCons'] ?? ''));
        $descripcion = trim((string) ($fila['Descripcion'] ?? ''));
        $telefono = trim((string) ($fila['TelefonoCons'] ?? ''));
        $correo = trim((string) ($fila['CorreoElectronico'] ?? ''));
        $limite = (int) ($fila['LimiteCancHoras'] ?? 0);

        $direccionOk =
            trim((string) ($fila['PaisDir'] ?? '')) !== ''
            && trim((string) ($fila['EstadoDir'] ?? '')) !== ''
            && trim((string) ($fila['MunicipioDir'] ?? '')) !== ''
            && trim((string) ($fila['CalleDir'] ?? '')) !== ''
            && trim((string) ($fila['CodPostDir'] ?? '')) !== '';

        return [
            'nombre' => [
                'ok' => $nombre !== '',
                'etiqueta' => 'Nombre del consultorio'
            ],
            'descripcion' => [
                'ok' => $descripcion !== '',
                'etiqueta' => 'Descripción del consultorio'
            ],
            'contacto' => [
                'ok' => $telefono !== '' || $correo !== '',
                'etiqueta' => 'Teléfono o correo de contacto'
            ],
            'direccion' => [
                'ok' => $direccionOk,
                'etiqueta' => 'Dirección completa'
            ],
            'horario' => [
                'ok' => (int) ($fila['TotalHorariosActivos'] ?? 0) > 0,
                'etiqueta' => 'Al menos un horario activo'
            ],
            'servicio' => [
                'ok' => (int) ($fila['TotalServiciosActivos'] ?? 0) > 0,
                'etiqueta' => 'Al menos un servicio activo'
            ],
            'psicologo' => [
                'ok' => (int) ($fila['TotalPsicologosActivos'] ?? 0) > 0,
                'etiqueta' => 'Al menos un psicólogo activo con cuenta activada'
            ],
            'cancelacion' => [
                'ok' => $limite > 0,
                'etiqueta' => 'Límite de cancelación configurado'
            ]
        ];
    }

    private function actorPerteneceAlConsultorio(
        string $clvUsu,
        string $clvCons
    ): bool {
        $sql = "SELECT COUNT(*)
                FROM consultorio_usuario
                WHERE ClvUsu = :clvUsu
                  AND ClvCons = :clvCons
                  AND EstatusConsUsu = 'ACTIVO'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvUsu' => trim($clvUsu),
            'clvCons' => trim($clvCons)
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
