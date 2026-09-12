<?php
$iniMes = date('Y-m-01');
$finMes = date('Y-m-d');

$ventaMes  = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado='emitida' AND fecha BETWEEN '$iniMes' AND '$finMes'")->fetchColumn();
$compraMes = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM compras WHERE estado='emitida' AND fecha BETWEEN '$iniMes' AND '$finMes'")->fetchColumn();
$ventasMes = (int) $db->query("SELECT COUNT(*) FROM ventas WHERE estado='emitida' AND fecha BETWEEN '$iniMes' AND '$finMes'")->fetchColumn();
$saldoCaja  = (float) $db->query("SELECT COALESCE(SUM(IF(tipo='ingreso',monto,0)),0) - COALESCE(SUM(IF(tipo='egreso',monto,0)),0) FROM caja")->fetchColumn();
$bajoStock  = $db->query("SELECT * FROM productos WHERE controla_stock=1 AND (stock <= 0.0005 OR stock < stock_minimo)")
              ->fetchAll();

$nClientes   = (int) $db->query('SELECT COUNT(*) FROM clientes WHERE activo=1')->fetchColumn();
$nProveedores = (int) $db->query('SELECT COUNT(*) FROM proveedores WHERE activo=1')->fetchColumn();
$valorInventario = (float) $db->query('SELECT COALESCE(SUM(stock*precio_compra),0) FROM productos WHERE controla_stock=1')->fetchColumn();

$ventasRecientes = $db->query('SELECT v.*, cl.razon_social FROM ventas v JOIN clientes cl ON cl.id=v.cliente_id ORDER BY v.fecha DESC, v.id DESC LIMIT 8')->fetchAll();

