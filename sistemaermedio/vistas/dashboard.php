<?php
$mActual  = date('Y-m');
$vMes     = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado='emitida' AND DATE_FORMAT(fecha,'%Y-%m')='$mActual'")->fetchColumn();
$cMes     = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM compras WHERE estado='emitida' AND DATE_FORMAT(fecha,'%Y-%m')='$mActual'")->fetchColumn();
$saldoCaja= (float) $db->query('SELECT COALESCE(SUM(IF(tipo_oper="ingreso",monto,0)),0) - COALESCE(SUM(IF(tipo_oper="egreso",monto,0)),0) FROM caja')->fetchColumn();

$cxcTotal = 0.0;
foreach ($db->query('SELECT v.*, cl.razon_social FROM ventas v JOIN clientes cl ON cl.id=v.cliente_id WHERE v.condicion="credito" AND v.estado="emitida"') as $v) {
    $pag = $db->prepare('SELECT COALESCE(SUM(monto),0) FROM venta_pagos WHERE venta_id=?'); $pag->execute([$v['id']]);
    $cxcTotal += max(0, (float) $v['total'] - (float) $pag->fetchColumn());
}
$cxpTotal = 0.0;
foreach ($db->query('SELECT c.*, p.razon_social FROM compras c JOIN proveedores p ON p.id=c.proveedor_id WHERE c.condicion="credito" AND c.estado="emitida"') as $c0) {
    $pag = $db->prepare('SELECT COALESCE(SUM(monto),0) FROM compra_pagos WHERE compra_id=?'); $pag->execute([$c0['id']]);
    $cxpTotal += max(0, (float) $c0['total'] - (float) $pag->fetchColumn());
}

