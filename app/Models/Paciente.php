<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Paciente extends Model
{
    public function crear(array $datos): void
    {
        $clvPer = trim((string) ($datos['ClvPer'] ?? ''));
        if ($clvPer === '') {
            throw new \InvalidArgumentException(
                'Paciente::crear requiere ClvPer explícito.'
            );
        }

        $clvUsu = array_key_exists('ClvUsu', $datos)
            ? $datos['ClvUsu']
            : null;
        if ($clvUsu !== null) {
            $clvUsu = trim((string) $clvUsu);
            if ($clvUsu === '') {
                $clvUsu = null;
            }
        }

        $stmt = $this->db->prepare(
            "INSERT INTO paciente (
                ClvPac,
                FotoPerfilPac,
                EstadoActivoPac,
                ClvUsu,
                ClvPer,
                ClvCons
            ) VALUES (?,?,?,?,?,?)"
        );

        $stmt->execute([
            $datos['ClvPac'],
            'perfil-default.png',
            1,
            $clvUsu,
            $clvPer,
            $datos['ClvCons'] ?? null
        ]);
    }

public function obtenerPorUsuario(string $clvUsu): ?array
{
    $sql = "SELECT *

            FROM paciente

            WHERE ClvUsu = :clvUsu

            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvUsu' => $clvUsu
    ]);

    $paciente = $stmt->fetch(\PDO::FETCH_ASSOC);

    return $paciente ?: null;
}

/**
 * Datos básicos del paciente por clave (sin joins clínicos).
 *
 * @return array<string, mixed>|null
 */
public function obtenerPorClaveBasico(string $clvPac): ?array
{
    $sql = "SELECT *
            FROM paciente
            WHERE ClvPac = :clvPac
            LIMIT 1";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        'clvPac' => trim($clvPac)
    ]);

    $paciente = $stmt->fetch(\PDO::FETCH_ASSOC);

    return $paciente ?: null;
}

/*
=====================================
    OBTENER PERFIL COMPLETO
=====================================
*/

public function obtenerPerfilCompleto(
    string $clvUsu
): ?array {

    $sql = "SELECT

                p.ClvPac,
                p.FotoPerfilPac,
                p.EstadoActivoPac,

                u.ClvUsu,
                u.CorreoUsu,
                u.TelefonoUsu,

                per.ClvPer,
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FechaNacimiento,
                per.GeneroPer,
                per.FotoPerfilPer,
                per.ClvDir,

                d.PaisDir,
                d.EstadoDir,
                d.MunicipioDir,
                d.ColoniaDir,
                d.CalleDir,
                d.CodPostDir,
                d.NumExtDir,
                d.NumIntDir,
                d.ReferenciaDir

            FROM paciente p

            INNER JOIN persona per
                ON per.ClvPer = p.ClvPer

            LEFT JOIN usuario u
                ON u.ClvUsu = p.ClvUsu

            LEFT JOIN direccion d
                ON per.ClvDir = d.ClvDir

            WHERE

                p.ClvUsu = :clvUsu

            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([

        'clvUsu' => $clvUsu

    ]);

    $perfil = $stmt->fetch(\PDO::FETCH_ASSOC);

    return $perfil ?: null;

}

/*
=====================================
        ACTUALIZAR PERFIL PERSONAL
=====================================
*/

    /**
     * Actualiza persona + dirección + fotografía del paciente autenticado.
     * No modifica correo ni teléfono.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed>|null $foto
     */
    public function actualizarPerfilPersonal(
        string $clvUsu,
        array $datos,
        ?array $foto = null
    ): void {
        $clvUsu = trim($clvUsu);
        $fotoNueva = null;
        $fotoAnterior = null;
        $direccionModel = new Direccion();

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "SELECT
                    p.ClvPac,
                    p.FotoPerfilPac,
                    p.ClvPer,
                    u.ClvUsu,
                    per.FotoPerfilPer,
                    per.ClvDir
                 FROM paciente p
                 INNER JOIN persona per
                    ON per.ClvPer = p.ClvPer
                 INNER JOIN usuario u
                    ON u.ClvUsu = p.ClvUsu
                 WHERE p.ClvUsu = :clvUsu
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['clvUsu' => $clvUsu]);
            $perfil = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$perfil) {
                throw new \RuntimeException(
                    'No se encontró el perfil del paciente.'
                );
            }

            $clvPer = (string) ($perfil['ClvPer'] ?? '');
            $clvDirActual = trim((string) ($perfil['ClvDir'] ?? ''));
            $fotoAnterior = trim((string) (
                $perfil['FotoPerfilPer']
                ?? $perfil['FotoPerfilPac']
                ?? ''
            ));
            $nombreFoto = $fotoAnterior !== ''
                ? $fotoAnterior
                : null;

            if (
                $foto !== null
                && isset($foto['error'])
                && (int) $foto['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                $fotoNueva = $this->guardarFotoPerfil($foto);
                $nombreFoto = $fotoNueva;
            }

            $stmtPersona = $this->db->prepare(
                "UPDATE persona
                 SET
                    NombrePer = :nombre,
                    ApPatPer = :apPat,
                    ApMatPer = :apMat,
                    FechaNacimiento = :fecha,
                    GeneroPer = :genero,
                    FotoPerfilPer = :foto
                 WHERE ClvPer = :clvPer"
            );

            $stmtPersona->execute([
                'nombre' => $datos['NombrePer'],
                'apPat' => $datos['ApPatPer'],
                'apMat' => $datos['ApMatPer'],
                'fecha' => $datos['FechaNacimiento'],
                'genero' => $datos['GeneroPer'],
                'foto' => $nombreFoto,
                'clvPer' => $clvPer
            ]);

            if ($nombreFoto !== null && $nombreFoto !== '') {
                $stmtPacFoto = $this->db->prepare(
                    "UPDATE paciente
                     SET FotoPerfilPac = :foto
                     WHERE ClvPac = :clvPac"
                );
                $stmtPacFoto->execute([
                    'foto' => $nombreFoto,
                    'clvPac' => $perfil['ClvPac']
                ]);
            }

            if (!empty($datos['actualizar_direccion'])) {
                $datosDir = [
                    'PaisDir' => $datos['PaisDir'],
                    'EstadoDir' => $datos['EstadoDir'],
                    'MunicipioDir' => $datos['MunicipioDir'],
                    'ColoniaDir' => $datos['ColoniaDir'],
                    'CalleDir' => $datos['CalleDir'],
                    'CodPostDir' => $datos['CodPostDir'],
                    'NumExtDir' => $datos['NumExtDir'],
                    'NumIntDir' => $datos['NumIntDir'],
                    'LatitudDir' => null,
                    'LongitudDir' => null,
                    'ReferenciaDir' => $datos['ReferenciaDir']
                ];

                $clvDirFinal = $clvDirActual;

                if ($clvDirActual !== '') {
                    $puedeActualizar =
                        $direccionModel->esExclusivaDePersona(
                            $clvDirActual,
                            $clvPer
                        );

                    if ($puedeActualizar) {
                        $direccionModel->actualizarPorClave(
                            $clvDirActual,
                            $datosDir
                        );
                    } else {
                        $clvDirFinal = $direccionModel->crear($datosDir);
                    }
                } else {
                    $clvDirFinal = $direccionModel->crear($datosDir);
                }

                if ($clvDirFinal !== $clvDirActual) {
                    $stmtDir = $this->db->prepare(
                        "UPDATE persona
                         SET ClvDir = :clvDir
                         WHERE ClvPer = :clvPer"
                    );
                    $stmtDir->execute([
                        'clvDir' => $clvDirFinal,
                        'clvPer' => $clvPer
                    ]);
                }
            }

            $this->db->commit();

            if (
                $fotoNueva !== null
                && $fotoAnterior !== ''
                && $fotoAnterior !== $fotoNueva
            ) {
                $this->eliminarFotoPerfilSiCorresponde($fotoAnterior);
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($fotoNueva !== null) {
                $this->eliminarFotoPerfilSiCorresponde($fotoNueva);
            }

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $foto
     */
    private function guardarFotoPerfil(array $foto): string
    {
        if (
            !isset(
                $foto['tmp_name'],
                $foto['size'],
                $foto['error']
            )
        ) {
            throw new \RuntimeException(
                'La fotografía recibida no es válida.'
            );
        }

        if ((int) $foto['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(
                'Ocurrió un error al subir la fotografía.'
            );
        }

        if ((int) $foto['size'] > 2 * 1024 * 1024) {
            throw new \RuntimeException(
                'La fotografía no debe superar 2 MB.'
            );
        }

        $tiposPermitidos = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $tipoMime = $finfo->file($foto['tmp_name']);

        if (
            !is_string($tipoMime)
            || !isset($tiposPermitidos[$tipoMime])
        ) {
            throw new \RuntimeException(
                'La fotografía debe ser JPG, PNG o WEBP.'
            );
        }

        $nombreArchivo =
            'perfil_' .
            bin2hex(random_bytes(16)) .
            '.' .
            $tiposPermitidos[$tipoMime];

        $directorio =
            \App\Config\Paths::publicPath() .
            '/uploads/perfiles';

        if (
            !is_dir($directorio)
            && !mkdir($directorio, 0775, true)
            && !is_dir($directorio)
        ) {
            throw new \RuntimeException(
                'No fue posible crear la carpeta de perfiles.'
            );
        }

        $rutaDestino =
            $directorio .
            DIRECTORY_SEPARATOR .
            $nombreArchivo;

        if (!move_uploaded_file($foto['tmp_name'], $rutaDestino)) {
            throw new \RuntimeException(
                'No fue posible guardar la fotografía.'
            );
        }

        if (!is_file($rutaDestino)) {
            throw new \RuntimeException(
                'La fotografía no quedó almacenada correctamente.'
            );
        }

        return $nombreArchivo;
    }

    private function eliminarFotoPerfilSiCorresponde(string $nombre): void
    {
        $nombre = basename(trim($nombre));

        $protegidas = [
            '',
            'default.png',
            'perfil-default.png'
        ];

        if (in_array($nombre, $protegidas, true)) {
            return;
        }

        if (
            $nombre === ''
            || str_contains($nombre, '..')
            || !preg_match('/^perfil_[a-f0-9]{24,64}\.(jpg|jpeg|png|webp)$/i', $nombre)
        ) {
            return;
        }

        $directorio =
            \App\Config\Paths::publicPath() .
            '/uploads/perfiles';

        $ruta = $directorio . DIRECTORY_SEPARATOR . $nombre;
        $realDir = realpath($directorio);
        $realArchivo = realpath($ruta);

        if (
            $realDir === false
            || $realArchivo === false
            || !str_starts_with($realArchivo, $realDir)
            || !is_file($realArchivo)
        ) {
            return;
        }

        @unlink($realArchivo);
    }

public function listarParaCrearCita(
    string $clvPsi
): array {
    $sql = "SELECT DISTINCT
                p.ClvPac,

                CONCAT(
                    TRIM(per.NombrePer),
                    ' ',
                    TRIM(per.ApPatPer),
                    ' ',
                    TRIM(COALESCE(per.ApMatPer, ''))
                ) AS NombrePaciente

            FROM cita c

            INNER JOIN paciente p
                ON c.ClvPac = p.ClvPac

            INNER JOIN persona per
                ON per.ClvPer = p.ClvPer

            LEFT JOIN usuario u
                ON u.ClvUsu = p.ClvUsu

            WHERE c.ClvPsi = :clvPsi

            ORDER BY
                NombrePaciente ASC";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvPsi' => $clvPsi
    ]);

    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

public function perteneceAPsicologo(
    string $clvPac,
    string $clvPsi
): bool {
    $sql = "SELECT 1
            FROM cita
            WHERE ClvPac = :clvPac
              AND ClvPsi = :clvPsi
            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvPac' => $clvPac,
        'clvPsi' => $clvPsi
    ]);

    return (bool) $stmt->fetchColumn();
}

