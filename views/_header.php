<?php
require_once __DIR__ . '/../modelo/Notificacion.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aura Automation &amp; Analytics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/estilos.css" rel="stylesheet">
    <link href="css/design-system.css" rel="stylesheet">
</head>
<body class="<?= estaLogueado() ? 'admin-app' : 'auth-app' ?>">
<?php if (estaLogueado()):
    $nNotif = (new Notificacion())->contarNoLeidas((int) $_SESSION['usuario_id']);
    $rutaActual = $_GET['ruta'] ?? 'dashboard'; ?>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-brand">
            <span class="app-logo"><i class="fa-solid fa-headset"></i></span>
            <span>Aura Automation &amp; Analytics</span>
        </div>
        <nav class="app-nav">
            <a class="app-link <?= $rutaActual === 'dashboard' ? 'active' : '' ?>" href="index.php?ruta=dashboard" <?= $rutaActual === 'dashboard' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-table-columns"></i>Panel</a>
            <a class="app-link <?= str_starts_with($rutaActual, 'ticket') ? 'active' : '' ?>" href="index.php?ruta=tickets"><i class="fa-solid fa-ticket"></i>Tickets</a>
            <a class="app-link <?= str_starts_with($rutaActual, 'atender') ? 'active' : '' ?>" href="index.php?ruta=atender"><i class="fa-solid fa-headset"></i>Atender llamada</a>
            <a class="app-link <?= str_starts_with($rutaActual, 'notificacion') ? 'active' : '' ?>" href="index.php?ruta=notificaciones">
                <i class="fa-solid fa-bell"></i>Notificaciones
                <?php if ($nNotif > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-1"><?= (int) $nNotif ?></span>
                <?php endif; ?>
            </a>
            <span class="app-nav-sep"></span>
            <a class="app-link <?= $rutaActual === 'planes' ? 'active' : '' ?>" href="index.php?ruta=planes"><i class="fa-solid fa-layer-group"></i>Sistemas</a>
            <a class="app-link <?= in_array($rutaActual, ['empresas', 'empresa-detalle', 'empresa-editar', 'gestionar-planes'], true) ? 'active' : '' ?>" href="index.php?ruta=empresas"><i class="fa-solid fa-building"></i>Empresas</a>
            <a class="app-link <?= $rutaActual === 'empresa-nueva' ? 'active' : '' ?>" href="index.php?ruta=empresa-nueva"><i class="fa-solid fa-building-circle-plus"></i>Nueva empresa</a>
            <span class="app-nav-sep"></span>
            <a class="app-link <?= in_array($rutaActual, ['agenda', 'horarios'], true) ? 'active' : '' ?>" href="index.php?ruta=agenda"><i class="fa-solid fa-calendar-day"></i>Agenda</a>
        </nav>
        <div class="app-user">
            <div class="app-avatar"><i class="fa-regular fa-user"></i></div>
            <div>
                <span class="app-user-name"><?= limpiar($_SESSION['usuario_nombre']) ?></span>
                <span class="app-user-rol"><?= ucfirst($_SESSION['usuario_rol']) ?></span>
            </div>
            <a href="index.php?ruta=logout" class="app-logout" title="Cerrar sesión"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </aside>
    <main class="app-main">
<?php endif; ?>
