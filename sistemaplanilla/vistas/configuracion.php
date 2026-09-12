<?php
$parametros = $db->query('SELECT * FROM parametros ORDER BY id')->fetchAll();
$conceptos  = $db->query('SELECT * FROM conceptos ORDER BY id')->fetchAll();

$edicion = null;
if (!empty($_GET['editar'])) {
    $stmt = $db->prepare('SELECT * FROM conceptos WHERE id=?'); $stmt->execute([(int)$_GET['editar']]);
    $edicion = $stmt->fetch();
}
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-gears me-2"></i>Configuración</h2>

<div class="row g-4">
    <!-- Parámetros -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-sliders me-1"></i>Parámetros del sistema (S/ %)</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="guardar_parametro">
                    <?= campoCsrf() ?>
                    <?php foreach ($parametros as $p): ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold"><?= limpiar($p['clave']) ?>
                                <span class="text-muted fw-normal">— <?= limpiar($p['descripcion']) ?></span>
                            </label>
                            <input type="number" step="0.0001" name="<?= limpiar($p['clave']) ?>" class="form-control" value="<?= $p['valor'] ?>" required>
                        </div>
                    <?php endforeach; ?>
                    <button class="btn btn-primary w-100"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar parámetros</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Conceptos -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-layer-group me-1"></i>Conceptos configurables</span>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalConcepto"><i class="fa-solid fa-plus me-1"></i>Nuevo concepto</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Nombre</th><th>Tipo</th><th>Ámbito</th><th class="text-end">Valor</th><th>Aplica</th><th>Estado</th><th class="text-end" style="width:90px">Acciones</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($conceptos as $c): ?>
                            <tr>
                                <td><?= limpiar($c['nombre']) ?></td>
                                <td><span class="badge bg-<?= $c['tipo'] === 'ingreso' ? 'success' : 'danger' ?>"><?= ucfirst($c['tipo']) ?></span></td>
                                <td class="small"><?= ucfirst($c['ambito']) ?></td>
                                <td class="text-end">
                                    <?php if ($c['naturaleza'] === 'porcentaje'): ?>
                                        <?= number_format($c['porcentaje'],2) ?> %
                                    <?php else: ?>
                                        <?= number_format($c['monto'],2) ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light border text-dark"><?= ucfirst($c['aplica']) ?></span></td>
                                <td><?= badgeEstado($c['activo'] ? 'activo' : 'inactivo') ?></td>
                                <td class="text-end">
                                    <a href="?vista=configuracion&editar=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar concepto?')">
                                        <input type="hidden" name="action" value="eliminar_concepto">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <?= campoCsrf() ?>
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal concepto -->
<div class="modal fade" id="modalConcepto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_concepto">
            <input type="hidden" name="id" value="<?= $edicion['id'] ?? '' ?>">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= $edicion ? 'Editar concepto' : 'Nuevo concepto' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="80" value="<?= limpiar($edicion['nombre'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="ingreso" <?= ($edicion['tipo'] ?? '') === 'ingreso' ? 'selected' : '' ?>>Ingreso</option>
                            <option value="descuento" <?= ($edicion['tipo'] ?? '') === 'descuento' ? 'selected' : '' ?>>Descuento</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ámbito</label>
                        <select name="ambito" class="form-select">
                            <option value="trabajador" <?= ($edicion['ambito'] ?? '') === 'trabajador' ? 'selected' : '' ?>>Trabajador</option>
                            <option value="empleador" <?= ($edicion['ambito'] ?? '') === 'empleador' ? 'selected' : '' ?>>Empleador</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Naturaleza</label>
                        <select name="naturaleza" class="form-select">
                            <option value="fijo" <?= ($edicion['naturaleza'] ?? '') === 'fijo' ? 'selected' : '' ?>>Monto fijo</option>
                            <option value="porcentaje" <?= ($edicion['naturaleza'] ?? '') === 'porcentaje' ? 'selected' : '' ?>>Porcentaje</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Monto (S/)</label>
                        <input type="number" step="0.01" name="monto" class="form-control" value="<?= $edicion['monto'] ?? 0 ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Porcentaje (%)</label>
                        <input type="number" step="0.0001" name="porcentaje" class="form-control" value="<?= $edicion['porcentaje'] ?? 0 ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Aplica en</label>
                        <select name="aplica" class="form-select">
                            <?php foreach (['todos','mensual','gratificacion','cts'] as $ap): ?>
                                <option value="<?= $ap ?>" <?= ($edicion['aplica'] ?? 'todos') === $ap ? 'selected' : '' ?>><?= ucfirst($ap) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="activo" id="conActivo" value="1" <?= empty($edicion) || $edicion['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="conActivo">Activo</label>
                        </div>
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
        new bootstrap.Modal(document.getElementById('modalConcepto')).show();
    <?php endif; ?>
});
</script>