public function perteneceAlConsultorio(
    string $clvPac,
    string $clvCons
): bool {
    $sql = "SELECT 1
            FROM paciente
            WHERE ClvPac = :clvPac
              AND ClvCons = :clvCons
            LIMIT 1";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        'clvPac' => trim($clvPac),
        'clvCons' => trim($clvCons)
    ]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Datos personales + dirección para completar información (psicólogo autorizado).
 *
 * @return array<string, mixed>|null
 */
public function obtenerDatosPersonalesParaPsicologo(
    string $clvPac,
    string $clvPsi,
    string $clvCons
): ?array {
    if (
        !$this->perteneceAPsicologo($clvPac, $clvPsi)
        || !$this->perteneceAlConsultorio($clvPac, $clvCons)
    ) {
        return null;
    }

    $sql = "SELECT
                p.ClvPac,
                p.FotoPerfilPac,
                p.ClvCons,
                u.ClvUsu,
                per.ClvPer,
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FechaNacimiento,
                per.GeneroPer,
                per.FotoPerfilPer,
                per.ClvDir,
                d.PaisDir,
                d.EstadoDir,
                d.MunicipioDir,
                d.ColoniaDir,
                d.CalleDir,
                d.CodPostDir,
                d.NumExtDir,
                d.NumIntDir,
                d.ReferenciaDir,
                CONCAT(
                    TRIM(per.NombrePer),
                    ' ',
                    TRIM(per.ApPatPer),
                    ' ',
                    TRIM(COALESCE(per.ApMatPer, ''))
                ) AS NombrePaciente
            FROM paciente p
            INNER JOIN persona per
                ON per.ClvPer = p.ClvPer
            LEFT JOIN usuario u
                ON u.ClvUsu = p.ClvUsu
            LEFT JOIN direccion d
                ON d.ClvDir = per.ClvDir
            WHERE p.ClvPac = :clvPac
              AND p.ClvCons = :clvCons
            LIMIT 1";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        'clvPac' => trim($clvPac),
        'clvCons' => trim($clvCons)
    ]);

    $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$fila) {
        return null;
    }

    return $this->enriquecerPacienteCatalogo($fila);
}

/**
 * Actualiza solo columnas de persona/dirección que siguen vacías.
 *
 * @param array<string, string> $payloadPersona
 * @param array<string, string> $payloadDireccion
 * @return array{actualizados: list<string>, omitidos: list<string>}
 */
public function completarInformacionPorPsicologo(
    string $clvPac,
    string $clvPsi,
    string $clvCons,
    array $payloadPersona,
    array $payloadDireccion
): array {
    if (
        !$this->perteneceAPsicologo($clvPac, $clvPsi)
        || !$this->perteneceAlConsultorio($clvPac, $clvCons)
    ) {
        throw new \RuntimeException('No autorizado.');
    }

    $direccionModel = new Direccion();
    $actualizados = [];
    $omitidos = [];

    try {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }

        $stmt = $this->db->prepare(
            "SELECT
                p.ClvPac,
                u.ClvUsu,
                per.ClvPer,
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FechaNacimiento,
                per.GeneroPer,
                per.ClvDir
             FROM paciente p
             INNER JOIN persona per ON per.ClvPer = p.ClvPer
             LEFT JOIN usuario u ON u.ClvUsu = p.ClvUsu
             WHERE p.ClvPac = :clvPac
               AND p.ClvCons = :clvCons
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([
            'clvPac' => trim($clvPac),
            'clvCons' => trim($clvCons)
        ]);
        $persona = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$persona) {
            throw new \RuntimeException('Paciente no encontrado.');
        }

        $clvPer = (string) $persona['ClvPer'];
        $setsPersona = [];
        $paramsPersona = ['clvPer' => $clvPer];

        $mapaPersona = [
            'NombrePer' => 'NombrePer',
            'ApPatPer' => 'ApPatPer',
            'ApMatPer' => 'ApMatPer',
            'FechaNacimiento' => 'FechaNacimiento',
            'GeneroPer' => 'GeneroPer'
        ];

        foreach ($mapaPersona as $campo => $columna) {
            if (!array_key_exists($campo, $payloadPersona)) {
                continue;
            }

            $nuevo = trim((string) $payloadPersona[$campo]);
            if ($this->esValorPersonalFaltante($nuevo)) {
                continue;
            }

            if (!$this->esValorPersonalFaltante($persona[$columna] ?? null)) {
                $omitidos[] = $campo;
                continue;
            }

            $setsPersona[] = "{$columna} = :{$campo}";
            $paramsPersona[$campo] = $nuevo;
            $actualizados[] = $campo;
        }

        if ($setsPersona !== []) {
            $sqlPersona = 'UPDATE persona SET '
                . implode(', ', $setsPersona)
                . ' WHERE ClvPer = :clvPer';
            $this->db->prepare($sqlPersona)->execute($paramsPersona);
        }

        $clvDirActual = trim((string) ($persona['ClvDir'] ?? ''));
        $dirActual = $clvDirActual !== ''
            ? $direccionModel->obtenerPorClave($clvDirActual)
            : null;

        $camposDirPermitidos = [
            'PaisDir', 'EstadoDir', 'MunicipioDir', 'ColoniaDir',
            'CalleDir', 'CodPostDir', 'NumExtDir', 'NumIntDir', 'ReferenciaDir'
        ];

        $hayPayloadDir = false;
        foreach ($camposDirPermitidos as $campo) {
            if (
                array_key_exists($campo, $payloadDireccion)
                && !$this->esValorPersonalFaltante($payloadDireccion[$campo])
            ) {
                $hayPayloadDir = true;
                break;
            }
        }

        if ($hayPayloadDir) {
            $resultadoDir = $this->aplicarCompletadoDireccion(
                $direccionModel,
                $clvPer,
                $clvDirActual,
                is_array($dirActual) ? $dirActual : [],
                $payloadDireccion,
                $camposDirPermitidos
            );

            foreach ($resultadoDir['actualizados'] as $campo) {
                $actualizados[] = $campo;
            }
            foreach ($resultadoDir['omitidos'] as $campo) {
                $omitidos[] = $campo;
            }
        }

        $this->db->commit();

        return [
            'actualizados' => $actualizados,
            'omitidos' => $omitidos
        ];
    } catch (\Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        throw $e;
    }
}

/**
 * @param array<string, mixed> $dirActual
 * @param array<string, string> $payload
 * @param list<string> $camposPermitidos
 * @return array{actualizados: list<string>, omitidos: list<string>}
 */
