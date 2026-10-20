<?php

class TareasDB
{
    private static ?PDO $instance = null;

    /** Archivo donde se guardan los parámetros de conexión que ya funcionaron. */
    private const CACHE = __DIR__ . '/conexion_cache.php';

    public static function conexion(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::conectar();
            self::asegurarEsquema(self::$instance);
        }

        return self::$instance;
    }

    /* ------------------------------------------------------------------ */
    /*  Conexión: caché + auto-detección (una sola vez, después es gratis) */
    /* ------------------------------------------------------------------ */

    private static function conectar(): PDO
    {
        $host    = getenv('TAREAS_DB_HOST') ?: '127.0.0.1';
        $usuario = getenv('TAREAS_DB_USER') ?: 'root';

        // Configuración explícita por variables de entorno TAREAS_DB_*:
        // se usa directamente, sin auto-detección.
        $claveEnv = getenv('TAREAS_DB_PASS');
        if ($claveEnv !== false) {
            $puerto = getenv('TAREAS_DB_PORT') ?: '3306';
            $pdo = self::intentar($host, $puerto, $usuario, $claveEnv);
            if ($pdo) {
                return $pdo;
            }
            self::fallar($host, $usuario, 'las variables de entorno TAREAS_DB_* no son válidas');
        }

        // 1) Parámetros guardados de la última conexión exitosa (más rápido).
        $cache = self::leerCache();
        if ($cache !== null) {
            $pdo = self::intentar($host, $cache['puerto'], $usuario, $cache['clave']);
            if ($pdo) {
                return $pdo;
            }
        }

        // 2) Auto-detección: puertos y contraseñas más comunes.
        foreach (['3306', '3307'] as $puerto) {
            foreach (['', '1234'] as $clave) {
                $pdo = self::intentar($host, $puerto, $usuario, $clave);
                if ($pdo) {
                    self::escribirCache(['puerto' => $puerto, 'clave' => $clave]);
                    return $pdo;
                }
            }
        }

        self::fallar($host, $usuario, 'no se encontró MySQL en los puertos 3306/3307');
    }

    private static function intentar(string $host, string $puerto, string $usuario, string $clave): ?PDO
    {
        try {
            return new PDO(
                "mysql:host=$host;port=$puerto;charset=utf8mb4",
                $usuario,
                $clave,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT            => 3,   // corto: si no responde, probamos la siguiente opción
                ]
            );
        } catch (PDOException $e) {
            return null;
        }
    }

    private static function fallar(string $host, string $usuario, string $motivo): void
    {
        die('No se pudo conectar a MySQL en ' . htmlspecialchars($host)
            . ' (usuario "' . htmlspecialchars($usuario) . '"): ' . htmlspecialchars($motivo) . '.
            Verifica que tu servidor MySQL esté en ejecución (XAMPP / WAMP / MySQL).');
    }

    private static function leerCache(): ?array
    {
        if (!is_file(self::CACHE)) {
            return null;
        }
        $datos = @include self::CACHE;
        return is_array($datos) && isset($datos['puerto']) ? $datos : null;
    }

    private static function escribirCache(array $datos): void
    {
        @file_put_contents(
            self::CACHE,
            "<?php\n// Autogenerado: parámetros de conexión que funcionaron. No editar.\nreturn "
            . var_export($datos, true) . ";\n",
            LOCK_EX
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Autoinstalación del esquema (solo la primera vez)                  */
    /* ------------------------------------------------------------------ */

    private static function asegurarEsquema(PDO $db): void
    {
        $base = getenv('TAREAS_DB_NAME') ?: 'tareas';

        // Si la base ya existe, solo la selecciona (sin ejecutar DDL en cada petición).
        try {
            $db->exec("USE `$base`");
        } catch (PDOException $e) {
            $db->exec("CREATE DATABASE `$base`
                       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $db->exec("USE `$base`");
        }

        // Verificación barata: si todo ya existe, no se ejecuta ningún DDL.
        $faltantes = $db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM information_schema.tables
                  WHERE table_schema = ? AND table_name IN ('tareas', 'audit_log', 'personal')) AS tablas,
                (SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema = ? AND table_name = 'tareas' AND column_name = 'responsable_id') AS columna"
        );
        $faltantes->execute([$base, $base]);
        $estado = $faltantes->fetch();
        if ((int) $estado['tablas'] >= 3 && (int) $estado['columna'] >= 1) {
            return;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS personal (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(120) NOT NULL,
                cargo VARCHAR(80) DEFAULT NULL,
                email VARCHAR(120) DEFAULT NULL,
                fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS tareas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(150) NOT NULL,
                descripcion TEXT DEFAULT NULL,
                prioridad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
                estado ENUM('pendiente','en_proceso','completada') NOT NULL DEFAULT 'pendiente',
                fecha_vencimiento DATE DEFAULT NULL,
                responsable_id INT DEFAULT NULL,
                fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tabla VARCHAR(50) NOT NULL,
                registro_id INT NOT NULL,
                accion VARCHAR(20) NOT NULL,
                resumen VARCHAR(255) DEFAULT NULL,
                datos_anteriores JSON DEFAULT NULL,
                datos_nuevos JSON DEFAULT NULL,
                usuario VARCHAR(100) DEFAULT NULL,
                fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Migración de bases creadas antes de agregar el personal:
        // si falta la columna responsable_id, se agrega con su clave foránea.
        if ((int) $estado['columna'] < 1) {
            $db->exec("ALTER TABLE tareas ADD COLUMN responsable_id INT DEFAULT NULL AFTER fecha_vencimiento");
        }
        try {
            $db->exec("ALTER TABLE tareas
                       ADD CONSTRAINT fk_tareas_personal
                       FOREIGN KEY (responsable_id) REFERENCES personal(id) ON DELETE SET NULL");
        } catch (PDOException $e) {
            // La clave ya existe: se ignora.
        }

        // Personal de ejemplo solo la primera vez (tabla vacía).
        if ((int) $db->query('SELECT COUNT(*) FROM personal')->fetchColumn() === 0) {
            $db->exec("
                INSERT INTO personal (nombre, cargo, email) VALUES
                ('Ana Torres', 'Coordinadora de proyectos', 'ana.torres@aura.com'),
                ('Luis Márquez', 'Desarrollador', 'luis.marquez@aura.com'),
                ('Carla Ruiz', 'Analista de calidad', 'carla.ruiz@aura.com'),
                ('Diego Fuentes', 'Soporte técnico', 'diego.fuentes@aura.com')
            ");
        }

        // Datos de ejemplo solo la primera vez (tabla vacía).
        if ((int) $db->query('SELECT COUNT(*) FROM tareas')->fetchColumn() === 0) {
            $db->exec("
                INSERT INTO tareas (titulo, descripcion, prioridad, estado, fecha_vencimiento, responsable_id) VALUES
                ('Configurar servidor de producción', 'Revisar Apache, PHP 8.2 y variables de entorno del servidor.', 'alta', 'en_proceso', CURDATE() + INTERVAL 3 DAY, 1),
                ('Diseñar plantilla de reporte mensual', 'Incluir gráficos de ingresos y gastos por sucursal.', 'media', 'pendiente', CURDATE() + INTERVAL 7 DAY, 2),
                ('Capacitar al equipo de ventas', 'Sesión de 2 horas sobre el nuevo módulo de facturación.', 'baja', 'pendiente', CURDATE() + INTERVAL 14 DAY, NULL),
                ('Corregir error en importación CSV', 'El separador de miles rompe la importación de documentos.', 'critica', 'en_proceso', CURDATE(), 3),
                ('Documentar API de pagos', 'Generar la documentación en formato OpenAPI.', 'media', 'completada', CURDATE() - INTERVAL 2 DAY, 2),
                ('Respaldado semanal de bases de datos', 'Verificar integridad de los respaldos automáticos.', 'alta', 'completada', CURDATE() - INTERVAL 1 DAY, NULL)
            ");
        }
    }
}
