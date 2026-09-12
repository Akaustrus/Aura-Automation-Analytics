<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $portalTitulo ?? 'Portal del Cliente' ?> - Aura Automation &amp; Analytics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/estilos.css" rel="stylesheet">
</head>
<body>
<?php if (!empty($_SESSION['cliente_id'])): ?>
<div class="portal-topbar">
    <div class="portal-brand">
        <span class="app-logo"><i class="fa-solid fa-building"></i></span>
        <span>Aura Automation &amp; Analytics · Portal del Cliente</span>
    </div>
    <div class="portal-user">
        <a href="index.php?ruta=portal-planes" class="btn btn-sm btn-light"><i class="fa-solid fa-layer-group me-1"></i>Mis sistemas</a>
        <a href="index.php?ruta=portal-nueva-incidencia" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-plus me-1"></i>Nueva incidencia</a>
        <a href="index.php?ruta=portal-mis-tickets" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-ticket me-1"></i>Mis incidencias</a>
        <a href="index.php?ruta=portal-citas" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-calendar-check me-1"></i>Mis citas</a>
        <a href="index.php?ruta=portal-agendar" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-calendar-plus me-1"></i>Agendar</a>
        <span class="portal-empresa"><?= limpiar($_SESSION['cliente_empresa'] ?? '') ?></span>
        <a href="index.php?ruta=portal-logout" class="btn btn-sm btn-outline-light">Salir</a>
    </div>
</div>
<main class="portal-main">
<?php endif; ?>
