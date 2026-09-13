<?php header('Content-Type: text/html; charset=UTF-8'); ?>
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
    <link href="css/design-system.css" rel="stylesheet">
    <script src="js/theme.js"></script>
</head>
<body class="portal-app">
<?php if (!empty($_SESSION['cliente_id'])):
    $rutaPortal = $_GET['ruta'] ?? 'portal-mis-tickets';
?>
<header class="portal-topbar">
    <div class="portal-topbar__inner">
        <a class="portal-brand" href="index.php?ruta=portal-mis-tickets" aria-label="Ir al inicio del portal">
            <span class="portal-brand__mark"><i class="fa-solid fa-headset"></i></span>
            <span class="portal-brand__text">
                <strong>Aura</strong>
                <small>Portal del cliente</small>
            </span>
        </a>

        <nav class="portal-nav" aria-label="Navegación del portal">
            <a href="index.php?ruta=portal-mis-tickets" class="portal-nav__link <?= in_array($rutaPortal, ['portal-mis-tickets', 'portal-ticket'], true) ? 'active' : '' ?>">
                <i class="fa-solid fa-ticket"></i><span>Incidencias</span>
            </a>
            <a href="index.php?ruta=portal-planes" class="portal-nav__link <?= in_array($rutaPortal, ['portal-planes', 'portal-plan'], true) ? 'active' : '' ?>">
                <i class="fa-solid fa-layer-group"></i><span>Sistemas</span>
            </a>
            <a href="index.php?ruta=portal-citas" class="portal-nav__link <?= in_array($rutaPortal, ['portal-citas', 'portal-agendar'], true) ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-check"></i><span>Citas</span>
            </a>
        </nav>

        <div class="portal-user">
            <button type="button" class="portal-theme-toggle" data-theme-toggle aria-label="Activar modo oscuro" title="Cambiar tema">
                <i class="fa-solid fa-moon" aria-hidden="true"></i>
                <span class="visually-hidden" data-theme-label>Modo oscuro</span>
            </button>
            <a href="index.php?ruta=portal-nueva-incidencia" class="btn btn-primary portal-new-ticket <?= $rutaPortal === 'portal-nueva-incidencia' ? 'active' : '' ?>" aria-label="Nueva incidencia">
                <i class="fa-solid fa-plus"></i><span>Nueva incidencia</span>
            </a>
            <div class="portal-account">
                <span class="portal-account__avatar" aria-hidden="true"><i class="fa-solid fa-building"></i></span>
                <span class="portal-account__name"><?= limpiar($_SESSION['cliente_empresa'] ?? '') ?></span>
                <a href="index.php?ruta=portal-logout" class="portal-logout" title="Cerrar sesión" aria-label="Cerrar sesión">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </div>
</header>
<main class="portal-main">
<?php endif; ?>
