<?php
[$desde, $hasta] = rangoFechas();

$saldoCaja  = (float) $db->query('SELECT COALESCE(SUM(IF(tipo="ingreso",monto,0)),0) - COALESCE(SUM(IF(tipo="egreso",monto,0)),0) FROM caja')->fetchColumn();
$valCosto   = (float) $db->query('SELECT COALESCE(SUM(stock*precio_compra),0) FROM productos WHERE controla_stock=1')->fetchColumn();
$valVenta   = (float) $db->query('SELECT COALESCE(SUM(stock*precio_venta),0) FROM productos WHERE controla_stock=1')->fetchColumn();
$totVentas  = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado='emitida'")->fetchColumn();
$totCompras = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM compras WHERE estado='emitida'")->fetchColumn();

$resumenMes = $db->query("SELECT mes,
    (SELECT COALESCE(SUM(v.total),0) FROM ventas v WHERE v.estado='emitida' AND DATE_FORMAT(v.fecha,'%Y-%m')=x.mes) vv,
    (SELECT COALESCE(SUM(c.total),0) FROM compras c WHERE c.estado='emitida' AND DATE_FORMAT(c.fecha,'%Y-%m')=x.mes) cc
    FROM (SELECT DATE_FORMAT(fecha,'%Y-%m') mes FROM ventas WHERE estado='emitida'
          UNION SELECT DATE_FORMAT(fecha,'%Y-%m') FROM compras WHERE estado='emitida') x
    ORDER BY mes DESC LIMIT 12")->fetchAll();
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-chart-line me-2"></i>Reportes y exportaciones</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-cart-shopping"></i></div>
            <div class="stat-num"><?= formatoMoneda($totVentas) ?></div>
            <div class="stat-label">Ventas acumuladas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-truck-fast"></i></div>
            <div class="stat-num"><?= formatoMoneda($totCompras) ?></div>
            <div class="stat-label">Compras acumuladas</div>
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
            <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="stat-num"><?= formatoMoneda($valVenta) ?></div>
            <div class="stat-label">Inventario (costo <?= formatoMoneda($valCosto) ?>)</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-file-export me-1"></i>Exportación de reportes</div>
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end mb-3" action="index.php">
                    <input type="hidden" name="vista" value="reportes">
                    <div class="col-md-3">
                        <label class="form-label small">Desde</label>
                        <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Hasta</label>
                        <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Formato</label>
                        <select name="formato" class="form-select form-select-sm">
                            <option value="xlsx">Excel (.xlsx)</option>
                            <option value="pdf">PDF</option>
                            <option value="doc">Word (.doc)</option>
                            <option value="csv">CSV</option>
                        </select>
                    </div>
                </form>
                <div class="row g-2">
                    <div class="col-md-4"><a class="btn btn-outline-primary btn-sm w-100" href="?action=exportar&tipo=ventas&desde=<?= $desde ?>&hasta=<?= $hasta ?>"><i class="fa-solid fa-cart-shopping me-1"></i>Ventas por rango</a></div>
                    <div class="col-md-4"><a class="btn btn-outline-primary btn-sm w-100" href="?action=exportar&tipo=compras&desde=<?= $desde ?>&hasta=<?= $hasta ?>"><i class="fa-solid fa-truck-fast me-1"></i>Compras por rango</a></div>
                    <div class="col-md-4"><a class="btn btn-outline-primary btn-sm w-100" href="?action=exportar&tipo=inventario"><i class="fa-solid fa-boxes-stacked me-1"></i>Inventario y valorización</a></div>
                    <div class="col-md-4"><a class="btn btn-outline-primary btn-sm w-100" href="?action=exportar&tipo=movimientos&desde=<?= $desde ?>&hasta=<?= $hasta ?>"><i class="fa-solid fa-right-left me-1"></i>Movimientos de stock</a></div>
                    <div class="col-md-4"><a class="btn btn-outline-primary btn-sm w-100" href="?action=exportar&tipo=caja&desde=<?= $desde ?>&hasta=<?= $hasta ?>"><i class="fa-solid fa-coins me-1"></i>Movimiento de caja</a></div>
                </div>
                <div class="alert alert-light border small mt-3 mb-0">
                    <i class="fa-solid fa-circle-info me-1"></i>Los formatos PDF, Word y CSV se generan igualmente desde el selector de formato agregando <code>&formato=pdf</code> al enlace.
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-table me-1"></i>Resumen mensual (últimos 12 meses)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Mes</th><th class="text-end">Ventas</th><th class="text-end">Compras</th><th class="text-end">Margen bruto*</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($resumenMes as $r): ?>
                            <?php [$a, $m] = explode('-', $r['mes']); ?>
                            <tr>
                                <td><?= nombreMes((int) $m) ?> <?= $a ?></td>
                                <td class="text-end"><?= number_format($r['vv'], 2) ?></td>
                                <td class="text-end"><?= number_format($r['cc'], 2) ?></td>
                                <td class="text-end"><strong><?= number_format((float) $r['vv'] - (float) $r['cc'], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$resumenMes): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Sin información.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer small text-muted">*Valor referencial: ventas − compras del mes (no considera otros gastos).</div>
        </div>
    </div>
</div>

<script>
const FORMATO = '<?= limpiar($_GET['formato'] ?? 'xlsx') ?>';
document.querySelectorAll('a[href*="action=exportar"]').forEach(a => {
    const sep = a.href.indexOf('?') >= 0 ? '&' : '?';
    a.href += sep + 'formato=' + FORMATO;
});
</script>