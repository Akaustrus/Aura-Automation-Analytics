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
    /*  Configuración: entorno.php > variables de entorno > auto-detección */
    /* ------------------------------------------------------------------ */

    /** Lee config/entorno.php (si existe) y lo combina con variables de entorno. */
    private static function config(): array
    {
        $cfg = [
            'host'    => null,
            'puerto'  => null,
            'usuario' => null,
            'clave'   => null,
            'base'    => null,
        ];

        $archivo = __DIR__ . '/entorno.php';
        if (is_file($archivo)) {
            $datos = @include $archivo;
            if (is_array($datos)) {
                foreach ($cfg as $clave => $valor) {
                    if (array_key_exists($clave, $datos) && $datos[$clave] !== null && $datos[$clave] !== '') {
                        $cfg[$clave] = $datos[$clave];
                    }
                }
            }
        }

        // Variables de entorno (si el archivo no fijó el valor).
        if ($cfg['host'] === null)    $cfg['host']    = getenv('TAREAS_DB_HOST') ?: getenv('DB_HOST') ?: null;
        if ($cfg['puerto'] === null)  $cfg['puerto']  = getenv('TAREAS_DB_PORT') ?: getenv('DB_PORT') ?: null;
        if ($cfg['usuario'] === null) $cfg['usuario'] = getenv('TAREAS_DB_USER') ?: getenv('DB_USER') ?: null;
        if ($cfg['clave'] === null)   $cfg['clave']   = getenv('TAREAS_DB_PASS') !== false ? getenv('TAREAS_DB_PASS') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : null);
        if ($cfg['base'] === null)    $cfg['base']    = getenv('TAREAS_DB_NAME') ?: 'tareas';

        return $cfg;
    }

    /* ------------------------------------------------------------------ */
    /*  Conexión: config manual > caché > auto-detección                   */
    /* ------------------------------------------------------------------ */

    private static function conectar(): PDO
    {
        $cfg = self::config();

        // 1) Si el usuario fijó host/usuario en entorno.php, se respeta al pie de la letra.
        if ($cfg['host'] !== null) {
            $claves = $cfg['clave'] !== null ? [(string) $cfg['clave']] : ['', '1234'];
            foreach ($claves as $clave) {
                $pdo = self::intentar($cfg['host'], $cfg['puerto'], (string) $cfg['usuario'], $clave);
                if ($pdo) {
                    self::escribirCache(['host' => $cfg['host'], 'puerto' => $cfg['puerto'], 'usuario' => $cfg['usuario'], 'clave' => $clave]);
                    return $pdo;
                }
            }
            self::fallar($cfg);
        }

        // 2) Parámetros guardados de la última conexión exitosa (lo más rápido).
        $cache = self::leerCache();
        if ($cache !== null) {
            $pdo = self::intentar($cache['host'], $cache['puerto'], $cache['usuario'], $cache['clave']);
            if ($pdo) {
                return $pdo;
            }
        }

        // 3) Auto-detección: socket local, luego TCP, puertos y claves típicas.
        //    'localhost' en PDO usa el socket de MySQL/MariaDB (como en Raspberry Pi).
        $intentos = [
            ['host' => 'localhost', 'puerto' => null, 'usuario' => 'root', 'clave' => ''],
            ['host' => 'localhost', 'puerto' => null, 'usuario' => 'root', 'clave' => '1234'],
            ['host' => '127.0.0.1', 'puerto' => '3306', 'usuario' => 'root', 'clave' => ''],
            ['host' => '127.0.0.1', 'puerto' => '3306', 'usuario' => 'root', 'clave' => '1234'],
            ['host' => '127.0.0.1', 'puerto' => '3307', 'usuario' => 'root', 'clave' => ''],
            ['host' => '127.0.0.1', 'puerto' => '3307', 'usuario' => 'root', 'clave' => '1234'],
        ];

        foreach ($intentos as $intento) {
            $pdo = self::intentar($intento['host'], $intento['puerto'], $intento['usuario'], $intento['clave']);
            if ($pdo) {
                self::escribirCache($intento);
                return $pdo;
            }
        }

        self::fallar($cfg);
    }

    /**
     * Intenta una conexión. Con host 'localhost' se usa el socket de MySQL/MariaDB
     * (sin puerto); con IP se usa TCP con timeout corto.
     */
    private static function intentar(?string $host, ?string $puerto, string $usuario, string $clave): ?PDO
    {
        $host = $host ?: '127.0.0.1';

        $dsn = 'mysql:host=' . $host;
        if ($host !== 'localhost' && $puerto) {
            $dsn .= ';port=' . $puerto;
        }
        $dsn .= ';charset=utf8mb4';

        try {
            return new PDO(
                $dsn,
                $usuario,
                $clave,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT            => 3,
                ]
            );
        } catch (PDOException $e) {
            return null;
        }
    }

    private static function fallar(array $cfg): never
    {
        $host    = $cfg['host'] ?: '127.0.0.1 / localhost';
        $usuario = $cfg['usuario'] ?: 'root';
        $base    = $cfg['base'] ?: 'tareas';

        $mensaje = "No se pudo conectar a MySQL/MariaDB (host: {$host}, usuario: \"{$usuario}\", base: \"{$base}\").\n\n"
            . "Qué revisar:\n"
            . "1) ¿El servicio está corriendo? En Raspberry Pi / Linux:\n"
            . "     sudo systemctl status mariadb   (o mysql)\n"
            . "     sudo systemctl start mariadb\n"
            . "2) Si usas MariaDB con autenticación por socket, prueba en config/entorno.php:\n"
            . "     'host' => 'localhost'  y un usuario propio (no root).\n"
            . "3) Crea un usuario dedicado desde la terminal:\n"
            . "     sudo mysql\n"
            . "     CREATE USER 'aura'@'localhost' IDENTIFIED BY 'tu_clave';\n"
            . "     GRANT ALL PRIVILEGES ON {$base}.* TO 'aura'@'localhost'; FLUSH PRIVILEGES;\n"
            . "     y ponlo en config/entorno.php (host/usuario/clave).\n"
            . "4) Si MySQL corre en otra máquina de la red local, usa su IP en 'host'.\n"
            . "5) Si lo fijaste todo en config/entorno.php, revisa que los datos sean correctos.";

        error_log('Error de BD (tareas): ' . $mensaje);
        throw new RuntimeException($mensaje);
    }

    /* ------------------------------------------------------------------ */
    /*  Caché de la conexión que ya funcionó                               */
    /* ------------------------------------------------------------------ */

    private static function leerCache(): ?array
    {
        if (!is_file(self::CACHE)) {
            return null;
        }
        $datos = @include self::CACHE;
        return is_array($datos) && isset($datos['host'], $datos['puerto'], $datos['usuario'], $datos['clave'])
            ? $datos
            : null;
    }

    private static function escribirCache(array $datos): void
    {
        $datos = [
            'host'    => $datos['host'] ?? null,
            'puerto'  => $datos['puerto'] ?? null,
            'usuario' => $datos['usuario'] ?? null,
            'clave'   => $datos['clave'] ?? null,
        ];
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
        $base = self::config()['base'] ?: 'tareas';
        $base = preg_replace('/[^A-Za-z0-9_]/', '', $base) ?: 'tareas';

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
