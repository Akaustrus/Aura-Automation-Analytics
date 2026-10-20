<?php

class SAPDB
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    public static function conexion(): PDO
    {
        if (self::$instance === null) {
            $host = getenv('SAP_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
            $port = getenv('SAP_DB_PORT') ?: getenv('DB_PORT') ?: '3306';
            $db   = getenv('SAP_DB_NAME') ?: 'sistemasap';
            $user = getenv('SAP_DB_USER') ?: getenv('DB_USER') ?: 'root';
            $passEnv = getenv('SAP_DB_PASS');
            $passEnv = $passEnv !== false ? $passEnv : getenv('DB_PASS');
            $pass = $passEnv !== false ? $passEnv : '1234';
            $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

            try {
                self::$instance = new PDO(
                    "mysql:host=$host;port=$port;dbname=$db;charset=$charset",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            } catch (PDOException $e) {
                error_log('Error de BD (sap): ' . $e->getMessage());
                die('No se pudo conectar a la base de datos "sistemasap".
                     Ejecuta primero <a href="instalador.php">el instalador</a>.');
            }
        }

        return self::$instance;
    }
}