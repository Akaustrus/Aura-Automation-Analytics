<?php
$iniMes = date('Y-m-01');
$finMes = date('Y-m-d');

$nDocMes  = (int) $db->query("SELECT COUNT(*) FROM comprobantes WHERE estado='emitido' AND fecha_emision BETWEEN '$iniMes' AND '$finMes'")->fetchColumn();
$totMes   = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM comprobantes WHERE estado='emitido' AND fecha_emision BETWEEN '$iniMes' AND '$finMes'")->fetchColumn();
$igvMes   = (float) $db->query("SELECT COALESCE(SUM(igv),0) FROM comprobantes WHERE estado='emitido' AND fecha_emision BETWEEN '$iniMes' AND '$finMes'")->fetchColumn();
$totAnio  = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM comprobantes WHERE estado='emitido' AND fecha_emision BETWEEN '" . date('Y-01-01') . "' AND '$finMes'")->fetchColumn();

$nClientes = (int) $db->query('SELECT COUNT(*) FROM clientes WHERE activo=1')->fetchColumn();
$nProductos = (int) $db->query('SELECT COUNT(*) FROM productos WHERE activo=1')->fetchColumn();

$recientes = $db->query('SELECT c.*, cl.razon_social FROM comprobantes c JOIN clientes cl ON cl.id=c.cliente_id ORDER BY c.fecha_emision DESC, c.id DESC LIMIT 8')->fetchAll();
$auditoria = $db->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 6')->fetchAll();

$tendencia = $db->query("SELECT DATE_FORMAT(fecha_emision,'%Y-%m') mes, COALESCE(SUM(total),0) total, COALESCE(SUM(igv),0) igv
                         FROM comprobantes WHERE estado='emitido'
                         GROUP BY mes ORDER BY mes DESC LIMIT 6")->fetchAll();
$tendencia = array_reverse($tendencia);

$porTipo = $db->query("SELECT tipo, COUNT(*) n, COALESCE(SUM(total),0) total FROM comprobantes
                       WHERE estado='emitido' AND fecha_emision BETWEEN '$iniMes' AND '$finMes'
                       GROUP BY tipo")->fetchAll();
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-table-columns me-2"></i>Panel de facturación</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-file-invoice"></i></div>
            <div class="stat-num"><?= $nDocMes ?></div>
            <div class="stat-label">Comprobantes del mes</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="stat-num"><?= formatoMoneda($totMes) ?></div>
            <div class="stat-label">Ventas del mes</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-percent"></i></div>
            <div class="stat-num"><?= formatoMoneda($igvMes) ?></div>
            <div class="stat-label">IGV generado (<?= number_format($totAnio,0) ?> anual)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-4">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-num"><?= $nClientes ?></div>
            <div class="stat-label">Clientes activos (<?= $nProductos ?> productos)</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-arrow-trend-up me-1"></i>Ventas mensuales (total con IGV)</div>
            <div class="card-body">
                <div style="height:220px; position:relative;"><canvas id="chartTend"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-chart-pie me-1"></i>Comprobantes por tipo</div>
            <div class="card-body">
                <div style="height:220px; position:relative;"><canvas id="chartTipo"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-file-invoice me-1"></i>Últimos comprobantes</span>
                <a href="?vista=comprobantes" class="btn btn-sm btn-outline-primary">Gestionar</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Tipo</th><th>Número</th><th>Fecha</th><th>Cliente</th>
                                <th class="text-end">IGV</th><th class="text-end">Total</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recientes as $r): ?>
                            <tr class="<?= $r['estado']==='anulado' ? 'table-warning text-muted' : '' ?>">
                                <td><span class="badge bg-secondary"><?= limpiar($r['tipo']) ?></span></td>
                                <td><?= numeroComprobante($r['serie'], (int) $r['numero']) ?></td>
                                <td><?= formatoFecha($r['fecha_emision']) ?></td>
                                <td class="text-truncate" style="max-width:160px"><?= limpiar($r['razon_social']) ?></td>
                                <td class="text-end"><?= number_format($r['igv'],2) ?></td>
                                <td class="text-end"><strong><?= number_format($r['total'],2) ?></strong></td>
                                <td><?= badgeEstado($r['estado']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recientes): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">Sin comprobantes todavía.</td></tr>
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
            <div class="card-footer small"><i class="fa-solid fa-circle-info me-1"></i>Facturas, boletas y notas con IGV, correlativos automáticos, impresión y PDF.</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var tend = <?= json_encode($tendencia) ?>;
    var etiquetas = tend.map(function(t){ var p=t.mes.split('-'); var mes=['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'][parseInt(p[1])-1]; return mes+' '+p[0]; });

    var ctxT = document.getElementById('chartTend');
    if (tend.length) {
        new Chart(ctxT, {
            type: 'bar',
            data: {
                labels: etiquetas,
                datasets: [{
                    label: 'Total ventas', data: tend.map(function(t){ return +t.total; }),
                    borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,.75)', borderRadius: 5
                },{
                    label: 'IGV', data: tend.map(function(t){ return +t.igv; }),
                    borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.55)', borderRadius: 5
                }]
            },
            options: { maintainAspectRatio: false,
                       plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
                       scales: { y: { beginAtZero: true, ticks: { callback: function(v){ return 'S/ ' + Number(v).toLocaleString('es-PE'); } } } } }
        });
    } else {
        ctxT.parentElement.innerHTML = '<p class="text-muted text-center py-4">Sin ventas registradas.</p>';
    }

    var ctxP = document.getElementById('chartTipo');
    if (<?= count($porTipo) ?>) {
        new Chart(ctxP, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map(fn($t)=>$t['tipo'], $porTipo)) ?>,
                datasets: [{ data: <?= json_encode(array_map(fn($t)=>(int)$t['n'], $porTipo)) ?>,
                             backgroundColor: ['#0d9488','#2563eb','#f59e0b','#dc2626','#7c3aed','#0891b2'] }]
            },
            options: { maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
    } else {
        ctxP.parentElement.innerHTML = '<p class="text-muted text-center py-4">Sin datos.</p>';
    }
});
</script>