$tendencia = $db->query("SELECT DATE_FORMAT(fecha,'%Y-%m') mes,
                         COALESCE(SUM(IF(estado='emitida',total,0)),0) ventas FROM ventas
                         GROUP BY mes ORDER BY mes DESC LIMIT 6")->fetchAll();
$compTend  = $db->query("SELECT DATE_FORMAT(fecha,'%Y-%m') mes,
                         COALESCE(SUM(IF(estado='emitida',total,0)),0) compras FROM compras
                         GROUP BY mes ORDER BY mes DESC LIMIT 6")->fetchAll();
$tendencia = array_reverse($tendencia);
$compMesa  = [];
foreach ($compTend as $c) $compMesa[$c['mes']] = (float) $c['compras'];

$porCliente = $db->query("SELECT cl.razon_social, COALESCE(SUM(v.total),0) total
                          FROM ventas v JOIN clientes cl ON cl.id=v.cliente_id
                          WHERE v.estado='emitida' GROUP BY cl.id, cl.razon_social ORDER BY total DESC LIMIT 5")->fetchAll();

$auditoria = $db->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 6')->fetchAll();
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-cubes me-2"></i>Panel del ERP — <?= limpiar(nombreEmpresa($db)) ?></h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-cart-shopping"></i></div>
            <div class="stat-num"><?= formatoMoneda($ventaMes) ?></div>
            <div class="stat-label">Ventas del mes (<?= $ventasMes ?>)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-truck-fast"></i></div>
            <div class="stat-num"><?= formatoMoneda($compraMes) ?></div>
            <div class="stat-label">Compras del mes</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-coins"></i></div>
            <div class="stat-num"><?= formatoMoneda($saldoCaja) ?></div>
            <div class="stat-label">Saldo de caja</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-4">
            <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="stat-num"><?= count($bajoStock) ?></div>
            <div class="stat-label">Productos por reponer</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-chart-column me-1"></i>Ventas vs Compras (últimos 6 meses)</div>
            <div class="card-body">
                <div style="height:220px; position:relative;"><canvas id="chartTend"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-chart-pie me-1"></i>Ventas por cliente</div>
            <div class="card-body">
                <div style="height:220px; position:relative;"><canvas id="chartClientes"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-cart-shopping me-1"></i>Últimas ventas</span>
                <a href="?vista=ventas" class="btn btn-sm btn-outline-primary">Gestionar</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Condición</th>
                                <th class="text-end">Total</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ventasRecientes as $v): ?>
                            <tr class="<?= $v['estado']==='anulada' ? 'table-warning text-muted' : '' ?>">
                                <td><strong><?= numeroVenta((int) $v['numero']) ?></strong></td>
                                <td><?= formatoFecha($v['fecha']) ?></td>
                                <td class="text-truncate" style="max-width:160px" title="<?= limpiar($v['razon_social']) ?>"><?= limpiar($v['razon_social']) ?></td>
                                <td><?= badgeEstado($v['condicion']) ?></td>
                                <td class="text-end"><strong><?= number_format($v['total'], 2) ?></strong></td>
                                <td><?= badgeEstado($v['estado']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$ventasRecientes): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Sin ventas todavía.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-boxes-stacked me-1"></i>Productos por reponer</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($bajoStock as $b): ?>
                        <li class="list-group-item py-2 d-flex justify-content-between align-items-center">
                            <span class="small"><?= limpiar($b['codigo']) ?> — <?= limpiar($b['nombre']) ?></span>
                            <span class="badge bg-warning text-dark">
                                <?= ((float) $b['stock'] <= 0.0005 ? 'AGOTADO' : 'Stock ' . formatoCantidad((float) $b['stock']) . ' / mín ' . formatoCantidad((float) $b['stock_minimo'])) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$bajoStock): ?>
                        <li class="list-group-item text-muted small py-3">Stock por encima del mínimo.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="card mt-4">
            <div class="card-header"><i class="fa-solid fa-file-shield me-1"></i>Actividad reciente</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($auditoria as $a): ?>
                        <li class="list-group-item py-2 d-flex justify-content-between">
                            <span class="small"><?= limpiar($a['resumen']) ?></span>
                            <span class="badge bg-light border text-dark small"><?= date('d/m', strtotime($a['fecha'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$auditoria): ?>
                        <li class="list-group-item text-muted small py-3">Sin actividad registrada.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="card-footer small"><i class="fa-solid fa-circle-info me-1"></i><?= $nClientes ?> clientes · <?= $nProveedores ?> proveedores · inventario valorizado en <?= formatoMoneda($valorInventario) ?> (costo)</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var tend = <?= json_encode($tendencia) ?>;
    var comp = <?= json_encode($compMesa) ?>;
    var etiquetas = tend.map(function(t){ var p=t.mes.split('-'); var mes=['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'][parseInt(p[1])-1]; return mes+' '+p[0]; });

    var ctxT = document.getElementById('chartTend');
    if (tend.length) {
        new Chart(ctxT, {
            type: 'bar',
            data: { labels: etiquetas, datasets: [
                { label: 'Ventas', data: tend.map(function(t){ return +t.ventas; }),
                  borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,.75)', borderRadius: 5 },
                { label: 'Compras', data: etiquetas.map(function(e,i){ return +(comp[tend[i].mes] || 0); }),
                  borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.55)', borderRadius: 5 }
            ]},
            options: { maintainAspectRatio: false,
                       plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
                       scales: { y: { beginAtZero: true, ticks: { callback: function(v){ return 'S/ ' + Number(v).toLocaleString('es-PE'); } } } } }
        });
    } else {
        ctxT.parentElement.innerHTML = '<p class="text-muted text-center py-4">Sin movimientos registrados.</p>';
    }

    var ctxC = document.getElementById('chartClientes');
    if (<?= count($porCliente) ?>) {
        new Chart(ctxC, {
            type: 'doughnut',
            data: { labels: <?= json_encode(array_map(fn($c)=>$c['razon_social'], $porCliente)) ?>,
                    datasets: [{ data: <?= json_encode(array_map(fn($c)=>(float)$c['total'], $porCliente)) ?>,
                                 backgroundColor: ['#0d9488','#2563eb','#f59e0b','#dc2626','#7c3aed'] }] },
            options: { maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
    } else {
        ctxC.parentElement.innerHTML = '<p class="text-muted text-center py-4">Sin ventas.</p>';
    }
});
</script>