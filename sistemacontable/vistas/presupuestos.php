<?php
$anio = (int) ($_GET['anio'] ?? date('Y'));
$resumen = $db->prepare(
  "SELECT p.id AS pres_id, c.id, c.codigo, c.nombre, c.tipo, p.monto AS presupuesto,
     COALESCE(SUM(CASE WHEN c.tipo='ingreso' THEN ad.haber-ad.debe ELSE ad.debe-ad.haber END),0) AS real_contable
   FROM presupuestos p
   JOIN cuentas c ON c.id = p.cuenta_id
   LEFT JOIN asiento_detalle ad ON ad.cuenta_id = c.id
   LEFT JOIN asientos a ON a.id = ad.asiento_id AND YEAR(a.fecha) = :anio
   WHERE p.anio = :anio
   GROUP BY c.id, p.monto
   ORDER BY c.codigo");
$resumen->execute([':anio'=>$anio]);
$rows = $resumen->fetchAll();

$cuentas = $db->query("SELECT id,codigo,nombre,tipo FROM cuentas WHERE tipo IN ('ingreso','egreso') ORDER BY codigo")->fetchAll();

$meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$mensual = $db->prepare("SELECT MONTH(a.fecha) mes, c.tipo, COALESCE(SUM(ad.debe),0) debe, COALESCE(SUM(ad.haber),0) haber
   FROM asiento_detalle ad JOIN asientos a ON a.id=ad.asiento_id JOIN cuentas c ON c.id=ad.cuenta_id
   WHERE YEAR(a.fecha)=:anio AND c.tipo IN ('ingreso','egreso')
   GROUP BY mes, c.tipo ORDER BY mes");
$mensual->execute([':anio'=>$anio]);
$mensual = $mensual->fetchAll();

$porMes = ['ingreso'=>array_fill(1,12,0), 'egreso'=>array_fill(1,12,0)];
foreach ($mensual as $m) {
    $val = $m['tipo']==='ingreso' ? $m['haber']-$m['debe'] : $m['debe']-$m['haber'];
    $porMes[$m['tipo']][(int)$m['mes']] = (float)$val;
}
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-chart-column me-2"></i>Presupuestos vs. Real</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="presupuestos">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Año</label>
            <select name="anio" class="form-select form-select-sm">
                <?php for ($y=date('Y')-1; $y<=date('Y')+1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y===$anio?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i>Ver</button>
        </div>
    </div>
</form>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-gears me-1"></i>Definir presupuesto (<?= $anio ?>)</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalPres"><i class="fa-solid fa-plus me-1"></i>Agregar</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Código</th><th>Cuenta</th><th>Tipo</th>
                        <th class="text-end">Presupuesto anual</th><th class="text-end">Real contable</th>
                        <th class="text-end">% ejecución</th><th>Estado</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $real = (float)$r['real_contable'];
                    $pres = (float)$r['presupuesto'];
                    $pct = $pres>0 ? round($real/$pres*100) : 0;
                    $cls = $pct > 100 ? 'danger' : ($pct > 80 ? 'warning' : 'success');
                ?>
                    <tr>
                        <td><?= $r['codigo'] ?></td>
                        <td><?= limpiar($r['nombre']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= ucfirst($r['tipo']) ?></span></td>
                        <td class="text-end"><?= number_format($pres,2) ?></td>
                        <td class="text-end"><?= number_format($real,2) ?></td>
                        <td class="text-end">
                            <div class="progress" style="height:16px">
                                <div class="progress-bar <?= $cls==='success'?'bg-success':($cls==='warning'?'bg-warning':'bg-danger') ?>"
                                     style="width:<?= min($pct,100) ?>%"></div>
                            </div>
                            <small><?= $pct ?>%</small>
                        </td>
                        <td><?= badgeEstado($pct>100?'anulado':($pct>0?'registrado':'borrador')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-chart-line me-1"></i>Tendencia mensual <?= $anio ?> (ingresos vs. egresos)</div>
    <div class="card-body">
        <canvas id="graficoPres" height="100"></canvas>
    </div>
</div>

<!-- Modal agregar presupuesto -->
<div class="modal fade" id="modalPres" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_presupuesto">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title">Nuevo presupuesto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Cuenta (ingreso/egreso)</label>
                    <select name="cuenta_id" class="form-select" required>
                        <?php foreach ($cuentas as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['codigo'] ?> — <?= limpiar($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Año</label>
                    <input type="number" name="anio" class="form-control" value="<?= $anio ?>" min="2020" max="2099" required>
                </div>
                <div>
                    <label class="form-label">Monto anual (S/)</label>
                    <input type="number" step="0.01" name="monto" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const meses = <?= json_encode($meses) ?>;
    const ingresos = <?= json_encode(array_values($porMes['ingreso'])) ?>;
    const egresos  = <?= json_encode(array_values($porMes['egreso'])) ?>;
    new Chart(document.getElementById('graficoPres'), {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [
                { label: 'Ingresos', data: ingresos, backgroundColor: '#0d9488' },
                { label: 'Egresos', data: egresos, backgroundColor: '#f59e0b' }
            ]
        },
        options: {
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>