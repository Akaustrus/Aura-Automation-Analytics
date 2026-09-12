<?php
$empActivos = (int) $db->query('SELECT COUNT(*) FROM empleados WHERE activo=1')->fetchColumn();
$empTotal   = (int) $db->query('SELECT COUNT(*) FROM empleados')->fetchColumn();

$periodoActual = periodoActual();
$ultima = $db->query("SELECT * FROM planillas WHERE periodo = '$periodoActual' AND tipo_planilla='mensual' ORDER BY id DESC LIMIT 1")->fetch();
if (!$ultima) $ultima = $db->query("SELECT * FROM planillas WHERE tipo_planilla='mensual' ORDER BY periodo DESC, id DESC LIMIT 1")->fetch();
$montoNomina = (float) ($ultima['total_neto'] ?? 0);
$essaludTotal = (float) ($ultima['total_essalud'] ?? 0);

$nAdelPend = (int) $db->query("SELECT COUNT(*) FROM adelantos WHERE estado='pendiente'")->fetchColumn();
$mAdelPend = (float) $db->query("SELECT COALESCE(SUM(monto),0) FROM adelantos WHERE estado='pendiente'")->fetchColumn();

$resumenes = $db->query("SELECT * FROM planillas WHERE tipo_planilla='mensual' ORDER BY periodo DESC LIMIT 6")->fetchAll();
$auditoria = $db->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 6')->fetchAll();

$tend = $db->query("SELECT periodo, total_bruto, total_neto FROM planillas
                    WHERE tipo_planilla='mensual' AND estado='emitida'
                    GROUP BY periodo ORDER BY periodo DESC LIMIT 6")->fetchAll();
$tend = array_reverse($tend);

$porArea = [];
if ($ultima) {
    foreach ($db->query("SELECT e.area, ROUND(SUM(d.neto),2) neto
                         FROM detalle_planilla d JOIN empleados e ON e.id=d.empleado_id
                         WHERE d.planilla_id=" . (int)$ultima['id'] . " GROUP BY e.area ORDER BY neto DESC") as $r) $porArea[] = $r;
}
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-table-columns me-2"></i>Panel de planillas</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-num"><?= $empActivos ?></div>
            <div class="stat-label">Empleados activos</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-money-check-dollar"></i></div>
            <div class="stat-num"><?= formatoMoneda($montoNomina) ?></div>
            <div class="stat-label">Neto a pagar — <?= $ultima ? nombrePeriodo($ultima['periodo']) : '—' ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-heart-pulse"></i></div>
            <div class="stat-num"><?= formatoMoneda($essaludTotal) ?></div>
            <div class="stat-label">ESSALUD del mes</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-4">
            <div class="stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <div class="stat-num"><?= $nAdelPend ?></div>
            <div class="stat-label">Adelantos por descontar (S/ <?= number_format($mAdelPend,0) ?>)</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-arrow-trend-up me-1"></i>Tendencia mensual (neto a pagar)</div>
            <div class="card-body">
                <div style="height:220px; position:relative;"><canvas id="chartTend"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-chart-pie me-1"></i>Neto por área — <?= $ultima ? nombrePeriodo($ultima['periodo']) : '—' ?></div>
            <div class="card-body">
                <div style="height:220px; position:relative;"><canvas id="chartArea"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-money-check-dollar me-1"></i>Últimas planillas mensuales</span>
                <a href="?vista=planillas" class="btn btn-sm btn-outline-primary">Gestionar</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Período</th><th class="text-end">Bruto</th><th class="text-end">ESSALUD</th><th class="text-end">Descuento</th><th class="text-end">Neto</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($resumenes as $pl): ?>
                            <tr>
                                <td><?= nombrePeriodo($pl['periodo']) ?></td>
                                <td class="text-end"><?= number_format($pl['total_bruto'],2) ?></td>
                                <td class="text-end"><?= number_format($pl['total_essalud'],2) ?></td>
                                <td class="text-end"><?= number_format($pl['total_descuento'],2) ?></td>
                                <td class="text-end"><strong><?= number_format($pl['total_neto'],2) ?></strong></td>
                                <td><?= badgeEstado($pl['estado']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$resumenes): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Sin planillas generadas.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-file-shield me-1"></i>Actividad reciente</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($auditoria as $a): ?>
                        <li class="list-group-item py-2">
                            <div class="d-flex justify-content-between">
                                <span class="small"><?= limpiar($a['resumen']) ?></span>
                                <span class="badge bg-light border text-dark small"><?= date('d/m', strtotime($a['fecha'])) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$auditoria): ?>
                        <li class="list-group-item text-muted small py-3">Sin actividad registrada.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="card-footer small"><i class="fa-solid fa-circle-info me-1"></i><?= $empTotal ?> empleados registrados · Esquema profesional con gratificaciones, CTS, T-Registro y portal del empleado.</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var tend = <?= json_encode($tend) ?>;
    var porArea = <?= json_encode($porArea) ?>;
    var etiquetas = <?= json_encode(array_map('nombrePeriodo', array_column($tend, 'periodo'))) ?>;

    var ctxT = document.getElementById('chartTend');
    if (tend.length) {
        new Chart(ctxT, {
            type: 'line',
            data: {
                labels: etiquetas,
                datasets: [{
                    label: 'Bruto', data: tend.map(function(t){ return +t.total_bruto; }),
                    borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .3
                },{
                    label: 'Neto', data: tend.map(function(t){ return +t.total_neto; }),
                    borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,.14)', fill: true, tension: .3
                }]
            },
            options: { maintainAspectRatio: false,
                       plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
                       scales: { y: { beginAtZero: true, ticks: { callback: function(v){ return 'S/ ' + Number(v).toLocaleString('es-PE'); } } } } }
        });
    } else {
        ctxT.parentElement.innerHTML = '<p class="text-muted text-center py-4">Sin planillas emitidas.</p>';
    }

    var ctxA = document.getElementById('chartArea');
    if (porArea.length) {
        new Chart(ctxA, {
            type: 'doughnut',
            data: {
                labels: porArea.map(function(r){ return r.area; }),
                datasets: [{ data: porArea.map(function(r){ return +r.neto; }),
                             backgroundColor: ['#0d9488','#2563eb','#f59e0b','#dc2626','#7c3aed','#0891b2', '#14b8a6'] }]
            },
            options: { maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
    } else {
        ctxA.parentElement.innerHTML = '<p class="text-muted text-center py-4">Sin datos.</p>';
    }
});
</script>