$productos = $db->query('SELECT p.*, COALESCE(s.stock_total,0) stock_total
                         FROM productos p LEFT JOIN (SELECT producto_id, SUM(cantidad) stock_total FROM stock GROUP BY producto_id) s ON s.producto_id=p.id
                         ORDER BY p.nombre')->fetchAll();
$porReponer = array_filter($productos, fn($p) => estadoProducto($p) === 'bajo' || estadoProducto($p) === 'agotado');
$porReponer = array_slice(array_values($porReponer), 0, 6);

$ultimas = $db->query('SELECT v.*, cl.razon_social FROM ventas v JOIN clientes cl ON cl.id=v.cliente_id ORDER BY v.fecha DESC, v.id DESC LIMIT 6')->fetchAll();

// Series últimos 6 meses
$labels = []; $serV = []; $serC = [];
for ($i = 5; $i >= 0; $i--) {
    $f = date('Y-m', strtotime("-$i months"));
    $labels[] = nombreMes((int) substr($f, 5, 2));
    $serV[] = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado='emitida' AND DATE_FORMAT(fecha,'%Y-%m')='$f'")->fetchColumn();
    $serC[] = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM compras WHERE estado='emitida' AND DATE_FORMAT(fecha,'%Y-%m')='$f'")->fetchColumn();
}
$porCliente = [];
foreach ($db->query("SELECT cl.razon_social, SUM(v.total) t FROM ventas v JOIN clientes cl ON cl.id=v.cliente_id WHERE v.estado='emitida' GROUP BY cl.razon_social ORDER BY t DESC LIMIT 6") as $r) {
    $porCliente['nombre'][] = $r['razon_social'];
    $porCliente['total'][]  = (float) $r['t'];
}
?>

<section class="page-heading module-page-heading mb-4">
    <div>
        <span class="page-eyebrow">Visión ejecutiva</span>
        <h1 class="page-title mb-1">Panel de control</h1>
        <p class="page-subtitle mb-0">Ventas, liquidez, obligaciones y alertas de inventario.</p>
    </div>
    <a href="index.php?vista=ventas" class="btn btn-primary"><i class="fa-solid fa-cart-plus me-1"></i>Gestionar ventas</a>
</section>

<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['fa-cart-shopping', 'Ventas del mes',     'success', $vMes],
        ['fa-truck-fast',    'Compras del mes',    'warning', $cMes],
        ['fa-coins',         'Saldo de caja',      'primary', $saldoCaja],
        ['fa-hand-holding-dollar', 'Por cobrar (CxC)', 'info',  $cxcTotal],
        ['fa-money-bill-transfer', 'Por pagar (CxP)', 'danger', $cxpTotal],
        ['fa-boxes-stacked', 'Por reponer',        'secondary', count($porReponer)],
    ];
    foreach ($kpis as [$ico, $lab, $color, $val]): ?>
        <div class="col-md-4 col-xl-2">
            <div class="card stat-card card-<?= $color ?>">
                <div class="card-body">
                    <div class="stat-icon"><i class="fa-solid <?= $ico ?>"></i></div>
                    <div class="stat-label"><?= $lab ?></div>
                    <div class="stat-value"><?= is_float($val) ? number_format($val, 2) : $val ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-body">
            <h6 class="card-title text-muted"><i class="fa-solid fa-chart-column me-2"></i>Ventas vs compras (últimos 6 meses)</h6>
            <div style="height:220px; position:relative;"><canvas id="chartVC"></canvas></div>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h6 class="card-title text-muted"><i class="fa-solid fa-chart-pie me-2"></i>Ventas por cliente</h6>
            <div style="height:220px; position:relative;"><canvas id="chartCliente"></canvas></div>
        </div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card"><div class="card-header bg-white"><i class="fa-solid fa-receipt me-2"></i>Últimas ventas</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Cant.</th><th class="text-end">Total</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($ultimas as $v): ?>
                        <tr class="<?= $v['estado']==='anulada' ? 'table-warning text-muted' : '' ?>">
                            <td><strong><?= numeroVenta((int) $v['numero']) ?></strong></td>
                            <td><?= formatoFecha($v['fecha']) ?></td>
                            <td class="text-truncate" style="max-width:150px" title="<?= limpiar($v['razon_social']) ?>"><?= limpiar($v['razon_social']) ?></td>
                            <td><?= ucfirst($v['condicion']) ?> · <?= strtoupper($v['tipo_doc']) ?></td>
                            <td class="text-end"><strong><?= number_format($v['total'], 2) ?></strong></td>
                            <td><?= badgeEstado($v['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$ultimas): ?><tr><td colspan="6" class="text-center text-muted py-3">Sin ventas registradas.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-4"><div class="card-header bg-white"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i>Alerta de stock</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Producto</th><th class="text-end">Stock</th><th class="text-end">Mínimo</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($porReponer as $p): ?>
                        <tr>
                            <td><strong><?= limpiar($p['nombre']) ?></strong><br><small class="text-muted"><?= limpiar($p['codigo']) ?></small></td>
                            <td class="text-end"><?= formatoCantidad((float) $p['stock_total']) ?></td>
                            <td class="text-end"><?= formatoCantidad((float) $p['stock_minimo']) ?></td>
                            <td><?= badgeEstado(estadoProducto($p)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$porReponer): ?><tr><td colspan="4" class="text-center text-muted py-3">Sin productos por reponer.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card"><div class="card-header bg-white"><i class="fa-solid fa-scale-balanced me-2"></i>Resumen financiero</div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-1"><span>CxC (clientes)</span><strong class="text-danger"><?= formatoMoneda($cxcTotal) ?></strong></div>
                <div class="d-flex justify-content-between py-1"><span>CxP (proveedores)</span><strong class="text-primary"><?= formatoMoneda($cxpTotal) ?></strong></div>
                <hr class="my-2">
                <div class="d-flex justify-content-between py-1"><span>Margen del mes (Ventas − Compras)</span>
                    <strong class="<?= $vMes - $cMes >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatoMoneda($vMes - $cMes) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<script>
const serieV = <?= json_encode($serV) ?>;
const serieC = <?= json_encode($serC) ?>;
const labels = <?= json_encode($labels) ?>;
new Chart(document.getElementById('chartVC'), {
    type: 'line',
    data: { labels, datasets: [
        { label: 'Ventas', data: serieV, borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,.15)', tension: .35, fill: true },
        { label: 'Compras', data: serieC, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.12)', tension: .35, fill: true },
    ]},
    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => v >= 1000 ? (v/1000) + 'k' : v } } } }
});
new Chart(document.getElementById('chartCliente'), {
    type: 'doughnut',
    data: { labels: <?= json_encode($porCliente['nombre'] ?? []) ?>,
        datasets: [{ data: <?= json_encode($porCliente['total'] ?? []) ?>,
            backgroundColor: ['#0d9488','#14b8a6','#f59e0b','#0ea5e9','#f43f5e','#8b5cf6'] }] },
    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } }
});
</script>
