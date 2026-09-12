<?php
$stock = $db->query('SELECT * FROM productos WHERE controla_stock=1 ORDER BY nombre')->fetchAll();
$ultimos = $db->query('SELECT m.*, p.codigo, p.nombre FROM movimientos m JOIN productos p ON p.id=m.producto_id
                       ORDER BY m.fecha DESC, m.id DESC LIMIT 15')->fetchAll();
$productosStock = $stock;

$valCosto = 0.0; $valVenta = 0.0; $unidades = 0.0;
foreach ($stock as $s) {
    $valCosto += (float) $s['stock'] * (float) $s['precio_compra'];
    $valVenta += (float) $s['stock'] * (float) $s['precio_venta'];
    $unidades += (float) $s['stock'];
}
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-warehouse me-2"></i>Inventario y movimientos
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalMov"><i class="fa-solid fa-right-left me-1"></i>Registrar movimiento</button>
</h2>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="stat-num"><?= formatoCantidad($unidades) ?></div>
            <div class="stat-label">Unidades en almacén</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-coins"></i></div>
            <div class="stat-num"><?= formatoMoneda($valCosto) ?></div>
            <div class="stat-label">Valorización al costo</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="stat-num"><?= formatoMoneda($valVenta) ?></div>
            <div class="stat-label">Valorización a precio de venta</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-box me-1"></i>Estado de stock</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Código</th><th>Producto</th><th>Categoría</th><th class="text-end">Stock</th>
                                <th class="text-end">Mínimo</th><th>Estado</th><th class="text-end">Valor. costo</th>
                                <th class="text-end">Valor. venta</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stock as $s): ?>
                            <?php $est = estadoStock($s); ?>
                            <tr>
                                <td><strong><?= limpiar($s['codigo']) ?></strong></td>
                                <td><?= limpiar($s['nombre']) ?></td>
                                <td class="small text-muted"><?= limpiar($s['categoria']) ?></td>
                                <td class="text-end"><strong><?= formatoCantidad((float) $s['stock']) ?></strong></td>
                                <td class="text-end"><?= formatoCantidad((float) $s['stock_minimo']) ?></td>
                                <td>
                                    <?= $est==='ok' ? badgeEstado('ok') : ($est==='bajo' ? badgeEstado('bajo') : ($est==='agotado' ? badgeEstado('agotado') : badgeEstado('servicio'))) ?>
                                </td>
                                <td class="text-end"><?= number_format((float) $s['stock'] * (float) $s['precio_compra'], 2) ?></td>
                                <td class="text-end"><?= number_format((float) $s['stock'] * (float) $s['precio_venta'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$stock): ?>
                            <tr><td colspan="8" class="text-center text-muted py-3">Sin productos con control de stock.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-right-left me-1"></i>Últimos movimientos</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Tipo</th><th>Producto</th><th class="text-end">Cantidad</th>
                        <th>Motivo</th><th>Referencia</th><th>Usuario</th></tr>
                </thead>
                <tbody>
                <?php foreach ($ultimos as $m): ?>
                    <tr>
                        <td><?= formatoFecha($m['fecha']) ?></td>
                        <td><?= badgeEstado($m['tipo']) ?></td>
                        <td><?= limpiar($m['codigo']) ?> — <?= limpiar($m['nombre']) ?></td>
                        <td class="text-end">
                            <span class="fw-semibold text-<?= (float) $m['cantidad'] < 0 ? 'danger' : 'success' ?>">
                                <?= ((float) $m['cantidad'] < 0 ? '' : '+') . formatoCantidad((float) $m['cantidad']) ?>
                            </span>
                        </td>
                        <td class="small"><?= limpiar($m['motivo']) ?></td>
                        <td class="small"><?= limpiar($m['referencia']) ?></td>
                        <td class="small text-muted"><?= limpiar($m['usuario']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ultimos): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Sin movimientos registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer small text-muted">
        <i class="fa-solid fa-circle-info me-1"></i>Las ventas y compras generan movimientos automáticos; aquí puedes registrar entradas, salidas y ajustes manuales.
    </div>
</div>

<div class="modal fade" id="modalMov" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="registrar_movimiento">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title">Registrar movimiento de stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Producto <span class="text-danger">*</span></label>
                        <select name="producto_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($productosStock as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= limpiar($p['codigo']) ?> — <?= limpiar($p['nombre']) ?> (stock: <?= formatoCantidad((float) $p['stock']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo" class="form-select" id="movTipo" required onchange="sugerirSigno()">
                            <option value="entrada">Entrada (+)</option>
                            <option value="salida">Salida (−)</option>
                            <option value="ajuste">Ajuste (±)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" name="cantidad" id="movCant" class="form-control" required step="any" min="0.001" value="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Motivo <span class="text-danger">*</span></label>
                        <input type="text" name="motivo" class="form-control" required placeholder="Ej. Devolución de cliente, sobrante de inventario…">
                    </div>
                </div>
                <div class="alert alert-light border small mt-3 mb-0" id="movAyuda">
                    En <strong>entradas</strong> y <strong>salidas</strong> la cantidad se aplica con su signo; en <strong>ajuste</strong> usa el signo que escribas (−5 reduce).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Registrar</button>
            </div>
        </form>
    </div>
</div>

<script>
function sugerirSigno(){
    const t = document.getElementById('movTipo').value;
    const c = document.getElementById('movCant');
    if (t === 'salida' && parseFloat(c.value) > 0) c.value = '';
    if (t === 'entrada' && parseFloat(c.value) < 0) c.value = Math.abs(c.value);
    if (t === 'ajuste') { c.value = ''; c.min = c.min.toString(); }
}
</script>