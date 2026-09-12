<?php
$almacenes = $db->query('SELECT * FROM almacenes WHERE estado=1 ORDER BY nombre')->fetchAll();
$productos = $db->query('SELECT p.*, COALESCE(s.stock_total,0) stock_total
                         FROM productos p LEFT JOIN (SELECT producto_id, SUM(cantidad) stock_total FROM stock GROUP BY producto_id) s ON s.producto_id=p.id
                         WHERE p.controla_stock=1 ORDER BY p.nombre')->fetchAll();

$matriz = [];
foreach ($db->query('SELECT * FROM stock ORDER BY producto_id, almacen_id') as $s) {
    $matriz[$s['producto_id']][$s['almacen_id']] = (float) $s['cantidad'];
}
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-warehouse me-2"></i>Inventario por almacén
    <button class="btn btn-sm btn-outline-warning float-end me-2" data-bs-toggle="modal" data-bs-target="#modalTraspaso"><i class="fa-solid fa-arrows-left-right me-1"></i>Traspaso</button>
    <button class="btn btn-sm btn-primary float-end me-2" data-bs-toggle="modal" data-bs-target="#modalMovimiento"><i class="fa-solid fa-plus me-1"></i>Movimiento manual</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="inventario">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">Producto</label>
            <select name="producto_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (($_GET['producto_f'] ?? '')===(string) $p['id']) ? 'selected' : '' ?>><?= limpiar($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i></button></div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Código</th><th>Producto</th>
                        <?php foreach ($almacenes as $a): ?><th class="text-end"><?= limpiar($a['nombre']) ?></th><?php endforeach; ?>
                        <th class="text-end">Total</th><th class="text-end">Mínimo</th><th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($productos as $p): ?>
                    <?php if (!empty($_GET['producto_f']) && (int) $_GET['producto_f'] !== (int) $p['id']) continue; ?>
                    <tr>
                        <td><span class="badge bg-light text-dark border"><?= limpiar($p['codigo']) ?></span></td>
                        <td class="text-truncate" style="max-width:200px" title="<?= limpiar($p['nombre']) ?>"><strong><?= limpiar($p['nombre']) ?></strong></td>
                        <?php foreach ($almacenes as $a): ?>
                            <td class="text-end"><?= formatoCantidad($matriz[$p['id']][$a['id']] ?? 0) ?></td>
                        <?php endforeach; ?>
                        <td class="text-end"><strong><?= formatoCantidad((float) $p['stock_total']) ?></strong></td>
                        <td class="text-end"><?= formatoCantidad((float) $p['stock_minimo']) ?></td>
                        <td><?= badgeEstado(estadoProducto($p)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$productos): ?><tr><td colspan="<?= count($almacenes) + 6 ?>" class="text-center text-muted py-3">Sin productos que controlen stock.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal movimiento manual -->
<div class="modal fade" id="modalMovimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="registrar_movimiento">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title">Movimiento manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="entrada">Entrada</option>
                            <option value="salida">Salida</option>
                            <option value="ajuste">Ajuste</option>
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Producto <span class="text-danger">*</span></label>
                        <select name="producto_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= limpiar($p['codigo']) ?> — <?= limpiar($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Almacén <span class="text-danger">*</span></label>
                        <select name="almacen_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($almacenes as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= limpiar($a['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" name="cantidad" class="form-control" step="0.001" min="0.001" required></div>
                    <div class="col-md-6"><label class="form-label">Motivo <span class="text-danger">*</span></label>
                        <input type="text" name="motivo" class="form-control" required placeholder="Ej. Devolución de cliente"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal traspaso -->
<div class="modal fade" id="modalTraspaso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="traspaso">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title">Traspaso entre almacenes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-12"><label class="form-label">Producto <span class="text-danger">*</span></label>
                        <select name="producto_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= limpiar($p['codigo']) ?> — <?= limpiar($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><label class="form-label">Almacén origen <span class="text-danger">*</span></label>
                        <select name="almacen_origen" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($almacenes as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= limpiar($a['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><label class="form-label">Almacén destino <span class="text-danger">*</span></label>
                        <select name="almacen_destino" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($almacenes as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= limpiar($a['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><label class="form-label">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" name="cantidad" class="form-control" step="0.001" min="0.001" required></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Traspasar</button>
            </div>
        </form>
    </div>
</div>