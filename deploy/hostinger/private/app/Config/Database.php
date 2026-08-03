<?php

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {

        if (self::$connection === null) {

            Config::load();

            try {
                $host = (string) Config::get('DB_HOST', 'localhost');
                $name = (string) Config::get('DB_NAME', '');
                $port = trim((string) Config::get('DB_PORT', ''));
                $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

                if ($port !== '') {
                    $dsn .= ';port=' . $port;
                }

                self::$connection = new PDO(
                    $dsn,
                    (string) Config::get('DB_USER', ''),
                    (string) Config::get('DB_PASS', '')
                );

                self::$connection->setAttribute(
                    PDO::ATTR_ERRMODE,
                    PDO::ERRMODE_EXCEPTION
                );

            } catch (PDOException $e) {

                die("Error de conexión: " . $e->getMessage());

            }

        }

        return self::$connection;

    }
}