<?php
/**
 * Sistema de Gestión de Tareas — Aura Automation & Analytics
 * Router principal independiente de los demás módulos.
 *
 * Entrar desde: http://localhost:8002/sistematareas/index.php
 * Las operaciones CRUD se realizan por AJAX vía api.php (sin recargar la página).
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['tareas_usuario'])) {
    $_SESSION['tareas_usuario'] = 'usuario-local';
}

$db = null;
try {
    $db = TareasDB::conexion();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Sin conexión a la base de datos</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">'
        . '<style>body{font-family:Poppins,system-ui,sans-serif;background:#f0fdfa;color:#134e4a;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1rem}'
        . '.caja{max-width:640px;background:#fff;border-radius:16px;padding:2rem;box-shadow:0 8px 30px rgba(13,148,136,.15);border-top:6px solid #0d9488}'
        . 'h1{font-size:1.2rem;margin:0 0 1rem}pre{white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:1rem;font-size:.85rem;color:#334155;overflow:auto}'
        . 'a{color:#0d9488;font-weight:600}</style></head><body><div class="caja">'
        . '<h1>&#9888; Sin conexión a la base de datos</h1>'
        . '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>'
        . '<p>Revisa la guía en <a href="README.md">README.md</a> o vuelve a intentarlo: <a href="index.php">Reintentar</a></p>'
        . '</div></body></html>';
    exit;
}

$vistaActual = preg_replace('/[^a-z0-9_]/i', '', $_GET['vista'] ?? 'tareas');
$vistaActual = $vistaActual === '' ? 'tareas' : $vistaActual;

require_once __DIR__ . '/includes/encabezado.php';

$vistaRuta = __DIR__ . '/vistas/' . $vistaActual . '.php';
if (is_file($vistaRuta)) {
    require_once $vistaRuta;
} else {
    echo '<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>Vista no encontrada.</div>';
}

require_once __DIR__ . '/includes/pie.php';
