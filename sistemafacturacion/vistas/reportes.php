<?php
[$desde, $hasta] = rangoFechas();

$resumenMensual = $db->query("SELECT DATE_FORMAT(fecha_emision,'%Y-%m') mes, COUNT(*) n,
                              COALESCE(SUM(subtotal),0) subtotal, COALESCE(SUM(igv),0) igv, COALESCE(SUM(total),0) total
                              FROM comprobantes WHERE estado='emitido'
                              GROUP BY mes ORDER BY mes DESC LIMIT 12")->fetchAll();

$anulados = $db->query('SELECT c.*, cl.razon_social FROM comprobantes c JOIN clientes cl ON cl.id=c.cliente_id WHERE c.estado="anulado" ORDER BY c.fecha_emision DESC')->fetchAll();
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-chart-line me-2"></i>Reportes y exportación</h2>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-file-export me-1"></i>Exportar comprobantes por rango de fechas</div>
            <div class="card-body">
                <form method="GET" action="index.php" class="row g-2 align-items-end mb-3">
                    <input type="hidden" name="action" value="exportar">
                    <input type="hidden" name="tipo" value="comprobantes">
                    <input type="hidden" name="formato" id="formatoComp" value="xlsx">
                    <div class="col-md-4">
                        <label class="form-label small">Desde</label>
                        <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Hasta</label>
                        <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Tipo</label>
                        <select name="tipo_f" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (tiposComprobante() as $t): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-success" onclick="exportarComp('xlsx')"><i class="fa-solid fa-file-excel me-1"></i>Excel</button>
                    <button class="btn btn-sm btn-danger" onclick="exportarComp('pdf')"><i class="fa-solid fa-file-pdf me-1"></i>PDF</button>
                    <button class="btn btn-sm btn-primary" onclick="exportarComp('doc')"><i class="fa-solid fa-file-word me-1"></i>Word</button>
                    <button class="btn btn-sm btn-secondary" onclick="exportarComp('csv')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-folder-open me-1"></i>Otros listados</div>
            <div class="card-body d-flex flex-column gap-2 justify-content-center">
                <div class="d-flex gap-2 align-items-center">
                    <span class="small text-muted flex-fill">Catálogo de productos</span>
                    <button class="btn btn-sm btn-outline-success" onclick="exportarSencillo('productos','xlsx')">Excel</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="exportarSencillo('productos','pdf')">PDF</button>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="small text-muted flex-fill">Base de clientes</span>
                    <button class="btn btn-sm btn-outline-success" onclick="exportarSencillo('clientes','xlsx')">Excel</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="exportarSencillo('clientes','pdf')">PDF</button>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="small text-muted flex-fill">Resumen mensual de ventas</span>
                    <button class="btn btn-sm btn-outline-success" onclick="exportarSencillo('resumen','xlsx')">Excel</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="exportarSencillo('resumen','pdf')">PDF</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-calendar-days me-1"></i>Resumen mensual de ventas (emitidos)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Mes</th><th class="text-end">N°</th><th class="text-end">Subtotal</th>
                                <th class="text-end">IGV</th><th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($resumenMensual as $r): ?>
                            <?php [$a, $m] = explode('-', $r['mes']); ?>
                            <tr>
                                <td><strong><?= nombreMes((int) $m) ?> <?= $a ?></strong></td>
                                <td class="text-end"><?= $r['n'] ?></td>
                                <td class="text-end"><?= number_format($r['subtotal'], 2) ?></td>
                                <td class="text-end"><?= number_format($r['igv'], 2) ?></td>
                                <td class="text-end"><strong><?= number_format($r['total'], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$resumenMensual): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Sin ventas registradas.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-ban me-1"></i>Comprobantes anulados</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Número</th><th>Fecha</th><th>Cliente</th><th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($anulados as $a): ?>
                            <tr class="text-muted">
                                <td><?= numeroComprobante($a['serie'], (int) $a['numero']) ?></td>
                                <td><?= formatoFecha($a['fecha_emision']) ?></td>
                                <td class="text-truncate" style="max-width:140px"><?= limpiar($a['razon_social']) ?></td>
                                <td class="text-end"><?= number_format($a['total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$anulados): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Sin comprobantes anulados.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportarComp(fmt){
    document.getElementById('formatoComp').value = fmt;
    document.querySelector('form[action="index.php"]').submit();
}
function exportarSencillo(tipo, fmt){
    location.href = 'index.php?action=exportar&tipo=' + tipo + '&formato=' + fmt;
}
</script>