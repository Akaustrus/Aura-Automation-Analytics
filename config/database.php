<?php

class Database
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    public static function conexion(): PDO
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $db   = getenv('DB_NAME') ?: 'softtech';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '1234';
            $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $opciones = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $opciones);
            } catch (PDOException $e) {
                error_log('Error de BD: ' . $e->getMessage());
                die('No se pudo conectar a la base de datos.');
            }
        }

        return self::$instance;
    }
}
