<?php
$portalTitulo = $suscripcion['plan_nombre'] ?? 'Detalle del sistema';
require __DIR__ . '/_portal_header.php';
$suscripcion = $suscripcion ?? [];
$tickets = $tickets ?? [];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title mb-0">
            <i class="fa-solid <?= limpiar($suscripcion['icono']) ?> me-2"></i><?= limpiar($suscripcion['plan_nombre']) ?>
        </h2>
        <a href="index.php?ruta=portal-planes" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Mis sistemas
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-id-card me-2"></i>Detalle de tu suscripción</span>
            <?= badgePlanEstado($suscripcion['estado'] ?? '') ?>
        </div>
        <div class="card-body p-4">
            <p class="text-muted mb-4"><?= limpiar($suscripcion['plan_descripcion']) ?></p>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Usuarios / licencias</div>
                    <div class="fw-semibold fs-5"><?= (int) $suscripcion['usuarios'] ?></div>
                    <small class="text-muted">incluidos en tu plan</small>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Módulos del sistema</div>
                    <div class="fw-semibold fs-5"><?= (int) $suscripcion['plan_modulos'] ?></div>
                    <small class="text-muted">funcionalidades disponibles</small>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Precio mensual</div>
                    <div class="fw-semibold fs-5"><?= formatoMoneda($suscripcion['precio_mensual']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Desde</div>
                    <div class="fw-semibold"><?= formatoFechaCorta($suscripcion['fecha_inicio']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Hasta</div>
                    <div class="fw-semibold"><?= formatoFechaCorta($suscripcion['fecha_vencimiento']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Estado de la suscripción</div>
                    <div class="fw-semibold"><?= etiquetaPlanEstado($suscripcion['estado']) ?></div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Observaciones</div>
                    <div><?= $suscripcion['notas'] ? limpiar($suscripcion['notas']) : '<span class="text-muted">—</span>' ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0 fw-semibold"><i class="fa-solid fa-ticket me-2"></i>Mis incidencias en <?= limpiar($suscripcion['plan_nombre']) ?></h5>
        <a href="index.php?ruta=portal-nueva-incidencia" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-plus me-1"></i>Nueva incidencia
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Asunto</th>
                    <th>Categoría</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td class="text-muted">#<?= (int) $t['id'] ?></td>
                        <td class="fw-semibold"><?= limpiar($t['asunto']) ?></td>
                        <td><span class="badge text-bg-light"><?= etiquetaCategoria($t['categoria']) ?></span></td>
                        <td><?= badgePrioridad($t['prioridad']) ?></td>
                        <td><?= badgeEstado($t['estado']) ?></td>
                        <td><a href="index.php?ruta=portal-ticket&id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($tickets)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        Todavía no tienes incidencias en este sistema.
                        <a href="index.php?ruta=portal-nueva-incidencia" class="d-block mt-2">Reportar una nueva</a>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
if (!empty($_SESSION['cliente_id'])) { echo "\n</main>"; }
require __DIR__ . '/_portal_footer.php';
?>