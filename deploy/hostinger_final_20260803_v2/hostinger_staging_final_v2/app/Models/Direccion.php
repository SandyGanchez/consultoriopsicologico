<?php

namespace App\Models;

use App\Core\Model;
use App\Services\ClaveService;
use PDO;
use PDOException;
use RuntimeException;

class Direccion extends Model
{
    public function perteneceAlConsultorio(
        string $clvDir,
        string $clvCons
    ): bool {
        $sql = "SELECT COUNT(*)
                FROM consultorio
                WHERE ClvDir = :clvDir
                  AND ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvDir' => trim($clvDir),
            'clvCons' => trim($clvCons)
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function obtenerPorConsultorio(
        string $clvCons
    ): ?array {
        $sql = "SELECT
                    d.ClvDir,
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
                    d.ReferenciaDir
                FROM direccion d
                INNER JOIN consultorio c
                    ON c.ClvDir = d.ClvDir
                WHERE c.ClvCons = :clvCons
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => trim($clvCons)
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public function actualizarPorConsultorio(
        string $clvCons,
        array $datos
    ): bool {
        $sql = "UPDATE direccion d
                INNER JOIN consultorio c
                    ON c.ClvDir = d.ClvDir
                SET
                    d.PaisDir = :pais,
                    d.EstadoDir = :estado,
                    d.MunicipioDir = :municipio,
                    d.ColoniaDir = :colonia,
                    d.CalleDir = :calle,
                    d.CodPostDir = :codPost,
                    d.NumExtDir = :numExt,
                    d.NumIntDir = :numInt,
                    d.LatitudDir = :latitud,
                    d.LongitudDir = :longitud,
                    d.ReferenciaDir = :referencia
                WHERE c.ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'pais' => $datos['PaisDir'],
            'estado' => $datos['EstadoDir'],
            'municipio' => $datos['MunicipioDir'],
            'colonia' => $datos['ColoniaDir'],
            'calle' => $datos['CalleDir'],
            'codPost' => $datos['CodPostDir'],
            'numExt' => $datos['NumExtDir'],
            'numInt' => $datos['NumIntDir'],
            'latitud' => $datos['LatitudDir'],
            'longitud' => $datos['LongitudDir'],
            'referencia' => $datos['ReferenciaDir'],
            'clvCons' => trim($clvCons)
        ]);

        return $stmt->rowCount() > 0;
    }

    public function obtenerPorClave(string $clvDir): ?array
    {
        $sql = "SELECT
                    ClvDir,
                    PaisDir,
                    EstadoDir,
                    MunicipioDir,
                    ColoniaDir,
                    CalleDir,
                    CodPostDir,
                    NumExtDir,
                    NumIntDir,
                    LatitudDir,
                    LongitudDir,
                    ReferenciaDir
                FROM direccion
                WHERE ClvDir = :clvDir
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvDir' => trim($clvDir)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function esUsadaPorConsultorio(string $clvDir): bool
    {
        $sql = "SELECT COUNT(*)
                FROM consultorio
                WHERE ClvDir = :clvDir";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvDir' => trim($clvDir)]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function contarPersonasConDireccion(string $clvDir): int
    {
        $sql = "SELECT COUNT(*)
                FROM persona
                WHERE ClvDir = :clvDir";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvDir' => trim($clvDir)]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * True si la dirección puede actualizarse solo para esta persona.
     */
    public function esExclusivaDePersona(
        string $clvDir,
        string $clvPer
    ): bool {
        $clvDir = trim($clvDir);
        $clvPer = trim($clvPer);

        if ($clvDir === '' || $clvPer === '') {
            return false;
        }

        if ($this->esUsadaPorConsultorio($clvDir)) {
            return false;
        }

        $sql = "SELECT COUNT(*)
                FROM persona
                WHERE ClvDir = :clvDir
                  AND ClvPer <> :clvPer";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvDir' => $clvDir,
            'clvPer' => $clvPer
        ]);

        return (int) $stmt->fetchColumn() === 0;
    }

    public function crear(array $datos): string
    {
        $intentos = 0;

        while ($intentos < 5) {
            $intentos++;
            $clvDir = ClaveService::generar(
                'direccion',
                'ClvDir',
                'DIR'
            );

            try {
                $sql = "INSERT INTO direccion (
                            ClvDir,
                            PaisDir,
                            EstadoDir,
                            MunicipioDir,
                            ColoniaDir,
                            CalleDir,
                            CodPostDir,
                            NumExtDir,
                            NumIntDir,
                            LatitudDir,
                            LongitudDir,
                            ReferenciaDir
                        ) VALUES (
                            :clvDir,
                            :pais,
                            :estado,
                            :municipio,
                            :colonia,
                            :calle,
                            :codPost,
                            :numExt,
                            :numInt,
                            :latitud,
                            :longitud,
                            :referencia
                        )";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'clvDir' => $clvDir,
                    'pais' => $datos['PaisDir'],
                    'estado' => $datos['EstadoDir'],
                    'municipio' => $datos['MunicipioDir'],
                    'colonia' => $datos['ColoniaDir'],
                    'calle' => $datos['CalleDir'],
                    'codPost' => $datos['CodPostDir'],
                    'numExt' => $datos['NumExtDir'],
                    'numInt' => $datos['NumIntDir'],
                    'latitud' => $datos['LatitudDir'],
                    'longitud' => $datos['LongitudDir'],
                    'referencia' => $datos['ReferenciaDir']
                ]);

                return $clvDir;
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    continue;
                }

