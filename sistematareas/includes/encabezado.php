<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';
header('Content-Type: text/html; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$vistaActual = preg_replace('/[^a-z0-9_]/i', '', $_GET['vista'] ?? 'tareas');
$vistaActual = $vistaActual === '' ? 'tareas' : $vistaActual;

$nav = [
    'tareas'    => ['fa-list-check', 'Mis tareas'],
    'personal'  => ['fa-users-gear', 'Personal'],
    'calendario'=> ['fa-calendar-days', 'Por vencer'],
    'historial' => ['fa-clock-rotate-left', 'Historial'],
    'panel'     => ['fa-chart-pie', 'Estadísticas'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Tareas — Aura Automation &amp; Analytics</title>
    <meta name="csrf-token" content="<?= limpiar(tokenCsrf()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/estilos.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-brand">
            <span class="app-logo"><i class="fa-solid fa-list-check"></i></span>
            <span>Sistema de Tareas</span>
        </div>
        <nav class="app-nav">
            <?php foreach ($nav as $clave => [$icono, $label]): ?>
                <a class="app-link <?= $vistaActual === $clave ? 'active' : '' ?>"
                   href="index.php?vista=<?= $clave ?>"><i class="fa-solid <?= $icono ?>"></i><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="app-user">
            <div class="app-avatar"><i class="fa-solid fa-user"></i></div>
            <div>
                <span class="app-user-name">Usuario local</span>
                <span class="app-user-rol">Sistema independiente</span>
            </div>
            <a href="../public/index.php?ruta=dashboard" class="app-logout" title="Volver al panel general"><i class="fa-solid fa-arrow-left"></i></a>
        </div>
    </aside>
    <main class="app-main">
