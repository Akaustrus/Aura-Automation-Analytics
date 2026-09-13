<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';
header('Content-Type: text/html; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['med_usuario'])) {
    redireccionar('login.php');
}

$usuario = $_SESSION['med_usuario'];
$db = MedDB::conexion();

$vistaActual = preg_replace('/[^a-z0-9_]/i', '', $_GET['vista'] ?? 'dashboard');
$vistaActual = $vistaActual === '' ? 'dashboard' : $vistaActual;
$message = getMensaje();

$navAll = [
    'dashboard'       => ['fa-table-columns',   'Panel'],
    'ventas'          => ['fa-cart-shopping',   'Ventas'],
    'cotizaciones'    => ['fa-file-lines',      'Cotizaciones'],
    'cxc'             => ['fa-hand-holding-dollar', 'CxC'],
    'compras'         => ['fa-truck-fast',      'Compras'],
    'ordenes_compra'  => ['fa-clipboard-list',  'Órdenes de compra'],
    'cxp'             => ['fa-money-bill-transfer', 'CxP'],
    'productos'       => ['fa-boxes-stacked',   'Productos'],
    'inventario'      => ['fa-warehouse',       'Inventario'],
    'kardex'          => ['fa-list-ol',         'Kardex'],
    'almacenes'       => ['fa-industry',        'Almacenes'],
    'clientes'        => ['fa-users',           'Clientes'],
    'proveedores'     => ['fa-truck',           'Proveedores'],
    'caja'            => ['fa-coins',           'Caja'],
    'reportes'        => ['fa-chart-line',      'Reportes'],
    'usuarios'        => ['fa-user-gear',       'Usuarios'],
    'configuracion'   => ['fa-gears',           'Configuración'],
    'auditoria'       => ['fa-file-shield',     'Auditoría'],
];

$nav = [];
foreach ($navAll as $cla => [$ico, $lab]) {
    if (puede($cla, $usuario)) $nav[$cla] = [$ico, $lab];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema ERP Medio — Aura Automation &amp; Analytics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/estilos.css" rel="stylesheet">
    <link href="../public/css/design-system.css" rel="stylesheet">
    <script src="../public/js/theme.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
</head>
<body class="module-app module-app--medio">
<?php if (empty($_GET['sin_shell'])): ?>
<div class="app-shell" id="appShell">
    <button type="button" class="app-menu-btn" id="appMenuBtn" aria-label="Abrir menú"><i class="fa-solid fa-bars"></i></button>
    <div class="app-overlay" id="appOverlay"></div>
    <aside class="app-sidebar" id="appSidebar">
        <div class="app-brand">
            <span class="app-logo"><i class="fa-solid fa-building"></i></span>
            <span>ERP EMPRESAS</span>
        </div>
        <nav class="app-nav">
            <?php foreach ($nav as $clave => [$icono, $label]): ?>
                <a class="app-link <?= $vistaActual === $clave ? 'active' : '' ?>"
                   href="index.php?vista=<?= $clave ?>"><i class="fa-solid <?= $icono ?>"></i><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="app-user">
            <div class="app-avatar"><i class="fa-solid fa-user-shield"></i></div>
            <div>
                <span class="app-user-name"><?= limpiar($usuario['nombre']) ?></span>
                <span class="app-user-rol"><?= rolNombre($usuario['rol']) ?></span>
            </div>
            <a href="salir.php" class="app-logout" title="Cerrar sesión"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </aside>
    <main class="app-main module-main">
        <header class="module-contextbar">
            <div><span class="module-contextbar__eyebrow">Ecosistema Aura</span><strong>ERP Mediana Empresa</strong></div>
            <div class="module-contextbar__actions"><button type="button" class="portal-theme-toggle" data-theme-toggle aria-label="Activar modo oscuro"><i class="fa-solid fa-moon"></i></button><a href="../public/index.php?ruta=dashboard" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Panel general</a></div>
        </header>
<?php endif; ?>
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $message['tipo'] === 'error' ? 'danger' : $message['tipo'] ?> alert-dismissible fade show mb-3" role="alert">
        <i class="fa-solid fa-circle-info me-2"></i><?= limpiar($message['texto']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