                throw $e;
            }
        }

        throw new RuntimeException(
            'No fue posible generar una clave de dirección.'
        );
    }

    public function actualizarPorClave(
        string $clvDir,
        array $datos
    ): bool {
        $sql = "UPDATE direccion
                SET
                    PaisDir = :pais,
                    EstadoDir = :estado,
                    MunicipioDir = :municipio,
                    ColoniaDir = :colonia,
                    CalleDir = :calle,
                    CodPostDir = :codPost,
                    NumExtDir = :numExt,
                    NumIntDir = :numInt,
                    ReferenciaDir = :referencia
                WHERE ClvDir = :clvDir";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'pais' => $datos['PaisDir'],
            'estado' => $datos['EstadoDir'],
            'municipio' => $datos['MunicipioDir'],
            'colonia' => $datos['ColoniaDir'],
            'calle' => $datos['CalleDir'],
            'codPost' => $datos['CodPostDir'],
            'numExt' => $datos['NumExtDir'],
            'numInt' => $datos['NumIntDir'],
            'referencia' => $datos['ReferenciaDir'],
            'clvDir' => trim($clvDir)
        ]);
    }

    /**
     * Actualiza solo columnas de lista blanca que siguen NULL o vacías.
     *
     * @param array<string, string> $campos
     */
    public function actualizarCamposVacios(
        string $clvDir,
        array $campos
    ): bool {
        $permitidos = [
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

        $actual = $this->obtenerPorClave($clvDir);

        if ($actual === null) {
            return false;
        }

        $sets = [];
        $params = ['clvDir' => trim($clvDir)];

        foreach ($permitidos as $campo) {
            if (!array_key_exists($campo, $campos)) {
                continue;
            }

            $nuevoBruto = $campos[$campo] ?? null;
            if ($nuevoBruto === null || trim((string) $nuevoBruto) === '') {
                continue;
            }

            $prevBruto = $actual[$campo] ?? null;
            if ($prevBruto !== null && trim((string) $prevBruto) !== '') {
                continue;
            }

            $sets[] = "{$campo} = :{$campo}";
            $params[$campo] = trim((string) $nuevoBruto);
        }

        if ($sets === []) {
            return true;
        }

        $sql = 'UPDATE direccion SET '
            . implode(', ', $sets)
            . ' WHERE ClvDir = :clvDir';

        return $this->db->prepare($sql)->execute($params);
    }
}
