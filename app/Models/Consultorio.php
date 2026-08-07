<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Consultorio extends Model
{
    /*
    =========================================
          INSTALACIÓN DE CONSULTORIO ÚNICO
    =========================================
    */

    /**
     * Estado de la instalación respecto al consultorio.
     * No elige arbitrariamente el primero cuando hay varios.
     *
     * @return array{
     *   estado: 'ninguno'|'unico'|'multiple',
     *   consultorio: ?array,
     *   total: int
     * }
     */
    public function obtenerEstadoInstalacion(): array
    {
        $total = $this->contarTodos();

        if ($total === 0) {
            return [
                'estado' => 'ninguno',
                'consultorio' => null,
                'total' => 0
            ];
        }

        if ($total > 1) {
            error_log(
                '[INSTALACION] Se detectaron '
                . $total
                . ' registros en consultorio; la instalación admite exactamente uno.'
            );

            return [
                'estado' => 'multiple',
                'consultorio' => null,
                'total' => $total
            ];
        }

        $consultorio = $this->cargarUnicoVerificado();

        if ($consultorio === null) {
            error_log(
                '[INSTALACION] Conteo=1 pero no fue posible cargar el consultorio único.'
            );

            return [
                'estado' => 'multiple',
                'consultorio' => null,
                'total' => $total
            ];
        }

        return [
            'estado' => 'unico',
            'consultorio' => $consultorio,
            'total' => 1
        ];
    }

    /**
     * Consultorio único de la instalación, o null si aún no existe.
     *
     * @throws \RuntimeException si hay más de un consultorio.
     */
    public function obtenerUnicoDeInstalacion(): ?array
    {
        $estado = $this->obtenerEstadoInstalacion();

        if ($estado['estado'] === 'multiple') {
            throw new \RuntimeException(
                'La instalación tiene más de un consultorio. Requiere corrección administrativa.'
            );
        }

        return $estado['consultorio'];
    }

    /**
     * Carga el único registro cuando COUNT(*) = 1 (condición en SQL).
     * El LIMIT 1 no es selección silenciosa entre varios: el WHERE ya exige unicidad.
     */
    private function cargarUnicoVerificado(): ?array
    {
        $sql = "
            SELECT
                c.ClvCons,
                c.NombreCons,
                c.LogotipoCons,
                c.FaviconCons,
                c.ImagenPortada,
                c.Slogan,
                c.Descripcion,
                c.TelefonoCons,
                c.CorreoElectronico,
                c.LimiteCancHoras,
                c.EstatusCons,
                c.PublicadoCons,
                c.FechaPublicacionCons,
                c.FechaRegistroCons,
                c.ClvDir,

                d.PaisDir,
                d.EstadoDir,
                d.MunicipioDir,
                d.ColoniaDir,
                d.CalleDir,
                d.NumExtDir,
                d.NumIntDir,
                d.CodPostDir,
                d.LatitudDir,
                d.LongitudDir,
                d.ReferenciaDir,

                cu.ClvConsUsu,
                cu.ClvUsu,
                cu.EsResponsable,
                cu.EstatusConsUsu,
                cu.FechaAsignacion,

                u.CorreoUsu,
                u.TelefonoUsu,
                u.EstadoUsu,
                u.RolUsu,
                u.RequiereCambioContrasena,

                p.NombrePer,
                p.ApPatPer,
                p.ApMatPer,
                p.FechaNacimiento,
                p.GeneroPer
            FROM consultorio c
            LEFT JOIN direccion d
                ON d.ClvDir = c.ClvDir
            LEFT JOIN consultorio_usuario cu
                ON cu.ClvCons = c.ClvCons
                AND cu.EsResponsable = 1
            LEFT JOIN usuario u
                ON u.ClvUsu = cu.ClvUsu
                AND u.RolUsu = 'CONSULTORIO'
            LEFT JOIN persona p
                ON p.ClvPer = u.ClvPer
            WHERE (SELECT COUNT(*) FROM consultorio) = 1
            LIMIT 1
        ";

        $fila = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /*
    =========================================
          MÉTODOS DEL ADMINISTRADOR
    =========================================
    */

    public function contarTodos(): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM consultorio
        ";

        $stmt = $this->db->query($sql);

        return (int) $stmt->fetchColumn();
    }

    public function contarPorEstado(
        string $estado
    ): int {
        $sql = "
            SELECT COUNT(*)
            FROM consultorio
            WHERE EstatusCons = :estado
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'estado' => $estado
        ]);

        return (int) $stmt->fetchColumn();
    }

 public function obtenerRecientes(
        int $limite = 5
    ): array {
        $limite = max(1, min($limite, 50));

        $sql = "
            SELECT
                c.ClvCons,
                c.NombreCons,
                c.LogotipoCons,
                c.Slogan,
                c.Descripcion,
                c.TelefonoCons,
                c.CorreoElectronico,
                c.LimiteCancHoras,
                c.EstatusCons,
                c.PublicadoCons,
                c.FechaPublicacionCons,
                c.FechaRegistroCons,

                d.MunicipioDir,
                d.EstadoDir,

                cu.ClvConsUsu,
                cu.ClvUsu,
                cu.EsResponsable,
                cu.EstatusConsUsu,
                cu.FechaAsignacion,

                u.CorreoUsu,
                u.TelefonoUsu,
                u.EstadoUsu,
                u.RolUsu,
                u.RequiereCambioContrasena,

                p.NombrePer,
                p.ApPatPer,
                p.ApMatPer

            FROM consultorio c

            LEFT JOIN direccion d
                ON d.ClvDir = c.ClvDir

            LEFT JOIN consultorio_usuario cu
                ON cu.ClvCons = c.ClvCons
                AND cu.EsResponsable = 1

            LEFT JOIN usuario u
                ON u.ClvUsu = cu.ClvUsu
                AND u.RolUsu = 'CONSULTORIO'

            LEFT JOIN persona p
                ON p.ClvPer = u.ClvPer

            ORDER BY c.FechaRegistroCons DESC

            LIMIT {$limite}
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTodos(): array
    {
        $sql = "
            SELECT
                c.ClvCons,
                c.NombreCons,
                c.LogotipoCons,
                c.FaviconCons,
                c.ImagenPortada,
                c.Slogan,
                c.Descripcion,
                c.TelefonoCons,
                c.CorreoElectronico,
                c.LimiteCancHoras,
                c.EstatusCons,
                c.PublicadoCons,
                c.FechaPublicacionCons,
                c.FechaRegistroCons,

                d.MunicipioDir,
                d.EstadoDir,

                cu.ClvConsUsu,
                cu.ClvUsu,
                cu.EsResponsable,
                cu.EstatusConsUsu,
                cu.FechaAsignacion,

                u.CorreoUsu,
                u.TelefonoUsu,
                u.RolUsu,
                u.EstadoUsu,
                u.RequiereCambioContrasena,

                p.NombrePer,
                p.ApPatPer,
                p.ApMatPer

            FROM consultorio c

            LEFT JOIN direccion d
                ON d.ClvDir = c.ClvDir

            LEFT JOIN consultorio_usuario cu
                ON cu.ClvCons = c.ClvCons
                AND cu.EsResponsable = 1

            LEFT JOIN usuario u
                ON u.ClvUsu = cu.ClvUsu
                AND u.RolUsu = 'CONSULTORIO'

            LEFT JOIN persona p
                ON p.ClvPer = u.ClvPer

            ORDER BY c.FechaRegistroCons DESC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(
        string $id
    ): ?array {
        return $this->obtenerPorClave($id);
    }

    public function existeCorreoConsultorio(
        string $correo,
        ?string $ignorarId = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM consultorio
            WHERE LOWER(CorreoElectronico) =
                  LOWER(:correo)
        ";

        $parametros = [
            'correo' => trim($correo)
        ];

        if ($ignorarId !== null) {
            $sql .= "
                AND ClvCons <> :ignorarId
            ";

            $parametros['ignorarId'] =
                $ignorarId;
        }

        $stmt = $this->db->prepare($sql);

        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existeNombreConsultorio(
        string $nombre,
        ?string $ignorarId = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM consultorio
            WHERE LOWER(TRIM(NombreCons)) =
                  LOWER(TRIM(:nombre))
        ";

        $parametros = [
            'nombre' => trim($nombre)
        ];

        if ($ignorarId !== null) {
            $sql .= "
                AND ClvCons <> :ignorarId
            ";

            $parametros['ignorarId'] = $ignorarId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existeCorreoUsuario(
        string $correo,
        ?string $ignorarUsuario = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM usuario
            WHERE LOWER(CorreoUsu) =
                  LOWER(:correo)
        ";

        $parametros = [
            'correo' => trim($correo)
        ];

        if ($ignorarUsuario !== null) {
            $sql .= "
                AND ClvUsu <> :ignorarUsuario
            ";

            $parametros['ignorarUsuario'] =
                $ignorarUsuario;
        }

        $stmt = $this->db->prepare($sql);

        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn() > 0;
    }

    /*
    =========================================
          INFORMACIÓN DEL CONSULTORIO
    =========================================
    */

    /**
     * Fragmento SQL común: consultorio público visible.
     */
    private function sqlFiltroConsultorioPublico(string $alias = 'c'): string
    {
        return "
            {$alias}.EstatusCons = 'ACTIVO'
            AND {$alias}.PublicadoCons = 1
            AND EXISTS (
                SELECT 1
                FROM consultorio_usuario cu
                INNER JOIN usuario u
                    ON u.ClvUsu = cu.ClvUsu
                WHERE cu.ClvCons = {$alias}.ClvCons
                  AND cu.EstatusConsUsu = 'ACTIVO'
                  AND u.RolUsu = 'CONSULTORIO'
                  AND u.EstadoUsu = 1
            )
        ";
    }

    /**
     * Listado de consultorios publicados para la portada de PsicoMatch.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPublicados(string $busqueda = ''): array
    {
        $busqueda = trim($busqueda);

        $sql = "
            SELECT
                c.ClvCons,
                c.NombreCons,
                c.LogotipoCons,
                c.ImagenPortada,
                c.Slogan,
                c.Descripcion,
                c.TelefonoCons,
                c.CorreoElectronico,
                c.PublicadoCons,
                c.FechaPublicacionCons,
                d.MunicipioDir,
                d.EstadoDir,
                d.ColoniaDir
            FROM consultorio c
            LEFT JOIN direccion d
                ON d.ClvDir = c.ClvDir
            WHERE " . $this->sqlFiltroConsultorioPublico('c');

        $params = [];

        if ($busqueda !== '') {
            $sql .= "
              AND (
                    c.NombreCons LIKE :busqueda
                    OR c.Slogan LIKE :busqueda
                    OR c.Descripcion LIKE :busqueda
                    OR d.MunicipioDir LIKE :busqueda
                    OR d.EstadoDir LIKE :busqueda
              )
            ";
            $params['busqueda'] = '%' . $busqueda . '%';
        }

        $sql .= '
            ORDER BY c.NombreCons ASC, c.ClvCons ASC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Consultorio público por clave explícita (sin seleccionar un registro implícito).
     */
    public function obtenerPublicadoPorClave(string $clvCons): ?array
    {
        $sql = "
            SELECT
                c.ClvCons,
                c.NombreCons,
                c.LogotipoCons,
                c.ImagenPortada,
                c.Slogan,
                c.Descripcion,
                c.TelefonoCons,
                c.CorreoElectronico,
                c.LimiteCancHoras,
                c.PublicadoCons,
                c.FechaPublicacionCons,

                d.PaisDir,
                d.EstadoDir,
                d.MunicipioDir,
                d.ColoniaDir,
                d.CalleDir,
                d.NumExtDir,
                d.NumIntDir,
                d.CodPostDir,
                d.LatitudDir,
                d.LongitudDir,
                d.ReferenciaDir

            FROM consultorio c

            LEFT JOIN direccion d
                ON c.ClvDir = d.ClvDir

            WHERE c.ClvCons = :clvCons
              AND " . $this->sqlFiltroConsultorioPublico('c') . "
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCons' => trim($clvCons)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Compatibilidad: primer consultorio publicado (solo branding legado en auth).
     * La portada y las páginas individuales NO deben usar este método.
     */
    public function obtenerInformacion(): ?array
    {
        $lista = $this->listarPublicados();

        return $lista[0] ?? null;
    }

    /**
     * Datos del consultorio para vista previa privada (sin exigir publicación).
     */
    public function obtenerParaVistaPrevia(string $clvCons): ?array
    {
        $sql = "
            SELECT
                c.ClvCons,
                c.NombreCons,
                c.LogotipoCons,
                c.ImagenPortada,
                c.Slogan,
                c.Descripcion,
                c.TelefonoCons,
                c.CorreoElectronico,
                c.LimiteCancHoras,
                c.EstatusCons,
                c.PublicadoCons,
                c.FechaPublicacionCons,

                d.PaisDir,
                d.EstadoDir,
                d.MunicipioDir,
                d.ColoniaDir,
                d.CalleDir,
                d.NumExtDir,
                d.NumIntDir,
                d.CodPostDir,
                d.LatitudDir,
                d.LongitudDir,
                d.ReferenciaDir

            FROM consultorio c

            LEFT JOIN direccion d
                ON c.ClvDir = d.ClvDir

            WHERE c.ClvCons = :clvCons
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCons' => trim($clvCons)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function actualizarConfiguracionGeneral(
        string $clvCons,
        array $datos
    ): bool {
        $sql = "UPDATE consultorio
                SET
                    NombreCons = :nombre,
                    Slogan = :slogan,
                    Descripcion = :descripcion,
                    TelefonoCons = :telefono,
                    CorreoElectronico = :correo,
                    LimiteCancHoras = :limite
                WHERE ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'nombre' => $datos['NombreCons'],
            'slogan' => $datos['Slogan'],
            'descripcion' => $datos['Descripcion'],
            'telefono' => $datos['TelefonoCons'],
            'correo' => $datos['CorreoElectronico'],
            'limite' => (int) $datos['LimiteCancHoras'],
            'clvCons' => trim($clvCons)
        ]);

        return $stmt->rowCount() > 0;
    }

    public function actualizarLogotipo(
        string $clvCons,
        string $rutaLogotipo
    ): bool {
        $sql = "UPDATE consultorio
                SET LogotipoCons = :logotipo
                WHERE ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'logotipo' => $rutaLogotipo,
            'clvCons' => trim($clvCons)
        ]);

        return $stmt->rowCount() > 0;
    }

    public function obtenerLogotipoActual(
        string $clvCons
    ): ?string {
        $sql = "SELECT LogotipoCons
                FROM consultorio
                WHERE ClvCons = :clvCons
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => trim($clvCons)
        ]);

        $valor = $stmt->fetchColumn();

        return is_string($valor) && $valor !== ''
            ? $valor
            : null;
    }

    public function actualizarImagenPortada(
        string $clvCons,
        ?string $rutaRelativa
    ): bool {
        $sql = "UPDATE consultorio
                SET ImagenPortada = :portada
                WHERE ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'portada' => $rutaRelativa,
            'clvCons' => trim($clvCons)
        ]);

        return $stmt->rowCount() > 0;
    }

    public function obtenerImagenPortadaActual(
        string $clvCons
    ): ?string {
        $sql = "SELECT ImagenPortada
                FROM consultorio
                WHERE ClvCons = :clvCons
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => trim($clvCons)
        ]);

        $valor = $stmt->fetchColumn();

        return is_string($valor) && $valor !== ''
            ? $valor
            : null;
    }

    public function obtenerResumenPublicoAgendamiento(
        string $clvCons
    ): ?array {
        $sql = "
            SELECT
                c.NombreCons,
                c.LimiteCancHoras,
                d.PaisDir,
                d.EstadoDir,
                d.MunicipioDir,
                d.ColoniaDir,
                d.CalleDir,
                d.CodPostDir,
                d.NumExtDir,
                d.NumIntDir,
                d.ReferenciaDir
            FROM consultorio c
            LEFT JOIN direccion d
                ON d.ClvDir = c.ClvDir
            WHERE c.ClvCons = :clvCons
              AND c.EstatusCons = 'ACTIVO'
              AND c.PublicadoCons = 1
              AND EXISTS (
                    SELECT 1
                    FROM consultorio_usuario cu
                    INNER JOIN usuario u
                        ON u.ClvUsu = cu.ClvUsu
                    WHERE cu.ClvCons = c.ClvCons
                      AND cu.EstatusConsUsu = 'ACTIVO'
                      AND u.RolUsu = 'CONSULTORIO'
                      AND u.EstadoUsu = 1
              )
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => trim($clvCons)
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

public function obtenerPorClave(
    string $clvCons
): ?array {
    $clvCons = trim($clvCons);

    $sql = "
        SELECT
            c.*,

            d.PaisDir,
            d.EstadoDir,
            d.MunicipioDir,
            d.ColoniaDir,
            d.CalleDir,
            d.CodPostDir,
            d.NumExtDir,
            d.NumIntDir,
            d.LatitudDir,
            d.LongitudDir,
            d.ReferenciaDir,

            cu.ClvConsUsu,
            cu.ClvUsu,
            cu.EsResponsable,
            cu.EstatusConsUsu,

            u.CorreoUsu,
            u.TelefonoUsu,
            u.EstadoUsu,
            u.RolUsu,
            u.RequiereCambioContrasena,

            p.NombrePer,
            p.ApPatPer,
            p.ApMatPer,
            p.FechaNacimiento,
            p.GeneroPer

        FROM consultorio c

        LEFT JOIN direccion d
            ON d.ClvDir = c.ClvDir

        LEFT JOIN consultorio_usuario cu
            ON cu.ClvCons = c.ClvCons
            AND cu.EsResponsable = 1
            AND cu.EstatusConsUsu = 'ACTIVO'

        LEFT JOIN usuario u
            ON u.ClvUsu = cu.ClvUsu
            AND u.RolUsu = 'CONSULTORIO'

        LEFT JOIN persona p
            ON p.ClvPer = u.ClvPer

        WHERE c.ClvCons = :clvCons

        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvCons' => $clvCons
    ]);

    $resultado = $stmt->fetch(
        \PDO::FETCH_ASSOC
    );

    return $resultado !== false
        ? $resultado
        : null;
}
    /*
    =========================================
          SERVICIOS
    =========================================
    */

    public function obtenerServicios(): array
    {
        $consultorio = $this->obtenerInformacion();

        if (!$consultorio) {
            return [];
        }

        return $this->obtenerServiciosPorClave(
            (string) $consultorio['ClvCons']
        );
    }

    public function obtenerServiciosPorClave(string $clvCons): array
    {
        $sql = "
            SELECT
                ClvServ,
                NombreServicio,
                Descripcion,
                DuracionMinutos,
                CostoServicio
            FROM servicios
            WHERE ClvCons = :clvCons
              AND EstatusServicio = 'ACTIVO'
            ORDER BY NombreServicio ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => trim($clvCons)
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerServiciosPublicosPsicologo(
        string $clvPsi
    ): array {
        $sql = "
            SELECT
                ps.ClvPsiServ,
                ps.PrecioServicio,
                ps.DuracionMinutos,
                ps.DescripcionServicio,

                s.ClvServ,
                s.NombreServicio,
                s.Descripcion

            FROM psicologo_servicio ps

            INNER JOIN servicios s
                ON ps.ClvServ = s.ClvServ

            INNER JOIN psicologo psi
                ON psi.ClvPsi = ps.ClvPsi

            WHERE ps.ClvPsi = :clvPsi
              AND ps.EstatusAsignacion = 'ACTIVA'
              AND s.EstatusServicio = 'ACTIVO'
              AND s.ClvCons = psi.ClvCons
              AND ps.PrecioServicio > 0
              AND ps.DuracionMinutos BETWEEN 1 AND 480

            ORDER BY s.NombreServicio
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi
        ]);

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    /*
    =========================================
          HORARIOS
    =========================================
    */

    public function obtenerHorarios(): array
    {
        return $this->obtenerHorariosPublicosCompletos();
    }

    /**
     * Horario semanal completo del consultorio público activo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerHorariosPublicosCompletos(): array
    {
        $consultorio = $this->obtenerInformacion();

        if (!$consultorio) {
            return [];
        }

        return $this->obtenerHorariosPorClave(
            (string) $consultorio['ClvCons']
        );
    }

    public function obtenerHorariosPorClave(string $clvCons): array
    {
        $orden = implode(
            "','",
            [
                'LUNES',
                'MARTES',
                'MIERCOLES',
                'JUEVES',
                'VIERNES',
                'SABADO',
                'DOMINGO'
            ]
        );

        $sql = "
            SELECT
                hc.DiaSemana,
                hc.HoraInicio,
                hc.HoraFin,
                hc.EstatusHorario
            FROM horario_consultorio hc
            WHERE hc.ClvCons = :clvCons
            ORDER BY FIELD(hc.DiaSemana, '{$orden}')
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => trim($clvCons)
        ]);

        $registrados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $porDia = [];

        foreach ($registrados as $horario) {
            $porDia[$horario['DiaSemana']] = $horario;
        }

        $dias = [
            'LUNES',
            'MARTES',
            'MIERCOLES',
            'JUEVES',
            'VIERNES',
            'SABADO',
            'DOMINGO'
        ];

        $resultado = [];

        foreach ($dias as $dia) {
            $registro = $porDia[$dia] ?? null;

            $resultado[] = [
                'DiaSemana' => $dia,
                'Etiqueta' => \App\Helpers\Helper::etiquetaDiaHorario($dia),
                'HoraInicio' => $registro['HoraInicio'] ?? null,
                'HoraFin' => $registro['HoraFin'] ?? null,
                'EstatusHorario' =>
                    $registro['EstatusHorario'] ?? 'INACTIVO',
                'Configurado' => $registro !== null
            ];
        }

        return $resultado;
    }

    /*
    =========================================
          CARACTERÍSTICAS
    =========================================
    */

    public function obtenerCaracteristicas(): array
    {
        $sql = "
            SELECT
                car.ClvCar,
                car.Titulo,
                car.Descripcion,
                car.Icono,
                car.OrdenCar
            FROM caracteristica car
            INNER JOIN consultorio co
                ON co.ClvCons = car.ClvCons
            WHERE car.EstadoCar = 1
              AND co.EstatusCons = 'ACTIVO'
              AND co.PublicadoCons = 1
              AND EXISTS (
                    SELECT 1
                    FROM consultorio_usuario cu
                    INNER JOIN usuario u
                        ON u.ClvUsu = cu.ClvUsu
                    WHERE cu.ClvCons = co.ClvCons
                      AND cu.EstatusConsUsu = 'ACTIVO'
                      AND u.RolUsu = 'CONSULTORIO'
                      AND u.EstadoUsu = 1
              )
            ORDER BY car.OrdenCar, car.Titulo
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    =========================================
          REDES SOCIALES (legacy)
    =========================================
    */

    /**
     * @deprecated Usar RedSocialConsultorio::listarPublicasPorConsultorio($clvCons).
     *             Este método exige ClvCons y no lista toda la tabla.
     */
    public function obtenerRedes(?string $clvCons = null): array
    {
        $clvCons = trim((string) $clvCons);
        if ($clvCons === '') {
            return [];
        }

        return $this->obtenerRedesPorClave($clvCons);
    }

    /*
    =========================================
          ESPECIALISTAS PÚBLICOS
    =========================================
    */

    public function obtenerEspecialistasPublicos(
        string $clvCons
    ): array {
        $sql = "
            SELECT
                psi.ClvPsi,
                psi.ClvCons,
                psi.EspecialidadPsi,
                psi.DescripcionProfesional,

                c.NombreCons,

                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FotoPerfilPer,

                CONCAT(
                    per.NombrePer,
                    ' ',
                    per.ApPatPer,
                    ' ',
                    COALESCE(per.ApMatPer, '')
                ) AS NombreCompleto

            FROM psicologo psi

            INNER JOIN consultorio c
                ON c.ClvCons = psi.ClvCons

            INNER JOIN usuario usu
                ON psi.ClvUsu = usu.ClvUsu

            INNER JOIN persona per
                ON usu.ClvPer = per.ClvPer

            WHERE psi.ClvCons = :clvCons
              AND psi.EstatusPsi = 'ACTIVO'
              AND psi.MostrarEnPagina = 1
              AND usu.EstadoUsu = 1
              AND c.EstatusCons = 'ACTIVO'
              AND c.PublicadoCons = 1
              AND EXISTS (
                    SELECT 1
                    FROM consultorio_usuario cu
                    INNER JOIN usuario u
                        ON u.ClvUsu = cu.ClvUsu
                    WHERE cu.ClvCons = c.ClvCons
                      AND cu.EstatusConsUsu = 'ACTIVO'
                      AND u.RolUsu = 'CONSULTORIO'
                      AND u.EstadoUsu = 1
              )

            ORDER BY
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function obtenerEspecialistasParaVistaPrevia(
        string $clvCons
    ): array {
        $sql = "
            SELECT
                psi.ClvPsi,
                psi.ClvCons,
                psi.EspecialidadPsi,
                psi.DescripcionProfesional,

                c.NombreCons,

                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FotoPerfilPer,

                CONCAT(
                    per.NombrePer,
                    ' ',
                    per.ApPatPer,
                    ' ',
                    COALESCE(per.ApMatPer, '')
                ) AS NombreCompleto

            FROM psicologo psi

            INNER JOIN consultorio c
                ON c.ClvCons = psi.ClvCons

            INNER JOIN usuario usu
                ON psi.ClvUsu = usu.ClvUsu

            INNER JOIN persona per
                ON usu.ClvPer = per.ClvPer

            WHERE psi.ClvCons = :clvCons
              AND psi.EstatusPsi = 'ACTIVO'
              AND psi.MostrarEnPagina = 1
              AND usu.EstadoUsu = 1

            ORDER BY
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCons' => trim($clvCons)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerEspecialistasPublicosActivos(): array
    {
        $sql = "
            SELECT
                psi.ClvPsi,
                psi.ClvCons,
                psi.EspecialidadPsi,
                psi.DescripcionProfesional,

                c.NombreCons,

                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FotoPerfilPer,

                CONCAT(
                    per.NombrePer,
                    ' ',
                    per.ApPatPer,
                    ' ',
                    COALESCE(per.ApMatPer, '')
                ) AS NombreCompleto

            FROM psicologo psi

            INNER JOIN consultorio c
                ON c.ClvCons = psi.ClvCons

            INNER JOIN usuario usu
                ON psi.ClvUsu = usu.ClvUsu

            INNER JOIN persona per
                ON usu.ClvPer = per.ClvPer

            WHERE psi.EstatusPsi = 'ACTIVO'
              AND psi.MostrarEnPagina = 1
              AND usu.EstadoUsu = 1
              AND c.EstatusCons = 'ACTIVO'
              AND c.PublicadoCons = 1
              AND EXISTS (
                    SELECT 1
                    FROM consultorio_usuario cu
                    INNER JOIN usuario u
                        ON u.ClvUsu = cu.ClvUsu
                    WHERE cu.ClvCons = c.ClvCons
                      AND cu.EstatusConsUsu = 'ACTIVO'
                      AND u.RolUsu = 'CONSULTORIO'
                      AND u.EstadoUsu = 1
              )

            ORDER BY
                c.NombreCons,
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function obtenerCaracteristicasPorClave(string $clvCons): array
    {
        $sql = "
            SELECT
                car.ClvCar,
                car.Titulo,
                car.Descripcion,
                car.Icono,
                car.OrdenCar
            FROM caracteristica car
            WHERE car.ClvCons = :clvCons
              AND car.EstadoCar = 1
            ORDER BY car.OrdenCar, car.Titulo
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCons' => trim($clvCons)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerRedesPorClave(string $clvCons): array
    {
        return (new RedSocialConsultorio())
            ->listarPublicasPorConsultorio($clvCons);
    }

    /**
     * Especialistas públicos del consultorio con filtros opcionales de búsqueda.
     *
     * @return list<array<string, mixed>>
     */
    public function buscarEspecialistasPublicos(
        string $clvCons,
        string $busqueda = '',
        string $especialidad = '',
        bool $requerirPublicado = true
    ): array {
        $clvCons = trim($clvCons);
        $busqueda = trim($busqueda);
        $especialidad = trim($especialidad);

        $sql = "
            SELECT
                psi.ClvPsi,
                psi.ClvCons,
                psi.EspecialidadPsi,
                psi.CedulaProfesional,
                psi.DescripcionProfesional,
                c.NombreCons,
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FotoPerfilPer,
                CONCAT(
                    per.NombrePer,
                    ' ',
                    per.ApPatPer,
                    ' ',
                    COALESCE(per.ApMatPer, '')
                ) AS NombreCompleto
            FROM psicologo psi
            INNER JOIN consultorio c
                ON c.ClvCons = psi.ClvCons
            INNER JOIN usuario usu
                ON psi.ClvUsu = usu.ClvUsu
            INNER JOIN persona per
                ON usu.ClvPer = per.ClvPer
            WHERE psi.ClvCons = :clvCons
              AND psi.EstatusPsi = 'ACTIVO'
              AND psi.MostrarEnPagina = 1
              AND usu.EstadoUsu = 1
              AND c.EstatusCons = 'ACTIVO'
        ";

        $params = ['clvCons' => $clvCons];

        if ($requerirPublicado) {
            $sql .= "
              AND c.PublicadoCons = 1
              AND EXISTS (
                    SELECT 1
                    FROM consultorio_usuario cu
                    INNER JOIN usuario u
                        ON u.ClvUsu = cu.ClvUsu
                    WHERE cu.ClvCons = c.ClvCons
                      AND cu.EstatusConsUsu = 'ACTIVO'
                      AND u.RolUsu = 'CONSULTORIO'
                      AND u.EstadoUsu = 1
              )
            ";
        }

        if ($busqueda !== '') {
            $sql .= "
              AND (
                    per.NombrePer LIKE :busqueda
                    OR per.ApPatPer LIKE :busqueda
                    OR per.ApMatPer LIKE :busqueda
                    OR CONCAT(
                        per.NombrePer, ' ', per.ApPatPer, ' ',
                        COALESCE(per.ApMatPer, '')
                    ) LIKE :busqueda
                    OR psi.EspecialidadPsi LIKE :busqueda
              )
            ";
            $params['busqueda'] = '%' . $busqueda . '%';
        }

        if ($especialidad !== '') {
            $sql .= ' AND psi.EspecialidadPsi = :especialidad';
            $params['especialidad'] = $especialidad;
        }

        $sql .= '
            ORDER BY
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<string>
     */
    public function listarEspecialidadesPublicas(
        string $clvCons,
        bool $requerirPublicado = true
    ): array {
        $sql = "
            SELECT DISTINCT psi.EspecialidadPsi
            FROM psicologo psi
            INNER JOIN consultorio c
                ON c.ClvCons = psi.ClvCons
            INNER JOIN usuario usu
                ON psi.ClvUsu = usu.ClvUsu
            WHERE psi.ClvCons = :clvCons
              AND psi.EstatusPsi = 'ACTIVO'
              AND psi.MostrarEnPagina = 1
              AND usu.EstadoUsu = 1
              AND c.EstatusCons = 'ACTIVO'
              AND TRIM(psi.EspecialidadPsi) <> ''
        ";

        if ($requerirPublicado) {
            $sql .= "
              AND c.PublicadoCons = 1
              AND EXISTS (
                    SELECT 1
                    FROM consultorio_usuario cu
                    INNER JOIN usuario u
                        ON u.ClvUsu = cu.ClvUsu
                    WHERE cu.ClvCons = c.ClvCons
                      AND cu.EstatusConsUsu = 'ACTIVO'
                      AND u.RolUsu = 'CONSULTORIO'
                      AND u.EstadoUsu = 1
              )
            ";
        }

        $sql .= ' ORDER BY psi.EspecialidadPsi ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCons' => trim($clvCons)]);

        $filas = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $filas
        )));
    }

    /**
     * Perfil público de un especialista. Si $requerirPublicado es false,
     * permite vista previa del propietario/administrador.
     *
     * @return array<string, mixed>|null
     */
    public function obtenerEspecialistaPublico(
        string $clvPsi,
        ?string $clvConsEsperado = null,
        bool $requerirPublicado = true
    ): ?array {
        $sql = "
            SELECT
                psi.ClvPsi,
                psi.ClvCons,
                psi.EspecialidadPsi,
                psi.DescripcionProfesional,
                psi.EstatusPsi,
                psi.MostrarEnPagina,
                c.NombreCons,
                c.EstatusCons,
                c.PublicadoCons,
                c.TelefonoCons,
                c.CorreoElectronico,
                d.MunicipioDir,
                d.EstadoDir,
                d.CalleDir,
                d.ColoniaDir,
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FotoPerfilPer,
                CONCAT(
                    per.NombrePer,
                    ' ',
                    per.ApPatPer,
                    ' ',
                    COALESCE(per.ApMatPer, '')
                ) AS NombreCompleto
            FROM psicologo psi
            INNER JOIN consultorio c
                ON c.ClvCons = psi.ClvCons
            LEFT JOIN direccion d
                ON d.ClvDir = c.ClvDir
            INNER JOIN usuario usu
                ON psi.ClvUsu = usu.ClvUsu
            INNER JOIN persona per
                ON usu.ClvPer = per.ClvPer
            WHERE psi.ClvPsi = :clvPsi
              AND psi.EstatusPsi = 'ACTIVO'
              AND psi.MostrarEnPagina = 1
              AND usu.EstadoUsu = 1
              AND c.EstatusCons = 'ACTIVO'
        ";

        $params = ['clvPsi' => trim($clvPsi)];

        if ($clvConsEsperado !== null && trim($clvConsEsperado) !== '') {
            $sql .= ' AND psi.ClvCons = :clvCons';
            $params['clvCons'] = trim($clvConsEsperado);
        }

        if ($requerirPublicado) {
            $sql .= "
              AND c.PublicadoCons = 1
              AND EXISTS (
                    SELECT 1
                    FROM consultorio_usuario cu
                    INNER JOIN usuario u
                        ON u.ClvUsu = cu.ClvUsu
                    WHERE cu.ClvCons = c.ClvCons
                      AND cu.EstatusConsUsu = 'ACTIVO'
                      AND u.RolUsu = 'CONSULTORIO'
                      AND u.EstadoUsu = 1
              )
            ";
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }
}