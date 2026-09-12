<?php
/**
 * Instalador del Módulo Contable (Aura Automation & Analytics)
 * Crea la base de datos "contabilidad" con tablas y datos de ejemplo.
 *
 * Ejecutar UNA sola vez desde el navegador:
 *   http://localhost:8000/sistemacontable/instalador.php
 *
 * Se puede re-ejecutar para reiniciar los datos de ejemplo.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '1234';
$base = getenv('DB_NAME') ?: 'contabilidad';

$resultado = ['ok' => false, 'mensajes' => [], 'total' => 0, 'base' => $base];

try {
    // Conexion sin base para poder crearla
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = file_get_contents(__DIR__ . '/contabilidad.sql');
    if ($sql === false) {
        throw new RuntimeException('No se pudo leer contabilidad.sql');
    }

    $pdo->exec($sql);
    $pdo->exec("USE $base");

    $resultado['ok'] = true;
    $resultado['mensajes'][] = 'Base de datos "' . $base . '" creada correctamente.';

    foreach (['cuentas', 'asientos', 'asiento_detalle', 'documentos'] as $tabla) {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
        $resultado['mensajes'][] = "Tabla $tabla: $n registro(s).";
        $resultado['total'] += $n;
    }

    $resultado['mensajes'][] = 'Todo listo. Puedes abrir el módulo contable.';
} catch (Throwable $e) {
    $resultado['mensajes'][] = 'ERROR: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - Módulo Contable</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/estilos.css" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .instalador-card { width: 100%; max-width: 560px; border-radius: 18px; }
    </style>
</head>
<body>
    <div class="card instalador-card shadow-lg">
        <div class="card-header py-3">
            <h1 class="h5 mb-0"><i class="fa-solid fa-database me-2 text-success"></i>Instalador del Módulo Contable</h1>
        </div>
        <div class="card-body">
            <?php foreach ($resultado['mensajes'] as $msg): ?>
                <?php $esError = str_starts_with($msg, 'ERROR:'); ?>
                <div class="alert <?= $esError ? 'alert-danger' : 'alert-success' ?> py-2 mb-2">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endforeach; ?>
            <hr>
            <a href="index.php" class="btn btn-primary"><i class="fa-solid fa-arrow-right me-1"></i>Ir al módulo contable</a>
            <a href="instalador.php" class="btn btn-outline-secondary ms-2"><i class="fa-solid fa-rotate me-1"></i>Reinstalar (reinicia datos)</a>
        </div>
    </div>
</body>
</html>