private function aplicarCompletadoDireccion(
    Direccion $direccionModel,
    string $clvPer,
    string $clvDirActual,
    array $dirActual,
    array $payload,
    array $camposPermitidos
): array {
    $actualizados = [];
    $omitidos = [];

    $vaciosDeseados = [];
    foreach ($camposPermitidos as $campo) {
        if (!array_key_exists($campo, $payload)) {
            continue;
        }
        $nuevo = trim((string) $payload[$campo]);
        if ($this->esValorPersonalFaltante($nuevo)) {
            continue;
        }

        if (
            $clvDirActual !== ''
            && !$this->esValorPersonalFaltante($dirActual[$campo] ?? null)
        ) {
            $omitidos[] = $campo;
            continue;
        }

        $vaciosDeseados[$campo] = $nuevo;
    }

    if ($vaciosDeseados === []) {
        return compact('actualizados', 'omitidos');
    }

    if ($clvDirActual === '') {
        $datosNuevos = $this->armarDireccionParaAlta($vaciosDeseados, []);
        $clvDir = $direccionModel->crear($datosNuevos);
        $this->db->prepare(
            'UPDATE persona SET ClvDir = :clvDir WHERE ClvPer = :clvPer'
        )->execute([
            'clvDir' => $clvDir,
            'clvPer' => $clvPer
        ]);
        $actualizados = array_keys($vaciosDeseados);

        return compact('actualizados', 'omitidos');
    }

    $exclusiva = $direccionModel->esExclusivaDePersona($clvDirActual, $clvPer);

    if ($exclusiva) {
        $direccionModel->actualizarCamposVacios(
            $clvDirActual,
            $vaciosDeseados
        );
        $actualizados = array_keys($vaciosDeseados);

        return compact('actualizados', 'omitidos');
    }

    // Dirección compartida: clonar completa y completar solo vacíos.
    $datosNuevos = $this->clonarDireccionIndependiente(
        $dirActual,
        $vaciosDeseados
    );
    $clvDir = $direccionModel->crear($datosNuevos);
    $this->db->prepare(
        'UPDATE persona SET ClvDir = :clvDir WHERE ClvPer = :clvPer'
    )->execute([
        'clvDir' => $clvDir,
        'clvPer' => $clvPer
    ]);
    $actualizados = array_keys($vaciosDeseados);

    return compact('actualizados', 'omitidos');
}

/**
 * Copia todos los valores de la dirección compartida y aplica solo
 * completados en campos que estaban null o vacíos.
 *
 * @param array<string, mixed> $base
 * @param array<string, string> $vaciosDeseados
 * @return array<string, mixed>
 */
private function clonarDireccionIndependiente(
    array $base,
    array $vaciosDeseados
): array {
    $camposTexto = [
        'PaisDir',
        'EstadoDir',
        'MunicipioDir',
        'ColoniaDir',
        'CalleDir',
        'CodPostDir',
        'NumExtDir',
        'NumIntDir',
        'ReferenciaDir'
    ];

    $datos = [];

    foreach ($camposTexto as $campo) {
        if (!$this->esValorPersonalFaltante($base[$campo] ?? null)) {
            $datos[$campo] = trim((string) $base[$campo]);
            continue;
        }

        if (
            array_key_exists($campo, $vaciosDeseados)
            && !$this->esValorPersonalFaltante($vaciosDeseados[$campo])
        ) {
            $datos[$campo] = trim((string) $vaciosDeseados[$campo]);
            continue;
        }

        $datos[$campo] = null;
    }

    $datos['LatitudDir'] = array_key_exists('LatitudDir', $base)
        ? $base['LatitudDir']
        : null;
    $datos['LongitudDir'] = array_key_exists('LongitudDir', $base)
        ? $base['LongitudDir']
        : null;

    if ($this->esValorPersonalFaltante($datos['PaisDir'] ?? null)) {
        $datos['PaisDir'] = 'México';
    }

    foreach (['EstadoDir', 'MunicipioDir', 'ColoniaDir', 'CodPostDir'] as $obligatorio) {
        if ($this->esValorPersonalFaltante($datos[$obligatorio] ?? null)) {
            throw new \RuntimeException(
                'Para registrar la dirección faltan datos obligatorios.'
            );
        }
    }

    return $datos;
}

/**
 * @param array<string, string> $nuevos
 * @param array<string, mixed> $base
 * @return array<string, mixed>
 */
private function armarDireccionParaAlta(array $nuevos, array $base): array
{
    $tomar = function (string $campo) use ($nuevos, $base): ?string {
        if (
            array_key_exists($campo, $nuevos)
            && !$this->esValorPersonalFaltante($nuevos[$campo])
        ) {
            return trim((string) $nuevos[$campo]);
        }

        if (!$this->esValorPersonalFaltante($base[$campo] ?? null)) {
            return trim((string) $base[$campo]);
        }

        return null;
    };

    $pais = $tomar('PaisDir') ?? 'México';
    $estado = $tomar('EstadoDir');
    $municipio = $tomar('MunicipioDir');
    $colonia = $tomar('ColoniaDir');
    $codPost = $tomar('CodPostDir');

    if (
        $estado === null
        || $municipio === null
        || $colonia === null
        || $codPost === null
    ) {
        throw new \RuntimeException(
            'Para registrar la dirección faltan datos obligatorios.'
        );
    }

    return [
        'PaisDir' => $pais,
        'EstadoDir' => $estado,
        'MunicipioDir' => $municipio,
        'ColoniaDir' => $colonia,
        'CalleDir' => $tomar('CalleDir'),
        'CodPostDir' => $codPost,
        'NumExtDir' => $tomar('NumExtDir'),
        'NumIntDir' => $tomar('NumIntDir'),
        'LatitudDir' => $base['LatitudDir'] ?? null,
        'LongitudDir' => $base['LongitudDir'] ?? null,
        'ReferenciaDir' => $tomar('ReferenciaDir')
    ];
}

private function esValorPersonalFaltante(mixed $valor): bool
{
    if ($valor === null) {
        return true;
    }

    return trim((string) $valor) === '';
}

