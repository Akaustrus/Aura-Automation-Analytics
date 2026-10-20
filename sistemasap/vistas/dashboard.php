<?php
$empTotal    = (int) $db->query('SELECT COUNT(*) FROM empleados WHERE estado!="inactivo"')->fetchColumn();
$empActivo   = (int) $db->query('SELECT COUNT(*) FROM empleados WHERE estado="activo"')->fetchColumn();
$prodTotal   = (int) $db->query('SELECT COUNT(*) FROM productos WHERE activo=1')->fetchColumn();
$stockBajo   = (int) $db->query('SELECT COUNT(*) FROM productos WHERE stock_actual <= punto_pedido AND activo=1')->fetchColumn();
$venture     = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM comprobantes WHERE estado!='anulado'")->fetchColumn();
$ventureMes  = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM comprobantes WHERE estado!='anulado' AND YEAR(fecha)=YEAR(CURDATE()) AND MONTH(fecha)=MONTH(CURDATE())")->fetchColumn();
$planillaMes = (float) $db->query("SELECT COALESCE(total_neto,0) FROM periodos_planilla WHERE periodo = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
$caja        = (float) $db->query("SELECT COALESCE(SUM(saldo),0) FROM cuentas_sap WHERE tipo='activo' AND codigo IN ('101','104','121')")->fetchColumn();
$recomendaciones = $db->query('SELECT * FROM recomendaciones WHERE estado="pendiente" ORDER BY FIELD(urgencia,"critica","alta","media","baja"), fecha_generacion DESC LIMIT 10')->fetchAll();

$ventasMensuales = [];
$stmt = $db->query("SELECT DATE_FORMAT(fecha, '%Y-%m') mes, SUM(ventas) total FROM historico_diario GROUP BY mes ORDER BY mes DESC LIMIT 6");
foreach (array_reverse($stmt->fetchAll()) as $m) {
    $ventasMensuales[] = ['mes' => $m['mes'], 'total' => (float) $m['total']];
}
$meses6 = array_column($ventasMensuales, 'mes');
$montos6 = array_column($ventasMensuales, 'total');

$ventas30 = (float) $db->query("SELECT COALESCE(AVG(ventas),0) FROM historico_diario WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$proy30 = empty($montos6) ? 0 : round(array_sum($montos6) / count($montos6) * 1.08, 2);
$gastos30 = (float) $db->query("SELECT COALESCE(SUM(gastos),0) FROM historico_diario WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$flujo30 = $ventas30 * 30 - $gastos30;

$diasSemana = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $diasSemana[] = $d;
}
$ventasPorDia = [];
foreach ($diasSemana as $d) {
    $st = $db->prepare('SELECT ventas FROM historico_diario WHERE fecha=?');
    $st->execute([$d]);
    $v = $st->fetchColumn();
    $ventasPorDia[] = $v !== false ? (float) $v : 0;
}
?>

<section class="page-heading module-page-heading mb-4">
    <div>
        <span class="page-eyebrow">Inteligencia de negocio</span>
        <h1 class="page-title mb-1">Panel predictivo</h1>
        <p class="page-subtitle mb-0">Vista integrada de RRHH, inventario, finanzas y predicciones del negocio.</p>
    </div>
    <a href="index.php?vista=planificacion" class="btn btn-primary"><i class="fa-solid fa-robot me-1"></i>Generar predicciones</a>
</section>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-num"><?= $empActivo ?>/<?= $empTotal ?></div>
            <div class="stat-label">Empleados activos</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="stat-num"><?= $stockBajo ?></div>
            <div class="stat-label">Productos bajo punto de pedido</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="stat-num"><?= formatoMoneda($ventureMes) ?></div>
            <div class="stat-label">Ventas del mes</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-4">
            <div class="stat-icon"><i class="fa-solid fa-scale-balanced"></i></div>
            <div class="stat-num"><?= formatoMoneda($caja) ?></div>
            <div class="stat-label">Liquidez (caja + bancos + cobrar)</div>
        </div>
    </div>
</div>

<!-- Tarjeta IA + Proyeccion -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="ai-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="ai-badge"><i class="fa-solid fa-brain"></i>IA Predictiva</span>
                    <h3 class="mt-2 mb-1">Proyección a 30 días</h3>
                </div>
                <i class="fa-solid fa-chart-line" style="font-size:2rem;opacity:.6"></i>
            </div>
            <div class="row g-2 text-center">
                <div class="col-4">
                    <div style="font-size:1.4rem;font-weight:700"><?= formatoMoneda($ventas30) ?></div>
                    <div style="font-size:.75rem;opacity:.85">Venta promedio diaria</div>
                </div>
                <div class="col-4">
                    <div style="font-size:1.4rem;font-weight:700"><?= formatoMoneda($proy30) ?></div>
                    <div style="font-size:.75rem;opacity:.85">Ventas proyectadas mes</div>
                </div>
                <div class="col-4">
                    <div style="font-size:1.4rem;font-weight:700" class="<?= $proy30 - $gastos30 >= 0 ? '' : '' ?>"><?= formatoMoneda($proy30 - $gastos30) ?></div>
                    <div style="font-size:.75rem;opacity:.85">Margen operativo estimado</div>
                </div>
            </div>
            <hr style="border-color:rgba(255,255,255,.2)">
            <div class="row g-2">
                <div class="col-md-4">
                    <a href="index.php?vista=planificacion" class="btn btn-light w-100"><i class="fa-solid fa-robot me-1"></i>Predicciones</a>
                </div>
                <div class="col-md-4">
                    <a href="index.php?vista=inventario" class="btn btn-light w-100"><i class="fa-solid fa-cart-shopping me-1"></i>Compras</a>
                </div>
                <div class="col-md-4">
                    <a href="index.php?vista=rrhh" class="btn btn-light w-100"><i class="fa-solid fa-user-plus me-1"></i>Contratar</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-chart-line me-1"></i>Ventas últimas 6 semanas</div>
            <div class="card-body">
                <canvas id="graficoVentas" style="max-height:170px"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Módulos -->
<div class="row g-3 mb-4">
    <div class="col-md-4"><a href="index.php?vista=rrhh" class="modulo-tile mt-rrhh"><div class="mt-icon"><i class="fa-solid fa-users"></i></div><div class="mt-title">Recursos Humanos</div><div class="mt-sub">Empleados, cargos, vacantes y predicción de contratación</div></a></div>
    <div class="col-md-4"><a href="index.php?vista=inventario" class="modulo-tile mt-inv"><div class="mt-icon"><i class="fa-solid fa-boxes-stacked"></i></div><div class="mt-title">Inventario</div><div class="mt-sub">Stock, movimientos, pedidos y cuándo comprar</div></a></div>
    <div class="col-md-4"><a href="index.php?vista=planificacion" class="modulo-tile mt-fin"><div class="mt-icon"><i class="fa-solid fa-robot"></i></div><div class="mt-title">Planificación</div><div class="mt-sub">IA predictiva: ventas, stock, personal y flujo de caja</div></a></div>
    <div class="col-md-4"><a href="index.php?vista=facturacion" class="modulo-tile mt-fac"><div class="mt-icon"><i class="fa-solid fa-file-invoice"></i></div><div class="mt-title">Facturación</div><div class="mt-sub">Boletas, facturas, clientes y comprobantes</div></a></div>
    <div class="col-md-4"><a href="index.php?vista=planilla" class="modulo-tile mt-plan"><div class="mt-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div><div class="mt-title">Planilla</div><div class="mt-sub">Pagos, descuentos, aportes y boletas de pago</div></a></div>
    <div class="col-md-4"><a href="index.php?vista=contabilidad" class="modulo-tile mt-con"><div class="mt-icon"><i class="fa-solid fa-scale-balanced"></i></div><div class="mt-title">Contabilidad</div><div class="mt-sub">Cuentas, transacciones y estado financiero</div></a></div>
</div>

<!-- Recomendaciones -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-lightbulb me-1"></i>Recomendaciones automáticas</span>
        <a href="index.php?vista=planificacion" class="btn btn-sm btn-outline-primary">Ver planificación</a>
    </div>
    <div class="card-body row g-0">
        <?php if (empty($recomendaciones)): ?>
            <div class="text-center py-4 text-muted"><i class="fa-solid fa-circle-check fa-2x mb-2 d-block" style="opacity:.4"></i>Sin recomendaciones pendientes. Todo bajo control.</div>
        <?php else: ?>
            <?php foreach ($recomendaciones as $rec): ?>
                <div class="col-lg-6 pe-lg-2 pb-1">
                    <div class="insight insight--<?= limpiar($rec['urgencia']) ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="insight-titulo"><i class="fa-solid fa-<?= $rec['area'] === 'rrhh' ? 'users' : ($rec['area'] === 'inventario' ? 'boxes-stacked' : ($rec['area'] === 'finanzas' ? 'scale-balanced' : 'chart-line')) ?> me-1"></i> <?= limpiar($rec['titulo']) ?></span>
                            <span class="urgencia urgencia-<?= limpiar($rec['urgencia']) ?>"><?= ucfirst(limpiar($rec['urgencia'])) ?></span>
                        </div>
                        <div class="insight-desc"><?= limpiar($rec['descripcion']) ?></div>
                        <?php if ($rec['accion_sugerida']): ?>
                            <div class="mt-1"><span class="badge bg-primary-subtle text-primary"><?= limpiar($rec['accion_sugerida']) ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    new Chart(document.getElementById('graficoVentas'), {
        type: 'line',
        data: {
            labels: <?= json_encode($diasSemana) ?>,
            datasets: [{
                label: 'Ventas (S/)',
                data: <?= json_encode($ventasPorDia) ?>,
                borderColor: '#7c3aed',
                backgroundColor: 'rgba(124,58,237,0.12)',
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>