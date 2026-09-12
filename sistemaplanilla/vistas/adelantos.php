<?php
$filtro = '';
if (isset($_GET['estado']) && in_array($_GET['estado'], ['pendiente','cobrado'], true)) {
    $filtro = ' WHERE a.estado = \'' . $_GET['estado'] . '\'';
}
$adelantos = $db->query("SELECT a.*, e.dni, e.nombres, e.apellidos, e.cargo
                         FROM adelantos a JOIN empleados e ON e.id = a.empleado_id$filtro
                         ORDER BY a.fecha DESC")->fetchAll();
$empleados = $db->query('SELECT id, dni, nombres, apellidos, cargo FROM empleados WHERE activo=1 ORDER BY apellidos')->fetchAll();

$totPend = (float) $db->query('SELECT COALESCE(SUM(monto),0) FROM adelantos WHERE estado=\'pendiente\'')->fetchColumn();
$totCob  = (float) $db->query('SELECT COALESCE(SUM(monto),0) FROM adelantos WHERE estado=\'cobrado\'')->fetchColumn();
$nPend   = (int) $db->query('SELECT COUNT(*) FROM adelantos WHERE estado=\'pendiente\'')->fetchColumn();

$edicion = null;
if (!empty($_GET['editar'])) {
    $stmt = $db->prepare('SELECT * FROM adelantos WHERE id=?'); $stmt->execute([(int)$_GET['editar']]);
    $edicion = $stmt->fetch();
}
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-hand-holding-dollar me-2"></i>Adelantos y préstamos
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalAdel"><i class="fa-solid fa-plus me-1"></i>Nuevo adelanto</button>
</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-num"><?= $nPend ?></div>
            <div class="stat-label">Adelantos pendientes</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="stat-num"><?= formatoMoneda($totPend) ?></div>
            <div class="stat-label">Monto por descontar</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="stat-num"><?= formatoMoneda($totCob) ?></div>
            <div class="stat-label">Ya descontados</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-4">
            <div class="stat-icon"><i class="fa-solid fa-list"></i></div>
            <div class="stat-num"><?= count($adelantos) ?></div>
            <div class="stat-label">Registros</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-list me-1"></i>Movimientos</span>
        <div class="btn-group btn-group-sm">
            <a href="?vista=adelantos" class="btn <?= !isset($_GET['estado']) ? 'btn-primary' : 'btn-outline-primary' ?>">Todos</a>
            <a href="?vista=adelantos&estado=pendiente" class="btn <?= ($_GET['estado'] ?? '') === 'pendiente' ? 'btn-primary' : 'btn-outline-primary' ?>">Pendientes</a>
            <a href="?vista=adelantos&estado=cobrado" class="btn <?= ($_GET['estado'] ?? '') === 'cobrado' ? 'btn-primary' : 'btn-outline-primary' ?>">Cobrados</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Empleado</th><th>Concepto</th><th class="text-end">Monto</th><th>Estado</th><th class="text-end" style="width:110px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($adelantos as $a): ?>
                    <tr>
                        <td><?= formatoFecha($a['fecha']) ?></td>
                        <td><?= limpiar($a['apellidos']) ?>, <?= limpiar($a['nombres']) ?><div class="small text-muted"><?= limpiar($a['cargo']) ?></div></td>
                        <td><?= limpiar($a['concepto']) ?></td>
                        <td class="text-end"><strong><?= number_format($a['monto'],2) ?></strong></td>
                        <td><?= badgeEstado($a['estado']) ?></td>
                        <td class="text-end">
                            <a href="?vista=adelantos&editar=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este adelanto?')">
                                <input type="hidden" name="action" value="eliminar_adelanto">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <?= campoCsrf() ?>
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$adelantos): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Sin movimientos registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info small mt-3 mb-0">
    <i class="fa-solid fa-circle-info me-1"></i>Los adelantos <b>pendientes</b> se descuentan automáticamente de la próxima planilla mensual que se genere y pasan a estado <b>cobrado</b>.
</div>

<!-- Modal crear / editar adelanto -->
<div class="modal fade" id="modalAdel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_adelanto">
            <input type="hidden" name="id" value="<?= $edicion['id'] ?? '' ?>">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= $edicion ? 'Editar adelanto' : 'Registrar adelanto' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Empleado <span class="text-danger">*</span></label>
                        <select name="empleado_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($empleados as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= (int)($edicion['empleado_id'] ?? 0) === (int)$e['id'] ? 'selected' : '' ?>><?= $e['apellidos'] ?>, <?= $e['nombres'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" class="form-control" required value="<?= $edicion['fecha'] ?? date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Monto (S/) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required value="<?= $edicion['monto'] ?? '' ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Concepto</label>
                        <input type="text" name="concepto" class="form-control" maxlength="120" value="<?= limpiar($edicion['concepto'] ?? 'Adelanto de sueldo') ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="pendiente" <?= ($edicion['estado'] ?? 'pendiente') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="cobrado" <?= ($edicion['estado'] ?? '') === 'cobrado' ? 'selected' : '' ?>>Cobrado</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    <?php if ($edicion): ?>
        new bootstrap.Modal(document.getElementById('modalAdel')).show();
    <?php endif; ?>
});
</script>