public function listarPorPsicologo(
    string $clvPsi,
    ?string $busqueda = null,
    ?string $filtro = null
): array {
    $sql = "SELECT
                p.ClvPac,
                p.FotoPerfilPac,

                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FechaNacimiento,

                CONCAT(
                    TRIM(per.NombrePer),
                    ' ',
                    TRIM(per.ApPatPer),
                    ' ',
                    TRIM(COALESCE(per.ApMatPer, ''))
                ) AS NombrePaciente,

                u.ClvUsu,
                u.CorreoUsu,
                u.TelefonoUsu,
                u.EstadoUsu,

                COUNT(c.ClvCita) AS TotalCitas,

                (
                    SELECT c0.EstadoCita
                    FROM cita c0
                    WHERE c0.ClvPac = p.ClvPac
                      AND c0.ClvPsi = :clvPsiPrim
                    ORDER BY
                        c0.FechaRegistroCita ASC,
                        c0.FechaCita ASC,
                        c0.HraInicioCita ASC
                    LIMIT 1
                ) AS PrimeraEstado,

                (
                    SELECT COUNT(*)
                    FROM historial_clinico h
                    WHERE h.ClvPac = p.ClvPac
                      AND h.ClvPsi = :clvPsiHist
                ) AS TotalHistorias,

                (
                    SELECT COUNT(*)
                    FROM cita cSeg
                    INNER JOIN historial_clinico hSeg
                        ON hSeg.ClvPac = cSeg.ClvPac
                       AND hSeg.ClvCons = cSeg.ClvCons
                       AND hSeg.ClvPsi = cSeg.ClvPsi
                    WHERE cSeg.ClvPac = p.ClvPac
                      AND cSeg.ClvPsi = :clvPsiSegPend
                      AND cSeg.EstadoCita = 'ASISTIDA'
                      AND NOT EXISTS (
                            SELECT 1
                            FROM seguimiento_sesion ss
                            WHERE (ss.ClvCita COLLATE utf8mb4_unicode_ci) = cSeg.ClvCita
                      )
                ) AS TotalSeguimientosPendientes,

                MIN(c.FechaCita) AS FechaDesde,

                SUM(
                    CASE
                        WHEN c.EstadoCita = 'ASISTIDA'
                        THEN 1 ELSE 0
                    END
                ) AS TotalAsistidas,

                (
                    SELECT c2.FechaCita
                    FROM cita c2
                    WHERE c2.ClvPac = p.ClvPac
                      AND c2.ClvPsi = :clvPsiProx
                      AND c2.EstadoCita = 'PROGRAMADA'
                      AND (
                            c2.FechaCita > CURDATE()
                            OR (
                                c2.FechaCita = CURDATE()
                                AND c2.HraInicioCita >= CURTIME()
                            )
                      )
                    ORDER BY
                        c2.FechaCita ASC,
                        c2.HraInicioCita ASC
                    LIMIT 1
                ) AS ProximaFecha,

                (
                    SELECT c2.HraInicioCita
                    FROM cita c2
                    WHERE c2.ClvPac = p.ClvPac
                      AND c2.ClvPsi = :clvPsiProxH
                      AND c2.EstadoCita = 'PROGRAMADA'
                      AND (
                            c2.FechaCita > CURDATE()
                            OR (
                                c2.FechaCita = CURDATE()
                                AND c2.HraInicioCita >= CURTIME()
                            )
                      )
                    ORDER BY
                        c2.FechaCita ASC,
                        c2.HraInicioCita ASC
                    LIMIT 1
                ) AS ProximaHora,

                (
                    SELECT s2.NombreServicio
                    FROM cita c2
                    INNER JOIN servicios s2
                        ON s2.ClvServ = c2.ClvServ
                    WHERE c2.ClvPac = p.ClvPac
                      AND c2.ClvPsi = :clvPsiProxS
                      AND c2.EstadoCita = 'PROGRAMADA'
                      AND (
                            c2.FechaCita > CURDATE()
                            OR (
                                c2.FechaCita = CURDATE()
                                AND c2.HraInicioCita >= CURTIME()
                            )
                      )
                    ORDER BY
                        c2.FechaCita ASC,
                        c2.HraInicioCita ASC
                    LIMIT 1
                ) AS ProximaServicio,

                (
                    SELECT c3.FechaCita
                    FROM cita c3
                    WHERE c3.ClvPac = p.ClvPac
                      AND c3.ClvPsi = :clvPsiUlt
                      AND (
                            c3.FechaCita < CURDATE()
                            OR (
                                c3.FechaCita = CURDATE()
                                AND c3.HraInicioCita < CURTIME()
                            )
                      )
                    ORDER BY
                        c3.FechaCita DESC,
                        c3.HraInicioCita DESC
                    LIMIT 1
                ) AS UltimaFecha,

                (
                    SELECT c3.HraInicioCita
                    FROM cita c3
                    WHERE c3.ClvPac = p.ClvPac
                      AND c3.ClvPsi = :clvPsiUltH
                      AND (
                            c3.FechaCita < CURDATE()
                            OR (
                                c3.FechaCita = CURDATE()
                                AND c3.HraInicioCita < CURTIME()
                            )
                      )
                    ORDER BY
                        c3.FechaCita DESC,
                        c3.HraInicioCita DESC
                    LIMIT 1
                ) AS UltimaHora,

                (
                    SELECT s3.NombreServicio
                    FROM cita c3
                    INNER JOIN servicios s3
                        ON s3.ClvServ = c3.ClvServ
                    WHERE c3.ClvPac = p.ClvPac
                      AND c3.ClvPsi = :clvPsiUltS
                      AND (
                            c3.FechaCita < CURDATE()
                            OR (
                                c3.FechaCita = CURDATE()
                                AND c3.HraInicioCita < CURTIME()
                            )
                      )
                    ORDER BY
                        c3.FechaCita DESC,
                        c3.HraInicioCita DESC
                    LIMIT 1
                ) AS UltimaServicio,

                (
                    SELECT c3.EstadoCita
                    FROM cita c3
                    WHERE c3.ClvPac = p.ClvPac
                      AND c3.ClvPsi = :clvPsiUltE
                      AND (
                            c3.FechaCita < CURDATE()
                            OR (
                                c3.FechaCita = CURDATE()
                                AND c3.HraInicioCita < CURTIME()
                            )
                      )
                    ORDER BY
                        c3.FechaCita DESC,
                        c3.HraInicioCita DESC
                    LIMIT 1
                ) AS UltimaEstado

            FROM paciente p

            INNER JOIN persona per
                ON per.ClvPer = p.ClvPer

            LEFT JOIN usuario u
                ON u.ClvUsu = p.ClvUsu

            INNER JOIN cita c
                ON c.ClvPac = p.ClvPac
               AND c.ClvPsi = :clvPsi

            WHERE 1 = 1";

    $parametros = [
        'clvPsi' => $clvPsi,
        'clvPsiPrim' => $clvPsi,
        'clvPsiHist' => $clvPsi,
        'clvPsiSegPend' => $clvPsi,
        'clvPsiProx' => $clvPsi,
        'clvPsiProxH' => $clvPsi,
        'clvPsiProxS' => $clvPsi,
        'clvPsiUlt' => $clvPsi,
        'clvPsiUltH' => $clvPsi,
        'clvPsiUltS' => $clvPsi,
        'clvPsiUltE' => $clvPsi
    ];

    $busqueda = trim((string) $busqueda);

    if ($busqueda !== '') {
        $sql .= "
            AND (
                per.NombrePer LIKE :busquedaNombre
                OR per.ApPatPer LIKE :busquedaApPat
                OR per.ApMatPer LIKE :busquedaApMat
                OR CONCAT(
                    per.NombrePer, ' ',
                    per.ApPatPer, ' ',
                    COALESCE(per.ApMatPer, '')
                ) LIKE :busquedaCompleto
                OR u.CorreoUsu LIKE :busquedaCorreo
                OR u.TelefonoUsu LIKE :busquedaTelefono
            )";

        $termino = '%' . $busqueda . '%';

        $parametros['busquedaNombre'] = $termino;
        $parametros['busquedaApPat'] = $termino;
        $parametros['busquedaApMat'] = $termino;
        $parametros['busquedaCompleto'] = $termino;
        $parametros['busquedaCorreo'] = $termino;
        $parametros['busquedaTelefono'] = $termino;
    }

    $filtro = strtolower(trim((string) $filtro));

    if ($filtro === 'proxima') {
        $sql .= "
            AND EXISTS (
                SELECT 1
                FROM cita cx
                WHERE cx.ClvPac = p.ClvPac
                  AND cx.ClvPsi = :clvPsiFiltro
                  AND cx.EstadoCita = 'PROGRAMADA'
                  AND (
                        cx.FechaCita > CURDATE()
                        OR (
                            cx.FechaCita = CURDATE()
                            AND cx.HraInicioCita >= CURTIME()
                        )
                  )
            )";

        $parametros['clvPsiFiltro'] = $clvPsi;
    } elseif ($filtro === 'atendidos') {
        $sql .= "
            AND EXISTS (
                SELECT 1
                FROM cita cx
                WHERE cx.ClvPac = p.ClvPac
                  AND cx.ClvPsi = :clvPsiFiltroA
                  AND cx.EstadoCita = 'ASISTIDA'
            )
            AND NOT EXISTS (
                SELECT 1
                FROM cita cx
                WHERE cx.ClvPac = p.ClvPac
                  AND cx.ClvPsi = :clvPsiFiltroB
                  AND cx.EstadoCita = 'PROGRAMADA'
                  AND (
                        cx.FechaCita > CURDATE()
                        OR (
                            cx.FechaCita = CURDATE()
                            AND cx.HraInicioCita >= CURTIME()
                        )
                  )
            )";

        $parametros['clvPsiFiltroA'] = $clvPsi;
        $parametros['clvPsiFiltroB'] = $clvPsi;
    } elseif ($filtro === 'historicos') {
        $sql .= "
            AND NOT EXISTS (
                SELECT 1
                FROM cita cx
                WHERE cx.ClvPac = p.ClvPac
                  AND cx.ClvPsi = :clvPsiFiltroH1
                  AND cx.EstadoCita = 'PROGRAMADA'
                  AND (
                        cx.FechaCita > CURDATE()
                        OR (
                            cx.FechaCita = CURDATE()
                            AND cx.HraInicioCita >= CURTIME()
                        )
                  )
            )
            AND NOT EXISTS (
                SELECT 1
                FROM cita cx
                WHERE cx.ClvPac = p.ClvPac
                  AND cx.ClvPsi = :clvPsiFiltroH2
                  AND cx.EstadoCita = 'ASISTIDA'
            )";

        $parametros['clvPsiFiltroH1'] = $clvPsi;
        $parametros['clvPsiFiltroH2'] = $clvPsi;
    }

    $sql .= "
            GROUP BY
                p.ClvPac,
                p.FotoPerfilPac,
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FechaNacimiento,
                u.ClvUsu,
                u.CorreoUsu,
                u.TelefonoUsu,
                u.EstadoUsu

            ORDER BY
                NombrePaciente ASC";

    $stmt = $this->db->prepare($sql);

    $stmt->execute($parametros);

    $filas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(
        [$this, 'enriquecerPacienteCatalogo'],
        $filas
    );
}

public function contarPorPsicologo(
    string $clvPsi,
    ?string $busqueda = null,
    ?string $filtro = null
): int {
    return count(
        $this->listarPorPsicologo(
            $clvPsi,
            $busqueda,
            $filtro
        )
    );
}

/**
 * Catálogo de expedientes (Fase 3B).
 * Relación autorizada: EXISTS cita con el psicólogo (sin duplicar pacientes).
 * Última sesión: última cita ASISTIDA ya iniciada/pasada.
 * Actividad reciente (filtro y orden): CASE entre
 *   última ASISTIDA y último seguimiento autorizado
 *   (NULL-safe; sin fechas ficticias) en los últimos 90 días.
 *
 * @param array{
 *   q?: string,
 *   actividad?: string,
 *   cita?: string,
 *   pendiente?: string,
 *   orden?: string,
 *   pagina?: int,
 *   porPagina?: int
 * } $opciones
 * @return array{
 *   items: list<array<string, mixed>>,
 *   total: int,
 *   pagina: int,
 *   porPagina: int,
 *   totalPaginas: int,
 *   desde: int,
 *   hasta: int
 * }
 */
public function listarCatalogoExpedientes(
    string $clvPsi,
    array $opciones = []
): array {
    $clvPsi = trim($clvPsi);
    $porPagina = max(1, min(48, (int) ($opciones['porPagina'] ?? 12)));
    $pagina = max(1, (int) ($opciones['pagina'] ?? 1));

    if ($clvPsi === '') {
        return [
            'items' => [],
            'total' => 0,
            'pagina' => 1,
            'porPagina' => $porPagina,
            'totalPaginas' => 1,
            'desde' => 0,
            'hasta' => 0
        ];
    }

    $filtros = $this->normalizarFiltrosCatalogoExpedientes($opciones);
    $base = $this->sqlBaseCatalogoExpedientes($clvPsi, $filtros);

    $sqlCount = 'SELECT COUNT(*) FROM (' . $base['sql'] . ') AS catalogo_exp';
    $stmtCount = $this->db->prepare($sqlCount);
    $stmtCount->execute($base['params']);
    $total = (int) $stmtCount->fetchColumn();

    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    if ($pagina > $totalPaginas) {
        $pagina = $totalPaginas;
    }

    $offset = ($pagina - 1) * $porPagina;
    $ordenSql = $this->ordenCatalogoExpedientesSql($filtros['orden']);

    $sqlList = $base['sql'] . '
            ORDER BY ' . $ordenSql . '
            LIMIT ' . (int) $porPagina . ' OFFSET ' . (int) $offset;

    $stmt = $this->db->prepare($sqlList);
    $stmt->execute($base['params']);
    $filas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $items = array_map(
        [$this, 'enriquecerCatalogoExpediente'],
        $filas
    );

    $desde = $total > 0 ? $offset + 1 : 0;
    $hasta = $total > 0 ? min($offset + count($items), $total) : 0;

    return [
        'items' => $items,
        'total' => $total,
        'pagina' => $pagina,
        'porPagina' => $porPagina,
        'totalPaginas' => $totalPaginas,
        'desde' => $desde,
        'hasta' => $hasta
    ];
}

