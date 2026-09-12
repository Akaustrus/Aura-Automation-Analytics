<?php
[$desde, $hasta] = rangoFechas();
$query = 'SELECT * FROM caja WHERE fecha BETWEEN :d AND :h';
$params = [':d' => $desde, ':h' => $hasta];
if (!empty($_GET['tipo_f'])) { $query .= ' AND tipo_oper = :t'; $params[':t'] = $_GET['tipo_f']; }
$stmt = $db->prepare($query . ' ORDER BY fecha, id');
$stmt->execute($params);
$movs = $stmt->fetchAll();

$queryBar = 'SELECT tipo_oper, COALESCE(SUM(monto),0) FROM caja WHERE fecha BETWEEN :d AND :h GROUP BY tipo_oper';
$paramsB = [':d' => $desde, ':h' => $hasta];
$stmtB = $db->prepare($queryBar);
$stmtB->execute($paramsB);
$totals = array_column($stmtB->fetchAll(), 1, 0);
$ingresos = (float) ($totals['ingreso'] ?? 0);
$egresos = (float) ($totals['egreso'] ?? 0);

$querySaldo = 'SELECT COALESCE(SUM(CASE WHEN tipo_oper="ingreso" THEN monto ELSE -monto END),0) FROM caja WHERE fecha <= :h';
$stmtS = $db->prepare($querySaldo);
$stmtS->execute([':h' => $hasta]);
$saldoPeriodo = (float) $stmtS->fetchColumn();

$tiposOperacion = ['ingreso', 'egreso'];
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-money-check-dollar me-2"></i>Caja
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalCaja"><i class="fa-solid fa-plus me-1"></i>Registrar operación</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="caja">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>"></div>
        <div class="col-md-3"><label class="form-label small">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>"></div>
        <div class="col-md-3"><label class="form-label small">Tipo</label>
            <select name="tipo_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($tiposOperacion as $t): ?>
                    <option value="<?= $t ?>" <?= ($_GET['tipo_f'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i></button></div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card card-success"><div class="card-body">
            <div class="stat-label">Ingresos (periodo)</div>
            <div class="stat-value"><?= number_format($ingresos, 2) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card card-danger"><div class="card-body">
            <div class="stat-label">Egresos (periodo)</div>
            <div class="stat-value"><?= number_format($egresos, 2) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card card-primary"><div class="card-body">
            <div class="stat-label">Neto (periodo)</div>
            <div class="stat-value"><?= number_format($ingresos - $egresos, 2) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card card-info"><div class="card-body">
            <div class="stat-label">Saldo acumulado al <?= formatoFecha($hasta) ?></div>
            <div class="stat-value"><?= number_format($saldoPeriodo, 2) ?></div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th class="text-end">Monto</th><th>Usuario</th><th class="text-end" style="width:70px"></th></tr>
                </thead>
                <tbody>
                <?php foreach ($movs as $m): ?>
                    <tr>
                        <td><?= formatoFecha($m['fecha']) ?></td>
                        <td><?= badgeEstado($m['tipo_oper']) ?></td>
                        <td><?= limpiar($m['concepto']) ?></td>
                        <td class="text-end"><strong class="<?= $m['tipo_oper'] === 'ingreso' ? 'text-success' : 'text-danger' ?>">
                            <?= $m['tipo_oper'] === 'ingreso' ? '+' : '-' ?><?= number_format($m['monto'], 2) ?></strong></td>
                        <td class="small"><?= limpiar($m['usuario']) ?></td>
                        <td class="text-end">
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta operación de caja?')">
                                <input type="hidden" name="action" value="eliminar_caja">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$movs): ?><tr><td colspan="6" class="text-center text-muted py-3">Sin movimientos en el periodo.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCaja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_caja">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title">Registrar operación de caja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-6"><label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <?php foreach ($tiposOperacion as $t): ?>
                                <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-12"><label class="form-label">Concepto <span class="text-danger">*</span></label>
                        <input type="text" name="concepto" class="form-control" maxlength="200" required></div>
                    <div class="col-6"><label class="form-label">Medio</label>
                        <select name="medio" class="form-select">
                            <?php foreach (mediosPago() as $m): ?><option value="<?= $m ?>"><?= ucfirst($m) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><label class="form-label">Monto <span class="text-danger">*</span></label>
                        <input type="number" name="monto" class="form-control" step="0.01" min="0.01" required></div>
                    <div class="col-12"><label class="form-label">Referencia</label>
                        <input type="text" name="referencia" class="form-control" maxlength="80"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registrar</button>
            </div>
        </form>
    </div>
</div>