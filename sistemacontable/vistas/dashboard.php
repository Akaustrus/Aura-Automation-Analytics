<?php
$docTotal     = $db->query('SELECT COUNT(*) FROM documentos WHERE estado="registrado"')->fetchColumn();
$docBase      = (float) $db->query('SELECT COALESCE(SUM(importe),0) FROM documentos WHERE estado="registrado"')->fetchColumn();
$docIgv       = (float) $db->query('SELECT COALESCE(SUM(igv),0) FROM documentos WHERE estado="registrado"')->fetchColumn();
$docTotalMonto= (float) $db->query('SELECT COALESCE(SUM(total),0) FROM documentos WHERE estado="registrado"')->fetchColumn();
$asientoTotal = (int) $db->query('SELECT COUNT(*) FROM asientos')->fetchColumn();
$cuentaTotal  = (int) $db->query('SELECT COUNT(*) FROM cuentas WHERE activo=1')->fetchColumn();
$ultDocs      = $db->query('SELECT id,tipo,numero,fecha,emisor,concepto,total,estado FROM documentos ORDER BY fecha DESC LIMIT 6')->fetchAll();
$ultAsientos  = $db->query('SELECT id,numero,fecha,glosa,estado FROM asientos ORDER BY numero DESC LIMIT 6')->fetchAll();

// Datos para gráficas
$porTipo = $db->query('SELECT tipo, COUNT(*) n FROM documentos WHERE estado="registrado" GROUP BY tipo')->fetchAll();
$porMes  = $db->query("SELECT MONTH(fecha) mes, tipo, SUM(total) monto FROM documentos WHERE estado='registrado' AND YEAR(fecha)=YEAR(CURDATE()) GROUP BY mes, tipo ORDER BY mes")->fetchAll();

$meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$tiposDoc = ['BOLETA','FACTURA','NOTA_CREDITO','NOTA_DEBITO','GUIA_REMISION','TICKET','OTRO'];
$conteoTipo = array_fill_keys($tiposDoc, 0);
foreach ($porTipo as $t) { $conteoTipo[$t['tipo']] = (int)$t['n']; }
$montoMes = array_fill(1,12,0);
foreach ($porMes as $m) { $montoMes[(int)$m['mes']] += (float)$m['monto']; }
?>

<section class="page-heading module-page-heading mb-4">
    <div>
        <span class="page-eyebrow">Resumen financiero</span>
        <h1 class="page-title mb-1">Panel contable</h1>
        <p class="page-subtitle mb-0">Documentos, impuestos y movimientos contables en un solo lugar.</p>
    </div>
    <a href="index.php?vista=documentos" class="btn btn-primary"><i class="fa-solid fa-file-circle-plus me-1"></i>Gestionar documentos</a>
</section>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-file-invoice"></i></div>
            <div class="stat-num"><?= $docTotal ?></div>
            <div class="stat-label">Documentos registrados</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-coins"></i></div>
            <div class="stat-num"><?= formatoMoneda($docBase) ?></div>
            <div class="stat-label">Base imponible total</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-percent"></i></div>
            <div class="stat-num"><?= formatoMoneda($docIgv) ?></div>
            <div class="stat-label">IGV total (18%)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-4">
            <div class="stat-icon"><i class="fa-solid fa-book"></i></div>
            <div class="stat-num"><?= $asientoTotal ?></div>
            <div class="stat-label">Asientos contables</div>
        </div>
    </div>
</div>

<!-- Gráficas -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Documentos por tipo</div>
            <div class="card-body"><canvas id="graficoTipos" style="max-height:180px"></canvas></div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Ingresos por mes (<?= date('Y') ?>)</div>
            <div class="card-body"><canvas id="graficoMensual" style="max-height:180px"></canvas></div>
        </div>
    </div>
</div>

<!-- Últimos documentos -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-clock me-1"></i>Últimos documentos registrados</span>
        <a href="index.php?vista=documentos" class="btn btn-sm btn-outline-primary">Ver todos</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Tipo</th><th>Número</th><th>Fecha</th><th>Emisor</th><th class="text-end">Base</th><th class="text-end">IGV</th><th class="text-end">Total</th><th>Estado</th></tr>
                </thead>
                <tbody>
                <?php foreach ($ultDocs as $d): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= limpiar($d['tipo']) ?></span></td>
                        <td><?= limpiar($d['numero']) ?></td>
                        <td><?= formatoFecha($d['fecha']) ?></td>
                        <td><?= limpiar($d['emisor']) ?></td>
                        <td class="text-end"><?= formatoMoneda((float)$d['total']/1.18) ?></td>
                        <td class="text-end"><?= formatoMoneda((float)$d['total']-(float)$d['total']/1.18) ?></td>
                        <td class="text-end"><strong><?= formatoMoneda((float) $d['total']) ?></strong></td>
                        <td><?= badgeEstado($d['estado']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Últimos asientos -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-clock me-1"></i>Últimos asientos</span>
        <a href="index.php?vista=asientos" class="btn btn-sm btn-outline-primary">Ver todos</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>N°</th><th>Fecha</th><th>Glosa</th><th>Estado</th></tr>
                </thead>
                <tbody>
                <?php foreach ($ultAsientos as $a): ?>
                    <tr>
                        <td><?= $a['numero'] ?></td>
                        <td><?= formatoFecha($a['fecha']) ?></td>
                        <td><?= limpiar($a['glosa']) ?></td>
                        <td><?= badgeEstado($a['estado']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    new Chart(document.getElementById('graficoTipos'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys(array_filter($conteoTipo))) ?>,
            datasets: [{
                data: <?= json_encode(array_values(array_filter($conteoTipo))) ?>,
                backgroundColor: ['#0d9488','#0f766e','#14b8a6','#2dd4bf','#64748b','#f59e0b','#6366f1']
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });
    new Chart(document.getElementById('graficoMensual'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($meses) ?>,
            datasets: [{
                label: 'Ingresos (S/)',
                data: <?= json_encode(array_values($montoMes)) ?>,
                backgroundColor: '#0d9488'
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });
});
</script>