/**
 * Conteos operativos del catálogo (propiedad del psicólogo, sin filtros de búsqueda).
 *
 * @return array{
 *   total: int,
 *   conCitaProxima: int,
 *   conPendiente: int,
 *   actividadReciente: int
 * }
 */
public function resumenCatalogoExpedientes(string $clvPsi): array
{
    $clvPsi = trim($clvPsi);

    $vacio = [
        'total' => 0,
        'conCitaProxima' => 0,
        'conPendiente' => 0,
        'actividadReciente' => 0
    ];

    if ($clvPsi === '') {
        return $vacio;
    }

    $sql = "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(tiene_proxima), 0) AS conCitaProxima,
                COALESCE(SUM(tiene_pendiente), 0) AS conPendiente,
                COALESCE(SUM(tiene_actividad), 0) AS actividadReciente
            FROM (
                SELECT
                    p.ClvPac,
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM cita cx
                        WHERE cx.ClvPac = p.ClvPac
                          AND cx.ClvPsi = :clvPsiProx
                          AND cx.EstadoCita = 'PROGRAMADA'
                          AND (
                                cx.FechaCita > CURDATE()
                                OR (
                                    cx.FechaCita = CURDATE()
                                    AND cx.HraInicioCita >= CURTIME()
                                )
                          )
                    ) THEN 1 ELSE 0 END AS tiene_proxima,
                    CASE WHEN (
                        (
                            SELECT COUNT(*)
                            FROM cita cA
                            WHERE cA.ClvPac = p.ClvPac
                              AND cA.ClvPsi = :clvPsiAsist
                              AND cA.EstadoCita = 'ASISTIDA'
                        ) > 0
                        AND (
                            SELECT COUNT(*)
                            FROM historial_clinico h
                            WHERE h.ClvPac = p.ClvPac
                              AND h.ClvPsi = :clvPsiHist
                        ) = 0
                    ) OR EXISTS (
                        SELECT 1
                        FROM cita cSeg
                        INNER JOIN historial_clinico hSeg
                            ON hSeg.ClvPac = cSeg.ClvPac
                           AND hSeg.ClvCons = cSeg.ClvCons
                           AND hSeg.ClvPsi = cSeg.ClvPsi
                        WHERE cSeg.ClvPac = p.ClvPac
                          AND cSeg.ClvPsi = :clvPsiSeg
                          AND cSeg.EstadoCita = 'ASISTIDA'
                          AND NOT EXISTS (
                                SELECT 1
                                FROM seguimiento_sesion ss
                                WHERE (ss.ClvCita COLLATE utf8mb4_unicode_ci) = cSeg.ClvCita
                          )
                    ) THEN 1 ELSE 0 END AS tiene_pendiente,
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM cita cAct
                        WHERE cAct.ClvPac = p.ClvPac
                          AND cAct.ClvPsi = :clvPsiActC
                          AND cAct.EstadoCita = 'ASISTIDA'
                          AND cAct.FechaCita >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    ) OR EXISTS (
                        SELECT 1
                        FROM seguimiento_sesion ssAct
                        INNER JOIN historial_clinico hAct
                            ON (hAct.ClvHist COLLATE utf8mb4_unicode_ci)
                             = (ssAct.ClvHist COLLATE utf8mb4_unicode_ci)
                        WHERE (hAct.ClvPac COLLATE utf8mb4_unicode_ci)
                            = (p.ClvPac COLLATE utf8mb4_unicode_ci)
                          AND (ssAct.ClvPsi COLLATE utf8mb4_unicode_ci)
                            = (:clvPsiActS COLLATE utf8mb4_unicode_ci)
                          AND DATE(ssAct.FechaRegistroSeg) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    ) THEN 1 ELSE 0 END AS tiene_actividad
                FROM paciente p
                WHERE EXISTS (
                    SELECT 1
                    FROM cita c
                    WHERE c.ClvPac = p.ClvPac
                      AND c.ClvPsi = :clvPsi
                )
            ) AS resumen";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        'clvPsi' => $clvPsi,
        'clvPsiProx' => $clvPsi,
        'clvPsiAsist' => $clvPsi,
        'clvPsiHist' => $clvPsi,
        'clvPsiSeg' => $clvPsi,
        'clvPsiActC' => $clvPsi,
        'clvPsiActS' => $clvPsi
    ]);

    $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'conCitaProxima' => (int) ($row['conCitaProxima'] ?? 0),
        'conPendiente' => (int) ($row['conPendiente'] ?? 0),
        'actividadReciente' => (int) ($row['actividadReciente'] ?? 0)
    ];
}

/**
 * @param array<string, mixed> $opciones
 * @return array{q: string, actividad: string, cita: string, pendiente: string, orden: string}
 */
private function normalizarFiltrosCatalogoExpedientes(array $opciones): array
{
    $q = trim((string) ($opciones['q'] ?? ''));
    if (mb_strlen($q, 'UTF-8') > 80) {
        $q = mb_substr($q, 0, 80, 'UTF-8');
    }

    $actividad = strtoupper(trim((string) ($opciones['actividad'] ?? 'TODOS')));
    $cita = strtoupper(trim((string) ($opciones['cita'] ?? 'TODOS')));
    $pendiente = strtoupper(trim((string) ($opciones['pendiente'] ?? 'TODOS')));
    $orden = strtoupper(trim((string) ($opciones['orden'] ?? 'NOMBRE_ASC')));

    $actividades = ['TODOS', 'ACTIVIDAD_RECIENTE', 'SIN_ACTIVIDAD_RECIENTE'];
    $citas = ['TODOS', 'CON_CITA_PROXIMA', 'SIN_CITA_PROXIMA'];
    $pendientes = ['TODOS', 'CON_PENDIENTE', 'SIN_PENDIENTE'];
    $ordenes = [
        'NOMBRE_ASC',
        'NOMBRE_DESC',
        'ACTIVIDAD_RECIENTE',
        'ACTIVIDAD_ANTIGUA'
    ];

    if (!in_array($actividad, $actividades, true)) {
        $actividad = 'TODOS';
    }
    if (!in_array($cita, $citas, true)) {
        $cita = 'TODOS';
    }
    if (!in_array($pendiente, $pendientes, true)) {
        $pendiente = 'TODOS';
    }
    if (!in_array($orden, $ordenes, true)) {
        $orden = 'NOMBRE_ASC';
    }

    return [
        'q' => $q,
        'actividad' => $actividad,
        'cita' => $cita,
        'pendiente' => $pendiente,
        'orden' => $orden
    ];
}

/**
 * @param array{q: string, actividad: string, cita: string, pendiente: string, orden: string} $filtros
 * @return array{sql: string, params: array<string, mixed>}
 */
