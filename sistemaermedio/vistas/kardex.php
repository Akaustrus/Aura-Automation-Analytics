<?php
[$desde, $hasta] = rangoFechas();
$productos = $db->query('SELECT p.id, p.codigo, p.nombre FROM productos p WHERE p.controla_stock=1 ORDER BY p.nombre')->fetchAll();
$almacenes = $db->query('SELECT * FROM almacenes ORDER BY nombre')->fetchAll();

$sql = 'SELECT m.*, p.codigo, p.nombre, a.nombre almacen FROM movimientos m
        JOIN productos p ON p.id=m.producto_id JOIN almacenes a ON a.id=m.almacen_id
        WHERE m.fecha BETWEEN :d AND :h';
$params = [':d' => $desde, ':h' => $hasta];
if (!empty($_GET['producto_f'])) { $sql .= ' AND m.producto_id = :p'; $params[':p'] = (int) $_GET['producto_f']; }
if (!empty($_GET['almacen_f']))  { $sql .= ' AND m.almacen_id = :a';  $params[':a'] = (int) $_GET['almacen_f']; }
if (!empty($_GET['tipo_f'])) {
    $tipos = ['entrada', 'salida', 'ajuste', 'traslado'];
    if (in_array($_GET['tipo_f'], $tipos, true)) { $sql .= ' AND m.tipo = :t'; $params[':t'] = $_GET['tipo_f']; }
}
$stmt = $db->prepare($sql . ' ORDER BY m.fecha DESC, m.id DESC LIMIT 500');
$stmt->execute($params);
$movs = $stmt->fetchAll();
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-list-ol me-2"></i>Kardex de inventario</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="kardex">
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label small">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>"></div>
        <div class="col-md-2"><label class="form-label small">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>"></div>
        <div class="col-md-3"><label class="form-label small">Producto</label>
            <select name="producto_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (($_GET['producto_f'] ?? '')===(string) $p['id']) ? 'selected' : '' ?>><?= limpiar($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-2"><label class="form-label small">Almacén</label>
            <select name="almacen_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($almacenes as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= (($_GET['almacen_f'] ?? '')===(string) $a['id']) ? 'selected' : '' ?>><?= limpiar($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-2"><label class="form-label small">Tipo</label>
            <select name="tipo_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach (['entrada', 'salida', 'ajuste', 'traslado'] as $t): ?>
                    <option value="<?= $t ?>" <?= (($_GET['tipo_f'] ?? '')===$t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-1 d-grid"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i></button></div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Tipo</th><th>Almacén</th><th>Producto</th>
                        <th class="text-end">Cantidad</th><th>Motivo</th><th>Referencia</th><th>Usuario</th></tr>
                </thead>
                <tbody>
                <?php foreach ($movs as $m): ?>
                    <tr>
                        <td><?= formatoFecha($m['fecha']) ?></td>
                        <td><?= badgeEstado($m['tipo']) ?></td>
                        <td class="small"><?= limpiar($m['almacen']) ?></td>
                        <td><strong><?= limpiar($m['nombre']) ?></strong> <span class="badge bg-light text-dark border"><?= limpiar($m['codigo']) ?></span></td>
                        <td class="text-end <?= (float) $m['cantidad'] < 0 ? 'text-danger' : 'text-success' ?>">
                            <strong><?= ((float) $m['cantidad'] < 0 ? '' : '+') . formatoCantidad((float) $m['cantidad']) ?></strong></td>
                        <td class="small"><?= limpiar($m['motivo']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= limpiar($m['referencia']) ?></span></td>
                        <td class="small text-muted"><?= limpiar($m['usuario']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$movs): ?><tr><td colspan="8" class="text-center text-muted py-3">Sin movimientos en el período.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>