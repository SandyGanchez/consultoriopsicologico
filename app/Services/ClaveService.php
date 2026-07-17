<?php

namespace App\Services;

use App\Config\Database;

class ClaveService
{
    public static function generar(string $tabla, string $campo, string $prefijo): string
    {
        $db = Database::connect();

        $sql = "SELECT MAX(CAST(SUBSTRING($campo, ?) AS UNSIGNED))
                FROM $tabla
                WHERE $campo LIKE ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            strlen($prefijo) + 1,
            $prefijo . '%'
        ]);

        $ultimoNumero = (int) $stmt->fetchColumn();
        $siguiente = $ultimoNumero + 1;

        return $prefijo . str_pad($siguiente, 3, '0', STR_PAD_LEFT);
    }
}