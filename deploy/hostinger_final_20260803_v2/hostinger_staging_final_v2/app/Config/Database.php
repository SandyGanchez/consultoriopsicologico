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

                // Política B: alinear sesión MySQL con APP_TIMEZONE (America/Mexico_City).
                // Si el servidor no tiene tablas de zonas con nombre, usar UTC-06:00.
                $timezone = trim((string) Config::get(
                    'APP_TIMEZONE',
                    'America/Mexico_City'
                ));

                if ($timezone === '' || $timezone === 'America/Mexico_City') {
                    $timezoneMysql = 'America/Mexico_City';
                    $timezoneFallback = '-06:00';
                } else {
                    $timezoneMysql = $timezone;
                    $timezoneFallback = '-06:00';
                }

                try {
                    $stmtTz = self::$connection->prepare('SET time_zone = :tz');
                    $stmtTz->execute(['tz' => $timezoneMysql]);
                } catch (PDOException $tzEx) {
                    $stmtTz = self::$connection->prepare('SET time_zone = :tz');
                    $stmtTz->execute(['tz' => $timezoneFallback]);
                }

            } catch (PDOException $e) {
                error_log(
                    'DATABASE_CONNECT: fallo de conexión PDO'
                );

                $env = strtolower(trim(
                    (string) Config::get('APP_ENV', 'production')
                ));

                if (
                    in_array(
                        $env,
                        ['development', 'local', 'dev'],
                        true
                    )
                ) {
                    http_response_code(500);
                    exit(
                        'Error de conexión a la base de datos. '
                        . 'Revisa la configuración local y el registro de errores.'
                    );
                }

                http_response_code(500);
                exit(
                    'El servicio no está disponible temporalmente. '
                    . 'Intenta más tarde.'
                );
            }

        }

        return self::$connection;

    }

    /**
     * Permite reiniciar la conexión (scripts CLI / pruebas sobre otra BD).
     */
    public static function resetConnection(): void
    {
        self::$connection = null;
    }
}