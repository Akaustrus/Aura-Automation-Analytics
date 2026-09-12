<?php
$productos = $db->query('SELECT p.*, c.nombre categoria, COALESCE(s.stock_total,0) stock_total
                         FROM productos p LEFT JOIN categorias c ON c.id=p.categoria_id
                         LEFT JOIN (SELECT producto_id, SUM(cantidad) stock_total FROM stock GROUP BY producto_id) s ON s.producto_id=p.id
                         ORDER BY p.nombre')->fetchAll();
$categorias = $db->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll();
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-boxes-stacked me-2"></i>Productos
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalProducto"><i class="fa-solid fa-plus me-1"></i>Nuevo producto</button>
</h2>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Código</th><th>Producto</th><th>Categoría</th><th>Unidad</th>
                        <th class="text-end">Stock</th><th class="text-end">P. compra</th><th class="text-end">P. venta</th>
                        <th>Estado</th><th class="text-end" style="width:110px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($productos as $p): ?>
                    <tr class="<?= $p['activo'] ? '' : 'text-muted' ?>">
                        <td><span class="badge bg-light text-dark border"><?= limpiar($p['codigo']) ?></span></td>
                        <td class="text-truncate" style="max-width:220px" title="<?= limpiar($p['nombre']) ?>"><strong><?= limpiar($p['nombre']) ?></strong></td>
                        <td class="small"><?= limpiar($p['categoria'] ?? '—') ?></td>
                        <td><?= limpiar($p['unidad']) ?></td>
                        <td class="text-end">
                            <?php if ($p['controla_stock']): ?>
                                <strong><?= formatoCantidad((float) $p['stock_total']) ?></strong>
                                <small class="text-muted">/ mín <?= formatoCantidad((float) $p['stock_minimo']) ?></small>
                            <?php else: ?><span class="badge bg-info-subtle text-info">Servicio</span><?php endif; ?>
                        </td>
                        <td class="text-end"><?= number_format($p['precio_compra'], 2) ?></td>
                        <td class="text-end"><strong><?= number_format($p['precio_venta'], 2) ?></strong></td>
                        <td><?= $p['controla_stock'] ? badgeEstado(estadoProducto($p)) : badgeEstado('servicio') ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" title="Editar" onclick="editarProducto(<?= $p['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar el producto <?= addslashes($p['nombre']) ?>?')">
                                <input type="hidden" name="action" value="eliminar_producto">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$productos): ?><tr><td colspan="9" class="text-center text-muted py-3">Sin productos.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_producto">
            <input type="hidden" name="id" id="pd_id" value="0">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title" id="pd_titulo">Nuevo producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-4"><label class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" name="codigo" id="pd_cod" class="form-control" required></div>
                    <div class="col-8"><label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="pd_nom" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Descripción</label>
                        <input type="text" name="descripcion" id="pd_desc" class="form-control"></div>
                    <div class="col-4"><label class="form-label">Categoría</label>
                        <select name="categoria_id" id="pd_cat" class="form-select">
                            <option value="">— Sin categoría —</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= limpiar($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-4"><label class="form-label">Unidad</label>
                        <input type="text" name="unidad" id="pd_uni" class="form-control" value="unidad" placeholder="unidad, caja, hora…"></div>
                    <div class="col-4"><label class="form-label">Stock mínimo</label>
                        <input type="number" name="stock_minimo" id="pd_min" class="form-control" step="0.001" min="0" value="0"></div>
                    <div class="col-6"><label class="form-label">Precio compra</label>
                        <input type="number" name="precio_compra" id="pd_pc" class="form-control" step="0.01" min="0" value="0"></div>
                    <div class="col-6"><label class="form-label">Precio venta</label>
                        <input type="number" name="precio_venta" id="pd_pv" class="form-control" step="0.01" min="0" value="0"></div>
                    <div class="col-6 d-flex align-items-center"><div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="controla_stock" id="pd_cs" checked>
                        <label class="form-check-label" for="pd_cs">Controla stock</label></div></div>
                    <div class="col-6 d-flex align-items-center"><div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" id="pd_act" checked>
                        <label class="form-check-label" for="pd_act">Activo</label></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
const PRODUCTOS = <?= json_encode($productos) ?>;
function editarProducto(id){
    const p = PRODUCTOS.find(x => String(x.id) === String(id));
    if (!p) return;
    document.getElementById('pd_id').value = p.id;
    document.getElementById('pd_titulo').textContent = 'Editar producto';
    document.getElementById('pd_cod').value = p.codigo;
    document.getElementById('pd_nom').value = p.nombre;
    document.getElementById('pd_desc').value = p.descripcion;
    document.getElementById('pd_cat').value = p.categoria_id || '';
    document.getElementById('pd_uni').value = p.unidad;
    document.getElementById('pd_min').value = p.stock_minimo;
    document.getElementById('pd_pc').value = p.precio_compra;
    document.getElementById('pd_pv').value = p.precio_venta;
    document.getElementById('pd_cs').checked = !!Number(p.controla_stock);
    document.getElementById('pd_act').checked = !!Number(p.activo);
    new bootstrap.Modal(document.getElementById('modalProducto')).show();
}
</script>