private function sqlBaseCatalogoExpedientes(
    string $clvPsi,
    array $filtros
): array {
    $sql = "SELECT
                p.ClvPac,
                p.FotoPerfilPac,
                per.FotoPerfilPer,
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                CONCAT(
                    TRIM(per.NombrePer),
                    ' ',
                    TRIM(per.ApPatPer),
                    ' ',
                    TRIM(COALESCE(per.ApMatPer, ''))
                ) AS NombrePaciente,
                (
                    SELECT COUNT(*)
                    FROM cita cSes
                    WHERE cSes.ClvPac = p.ClvPac
                      AND cSes.ClvPsi = :clvPsiSesiones
                      AND cSes.EstadoCita = 'ASISTIDA'
                ) AS TotalAsistidas,
                (
                    SELECT COUNT(*)
                    FROM historial_clinico h
                    WHERE h.ClvPac = p.ClvPac
                      AND h.ClvPsi = :clvPsiHist
                ) AS TotalHistorias,
                (
                    SELECT COUNT(*)
                    FROM cita cSeg
                    INNER JOIN historial_clinico hSeg
                        ON hSeg.ClvPac = cSeg.ClvPac
                       AND hSeg.ClvCons = cSeg.ClvCons
                       AND hSeg.ClvPsi = cSeg.ClvPsi
                    WHERE cSeg.ClvPac = p.ClvPac
                      AND cSeg.ClvPsi = :clvPsiSegPend
                      AND cSeg.EstadoCita = 'ASISTIDA'
                      AND NOT EXISTS (
                            SELECT 1
                            FROM seguimiento_sesion ss
                            WHERE (ss.ClvCita COLLATE utf8mb4_unicode_ci) = cSeg.ClvCita
                      )
                ) AS TotalSeguimientosPendientes,
                (
                    SELECT c2.FechaCita
                    FROM cita c2
                    WHERE c2.ClvPac = p.ClvPac
                      AND c2.ClvPsi = :clvPsiProx
                      AND c2.EstadoCita = 'PROGRAMADA'
                      AND (
                            c2.FechaCita > CURDATE()
                            OR (
                                c2.FechaCita = CURDATE()
                                AND c2.HraInicioCita >= CURTIME()
                            )
                      )
                    ORDER BY c2.FechaCita ASC, c2.HraInicioCita ASC
                    LIMIT 1
                ) AS ProximaFecha,
                (
                    SELECT c2.HraInicioCita
                    FROM cita c2
                    WHERE c2.ClvPac = p.ClvPac
                      AND c2.ClvPsi = :clvPsiProxH
                      AND c2.EstadoCita = 'PROGRAMADA'
                      AND (
                            c2.FechaCita > CURDATE()
                            OR (
                                c2.FechaCita = CURDATE()
                                AND c2.HraInicioCita >= CURTIME()
                            )
                      )
                    ORDER BY c2.FechaCita ASC, c2.HraInicioCita ASC
                    LIMIT 1
                ) AS ProximaHora,
                (
                    SELECT c3.FechaCita
                    FROM cita c3
                    WHERE c3.ClvPac = p.ClvPac
                      AND c3.ClvPsi = :clvPsiUlt
                      AND c3.EstadoCita = 'ASISTIDA'
                      AND (
                            c3.FechaCita < CURDATE()
                            OR (
                                c3.FechaCita = CURDATE()
                                AND c3.HraInicioCita <= CURTIME()
                            )
                      )
                    ORDER BY c3.FechaCita DESC, c3.HraInicioCita DESC
                    LIMIT 1
                ) AS UltimaFecha,
                (
                    SELECT c3.HraInicioCita
                    FROM cita c3
                    WHERE c3.ClvPac = p.ClvPac
                      AND c3.ClvPsi = :clvPsiUltH
                      AND c3.EstadoCita = 'ASISTIDA'
                      AND (
                            c3.FechaCita < CURDATE()
                            OR (
                                c3.FechaCita = CURDATE()
                                AND c3.HraInicioCita <= CURTIME()
                            )
                      )
                    ORDER BY c3.FechaCita DESC, c3.HraInicioCita DESC
                    LIMIT 1
                ) AS UltimaHora,
                (
                    SELECT
                        CASE
                            WHEN act.UltimaAsistida IS NULL
                                THEN act.UltimoSeguimiento
                            WHEN act.UltimoSeguimiento IS NULL
                                THEN act.UltimaAsistida
                            ELSE GREATEST(
                                act.UltimaAsistida,
                                act.UltimoSeguimiento
                            )
                        END
                    FROM (
                        SELECT
                            (
                                SELECT MAX(cAct.FechaCita)
                                FROM cita cAct
                                WHERE cAct.ClvPac = p.ClvPac
                                  AND cAct.ClvPsi = :clvPsiActMaxC
                                  AND cAct.EstadoCita = 'ASISTIDA'
                            ) AS UltimaAsistida,
                            (
                                SELECT DATE(MAX(ssAct.FechaRegistroSeg))
                                FROM seguimiento_sesion ssAct
                                INNER JOIN historial_clinico hAct
                                    ON (hAct.ClvHist COLLATE utf8mb4_unicode_ci)
                                     = (ssAct.ClvHist COLLATE utf8mb4_unicode_ci)
                                WHERE (hAct.ClvPac COLLATE utf8mb4_unicode_ci)
                                    = (p.ClvPac COLLATE utf8mb4_unicode_ci)
                                  AND (ssAct.ClvPsi COLLATE utf8mb4_unicode_ci)
                                    = (:clvPsiActMaxS COLLATE utf8mb4_unicode_ci)
                            ) AS UltimoSeguimiento
                    ) AS act
                ) AS UltimaActividadFecha
            FROM paciente p
            INNER JOIN persona per
                ON per.ClvPer = p.ClvPer
            LEFT JOIN usuario u
                ON u.ClvUsu = p.ClvUsu
            WHERE EXISTS (
                SELECT 1
                FROM cita cAuth
                WHERE cAuth.ClvPac = p.ClvPac
                  AND cAuth.ClvPsi = :clvPsi
            )";

    $params = [
        'clvPsi' => $clvPsi,
        'clvPsiSesiones' => $clvPsi,
        'clvPsiHist' => $clvPsi,
        'clvPsiSegPend' => $clvPsi,
        'clvPsiProx' => $clvPsi,
        'clvPsiProxH' => $clvPsi,
        'clvPsiUlt' => $clvPsi,
        'clvPsiUltH' => $clvPsi,
        'clvPsiActMaxC' => $clvPsi,
        'clvPsiActMaxS' => $clvPsi
    ];

    if ($filtros['q'] !== '') {
        $like = '%' . $this->escaparComodinesLike($filtros['q']) . '%';
        $sql .= '
            AND (
                per.NombrePer LIKE :qNombre ESCAPE \'\\\\\'
                OR per.ApPatPer LIKE :qApPat ESCAPE \'\\\\\'
                OR per.ApMatPer LIKE :qApMat ESCAPE \'\\\\\'
                OR p.ClvPac LIKE :qFolio ESCAPE \'\\\\\'
                OR CONCAT(
                    per.NombrePer, \' \',
                    per.ApPatPer, \' \',
                    COALESCE(per.ApMatPer, \'\')
                ) LIKE :qCompleto ESCAPE \'\\\\\'
            )';
        $params['qNombre'] = $like;
        $params['qApPat'] = $like;
        $params['qApMat'] = $like;
        $params['qFolio'] = $like;
        $params['qCompleto'] = $like;
    }

    if ($filtros['cita'] === 'CON_CITA_PROXIMA') {
        $sql .= '
            AND EXISTS (
                SELECT 1
                FROM cita cx
                WHERE cx.ClvPac = p.ClvPac
                  AND cx.ClvPsi = :clvPsiFiltroCita
                  AND cx.EstadoCita = \'PROGRAMADA\'
                  AND (
                        cx.FechaCita > CURDATE()
                        OR (
                            cx.FechaCita = CURDATE()
                            AND cx.HraInicioCita >= CURTIME()
                        )
                  )
            )';
        $params['clvPsiFiltroCita'] = $clvPsi;
    } elseif ($filtros['cita'] === 'SIN_CITA_PROXIMA') {
        $sql .= '
            AND NOT EXISTS (
                SELECT 1
                FROM cita cx
                WHERE cx.ClvPac = p.ClvPac
                  AND cx.ClvPsi = :clvPsiFiltroCita
                  AND cx.EstadoCita = \'PROGRAMADA\'
                  AND (
                        cx.FechaCita > CURDATE()
                        OR (
                            cx.FechaCita = CURDATE()
                            AND cx.HraInicioCita >= CURTIME()
                        )
                  )
            )';
        $params['clvPsiFiltroCita'] = $clvPsi;
    }

    if ($filtros['actividad'] === 'ACTIVIDAD_RECIENTE') {
        $sql .= '
            AND (
                EXISTS (
                    SELECT 1
                    FROM cita cAct
                    WHERE cAct.ClvPac = p.ClvPac
                      AND cAct.ClvPsi = :clvPsiFiltroActC
                      AND cAct.EstadoCita = \'ASISTIDA\'
                      AND cAct.FechaCita >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                )
                OR EXISTS (
                    SELECT 1
                    FROM seguimiento_sesion ssAct
                    INNER JOIN historial_clinico hAct
                        ON (hAct.ClvHist COLLATE utf8mb4_unicode_ci)
                         = (ssAct.ClvHist COLLATE utf8mb4_unicode_ci)
                    WHERE (hAct.ClvPac COLLATE utf8mb4_unicode_ci)
                        = (p.ClvPac COLLATE utf8mb4_unicode_ci)
                      AND (ssAct.ClvPsi COLLATE utf8mb4_unicode_ci)
                        = (:clvPsiFiltroActS COLLATE utf8mb4_unicode_ci)
                      AND DATE(ssAct.FechaRegistroSeg) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                )
            )';
        $params['clvPsiFiltroActC'] = $clvPsi;
        $params['clvPsiFiltroActS'] = $clvPsi;
    } elseif ($filtros['actividad'] === 'SIN_ACTIVIDAD_RECIENTE') {
        $sql .= '
            AND NOT EXISTS (
                SELECT 1
                FROM cita cAct
                WHERE cAct.ClvPac = p.ClvPac
                  AND cAct.ClvPsi = :clvPsiFiltroActC
                  AND cAct.EstadoCita = \'ASISTIDA\'
                  AND cAct.FechaCita >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            )
            AND NOT EXISTS (
                SELECT 1
                FROM seguimiento_sesion ssAct
                INNER JOIN historial_clinico hAct
                    ON (hAct.ClvHist COLLATE utf8mb4_unicode_ci)
                     = (ssAct.ClvHist COLLATE utf8mb4_unicode_ci)
                WHERE (hAct.ClvPac COLLATE utf8mb4_unicode_ci)
                    = (p.ClvPac COLLATE utf8mb4_unicode_ci)
                  AND (ssAct.ClvPsi COLLATE utf8mb4_unicode_ci)
                    = (:clvPsiFiltroActS COLLATE utf8mb4_unicode_ci)
                  AND DATE(ssAct.FechaRegistroSeg) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            )';
        $params['clvPsiFiltroActC'] = $clvPsi;
        $params['clvPsiFiltroActS'] = $clvPsi;
    }

    if ($filtros['pendiente'] === 'CON_PENDIENTE') {
        $sql .= '
            AND (
                (
                    EXISTS (
                        SELECT 1
                        FROM cita cA
                        WHERE cA.ClvPac = p.ClvPac
                          AND cA.ClvPsi = :clvPsiPendAsist
                          AND cA.EstadoCita = \'ASISTIDA\'
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM historial_clinico hP
                        WHERE hP.ClvPac = p.ClvPac
                          AND hP.ClvPsi = :clvPsiPendHist
                    )
                )
                OR EXISTS (
                    SELECT 1
                    FROM cita cSeg
                    INNER JOIN historial_clinico hSeg
                        ON hSeg.ClvPac = cSeg.ClvPac
                       AND hSeg.ClvCons = cSeg.ClvCons
                       AND hSeg.ClvPsi = cSeg.ClvPsi
                    WHERE cSeg.ClvPac = p.ClvPac
                      AND cSeg.ClvPsi = :clvPsiPendSeg
                      AND cSeg.EstadoCita = \'ASISTIDA\'
                      AND NOT EXISTS (
                            SELECT 1
                            FROM seguimiento_sesion ss
                            WHERE (ss.ClvCita COLLATE utf8mb4_unicode_ci) = cSeg.ClvCita
                      )
                )
            )';
        $params['clvPsiPendAsist'] = $clvPsi;
        $params['clvPsiPendHist'] = $clvPsi;
        $params['clvPsiPendSeg'] = $clvPsi;
    } elseif ($filtros['pendiente'] === 'SIN_PENDIENTE') {
        $sql .= '
            AND NOT (
                (
                    EXISTS (
                        SELECT 1
                        FROM cita cA
                        WHERE cA.ClvPac = p.ClvPac
                          AND cA.ClvPsi = :clvPsiPendAsist
                          AND cA.EstadoCita = \'ASISTIDA\'
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM historial_clinico hP
                        WHERE hP.ClvPac = p.ClvPac
                          AND hP.ClvPsi = :clvPsiPendHist
                    )
                )
                OR EXISTS (
                    SELECT 1
                    FROM cita cSeg
                    INNER JOIN historial_clinico hSeg
                        ON hSeg.ClvPac = cSeg.ClvPac
                       AND hSeg.ClvCons = cSeg.ClvCons
                       AND hSeg.ClvPsi = cSeg.ClvPsi
                    WHERE cSeg.ClvPac = p.ClvPac
                      AND cSeg.ClvPsi = :clvPsiPendSeg
                      AND cSeg.EstadoCita = \'ASISTIDA\'
                      AND NOT EXISTS (
                            SELECT 1
                            FROM seguimiento_sesion ss
                            WHERE (ss.ClvCita COLLATE utf8mb4_unicode_ci) = cSeg.ClvCita
                      )
                )
            )';
        $params['clvPsiPendAsist'] = $clvPsi;
        $params['clvPsiPendHist'] = $clvPsi;
        $params['clvPsiPendSeg'] = $clvPsi;
    }

    return ['sql' => $sql, 'params' => $params];
}

private function ordenCatalogoExpedientesSql(string $orden): string
{
    return match ($orden) {
        'NOMBRE_DESC' => 'NombrePer DESC, ApPatPer DESC, ApMatPer DESC, p.ClvPac DESC',
        'ACTIVIDAD_RECIENTE' =>
            '(UltimaActividadFecha IS NULL) ASC, UltimaActividadFecha DESC, NombrePer ASC, ApPatPer ASC',
        'ACTIVIDAD_ANTIGUA' =>
            '(UltimaActividadFecha IS NULL) ASC, UltimaActividadFecha ASC, NombrePer ASC, ApPatPer ASC',
        default => 'NombrePer ASC, ApPatPer ASC, ApMatPer ASC, p.ClvPac ASC',
    };
}

private function escaparComodinesLike(string $valor): string
{
    return str_replace(
        ['\\', '%', '_'],
        ['\\\\', '\\%', '\\_'],
        $valor
    );
}

/**
 * @param array<string, mixed> $fila
 * @return array<string, mixed>
 */
private function enriquecerCatalogoExpediente(array $fila): array
{
    $totalAsistidas = (int) ($fila['TotalAsistidas'] ?? 0);
    $totalHistorias = (int) ($fila['TotalHistorias'] ?? 0);
    $segPend = (int) ($fila['TotalSeguimientosPendientes'] ?? 0);
    $tieneProxima = !empty($fila['ProximaFecha']);

    $historiaPendiente = $totalAsistidas > 0 && $totalHistorias === 0;
    $conPendiente = $historiaPendiente || $segPend > 0;

    $fila['TieneCitaProxima'] = $tieneProxima;
    $fila['TienePendiente'] = $conPendiente;
    $fila['HistoriaPendiente'] = $historiaPendiente;
    $fila['SeguimientoPendiente'] = $segPend > 0;

    /*
     * Misma regla de 90 días que el filtro SQL:
     * UltimaActividadFecha = CASE NULL-safe entre ASISTIDA y seguimiento.
     */
    $actividadReciente = false;
    $ultimaActividad = trim((string) ($fila['UltimaActividadFecha'] ?? ''));
    if ($ultimaActividad !== '') {
        try {
            $fechaAct = new \DateTimeImmutable($ultimaActividad);
            $limite = (new \DateTimeImmutable('today'))->modify('-90 days');
            $actividadReciente = $fechaAct >= $limite;
        } catch (\Throwable $e) {
            $actividadReciente = false;
        }
    }
    $fila['ActividadReciente'] = $actividadReciente;
    $fila['Iniciales'] = $this->calcularIniciales(
        (string) ($fila['NombrePer'] ?? ''),
        (string) ($fila['ApPatPer'] ?? '')
    );

    $foto = trim((string) ($fila['FotoPerfilPac'] ?? ''));
    $fotoPersona = trim((string) ($fila['FotoPerfilPer'] ?? ''));
    $fotoEsDefault = $foto === ''
        || $foto === 'default.png'
        || $foto === 'perfil-default.png';

    if ($fotoEsDefault && $fotoPersona !== '') {
        $foto = $fotoPersona;
        $fotoEsDefault = $foto === 'default.png'
            || $foto === 'perfil-default.png';
    }

    $fila['TieneFoto'] = !$fotoEsDefault && $foto !== '';
    $fila['FotoArchivo'] = $fila['TieneFoto'] ? $foto : '';

    return $fila;
}

public function obtenerParaPsicologo(
    string $clvPac,
    string $clvPsi
): ?array {
    $clvPac = trim($clvPac);
    $clvPsi = trim($clvPsi);

    if ($clvPac === '' || $clvPsi === '') {
        return null;
    }

    if (!$this->perteneceAPsicologo($clvPac, $clvPsi)) {
        return null;
    }

    $sql = "SELECT
                p.ClvPac,
                p.FotoPerfilPac,

                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FechaNacimiento,
                per.GeneroPer,
                per.FotoPerfilPer,

                CONCAT(
                    TRIM(per.NombrePer),
                    ' ',
                    TRIM(per.ApPatPer),
                    ' ',
                    TRIM(COALESCE(per.ApMatPer, ''))
                ) AS NombrePaciente,

                u.ClvUsu,
                u.CorreoUsu,
                u.TelefonoUsu,
                u.EstadoUsu,

                (
                    SELECT COUNT(*)
                    FROM cita c
                    WHERE c.ClvPac = p.ClvPac
                      AND c.ClvPsi = :clvPsi
                ) AS TotalCitas,

                (
                    SELECT c0.EstadoCita
                    FROM cita c0
                    WHERE c0.ClvPac = p.ClvPac
                      AND c0.ClvPsi = :clvPsiPrim
                    ORDER BY
                        c0.FechaRegistroCita ASC,
                        c0.FechaCita ASC,
                        c0.HraInicioCita ASC
                    LIMIT 1
                ) AS PrimeraEstado,

                (
                    SELECT COUNT(*)
                    FROM historial_clinico h
                    WHERE h.ClvPac = p.ClvPac
                      AND h.ClvPsi = :clvPsiHist
                ) AS TotalHistorias,

                (
                    SELECT MIN(c.FechaCita)
                    FROM cita c
                    WHERE c.ClvPac = p.ClvPac
                      AND c.ClvPsi = :clvPsiFecha
                ) AS FechaDesde,

                (
                    SELECT COUNT(*)
                    FROM cita c
                    WHERE c.ClvPac = p.ClvPac
                      AND c.ClvPsi = :clvPsiAsist
                      AND c.EstadoCita = 'ASISTIDA'
                ) AS TotalAsistidas,

                (
                    SELECT c2.FechaCita
                    FROM cita c2
                    WHERE c2.ClvPac = p.ClvPac
                      AND c2.ClvPsi = :clvPsiProx
                      AND c2.EstadoCita = 'PROGRAMADA'
                      AND (
                            c2.FechaCita > CURDATE()
                            OR (
                                c2.FechaCita = CURDATE()
                                AND c2.HraInicioCita >= CURTIME()
                            )
                      )
                    ORDER BY
                        c2.FechaCita ASC,
                        c2.HraInicioCita ASC
                    LIMIT 1
                ) AS ProximaFecha,

                (
                    SELECT c2.HraInicioCita
                    FROM cita c2
                    WHERE c2.ClvPac = p.ClvPac
                      AND c2.ClvPsi = :clvPsiProxH
                      AND c2.EstadoCita = 'PROGRAMADA'
                      AND (
                            c2.FechaCita > CURDATE()
                            OR (
                                c2.FechaCita = CURDATE()
                                AND c2.HraInicioCita >= CURTIME()
                            )
                      )
                    ORDER BY
                        c2.FechaCita ASC,
                        c2.HraInicioCita ASC
                    LIMIT 1
                ) AS ProximaHora,

                (
                    SELECT s2.NombreServicio
                    FROM cita c2
                    INNER JOIN servicios s2
                        ON s2.ClvServ = c2.ClvServ
                    WHERE c2.ClvPac = p.ClvPac
                      AND c2.ClvPsi = :clvPsiProxS
                      AND c2.EstadoCita = 'PROGRAMADA'
                      AND (
                            c2.FechaCita > CURDATE()
                            OR (
                                c2.FechaCita = CURDATE()
                                AND c2.HraInicioCita >= CURTIME()
                            )
                      )
                    ORDER BY
                        c2.FechaCita ASC,
                        c2.HraInicioCita ASC
                    LIMIT 1
                ) AS ProximaServicio,

                (
                    SELECT c3.FechaCita
                    FROM cita c3
                    WHERE c3.ClvPac = p.ClvPac
                      AND c3.ClvPsi = :clvPsiUlt
                      AND (
                            c3.FechaCita < CURDATE()
                            OR (
                                c3.FechaCita = CURDATE()
                                AND c3.HraInicioCita < CURTIME()
                            )
                      )
                    ORDER BY
                        c3.FechaCita DESC,
                        c3.HraInicioCita DESC
                    LIMIT 1
                ) AS UltimaFecha,

                (
                    SELECT c3.HraInicioCita
                    FROM cita c3
                    WHERE c3.ClvPac = p.ClvPac
                      AND c3.ClvPsi = :clvPsiUltH
                      AND (
                            c3.FechaCita < CURDATE()
                            OR (
                                c3.FechaCita = CURDATE()
                                AND c3.HraInicioCita < CURTIME()
                            )
                      )
                    ORDER BY
                        c3.FechaCita DESC,
                        c3.HraInicioCita DESC
                    LIMIT 1
                ) AS UltimaHora,

                (
                    SELECT s3.NombreServicio
                    FROM cita c3
                    INNER JOIN servicios s3
                        ON s3.ClvServ = c3.ClvServ
                    WHERE c3.ClvPac = p.ClvPac
                      AND c3.ClvPsi = :clvPsiUltS
                      AND (
                            c3.FechaCita < CURDATE()
                            OR (
                                c3.FechaCita = CURDATE()
                                AND c3.HraInicioCita < CURTIME()
                            )
                      )
                    ORDER BY
                        c3.FechaCita DESC,
                        c3.HraInicioCita DESC
                    LIMIT 1
                ) AS UltimaServicio,

                (
                    SELECT c3.EstadoCita
                    FROM cita c3
                    WHERE c3.ClvPac = p.ClvPac
                      AND c3.ClvPsi = :clvPsiUltE
                      AND (
                            c3.FechaCita < CURDATE()
                            OR (
                                c3.FechaCita = CURDATE()
                                AND c3.HraInicioCita < CURTIME()
                            )
                      )
                    ORDER BY
                        c3.FechaCita DESC,
                        c3.HraInicioCita DESC
                    LIMIT 1
                ) AS UltimaEstado

            FROM paciente p

            INNER JOIN persona per
                ON per.ClvPer = p.ClvPer

            LEFT JOIN usuario u
                ON u.ClvUsu = p.ClvUsu

            WHERE p.ClvPac = :clvPac
              AND EXISTS (
                    SELECT 1
                    FROM cita cx
                    WHERE cx.ClvPac = p.ClvPac
                      AND cx.ClvPsi = :clvPsiExiste
              )

            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvPac' => $clvPac,
        'clvPsi' => $clvPsi,
        'clvPsiPrim' => $clvPsi,
        'clvPsiHist' => $clvPsi,
        'clvPsiFecha' => $clvPsi,
        'clvPsiAsist' => $clvPsi,
        'clvPsiProx' => $clvPsi,
        'clvPsiProxH' => $clvPsi,
        'clvPsiProxS' => $clvPsi,
        'clvPsiUlt' => $clvPsi,
        'clvPsiUltH' => $clvPsi,
        'clvPsiUltS' => $clvPsi,
        'clvPsiUltE' => $clvPsi,
        'clvPsiExiste' => $clvPsi
    ]);

    $paciente = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$paciente) {
        return null;
    }

    return $this->enriquecerPacienteCatalogo($paciente);
}

