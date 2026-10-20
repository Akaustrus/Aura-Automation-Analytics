<?php
$inventario = $db->query('SELECT p.*, c.nombre AS categoria, prov.nombre AS proveedor, prov.tiempo_entrega_dias
                          FROM productos p JOIN categorias c ON c.id=p.categoria_id JOIN proveedores prov ON prov.id=p.proveedor_id
                          WHERE p.activo=1 ORDER BY p.stock_actual ASC')->fetchAll();
$productos = $db->query('SELECT id, codigo, nombre, stock_actual FROM productos WHERE activo=1 ORDER BY nombre')->fetchAll();
$proveedores = $db->query('SELECT id, nombre, tiempo_entrega_dias FROM proveedores ORDER BY nombre')->fetchAll();
$categorias = $db->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
$ultimosMov = $db->query('SELECT m.*, p.nombre AS producto FROM movimientos_inventario m JOIN productos p ON p.id=m.producto_id ORDER BY m.fecha DESC, m.id DESC LIMIT 15')->fetchAll();
$comprasPend = $db->query('SELECT oc.*, prov.nombre AS proveedor FROM ordenes_compra oc JOIN proveedores prov ON prov.id=oc.proveedor_id WHERE oc.estado IN ("pendiente","aprobada")')->fetchAll();

$valorInv = (float) $db->query('SELECT COALESCE(SUM(stock_actual * costo_unitario),0) FROM productos WHERE activo=1')->fetchColumn();
$unidTotal = (int) $db->query('SELECT COALESCE(SUM(stock_actual),0) FROM productos WHERE activo=1')->fetchColumn();
$bajoStock = $db->query('SELECT COUNT(*) FROM productos WHERE stock_actual <= punto_pedido AND activo=1')->fetchColumn();
foreach ($inventario as &$p) {
    $p['dias_restantes'] = diasRestantesStock($db, (int) $p['id']);
    $p['usa_diaria'] = usoDiarioPromedio($db, (int) $p['id']);
}
?>

<section class="page-heading module-page-heading mb-4">
    <div>
        <span class="page-eyebrow">Inventario</span>
        <h1 class="page-title mb-1">Stock y compras</h1>
        <p class="page-subtitle mb-0">Control de existencias, movimientos y predicción de cuándo hacer pedidos.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalProducto"><i class="fa-solid fa-box-open me-1"></i>Nuevo producto</button>
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalMovimiento"><i class="fa-solid fa-right-left me-1"></i>Registrar movimiento</button>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat stat-3"><div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div><div class="stat-num"><?= $unidTotal ?></div><div class="stat-label">Unidades en stock</div></div></div>
    <div class="col-md-3"><div class="stat stat-1"><div class="stat-icon"><i class="fa-solid fa-coins"></i></div><div class="stat-num"><?= formatoMoneda($valorInv) ?></div><div class="stat-label">Valor de inventario</div></div></div>
    <div class="col-md-3"><div class="stat stat-6"><div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="stat-num"><?= $bajoStock ?></div><div class="stat-label">Productos bajo punto pedido</div></div></div>
    <div class="col-md-3"><div class="stat stat-5"><div class="stat-icon"><i class="fa-solid fa-truck-ramp-box"></i></div><div class="stat-num"><?= count($comprasPend) ?></div><div class="stat-label">Órdenes de compra activas</div></div></div>
</div>

<!-- Tabla de inventario -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-boxes-stacked me-1"></i>Productos</span>
        <span class="badge bg-primary-subtle text-primary"><?= count($inventario) ?> productos</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Código</th><th>Producto</th><th>Categoría</th><th class="text-end">Stock</th><th class="text-end">Mín</th><th class="text-end">Punto pedido</th><th class="text-end">Uso diario</th><th class="text-end">Días restantes</th><th class="text-end">Costo</th><th class="text-end">Venta</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($inventario as $p): ?>
                    <?php
                        $nivel = $p['stock_actual'] <= $p['stock_minimo'] ? 'danger' : ($p['stock_actual'] <= $p['punto_pedido'] ? 'warning' : 'success');
                        $claseDias = $p['dias_restantes'] <= $p['tiempo_entrega_dias'] ? 'pred-malo' : ($p['dias_restantes'] <= 60 ? 'pred-neutro' : 'pred-bueno');
                    ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= limpiar($p['codigo']) ?></span></td>
                        <td><strong><?= limpiar($p['nombre']) ?></strong><br><small class="text-muted"><?= limpiar($p['proveedor']) ?> · entrega <?= (int)$p['tiempo_entrega_dias'] ?> días</small></td>
                        <td><small><?= limpiar($p['categoria']) ?></small></td>
                        <td class="text-end"><span class="badge bg-<?= $nivel ?>"><?= (int)$p['stock_actual'] ?></span></td>
                        <td class="text-end"><?= (int)$p['stock_minimo'] ?></td>
                        <td class="text-end"><?= (int)$p['punto_pedido'] ?></td>
                        <td class="text-end"><?= number_format($p['usa_diaria'], 1) ?></td>
                        <td class="text-end <?= $claseDias ?>"><?= $p['dias_restantes'] === 999 ? '∞' : $p['dias_restantes'] . ' d' ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $p['costo_unitario']) ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $p['precio_venta']) ?></td>
                        <td>
                            <?php if ($p['stock_actual'] <= $p['punto_pedido']): ?>
                                <span class="badge bg-danger"><i class="fa-solid fa-bell me-1"></i>Reabastecer</span>
                            <?php else: ?>
                                <span class="badge bg-success">OK</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalProducto"
                                    data-id="<?= $p['id'] ?>" data-codigo="<?= limpiar($p['codigo']) ?>" data-nombre="<?= limpiar($p['nombre']) ?>"
                                    data-cat="<?= $p['categoria_id'] ?>" data-prov="<?= $p['proveedor_id'] ?>" data-stock="<?= $p['stock_actual'] ?>"
                                    data-min="<?= $p['stock_minimo'] ?>" data-max="<?= $p['stock_maximo'] ?>" data-pp="<?= $p['punto_pedido'] ?>"
                                    data-costo="<?= $p['costo_unitario'] ?>" data-precio="<?= $p['precio_venta'] ?>" data-unidad="<?= limpiar($p['unidad']) ?>"><i class="fa-solid fa-pen"></i></button>
                            <form method="post" class="d-inline" data-confirm="¿Eliminar producto?">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" name="action" value="eliminar_producto" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <!-- Movimientos -->
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-right-left me-1"></i>Últimos movimientos</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th class="text-end">Cantidad</th><th>Concepto</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ultimosMov as $m): ?>
                            <tr>
                                <td><?= formatoFecha($m['fecha']) ?></td>
                                <td><small><?= limpiar($m['producto']) ?></small></td>
                                <td>
                                    <?php if ($m['tipo'] === 'entrada'): ?><span class="badge bg-success"><i class="fa-solid fa-arrow-down me-1"></i>Entrada</span>
                                    <?php elseif ($m['tipo'] === 'salida'): ?><span class="badge bg-warning text-dark"><i class="fa-solid fa-arrow-up me-1"></i>Salida</span>
                                    <?php else: ?><span class="badge bg-secondary">Ajuste</span><?php endif; ?>
                                </td>
                                <td class="text-end"><strong><?= $m['tipo'] === 'salida' ? '-' : '+' ?><?= (int)$m['cantidad'] ?></strong></td>
                                <td><small class="text-muted"><?= limpiar($m['concepto']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <!-- Órdenes de compra -->
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-cart-shopping me-1"></i>Órdenes de compra</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Código</th><th>Proveedor</th><th class="text-end">Total</th><th class="text-end">Entrega</th><th>Estado</th></tr></thead>
                        <tbody>
                        <?php foreach ($comprasPend as $oc): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= limpiar($oc['codigo']) ?></span></td>
                                <td><small><?= limpiar($oc['proveedor']) ?></small></td>
                                <td class="text-end"><?= formatoMoneda((float) $oc['total']) ?></td>
                                <td class="text-end"><?= formatoFecha($oc['fecha_estimada_entrega']) ?></td>
                                <td><?= badgeEstado($oc['estado']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($comprasPend)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Sin órdenes pendientes.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Producto -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <?= campoCsrf() ?>
                <input type="hidden" name="id" id="prod_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-box-open me-1"></i><span id="prod_titulo">Nuevo producto</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Código *</label><input type="text" name="codigo" id="prod_codigo" class="form-control" required></div>
                        <div class="col-md-8"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="prod_nombre" class="form-control" required></div>
                        <div class="col-md-6">
                            <label class="form-label">Categoría</label>
                            <select name="categoria_id" id="prod_cat" class="form-select" required>
                                <?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>"><?= limpiar($c['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Proveedor</label>
                            <select name="proveedor_id" id="prod_prov" class="form-select" required>
                                <?php foreach ($proveedores as $pr): ?><option value="<?= $pr['id'] ?>"><?= limpiar($pr['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label">Stock actual</label><input type="number" name="stock_actual" id="prod_stock" class="form-control" min="0" value="0"></div>
                        <div class="col-md-3"><label class="form-label">Stock mínimo</label><input type="number" name="stock_minimo" id="prod_min" class="form-control" min="0"></div>
                        <div class="col-md-3"><label class="form-label">Stock máximo</label><input type="number" name="stock_maximo" id="prod_max" class="form-control" min="0"></div>
                        <div class="col-md-3"><label class="form-label">Punto de pedido</label><input type="number" name="punto_pedido" id="prod_pp" class="form-control" min="0"></div>
                        <div class="col-md-4"><label class="form-label">Costo unitario *</label><input type="number" name="costo_unitario" id="prod_costo" class="form-control" step="0.01" min="0" required></div>
                        <div class="col-md-4"><label class="form-label">Precio venta</label><input type="number" name="precio_venta" id="prod_precio" class="form-control" step="0.01" min="0"></div>
                        <div class="col-md-4"><label class="form-label">Unidad</label><input type="text" name="unidad" id="prod_unidad" class="form-control" value="unidades"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="action" value="guardar_producto" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Movimiento -->
<div class="modal fade" id="modalMovimiento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= campoCsrf() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-right-left me-1"></i>Registrar movimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Producto</label>
                        <select name="producto_id" class="form-select" required>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?= $p['id'] ?>">[<?= limpiar($p['codigo']) ?>] <?= limpiar($p['nombre']) ?> (stock: <?= (int)$p['stock_actual'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select">
                                <option value="entrada">Entrada</option>
                                <option value="salida">Salida</option>
                                <option value="ajuste">Ajuste</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Cantidad *</label><input type="number" name="cantidad" class="form-control" min="1" required></div>
                        <div class="col-md-6"><label class="form-label">Fecha</label><input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Costo unitario</label><input type="number" name="costo_unitario" class="form-control" step="0.01" min="0" value="0"></div>
                        <div class="col-12"><label class="form-label">Concepto</label><input type="text" name="concepto" class="form-control" placeholder="Ej: Venta, reposición, merma..."></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="action" value="registrar_movimiento" class="btn btn-primary">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('modalProducto').addEventListener('show.bs.modal', function (ev) {
    const btn = ev.relatedTarget;
    if (!btn.dataset.id) return;
    document.getElementById('prod_titulo').textContent = 'Editar producto';
    document.getElementById('prod_id').value = btn.dataset.id;
    document.getElementById('prod_codigo').value = btn.dataset.codigo;
    document.getElementById('prod_nombre').value = btn.dataset.nombre;
    document.getElementById('prod_cat').value = btn.dataset.cat;
    document.getElementById('prod_prov').value = btn.dataset.prov;
    document.getElementById('prod_stock').value = btn.dataset.stock;
    document.getElementById('prod_min').value = btn.dataset.min;
    document.getElementById('prod_max').value = btn.dataset.max;
    document.getElementById('prod_pp').value = btn.dataset.pp;
    document.getElementById('prod_costo').value = btn.dataset.costo;
    document.getElementById('prod_precio').value = btn.dataset.precio;
    document.getElementById('prod_unidad').value = btn.dataset.unidad;
});
</script>