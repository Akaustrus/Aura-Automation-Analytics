<?php
[$desde, $hasta] = rangoFechas();
$saldo       = (float) $db->query('SELECT COALESCE(SUM(IF(tipo="ingreso",monto,0)),0) - COALESCE(SUM(IF(tipo="egreso",monto,0)),0) FROM caja')->fetchColumn();
$ingresoMes  = (float) $db->query("SELECT COALESCE(SUM(IF(tipo='ingreso',monto,0)),0) FROM caja WHERE fecha BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-d') . "'")->fetchColumn();
$egresoMes   = (float) $db->query("SELECT COALESCE(SUM(IF(tipo='egreso',monto,0)),0) FROM caja WHERE fecha BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-d') . "'")->fetchColumn();

$query = 'SELECT * FROM caja WHERE fecha BETWEEN :d AND :h';
$params = [':d' => $desde, ':h' => $hasta];
if (!empty($_GET['tipo_f'])) { $query .= ' AND tipo = :t'; $params[':t'] = $_GET['tipo_f']; }
$stmt = $db->prepare($query . ' ORDER BY fecha DESC, id DESC');
$stmt->execute($params);
$movs = $stmt->fetchAll();

$tIn = array_sum(array_map(fn($m) => $m['tipo']==='ingreso' ? (float) $m['monto'] : 0, $movs));
$tEg = array_sum(array_map(fn($m) => $m['tipo']==='egreso' ? (float) $m['monto'] : 0, $movs));
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-coins me-2"></i>Caja</h2>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
            <div class="stat-num"><?= formatoMoneda($saldo) ?></div>
            <div class="stat-label">Saldo actual</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
            <div class="stat-num"><?= formatoMoneda($ingresoMes) ?></div>
            <div class="stat-label">Ingresos del mes</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-arrow-trend-down"></i></div>
            <div class="stat-num"><?= formatoMoneda($egresoMes) ?></div>
            <div class="stat-label">Egresos del mes</div>
        </div>
    </div>
</div>

<div class="card p-3 mb-4">
    <form method="POST" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="guardar_caja">
        <?= campoCsrf() ?>
        <div class="col-md-2">
            <label class="form-label small">Fecha</label>
            <input type="date" name="fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Tipo</label>
            <select name="tipo" class="form-select form-select-sm" id="cajaTipo">
                <option value="ingreso" class="text-success">Ingreso (+)</option>
                <option value="egreso" class="text-danger">Egreso (−)</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Concepto <span class="text-danger">*</span></label>
            <input type="text" name="concepto" class="form-control form-control-sm" required placeholder="Ej. Venta mostrador, pago a proveedor…">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Medio</label>
            <select name="medio" class="form-select form-select-sm">
                <?php foreach (mediosPago() as $m): ?>
                    <option value="<?= $m ?>" <?= $m==='efectivo' ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Monto <span class="text-danger">*</span></label>
            <input type="number" name="monto" class="form-control form-control-sm" required min="0.01" step="0.01">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Referencia</label>
            <input type="text" name="referencia" class="form-control form-control-sm" placeholder="Ej. V-0004">
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-plus me-1"></i>Registrar movimiento</button>
        </div>
    </form>
</div>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="caja">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Tipo</label>
            <select name="tipo_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="ingreso" <?= ($_GET['tipo_f'] ?? '')==='ingreso' ? 'selected' : '' ?>>Ingresos</option>
                <option value="egreso" <?= ($_GET['tipo_f'] ?? '')==='egreso' ? 'selected' : '' ?>>Egresos</option>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
        </div>
    </div>
</form>

<div class="alert alert-light border small py-2 d-flex justify-content-between">
    <span>Movimientos del período <strong><?= formatoFecha($desde) ?> → <?= formatoFecha($hasta) ?></strong>:</span>
    <span>Ingresos <strong class="text-success">+<?= number_format($tIn, 2) ?></strong> ·
          Egresos <strong class="text-danger">−<?= number_format($tEg, 2) ?></strong> ·
          Neto <strong><?= number_format($tIn - $tEg, 2) ?></strong></span>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Tipo</th><th>Medio</th><th>Concepto</th>
                        <th>Referencia</th><th class="text-end">Monto</th><th class="text-end" style="width:70px">Acción</th></tr>
                </thead>
                <tbody>
                <?php foreach ($movs as $m): ?>
                    <tr>
                        <td><?= formatoFecha($m['fecha']) ?></td>
                        <td><?= badgeEstado($m['tipo']) ?></td>
                        <td class="small"><?= ucfirst($m['medio']) ?></td>
                        <td><?= limpiar($m['concepto']) ?></td>
                        <td class="small text-muted"><?= limpiar($m['referencia']) ?></td>
                        <td class="text-end">
                            <strong class="text-<?= $m['tipo']==='ingreso' ? 'success' : 'danger' ?>">
                                <?= ($m['tipo']==='ingreso' ? '+' : '−') . number_format($m['monto'], 2) ?>
                            </strong>
                        </td>
                        <td class="text-end">
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este movimiento?')">
                                <input type="hidden" name="action" value="eliminar_caja">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$movs): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Sin movimientos en el período.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>