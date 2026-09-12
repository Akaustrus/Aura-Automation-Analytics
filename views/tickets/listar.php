<?php require __DIR__ . '/../_header.php';
$tickets = $tickets ?? [];
$planes = $planes ?? [];
$actual = $_GET['estado'] ?? null;
$actualPlan = !empty($_GET['plan']) ? (int) $_GET['plan'] : null;
$filtros = [
    null => 'Todos', 'nuevo' => 'Nuevos', 'en_curso' => 'En curso', 'pausado' => 'Pausados',
    'culminado' => 'Culminados', 'resuelto' => 'Resueltos', 'cerrado' => 'Cerrados',
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="page-title mb-0"><i class="fa-solid fa-ticket me-2"></i>Incidencias de los sistemas</h2>
    <a href="index.php?ruta=atender" class="btn btn-primary"><i class="fa-solid fa-headset me-1"></i>Atender llamada</a>
</div>

<div class="filtros mb-2">
    <?php foreach ($filtros as $valor => $label): ?>
        <a href="<?= $valor === null
                ? 'index.php?ruta=tickets' . ($actualPlan ? '&plan=' . $actualPlan : '')
                : 'index.php?ruta=tickets&estado=' . $valor . ($actualPlan ? '&plan=' . $actualPlan : '') ?>"
           class="filtro-btn <?= $actual === $valor ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<div class="filtros mb-3">
    <a href="index.php?ruta=tickets" class="filtro-btn <?= !$actualPlan ? 'active' : '' ?>">
        <i class="fa-solid fa-layer-group me-1"></i>Todos los sistemas
    </a>
    <?php foreach ($planes as $p): ?>
        <a href="index.php?ruta=tickets&plan=<?= (int) $p['id'] ?>" class="filtro-btn <?= $actualPlan === (int) $p['id'] ? 'active' : '' ?>">
            <i class="fa-solid <?= limpiar($p['icono']) ?> me-1"></i><?= limpiar($p['nombre']) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Asunto</th>
                <th>Empresa</th>
                <th>Sistema</th>
                <th>Categoría</th>
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
                    <td><?= limpiar($t['cliente_nombre']) ?></td>
                    <td>
                        <?php if ($t['plan_nombre']): ?>
                            <span class="badge text-bg-light"><i class="fa-solid <?= limpiar($t['plan_icono']) ?> me-1"></i><?= limpiar($t['plan_nombre']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-light"><?= etiquetaCategoria($t['categoria']) ?></span></td>
                    <td><?= badgePrioridad($t['prioridad']) ?></td>
                    <td><?= $t['encargado_nombre'] ? limpiar($t['encargado_nombre']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= badgeEstado($t['estado']) ?></td>
                    <td><a href="index.php?ruta=ticket-detalle&id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
                <tr><td colspan="9" class="text-center text-muted py-5">No hay incidencias.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../_footer.php'; ?>