/**
 * @return list<array<string, mixed>>
 */
public function listarCitasConPsicologo(
    string $clvPac,
    string $clvPsi,
    ?string $estado = null,
    int $pagina = 1,
    int $porPagina = 10
): array {
    if (!$this->perteneceAPsicologo($clvPac, $clvPsi)) {
        return [];
    }

    $estado = strtoupper(trim((string) $estado));
    $permitidos = [
        'PROGRAMADA',
        'ASISTIDA',
        'CANCELADA',
        'INASISTENCIA'
    ];
    $filtrarEstado = in_array($estado, $permitidos, true) ? $estado : null;
    $porPagina = max(1, min(50, $porPagina));
    $pagina = max(1, $pagina);
    $offset = ($pagina - 1) * $porPagina;

    $sql = "SELECT
                c.ClvCita,
                c.FechaCita,
                c.HraInicioCita,
                c.HraFinCita,
                c.DuracionAplicadaMin,
                c.EstadoCita,
                s.NombreServicio
            FROM cita c
            INNER JOIN servicios s
                ON s.ClvServ = c.ClvServ
            WHERE c.ClvPac = :clvPac
              AND c.ClvPsi = :clvPsi";

    if ($filtrarEstado !== null) {
        $sql .= " AND c.EstadoCita = :estado";
    }

    $sql .= " ORDER BY
                c.FechaCita DESC,
                c.HraInicioCita DESC
              LIMIT :limite OFFSET :offset";

    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':clvPac', $clvPac);
    $stmt->bindValue(':clvPsi', $clvPsi);

    if ($filtrarEstado !== null) {
        $stmt->bindValue(':estado', $filtrarEstado);
    }

    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * @return array{TODAS: int, PROGRAMADA: int, ASISTIDA: int, CANCELADA: int, INASISTENCIA: int}
 */
