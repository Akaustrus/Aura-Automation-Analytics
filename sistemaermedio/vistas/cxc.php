<?php
$cxc = $db->query('SELECT v.*, cl.razon_social FROM ventas v JOIN clientes cl ON cl.id=v.cliente_id
                   WHERE v.condicion="credito" AND v.estado="emitida" ORDER BY v.fecha, v.numero')->fetchAll();

$filas = [];
$totalCxc = 0.0;
foreach ($cxc as $v) {
    $pag = $db->prepare('SELECT COALESCE(SUM(monto),0) FROM venta_pagos WHERE venta_id=?'); $pag->execute([$v['id']]);
    $saldo = max(0, (float) $v['total'] - (float) $pag->fetchColumn());
    $v['saldo'] = $saldo;
    $v['dias'] = max(0, (int) ((time() - strtotime($v['fecha'])) / 86400));
    $pagos = $db->prepare('SELECT * FROM venta_pagos WHERE venta_id=? ORDER BY fecha'); $pagos->execute([$v['id']]);
    $v['pagos'] = $pagos->fetchAll();
    $v['total_pagado'] = (float) $v['total'] - $saldo;
    $totalCxc += $saldo;
    $filas[] = $v;
}

function antiguedad(int $dias): string
{
    if ($dias <= 30) return '0-30 días';
    if ($dias <= 60) return '31-60 días';
    if ($dias <= 90) return '61-90 días';
    return '+90 días';
}
$porRango = [];
foreach ($filas as $f) {
    if ($f['saldo'] > 0) $porRango[antiguedad($f['dias'])] = ($porRango[antiguedad($f['dias'])] ?? 0) + $f['saldo'];
}
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Cuentas por cobrar</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card card-danger"><div class="card-body">
            <div class="stat-label">Saldo total CxC</div>
            <div class="stat-value"><?= number_format($totalCxc, 2) ?></div>
        </div></div>
    </div>
    <?php foreach (['0-30 días', '31-60 días', '61-90 días', '+90 días'] as $rango): ?>
        <div class="col-md-2 col-6">
            <div class="card stat-card card-<?= match ($rango) { '0-30 días' => 'success', '31-60 días' => 'warning', '61-90 días' => 'danger', default => 'secondary' } ?>"><div class="card-body">
                <div class="stat-label"><?= $rango ?></div>
                <div class="stat-value"><?= number_format($porRango[$rango] ?? 0, 2) ?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!$filas): ?>
    <div class="alert alert-light border">No hay cuentas por cobrar (ventas a crédito emitidas).</div>
<?php endif; ?>

<?php foreach ($filas as $v): ?>
    <div class="card mb-4 <?= $v['saldo'] <= 0 ? 'border-success' : '' ?>">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <i class="fa-solid fa-receipt me-2"></i><strong><?= numeroVenta((int) $v['numero']) ?></strong>
                <span class="text-muted small ms-2"><?= formatoFecha($v['fecha']) ?> · <?= limpiar($v['razon_social']) ?></span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="small text-muted">Total <?= number_format($v['total'], 2) ?> · Pagado <?= number_format($v['total_pagado'], 2) ?></span>
                <span class="badge bg-<?= $v['saldo'] <= 0 ? 'success' : ($v['dias'] <= 30 ? 'warning' : 'danger') ?>">
                    Saldo: <?= number_format($v['saldo'], 2) ?> (<?= $v['dias'] ?> días)</span>
                <?php if ($v['saldo'] > 0): ?>
                    <button class="btn btn-sm btn-outline-primary" onclick="cobrar(<?= $v['id'] ?>, <?= $v['saldo'] ?>)" id="cobrarBtn-<?= $v['id'] ?>">
                        <i class="fa-solid fa-hand-holding-dollar me-1"></i>Cobrar</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Fecha</th><th>Medio</th><th class="text-end">Monto</th><th>Referencia</th><th class="text-end" style="width:70px"></th></tr></thead>
                <tbody>
                <?php foreach ($v['pagos'] as $p): ?>
                    <tr>
                        <td><?= formatoFecha($p['fecha']) ?></td>
                        <td><?= badgeEstado($p['medio']) ?></td>
                        <td class="text-end"><strong><?= number_format($p['monto'], 2) ?></strong></td>
                        <td class="small"><?= limpiar($p['referencia']) ?></td>
                        <td class="text-end">
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este cobro?')">
                                <input type="hidden" name="action" value="eliminar_cobro">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$v['pagos']): ?><tr><td colspan="5" class="text-center text-muted py-2">Sin cobros registrados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="modalCobro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_cobro">
            <input type="hidden" name="venta_id" id="cb_venta">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title">Registrar cobro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-6"><label class="form-label">Medio</label>
                        <select name="medio" class="form-select">
                            <?php foreach (mediosPago() as $m): ?><option value="<?= $m ?>"><?= ucfirst($m) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-12"><label class="form-label">Monto <span class="text-danger">*</span></label>
                        <input type="number" name="monto" id="cb_monto" class="form-control" step="0.01" min="0.01" required></div>
                    <div class="col-12"><label class="form-label">Referencia</label>
                        <input type="text" name="referencia" class="form-control" placeholder="Ej. Abono parcial"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registrar cobro</button>
            </div>
        </form>
    </div>
</div>

<script>
function cobrar(id, saldo){
    document.getElementById('cb_venta').value = id;
    document.getElementById('cb_monto').value = saldo;
    new bootstrap.Modal(document.getElementById('modalCobro')).show();
}
</script>