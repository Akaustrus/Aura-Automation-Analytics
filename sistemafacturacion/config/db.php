<?php

class FacturaDB
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    public static function conexion(): PDO
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $db   = getenv('DB_NAME') ?: 'facturacion';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '1234';
            $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

            try {
                self::$instance = new PDO(
                    "mysql:host=$host;dbname=$db;charset=$charset",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            } catch (PDOException $e) {
                error_log('Error de BD (facturacion): ' . $e->getMessage());
                die('No se pudo conectar a la base de datos "facturacion".
                     Ejecuta primero <a href="instalador.php">el instalador</a>.');
            }
        }

        return self::$instance;
    }
}