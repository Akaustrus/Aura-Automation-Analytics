<?php require __DIR__ . '/_header.php';
$sistemas = $sistemas ?? [];
$conteo = $conteo ?? [];
$porEstado = $porEstado ?? [];
?>

<div class="page-heading admin-hero admin-hero--compact mb-4">
    <div>
        <span class="page-eyebrow">Ecosistema de productos</span>
        <h1 class="page-title mb-1">Sistemas y soluciones</h1>
        <p class="page-subtitle mb-0">Gestiona el portafolio contratado por cada empresa.</p>
    </div>
    <a href="index.php?ruta=empresa-nueva" class="btn btn-primary">
        <i class="fa-solid fa-building-circle-plus me-1"></i>Nueva empresa
    </a>
</div>

<div class="alert alert-info d-flex align-items-center">
    <i class="fa-solid fa-circle-info me-2"></i>
    <div>Los <strong>5 sistemas</strong> que vendemos a las empresas. Cada uno tiene su propio módulo
        y base de datos. Desde aquí controlas qué empresa usa cada sistema, sus licencias y su estado.</div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat stat-1">
            <i class="fa-solid fa-boxes-stacked stat-icon"></i>
            <div class="stat-num"><?= count($sistemas) ?></div>
            <div class="stat-label">Sistemas disponibles</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-2">
            <i class="fa-solid fa-circle-check stat-icon"></i>
            <div class="stat-num"><?= (int) ($porEstado['activo'] ?? 0) ?></div>
            <div class="stat-label">Suscripciones activas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-3">
            <i class="fa-solid fa-clock stat-icon"></i>
            <div class="stat-num"><?= (int) ($porEstado['pendiente'] ?? 0) ?></div>
            <div class="stat-label">Pendientes de activar</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-4">
            <i class="fa-solid fa-triangle-exclamation stat-icon"></i>
            <div class="stat-num"><?= (int) ($porEstado['vencido'] ?? 0) ?></div>
            <div class="stat-label">Vencidas por renovar</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($sistemas as $p):
        $empresas = (int) ($conteo[$p['id']]['empresas'] ?? 0);
        $activas = (int) ($conteo[$p['id']]['activas'] ?? 0);
    ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 solution-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start gap-3">
                        <span class="app-logo"><i class="fa-solid <?= limpiar($p['icono']) ?>"></i></span>
                        <div class="flex-grow-1">
                            <h5 class="mb-1 fw-semibold"><?= limpiar($p['nombre']) ?></h5>
                            <small class="text-muted d-block mb-2">
                                <i class="fa-solid fa-folder me-1"></i><?= limpiar($p['modulo']) ?>
                            </small>
                            <p class="text-muted small mb-3"><?= limpiar($p['descripcion']) ?></p>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge text-bg-light"><i class="fa-solid fa-cubes me-1"></i><?= (int) $p['modulos'] ?> módulos</span>
                        <span class="badge text-bg-light"><i class="fa-solid fa-building me-1"></i><?= $empresas ?> empresas</span>
                    </div>
                    <div class="progress mb-1" style="height: 6px;">
                        <div class="progress-bar" style="width: <?= $empresas ? min(100, $activas) : 0 ?>%"></div>
                    </div>
                    <small class="text-muted"><?= $activas ?> suscripciones activas</small>
                    <div class="d-flex gap-2 mt-3">
                        <a href="index.php?ruta=tickets&plan=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-ticket me-1"></i>Incidencias del sistema
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
