<?php
$productos = $db->query('SELECT * FROM productos ORDER BY descripcion')->fetchAll();

$editando = null;
if (!empty($_GET['editar'])) {
    $stmt = $db->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch();
}

$nTotal   = count($productos);
$nProd    = count(array_filter($productos, fn($p) => $p['tipo'] === 'producto'));
$nServ    = count(array_filter($productos, fn($p) => $p['tipo'] === 'servicio'));
$precioMayor = $productos ? max(array_map(fn($p) => (float) $p['precio'], $productos)) : 0;
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-boxes-stacked me-2"></i>Productos y servicios
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalProd"><i class="fa-solid fa-plus me-1"></i>Nuevo producto</button>
</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
            <div class="stat-num"><?= $nProd ?></div>
            <div class="stat-label">Productos</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-headset"></i></div>
            <div class="stat-num"><?= $nServ ?></div>
            <div class="stat-label">Servicios</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-tags"></i></div>
            <div class="stat-num"><?= $nTotal ?></div>
            <div class="stat-label">Catálogo total</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-4">
            <div class="stat-icon"><i class="fa-solid fa-arrow-up-wide-short"></i></div>
            <div class="stat-num">S/ <?= number_format($precioMayor, 0) ?></div>
            <div class="stat-label">Precio más alto</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Código</th><th>Descripción</th><th>Tipo</th><th class="text-end">Precio (S/)</th>
                        <th>IGV</th><th>Estado</th><th class="text-end" style="width:140px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($productos as $p): ?>
                    <tr class="<?= $p['activo'] ? '' : 'table-secondary text-muted' ?>">
                        <td><span class="badge bg-light border text-dark"><?= limpiar($p['codigo']) ?></span></td>
                        <td><?= limpiar($p['descripcion']) ?></td>
                        <td><?= badgeEstado($p['tipo']) ?></td>
                        <td class="text-end"><strong><?= number_format($p['precio'], 2) ?></strong></td>
                        <td><?= $p['afecto_igv'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                        <td><?= badgeEstado($p['activo'] ? 'activo' : 'inactivo') ?></td>
                        <td class="text-end">
                            <a href="?vista=productos&editar=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar <?= limpiar($p['descripcion']) ?>?')">
                                <input type="hidden" name="action" value="eliminar_producto">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$productos): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Sin productos registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal crear / editar producto -->
<div class="modal fade" id="modalProd" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_producto">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= $editando ? 'Editar producto' : 'Registrar producto' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" name="codigo" class="form-control" required maxlength="20" value="<?= limpiar($editando['codigo'] ?? '') ?>" placeholder="Ej. SRV001">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="producto" <?= ($editando['tipo'] ?? 'producto')==='producto' ? 'selected' : '' ?>>Producto</option>
                            <option value="servicio" <?= ($editando['tipo'] ?? '')==='servicio' ? 'selected' : '' ?>>Servicio</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="afecto_igv" id="prodIgv" value="1" <?= empty($editando) || $editando['afecto_igv'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="prodIgv">Afecto a IGV</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción <span class="text-danger">*</span></label>
                        <input type="text" name="descripcion" class="form-control" required maxlength="150" value="<?= limpiar($editando['descripcion'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Precio de venta (S/) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="precio" class="form-control" required value="<?= $editando['precio'] ?? '' ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="activo" id="prodActivo" value="1" <?= empty($editando) || $editando['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="prodActivo">Disponible para venta</label>
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
    <?php if ($editando): ?>
        new bootstrap.Modal(document.getElementById('modalProd')).show();
    <?php endif; ?>
});
</script>