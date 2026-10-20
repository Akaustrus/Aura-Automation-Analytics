<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';
header('Content-Type: text/html; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['sap_usuario'])) {
    $_SESSION['sap_usuario'] = 'usuario-local';
}

$vistaActual = preg_replace('/[^a-z0-9_]/i', '', $_GET['vista'] ?? 'dashboard');
$message = getMensaje();

$nav = [
    'dashboard'     => ['fa-table-columns', 'Panel predictivo'],
    'planificacion' => ['fa-robot', 'Planificacion'],
    'inventario'    => ['fa-boxes-stacked', 'Inventario'],
    'rrhh'          => ['fa-users', 'RRHH'],
    'planilla'      => ['fa-file-invoice-dollar', 'Planilla'],
    'facturacion'   => ['fa-file-invoice', 'Facturacion'],
    'contabilidad'  => ['fa-scale-balanced', 'Contabilidad'],
    'auditoria'     => ['fa-clipboard-list', 'Auditoria'],
];

sincronizarRecomendaciones($db);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo SAP — Aura Automation &amp; Analytics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/estilos.css" rel="stylesheet">
    <link href="../public/css/design-system.css" rel="stylesheet">
    <script src="../public/js/theme.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body class="module-app module-app--sap">
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-brand">
            <span class="app-logo"><i class="fa-solid fa-brain"></i></span>
            <span>Módulo SAP</span>
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
                <span class="app-user-name">Módulo SAP</span>
                <span class="app-user-rol">Análisis Predictivo</span>
            </div>
            <a href="../public/index.php?ruta=dashboard" class="app-logout" title="Volver al panel general"><i class="fa-solid fa-arrow-left"></i></a>
        </div>
    </aside>
    <main class="app-main module-main">
        <header class="module-contextbar">
            <div><span class="module-contextbar__eyebrow">Ecosistema Aura</span><strong>Sistema de Análisis Predictivo</strong></div>
            <div class="module-contextbar__actions"><button type="button" class="portal-theme-toggle" data-theme-toggle aria-label="Activar modo oscuro"><i class="fa-solid fa-moon"></i></button><a href="../public/index.php?ruta=dashboard" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Panel general</a></div>
        </header>
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $message['tipo'] === 'error' ? 'danger' : $message['tipo'] ?> alert-dismissible fade show mb-3" role="alert">
        <i class="fa-solid fa-circle-info me-2"></i><?= limpiar($message['texto']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>