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

                self::$connection = new PDO(

                    "mysql:host=" . Config::get('DB_HOST') .
                    ";dbname=" . Config::get('DB_NAME') .
                    ";charset=utf8mb4",

                    Config::get('DB_USER'),

                    Config::get('DB_PASS')

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