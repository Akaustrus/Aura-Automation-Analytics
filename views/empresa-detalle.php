<?php require __DIR__ . '/_header.php';
$empresa = $empresa ?? [];
$planes = $planes ?? [];
$tickets = $tickets ?? [];
$flash = $_GET['creada'] ?? $_GET['actualizada'] ?? $_GET['plan_asignado'] ?? $_GET['plan_actualizado'] ?? $_GET['plan_eliminado'] ?? null;
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-3">
        <span class="app-logo app-logo--lg"><i class="fa-solid fa-building"></i></span>
        <div>
            <h2 class="page-title mb-0"><?= limpiar($empresa['empresa']) ?></h2>
            <div class="text-muted small">RUC <?= limpiar($empresa['ruc']) ?></div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?ruta=gestionar-planes&id=<?= (int) $empresa['id'] ?>" class="btn btn-primary">
            <i class="fa-solid fa-sliders me-1"></i>Gestionar planes
        </a>
        <a href="index.php?ruta=empresa-editar&id=<?= (int) $empresa['id'] ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-pen me-1"></i>Editar
        </a>
        <form method="POST" action="index.php?ruta=empresa-eliminar" class="m-0"
              onsubmit="return confirm('¿Eliminar esta empresa y todas sus suscripciones?');">
            <?= campoCsrf() ?>
            <input type="hidden" name="id" value="<?= (int) $empresa['id'] ?>">
            <button class="btn btn-outline-danger"><i class="fa-solid fa-trash me-1"></i>Eliminar</button>
        </form>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success d-flex align-items-center">
        <i class="fa-solid fa-circle-check me-2"></i>
        <span>
            <?= match ($flash) {
                '1' => 'Operación realizada correctamente.',
                'plan_asignado' => 'Sistema asignado a la empresa.',
                'plan_actualizado' => 'Suscripción actualizada.',
                'plan_eliminado' => 'Sistema retirado de la empresa.',
                default => 'Cambios guardados.',
            } ?>
        </span>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="fa-solid fa-address-card me-2"></i>Contacto</div>
            <div class="card-body">
                <div class="mb-2"><i class="fa-solid fa-user me-2 text-muted"></i><?= $empresa['contacto'] ? limpiar($empresa['contacto']) : '<span class="text-muted">—</span>' ?></div>
                <div class="mb-2"><i class="fa-solid fa-phone me-2 text-muted"></i><?= $empresa['telefono'] ? limpiar($empresa['telefono']) : '<span class="text-muted">—</span>' ?></div>
                <div class="mb-2"><i class="fa-solid fa-envelope me-2 text-muted"></i><?= $empresa['email'] ? limpiar($empresa['email']) : '<span class="text-muted">—</span>' ?></div>
                <div class="mb-2"><i class="fa-solid fa-calendar-plus me-2 text-muted"></i>Cliente desde <?= formatoFechaCorta($empresa['fecha_creacion']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="fa-solid fa-layer-group me-2"></i>Sistemas</div>
            <div class="card-body">
                <div class="stat-num mb-1" style="color: var(--teal);"><?= count($planes) ?></div>
                <div class="stat-label text-muted">sistemas contratados (<?= count(array_filter($planes, fn ($p) => $p['estado'] === 'activo')) ?> activos)</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="fa-solid fa-ticket me-2"></i>Incidencias</div>
            <div class="card-body">
                <div class="stat-num mb-1" style="color: var(--teal);"><?= count($tickets) ?></div>
                <div class="stat-label text-muted">tickets registrados</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-sliders me-2"></i>Planes / sistemas contratados</span>
        <a href="index.php?ruta=gestionar-planes&id=<?= (int) $empresa['id'] ?>" class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-plus me-1"></i>Asignar / editar
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Sistema</th>
                <th>Estado</th>
                <th>Usuarios</th>
                <th>Precio</th>
                <th>Vigencia</th>
                <th>Incidencias</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($planes as $p): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="app-logo app-logo--sm"><i class="fa-solid <?= limpiar($p['icono']) ?>"></i></span>
                            <div>
                                <div class="fw-semibold"><?= limpiar($p['plan_nombre']) ?></div>
                                <small class="text-muted"><?= (int) $p['plan_modulos'] ?> módulos</small>
                            </div>
                        </div>
                    </td>
                    <td><?= badgePlanEstado($p['estado']) ?></td>
                    <td><?= (int) $p['usuarios'] ?></td>
                    <td><?= formatoMoneda($p['precio_mensual']) ?><small class="text-muted">/mes</small></td>
                    <td>
                        <small class="d-block"><?= formatoFechaCorta($p['fecha_inicio']) ?> → <?= formatoFechaCorta($p['fecha_vencimiento']) ?></small>
                        <?php if ($p['fecha_vencimiento']): ?>
                            <span class="text-muted small"><?= date('Y-m-d') <= $p['fecha_vencimiento'] ? 'Vigente' : 'Vencido' ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-light"><?= (int) $p['tickets'] ?></span></td>
                    <td class="text-end">
                        <a href="index.php?ruta=gestionar-planes&id=<?= (int) $empresa['id'] ?>&editar=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-pen me-1"></i>Editar
                        </a>
                        <form method="POST" action="index.php?ruta=plan-eliminar" class="d-inline m-0"
                              onsubmit="return confirm('¿Quitar este sistema de la empresa?');">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($planes)): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">
                    <i class="fa-solid fa-box-open d-block mb-2" style="font-size: 2rem;"></i>
                    Esta empresa aún no tiene sistemas contratados.
                    <a href="index.php?ruta=gestionar-planes&id=<?= (int) $empresa['id'] ?>" class="d-block mt-2">Asignar su primer plan</a>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold"><i class="fa-solid fa-ticket me-2"></i>Incidencias de la empresa</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Asunto</th>
                <th>Sistema</th>
                <th>Prioridad</th>
                <th>Encargado</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td class="text-muted">#<?= (int) $t['id'] ?></td>
                    <td class="fw-semibold"><?= limpiar($t['asunto']) ?></td>
                    <td>
                        <?php if ($t['plan_nombre']): ?>
                            <span class="badge text-bg-light"><i class="fa-solid <?= limpiar($t['plan_icono']) ?> me-1"></i><?= limpiar($t['plan_nombre']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= badgePrioridad($t['prioridad']) ?></td>
                    <td><?= $t['encargado_nombre'] ? limpiar($t['encargado_nombre']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= badgeEstado($t['estado']) ?></td>
                    <td><a href="index.php?ruta=ticket-detalle&id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Sin incidencias registradas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>