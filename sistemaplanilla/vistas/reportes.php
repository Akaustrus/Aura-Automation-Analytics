<?php
$periodo = validarPeriodo((string) ($_GET['periodo'] ?? periodoActual())) ?: periodoActual();
$tipoP   = in_array($_GET['tipo'] ?? '', ['mensual','gratificacion','cts'], true) ? $_GET['tipo'] : 'mensual';
$nMes    = (int) ($_GET['nmes'] ?? 6);
$nMes    = max(3, min(24, $nMes));

/* ---- Resumen por área ---- */
$porArea = [];
foreach ($db->query("SELECT e.area, COUNT(*) n, ROUND(SUM(d.sueldo_bruto),2) bruto, ROUND(SUM(d.descuento+d.renta+d.adelantos),2) descuento, ROUND(SUM(d.neto),2) neto
                    FROM detalle_planilla d JOIN empleados e ON e.id=d.empleado_id JOIN planillas p ON p.id=d.planilla_id
                    WHERE p.periodo='$periodo' AND p.tipo_planilla='$tipoP'
                    GROUP BY e.area ORDER BY neto DESC") as $r) $porArea[] = $r;

/* ---- T-Registro (vista previa) ---- */
$treg = [];
foreach ($db->query('SELECT * FROM empleados WHERE activo=1 ORDER BY apellidos') as $e) {
    $treg[] = $e;
}

/* ---- Tendencia ---- */
$tend = $db->query("SELECT periodo, SUM(total_bruto) bruto, SUM(total_neto) neto
                    FROM planillas WHERE tipo_planilla='mensual' AND estado='emitida'
                    GROUP BY periodo ORDER BY periodo DESC LIMIT $nMes")->fetchAll();
$tend = array_reverse($tend);
$tPorArea = [];
$totEmp = (float) $db->query('SELECT COALESCE(SUM(total_neto),0) FROM planillas WHERE tipo_planilla=\'mensual\' AND estado=\'emitida\'')->fetchColumn();
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-chart-line me-2"></i>Reportes</h2>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <input type="hidden" name="vista" value="reportes">
            <div class="col-md-2">
                <label class="form-label">Período</label>
                <input type="month" name="periodo" class="form-control" value="<?= $periodo ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <?php foreach (['mensual'=>'Mensual','gratificacion'=>'Gratificación','cts'=>'CTS'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $tipoP === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Meses (tendencia)</label>
                <input type="number" name="nmes" min="3" max="24" class="form-control" value="<?= $nMes ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="index.php?action=tregistro_txt&periodo=<?= $periodo ?>" class="btn btn-outline-dark"><i class="fa-solid fa-file-lines me-1"></i>T-Registro (archivo plano)</a>
                <?php if ($porArea): ?>
                    <div class="btn-group btn-group-sm mt-1">
                        <a href="?action=exportar&tipo=resumen_area&periodo=<?= $periodo ?>&tipo_planilla=<?= $tipoP ?>&formato=xlsx" class="btn btn-outline-primary">Excel</a>
                        <a href="?action=exportar&tipo=resumen_area&periodo=<?= $periodo ?>&tipo_planilla=<?= $tipoP ?>&formato=pdf" class="btn btn-outline-danger">PDF</a>
                        <a href="?action=exportar&tipo=resumen_area&periodo=<?= $periodo ?>&tipo_planilla=<?= $tipoP ?>&formato=csv" class="btn btn-outline-secondary">CSV</a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Resumen por área -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-building me-1"></i>Resumen por área — <?= nombreTipoPlanilla($tipoP) ?> <?= nombrePeriodo($periodo) ?></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Área</th><th class="text-end">N. emp.</th><th class="text-end">Bruto</th><th class="text-end">Descuento</th><th class="text-end">Neto</th></tr>
                        </thead>
                        <tbody>
                        <?php $tA=['n'=>0,'b'=>0,'d'=>0,'net'=>0]; foreach ($porArea as $r):
                            $tA['n'] += (int)$r['n']; $tA['b'] += (float)$r['bruto']; $tA['d'] += (float)$r['descuento']; $tA['net'] += (float)$r['neto']; ?>
                            <tr>
                                <td><?= limpiar($r['area']) ?: '—' ?></td>
                                <td class="text-end"><?= $r['n'] ?></td>
                                <td class="text-end"><?= number_format($r['bruto'],2) ?></td>
                                <td class="text-end"><?= number_format($r['descuento'],2) ?></td>
                                <td class="text-end"><strong><?= number_format($r['neto'],2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($tA['n'] > 0): ?>
                            <tr class="table-light fw-bold">
                                <td>TOTAL</td><td class="text-end"><?= $tA['n'] ?></td>
                                <td class="text-end"><?= number_format($tA['b'],2) ?></td>
                                <td class="text-end"><?= number_format($tA['d'],2) ?></td>
                                <td class="text-end"><?= number_format($tA['net'],2) ?></td>
                            </tr>
                        <?php elseif (!$porArea): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No hay planilla emitida para este período y tipo.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- T-Registro -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-file-lines me-1"></i>T-Registro / PLAME <?= nombrePeriodo($periodo) ?></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>#</th><th>DNI</th><th>Apellidos · Nombres</th><th>Contrato</th><th class="text-end">Sueldo base</th><th>Régimen</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($treg as $i => $e): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= limpiar($e['dni']) ?></td>
                                <td><?= limpiar($e['apellidos']) ?>, <?= limpiar($e['nombres']) ?></td>
                                <td class="small"><?= str_replace('_', ' ', $e['tipo_contrato']) ?></td>
                                <td class="text-end"><?= number_format($e['sueldo_base'],2) ?></td>
                                <td><span class="badge bg-info text-dark"><?= strtoupper($e['regimen']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Formato de archivo plano compatible con la planilla electrónica (SOL 2012). Bloque 01 (trabajador) y 02 (remuneración).
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tendencia -->
<div class="card mt-4">
    <div class="card-header"><i class="fa-solid fa-arrow-trend-up me-1"></i>Tendencia mensual de la planilla</div>
    <div class="card-body">
        <div style="height:280px; position:relative;">
            <canvas id="tendenciaChart"></canvas>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Período</th><th class="text-end">Total bruto</th><th class="text-end">Total neto</th><th class="text-end">% neto/bruto</th></tr>
                </thead>
                <tbody>
                <?php foreach ($tend as $t): ?>
                    <tr>
                        <td><?= nombrePeriodo($t['periodo']) ?></td>
                        <td class="text-end"><?= number_format($t['bruto'],2) ?></td>
                        <td class="text-end"><strong><?= number_format($t['neto'],2) ?></strong></td>
                        <td class="text-end"><?= $t['bruto'] > 0 ? number_format($t['neto'] / $t['bruto'] * 100, 1) . ' %' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tend): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">Sin planillas mensuales emitidas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var ctx = document.getElementById('tendenciaChart');
    <?php if (!$tend): ?>
        ctx.parentElement.innerHTML = '<p class="text-muted text-center py-4">No hay datos suficientes.</p>';
        return;
    <?php endif; ?>
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($t) => nombrePeriodo($t['periodo']), $tend)) ?>,
            datasets: [
                { label: 'Bruto', data: <?= json_encode(array_map(fn($t) => (float)$t['bruto'], $tend)) ?>,
                  borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,.12)', tension: .3, fill: true },
                { label: 'Neto', data: <?= json_encode(array_map(fn($t) => (float)$t['neto'], $tend)) ?>,
                  borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.10)', tension: .3, fill: true }
            ]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
                   scales: { y: { beginAtZero: true, ticks: { callback: v => 'S/ ' + Number(v).toLocaleString('es-PE') } } } }
    });
});
</script>