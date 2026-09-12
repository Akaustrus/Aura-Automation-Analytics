<?php
$busqueda = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM productos WHERE 1=1';
$args = [];
if ($busqueda !== '') {
    $sql .= ' AND (nombre LIKE ? OR codigo LIKE ? OR categoria LIKE ?)';
    $args[] = '%' . $busqueda . '%';
    $args[] = '%' . $busqueda . '%';
    $args[] = '%' . $busqueda . '%';
}
$stmt = $db->prepare($sql . ' ORDER BY nombre');
$stmt->execute($args);
$productos = $stmt->fetchAll();

$editando = null;
if (!empty($_GET['editar'])) {
    $s = $db->prepare('SELECT * FROM productos WHERE id=?'); $s->execute([(int) $_GET['editar']]);
    $editando = $s->fetch();
}

$categorias = array_map(fn($c) => $c['categoria'],
    $db->query("SELECT DISTINCT categoria FROM productos WHERE categoria <> '' ORDER BY categoria")->fetchAll());
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-boxes-stacked me-2"></i>Productos &amp; servicios
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalProducto"><i class="fa-solid fa-plus me-1"></i>Nuevo producto</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="productos">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small">Buscar (código, nombre o categoría)</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?= limpiar($busqueda) ?>">
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i>Buscar</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Código</th><th>Nombre</th><th>Categoría</th><th>Unidad</th>
                        <th class="text-end">Stock</th><th>Mínimo</th><th class="text-end">P. compra</th>
                        <th class="text-end">P. venta</th><th>Tipo</th><th>Estado</th>
                        <th class="text-end" style="width:140px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($productos as $p): ?>
                    <?php $est = estadoStock($p); ?>
                    <tr>
                        <td><strong><?= limpiar($p['codigo']) ?></strong></td>
                        <td><?= limpiar($p['nombre']) ?><div class="small text-muted"><?= limpiar($p['descripcion']) ?></div></td>
                        <td class="small"><?= limpiar($p['categoria']) ?></td>
                        <td class="small"><?= limpiar($p['unidad']) ?></td>
                        <td class="text-end">
                            <?php if ($p['controla_stock']): ?>
                                <span class="badge bg-<?= $est==='ok' ? 'success' : ($est==='bajo' ? 'warning text-dark' : 'danger') ?>">
                                    <?= formatoCantidad((float) $p['stock']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= $p['controla_stock'] ? formatoCantidad((float) $p['stock_minimo']) : '—' ?></td>
                        <td class="text-end"><?= number_format($p['precio_compra'], 2) ?></td>
                        <td class="text-end"><strong><?= number_format($p['precio_venta'], 2) ?></strong></td>
                        <td><?= badgeEstado($p['controla_stock'] ? 'activo' : 'servicio') ?></td>
                        <td><?= badgeEstado($p['activo'] ? 'activo' : 'inactivo') ?></td>
                        <td class="text-end">
                            <a href="?vista=productos&editar=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar el producto <?= limpiar($p['codigo']) ?>?')">
                                <input type="hidden" name="action" value="eliminar_producto">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$productos): ?>
                    <tr><td colspan="11" class="text-center text-muted py-3">Sin productos registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_producto">
            <?php if ($editando): ?><input type="hidden" name="id" value="<?= $editando['id'] ?>"><?php endif; ?>
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= $editando ? 'Editar producto' : 'Registrar producto' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" name="codigo" class="form-control" required value="<?= limpiar($editando['codigo'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required value="<?= limpiar($editando['nombre'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="descripcion" class="form-control" value="<?= limpiar($editando['descripcion'] ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Categoría</label>
                        <input type="text" name="categoria" class="form-control" list="listaCat" value="<?= limpiar($editando['categoria'] ?? '') ?>" placeholder="Ej. Equipos">
                        <datalist id="listaCat">
                            <?php foreach ($categorias as $cat): ?><option value="<?= limpiar($cat) ?>"></option><?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unidad</label>
                        <select name="unidad" class="form-select">
                            <?php foreach (['unidad','caja','kg','litro','servicio'] as $u): ?>
                                <option value="<?= $u ?>" <?= ($editando['unidad'] ?? 'unidad')===$u ? 'selected' : '' ?>><?= $u ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end pb-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="controla_stock" id="prdCtrl"
                                   <?= !$editando || $editando['controla_stock'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="prdCtrl">Controla stock (desmarca para servicios)</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock actual</label>
                        <input type="text" class="form-control" disabled value="<?= $editando ? formatoCantidad((float) $editando['stock']) : '0 (se gestiona en Inventario)' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock mínimo de alerta</label>
                        <input type="number" name="stock_minimo" class="form-control" min="0" step="any" value="<?= $editando['stock_minimo'] ?? '0' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Precio compra</label>
                        <input type="number" name="precio_compra" class="form-control" min="0" step="0.01" value="<?= $editando['precio_compra'] ?? '0' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Precio venta</label>
                        <input type="number" name="precio_venta" class="form-control" min="0" step="0.01" value="<?= $editando['precio_venta'] ?? '0' ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-end pb-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="activo" id="prdActivo"
                                   <?= !$editando || $editando['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="prdActivo">Producto activo</label>
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
    <?php if ($editando): ?>new bootstrap.Modal(document.getElementById('modalProducto')).show();<?php endif; ?>
});
</script>