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

$db = TareasDB::conexion();

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