public function contarCitasConPsicologoPorEstado(
    string $clvPac,
    string $clvPsi
): array {
    $base = [
        'TODAS' => 0,
        'PROGRAMADA' => 0,
        'ASISTIDA' => 0,
        'CANCELADA' => 0,
        'INASISTENCIA' => 0
    ];

    if (!$this->perteneceAPsicologo($clvPac, $clvPsi)) {
        return $base;
    }

    $sql = "SELECT EstadoCita, COUNT(*) AS total
            FROM cita
            WHERE ClvPac = :clvPac
              AND ClvPsi = :clvPsi
            GROUP BY EstadoCita";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        'clvPac' => $clvPac,
        'clvPsi' => $clvPsi
    ]);

    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $fila) {
        $estado = strtoupper(trim((string) ($fila['EstadoCita'] ?? '')));
        $total = (int) ($fila['total'] ?? 0);

        if (isset($base[$estado])) {
            $base[$estado] = $total;
            $base['TODAS'] += $total;
        }
    }

    return $base;
}

private function enriquecerPacienteCatalogo(
    array $paciente
): array {
    $tieneProxima = !empty($paciente['ProximaFecha']);
    $totalAsistidas = (int) ($paciente['TotalAsistidas'] ?? 0);
    $estadoUsu = (int) ($paciente['EstadoUsu'] ?? 1);
    $primeraEstado = strtoupper(
        trim((string) ($paciente['PrimeraEstado'] ?? ''))
    );
    $totalHistorias = (int) ($paciente['TotalHistorias'] ?? 0);
    $totalCitas = (int) ($paciente['TotalCitas'] ?? 0);

    if ($estadoUsu === 0) {
        $estadoRelacion = 'CUENTA_PENDIENTE';
        $estadoEtiqueta = 'Cuenta pendiente de activación';
    } elseif (
        $totalCitas === 1 &&
        $primeraEstado === 'PROGRAMADA' &&
        $tieneProxima
    ) {
        $estadoRelacion = 'PRIMERA_CITA_PENDIENTE';
        $estadoEtiqueta = 'Primera cita pendiente';
    } elseif (
        $totalCitas === 1 &&
        $primeraEstado === 'CANCELADA'
    ) {
        $estadoRelacion = 'PRIMERA_CITA_CANCELADA';
        $estadoEtiqueta = 'Primera cita cancelada';
    } elseif (
        $totalCitas === 1 &&
        $primeraEstado === 'INASISTENCIA'
    ) {
        $estadoRelacion = 'PRIMERA_INASISTENCIA';
        $estadoEtiqueta = 'Primera inasistencia';
    } elseif ($totalAsistidas > 0 && $totalHistorias === 0) {
        $estadoRelacion = 'HISTORIA_PENDIENTE';
        $estadoEtiqueta = 'Historia clínica pendiente';
    } elseif ($totalHistorias > 0) {
        $estadoRelacion = 'HISTORIA_COMPLETA';
        $estadoEtiqueta = 'Historia clínica completa';
    } elseif ($tieneProxima) {
        $estadoRelacion = 'CON_CITA_PROXIMA';
        $estadoEtiqueta = 'Paciente activo';
    } elseif ($totalAsistidas > 0) {
        $estadoRelacion = 'PACIENTE_ACTIVO';
        $estadoEtiqueta = 'Paciente activo';
    } else {
        $estadoRelacion = 'PACIENTE_ACTIVO';
        $estadoEtiqueta = 'Paciente activo';
    }

    $paciente['EstadoRelacion'] = $estadoRelacion;
    $paciente['EstadoEtiqueta'] = $estadoEtiqueta;
    $paciente['Iniciales'] = $this->calcularIniciales(
        (string) ($paciente['NombrePer'] ?? ''),
        (string) ($paciente['ApPatPer'] ?? '')
    );

    $foto = trim((string) ($paciente['FotoPerfilPac'] ?? ''));
    $fotoPersona = trim((string) ($paciente['FotoPerfilPer'] ?? ''));
    $fotoEsDefault = $foto === ''
        || $foto === 'default.png'
        || $foto === 'perfil-default.png';

    if ($fotoEsDefault && $fotoPersona !== '') {
        $foto = $fotoPersona;
        $fotoEsDefault = false;
    }

    $paciente['TieneFoto'] = !$fotoEsDefault;
    $paciente['FotoArchivo'] = $fotoEsDefault ? '' : $foto;

    if (!empty($paciente['FechaNacimiento'])) {
        try {
            $nacimiento = new \DateTimeImmutable(
                (string) $paciente['FechaNacimiento']
            );
            $hoy = new \DateTimeImmutable('today');
            $paciente['Edad'] = $nacimiento->diff($hoy)->y;
        } catch (\Throwable $e) {
            $paciente['Edad'] = null;
        }
    } else {
        $paciente['Edad'] = null;
    }

    return $paciente;
}

private function calcularIniciales(
    string $nombre,
    string $apellido
): string {
    $inicialNombre = mb_strtoupper(
        mb_substr(trim($nombre), 0, 1, 'UTF-8'),
        'UTF-8'
    );
    $inicialApellido = mb_strtoupper(
        mb_substr(trim($apellido), 0, 1, 'UTF-8'),
        'UTF-8'
    );

    $iniciales = $inicialNombre . $inicialApellido;

    return $iniciales !== '' ? $iniciales : 'P';
}
}