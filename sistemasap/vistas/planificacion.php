<?php
$horizonte = in_array($_GET['horizonte'] ?? '', ['7d', '30d', '90d'], true) ? $_GET['horizonte'] : '30d';
$pasos = (int) rtrim($horizonte, 'd');

$ultimasPredicciones = $db->query('SELECT * FROM predicciones ORDER BY fecha_generacion DESC LIMIT 8')->fetchAll();
$recomendaciones = $db->query('SELECT * FROM recomendaciones WHERE estado!="completada" ORDER BY FIELD(urgencia,"critica","alta","media","baja"), fecha_generacion DESC LIMIT 12')->fetchAll();
$historial = $db->query('SELECT fecha, ventas, unidades_vendidas, clientes_atendidos, gastos FROM historico_diario ORDER BY fecha DESC LIMIT 30')->fetchAll();

// Ventas diarias para proyección
$daily = $db->query('SELECT fecha, ventas FROM historico_diario ORDER BY fecha')->fetchAll();
$ventasSeries = array_map(fn($d) => (float) $d['ventas'], $daily);
$proyeccion = proyectarSerie($ventasSeries, $pasos);
$promedio30 = promedioMovil($ventasSeries, 30);

// Stock con estimación de días
$stock_prediccion = $db->query("SELECT p.id, p.nombre, p.stock_actual, p.punto_pedido, p.stock_minimo, p.costo_unitario, prov.nombre AS proveedor, prov.tiempo_entrega_dias
                                FROM productos p JOIN proveedores prov ON prov.id = p.proveedor_id WHERE p.activo=1 ORDER BY p.stock_actual ASC")->fetchAll();
foreach ($stock_prediccion as &$sp) {
    $sp['dias_restantes'] = diasRestantesStock($db, (int) $sp['id']);
    $sp['usa_diaria'] = usoDiarioPromedio($db, (int) $sp['id']);
    $sp['dia_compra'] = max(0, $sp['dias_restantes'] - (int) $sp['tiempo_entrega_dias']);
}

// Personal
$personal_req = $db->query("SELECT c.id AS cargo_id, c.nombre AS cargo, c.requeridos, COUNT(e.id) AS actuales
                            FROM cargos c LEFT JOIN empleados e ON e.cargo_id=c.id AND e.estado='activo'
                            GROUP BY c.id, c.nombre, c.requeridos")->fetchAll();

// Flujo de caja
$ingresosM = (float) $db->query("SELECT COALESCE(SUM(monto),0) FROM transacciones WHERE tipo='ingreso' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$egresosM = (float) $db->query("SELECT COALESCE(SUM(monto),0) FROM transacciones WHERE tipo='egreso' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$gastosMOp = (float) $db->query("SELECT COALESCE(SUM(gastos),0) FROM historico_diario WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$flujo30 = $ingresosM - $egresosM - $gastosMOp;
?>

<section class="page-heading module-page-heading mb-4">
    <div>
        <span class="page-eyebrow">Inteligencia Artificial Predictiva</span>
        <h1 class="page-title mb-1">Planificación</h1>
        <p class="page-subtitle mb-0">Predicciones de ventas, stock, personal y flujo de caja. El sistema te dice QUÉ comprar, CUÁNDO y CUÁNTOS contratar.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="post" class="d-flex gap-2">
            <?= campoCsrf() ?>
            <select name="horizonte" class="form-select form-select-sm w-auto">
                <option value="7d" <?= $horizonte === '7d' ? 'selected' : '' ?>>7 días</option>
                <option value="30d" <?= $horizonte === '30d' ? 'selected' : '' ?>>30 días</option>
                <option value="90d" <?= $horizonte === '90d' ? 'selected' : '' ?>>90 días</option>
            </select>
            <button type="submit" name="action" value="generar_predicciones" class="btn btn-primary btn-sm"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Generar predicción</button>
        </form>
        <form method="post">
            <?= campoCsrf() ?>
            <button type="submit" name="action" value="actualizar_historial" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-database me-1"></i>Capturar histórico de hoy</button>
        </form>
    </div>
</section>

<!-- Predicciones destacadas -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="ai-card h-100">
            <span class="ai-badge"><i class="fa-solid fa-money-bill-trend-up"></i>Ventas proyectadas</span>
            <h3 class="mt-3 mb-0"><?= formatoMoneda(array_sum($proyeccion)) ?></h3>
            <p class="mb-0" style="font-size:.85rem;opacity:.9">Proyección de ventas para los próximos <?= $pasos ?> días (promedio diario: <?= formatoMoneda($promedio30) ?>).</p>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card h-100 text-center">
            <div class="card-body d-flex flex-column justify-content-center">
                <div class="stat-icon" style="color:#ef4444"><i class="fa-solid fa-exclamation-triangle"></i></div>
                <div style="font-size:1.7rem;font-weight:700;color:var(--ink)"><?= count(array_filter($stock_prediccion, fn($p) => $p['dias_restantes'] <= $pasos)) ?></div>
                <div style="font-size:.8rem;color:var(--muted)">Productos por agotarse &lt; <?= $pasos ?> días</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card h-100 text-center">
            <div class="card-body d-flex flex-column justify-content-center">
                <div class="stat-icon" style="color:#7c3aed"><i class="fa-solid fa-user-plus"></i></div>
                <div style="font-size:1.7rem;font-weight:700;color:var(--ink)"><?= array_sum(array_map(fn($c) => max(0, (int)$c['requeridos'] - (int)$c['actuales']), $personal_req)) ?></div>
                <div style="font-size:.8rem;color:var(--muted)">Personas por contratar</div>
            </div>
        </div>
    </div>
</div>

<!-- Gráfica de proyección de ventas -->
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-chart-line me-1"></i>Proyección de ventas — próximos <?= $pasos ?> días</div>
    <div class="card-body">
        <canvas id="graficoProyeccion" style="height:220px"></canvas>
    </div>
</div>

<!-- CUÁNDO COMPRAR -->
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-cart-shopping me-1"></i>Cuándo comprar — estimación por producto</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Producto</th><th>Stock</th><th>Punto pedido</th><th class="text-end">Uso diario</th><th class="text-end">Días restantes</th><th class="text-end">Entrega prov.</th><th>Recomendación</th></tr>
                </thead>
                <tbody>
                <?php foreach ($stock_prediccion as $sp): ?>
                    <?php
                        $claseDias = $sp['dias_restantes'] <= $sp['tiempo_entrega_dias'] ? 'pred-malo' : ($sp['dias_restantes'] <= 60 ? 'pred-neutro' : 'pred-bueno');
                        $req = $sp['dias_restantes'] <= $sp['tiempo_entrega_dias'];
                    ?>
                    <tr>
                        <td><strong><?= limpiar($sp['nombre']) ?></strong><br><small class="text-muted"><?= limpiar($sp['proveedor']) ?></small></td>
                        <td><span class="badge bg-<?= $sp['stock_actual'] <= $sp['punto_pedido'] ? 'danger' : 'success' ?>"><?= (int)$sp['stock_actual'] ?></span> <small class="text-muted">/ mín <?= (int)$sp['stock_minimo'] ?></small></td>
                        <td><?= (int)$sp['punto_pedido'] ?></td>
                        <td class="text-end"><?= number_format($sp['usa_diaria'], 1) ?></td>
                        <td class="text-end <?= $claseDias ?>"><?= $sp['dias_restantes'] === 999 ? '∞' : $sp['dias_restantes'] . ' días' ?></td>
                        <td class="text-end"><?= (int)$sp['tiempo_entrega_dias'] ?> días</td>
                        <td>
                            <?php if ($req): ?>
                                <span class="badge bg-danger"><i class="fa-solid fa-bell me-1"></i>Comprar YA (<?= max(1, (int)$sp['punto_pedido'] - (int)$sp['stock_actual']) ?> und)</span>
                            <?php elseif ($sp['dias_restantes'] <= 60): ?>
                                <span class="badge bg-warning text-dark">Comprar en ~<?= $sp['dia_compra'] ?> días</span>
                            <?php else: ?>
                                <span class="badge bg-success">Sin urgencia</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- A QUIÉN CONTRATAR -->
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-users-gear me-1"></i>Quién contratar — brechas por cargo</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Cargo</th><th>Requeridos</th><th>Actuales</th><th>Faltantes</th><th>Acción</th></tr>
                </thead>
                <tbody>
                <?php foreach ($personal_req as $c): ?>
                    <?php $faltan = max(0, (int)$c['requeridos'] - (int)$c['actuales']); ?>
                    <tr>
                        <td><strong><?= limpiar($c['cargo']) ?></strong></td>
                        <td><?= (int)$c['requeridos'] ?></td>
                        <td><span class="badge bg-<?= $c['actuales'] >= $c['requeridos'] ? 'success' : ($faltan > 0 ? 'warning' : 'secondary') ?>"><?= (int)$c['actuales'] ?></span></td>
                        <td class="<?= $faltan > 0 ? 'pred-malo' : 'pred-bueno' ?>"><?= $faltan > 0 ? "-$faltan" : 'Completo' ?></td>
                        <td>
                            <?php if ($faltan > 0): ?>
                                <a href="index.php?vista=rrhh" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-user-plus me-1"></i>Abrir vacante</a>
                            <?php else: ?>
                                <span class="text-muted"><i class="fa-solid fa-check"></i> Sin brecha</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Flujo de caja -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-scale-balanced me-1"></i>Flujo de caja — últimos 30 días</div>
            <div class="card-body">
                <div class="row text-center g-2">
                    <div class="col-4">
                        <div style="font-size:1.3rem;font-weight:700;color:#16a34a"><?= formatoMoneda($ingresosM) ?></div>
                        <div style="font-size:.75rem;color:var(--muted)">Ingresos</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:1.3rem;font-weight:700;color:#dc2626"><?= formatoMoneda($egresosM + $gastosMOp) ?></div>
                        <div style="font-size:.75rem;color:var(--muted)">Egresos + gastos</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:1.3rem;font-weight:700" class="<?= $flujo30 >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatoMoneda($flujo30) ?></div>
                        <div style="font-size:.75rem;color:var(--muted)">Flujo neto</div>
                    </div>
                </div>
                <?php if ($flujo30 < 0): ?>
                    <div class="alert alert-danger mt-3 mb-0 py-2"><i class="fa-solid fa-triangle-exclamation me-1"></i>Gastos mayores a ingresos. Considera reducir gastos o subir precios.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-clock-rotate-left me-1"></i>Histórico reciente</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Fecha</th><th class="text-end">Ventas</th><th class="text-end">Unid.</th><th class="text-end">Clientes</th><th class="text-end">Gastos</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($historial, 0, 10) as $h): ?>
                            <tr>
                                <td><?= formatoFecha($h['fecha']) ?></td>
                                <td class="text-end"><?= formatoMoneda((float) $h['ventas']) ?></td>
                                <td class="text-end"><?= (int)$h['unidades_vendidas'] ?></td>
                                <td class="text-end"><?= (int)$h['clientes_atendidos'] ?></td>
                                <td class="text-end"><?= formatoMoneda((float) $h['gastos']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recomendaciones completas -->
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-lightbulb me-1"></i>Recomendaciones del sistema</div>
    <div class="card-body">
        <?php if (empty($recomendaciones)): ?>
            <div class="text-center py-4 text-muted"><i class="fa-solid fa-face-smile fa-2x mb-2 d-block" style="opacity:.4"></i>Sin recomendaciones pendientes.</div>
        <?php else: ?>
            <?php foreach ($recomendaciones as $rec): ?>
                <div class="insight insight--<?= limpiar($rec['urgencia']) ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="insight-titulo">
                                <i class="fa-solid fa-<?= $rec['area'] === 'rrhh' ? 'users' : ($rec['area'] === 'inventario' ? 'boxes-stacked' : ($rec['area'] === 'finanzas' ? 'scale-balanced' : 'chart-line')) ?> me-1"></i>
                                <?= limpiar($rec['titulo']) ?>
                            </span>
                            <div class="insight-desc"><?= limpiar($rec['descripcion']) ?></div>
                            <?php if ($rec['accion_sugerida']): ?>
                                <div class="mt-1"><span class="badge bg-primary-subtle text-primary"><i class="fa-solid fa-arrow-right me-1"></i><?= limpiar($rec['accion_sugerida']) ?></span></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-end ms-3">
                            <span class="urgencia urgencia-<?= limpiar($rec['urgencia']) ?> d-block mb-1"><?= ucfirst(limpiar($rec['urgencia'])) ?></span>
                            <small class="text-muted"><?= formatoFecha(substr($rec['fecha_generacion'], 0, 10)) ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    new Chart(document.getElementById('graficoProyeccion'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_merge(array_column(array_slice($daily, -15), 'fecha'), array_map(fn($i) => 'D+' . ($i + 1), range(0, $pasos - 1)))) ?>,
            datasets: [
                {
                    label: 'Histórico',
                    data: <?= json_encode(array_merge(array_slice(array_map(fn($d) => (float)$d['ventas'], $daily), -15), array_fill(0, $pasos, null))) ?>,
                    borderColor: '#64748b',
                    backgroundColor: 'rgba(100,116,139,0.15)',
                    fill: false,
                    tension: 0.3
                },
                {
                    label: 'Proyección (IA)',
                    data: <?= json_encode(array_merge(array_fill(0, 15, null), $proyeccion)) ?>,
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124,58,237,0.15)',
                    fill: true,
                    borderDash: [6, 4],
                    tension: 0.3
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>