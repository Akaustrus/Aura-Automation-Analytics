<?php
[$desde, $hasta] = rangoFechas();

// --- Estado de resultados (P&G) ---
$ingresos = $db->prepare("SELECT c.codigo,c.nombre,COALESCE(SUM(ad.haber-ad.debe),0) monto
    FROM asiento_detalle ad JOIN cuentas c ON c.id=ad.cuenta_id
    JOIN asientos a ON a.id=ad.asiento_id
    WHERE c.tipo='ingreso' AND a.fecha BETWEEN :d AND :h GROUP BY c.id ORDER BY c.codigo");
$ingresos->execute([':d'=>$desde, ':h'=>$hasta]);
$ingresos = $ingresos->fetchAll();

$egresos = $db->prepare("SELECT c.codigo,c.nombre,COALESCE(SUM(ad.debe-ad.haber),0) monto
    FROM asiento_detalle ad JOIN cuentas c ON c.id=ad.cuenta_id
    JOIN asientos a ON a.id=ad.asiento_id
    WHERE c.tipo='egreso' AND a.fecha BETWEEN :d AND :h GROUP BY c.id ORDER BY c.codigo");
$egresos->execute([':d'=>$desde, ':h'=>$hasta]);
$egresos = $egresos->fetchAll();

$totIng = array_sum(array_map(fn($r)=>(float)$r['monto'], $ingresos));
$totEgr = array_sum(array_map(fn($r)=>(float)$r['monto'], $egresos));
$resultado = $totIng - $totEgr;

// --- Balance general ---
$activos = $db->prepare("SELECT c.codigo,c.nombre,COALESCE(SUM(ad.debe-ad.haber),0) monto
    FROM asiento_detalle ad JOIN cuentas c ON c.id=ad.cuenta_id
    JOIN asientos a ON a.id=ad.asiento_id
    WHERE c.tipo='activo' AND a.fecha BETWEEN :d AND :h GROUP BY c.id ORDER BY c.codigo");
$activos->execute([':d'=>$desde, ':h'=>$hasta]);
$activos = $activos->fetchAll();

$pasivoPat = $db->prepare("SELECT c.codigo,c.nombre,c.tipo,COALESCE(SUM(ad.haber-ad.debe),0) monto
    FROM asiento_detalle ad JOIN cuentas c ON c.id=ad.cuenta_id
    JOIN asientos a ON a.id=ad.asiento_id
    WHERE c.tipo IN ('pasivo','patrimonio') AND a.fecha BETWEEN :d AND :h GROUP BY c.id ORDER BY c.codigo");
$pasivoPat->execute([':d'=>$desde, ':h'=>$hasta]);
$pasivoPat = $pasivoPat->fetchAll();

$totAct = array_sum(array_map(fn($r)=>(float)$r['monto'], $activos));
$totPasPat = array_sum(array_map(fn($r)=>(float)$r['monto'], $pasivoPat));
$diferencia = $totAct - $totPasPat;
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-chart-pie me-2"></i>Estados Financieros
    <a class="btn btn-sm btn-outline-success float-end"
       href="?action=exportar&tipo=estados&formato=xlsx&desde=<?= $desde ?>&hasta=<?= $hasta ?>">
        <i class="fa-brands fa-file-excel me-1"></i>Exportar</a>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="estados">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>">
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
        </div>
        <div class="col-md-4 text-end">
            <?php foreach (['pdf'=>'PDF','doc'=>'Word','csv'=>'CSV'] as $f=>$l): ?>
                <a class="btn btn-sm btn-outline-secondary"
                   href="?action=exportar&tipo=estados&formato=<?= $f ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>">
                    <i class="fa-solid fa-file me-1"></i><?= $l ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</form>

<div class="row g-3">
    <!-- Estado de resultados -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-chart-line me-1"></i>Estado de resultados (P&G)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light"><tr><th>Cuenta</th><th class="text-end">Ingresos</th></tr></thead>
                    <tbody>
                    <?php foreach ($ingresos as $r): ?>
                        <tr><td><?= $r['codigo'] ?> — <?= limpiar($r['nombre']) ?></td>
                            <td class="text-end"><?= number_format($r['monto'],2) ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="table-success"><td><strong>Total ingresos</strong></td>
                        <td class="text-end"><strong><?= number_format($totIng,2) ?></strong></td></tr>
                    </tbody>
                </table>
                <table class="table table-sm mb-0 align-middle">
                    <tbody>
                    <?php foreach ($egresos as $r): ?>
                        <tr><td><?= $r['codigo'] ?> — <?= limpiar($r['nombre']) ?></td>
                            <td class="text-end">(<?= number_format($r['monto'],2) ?>)</td></tr>
                    <?php endforeach; ?>
                    <tr class="table-danger"><td><strong>Total egresos</strong></td>
                        <td class="text-end"><strong>(<?= number_format($totEgr,2) ?>)</strong></td></tr>
                    <tr class="<?= $resultado>=0?'table-primary':'table-warning' ?>">
                        <td><strong><?= $resultado>=0 ? 'Utilidad' : 'Pérdida' ?></strong></td>
                        <td class="text-end"><strong><?= number_format($resultado,2) ?></strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Balance general -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-scale-balanced me-1"></i>Balance general</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Activos</th><th class="text-end">Monto</th><th>Pasivo + Patrimonio</th><th class="text-end">Monto</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $max = max(count($activos), count($pasivoPat));
                        for ($i=0; $i<$max; $i++) {
                            $a = $activos[$i] ?? null;
                            $p = $pasivoPat[$i] ?? null;
                            echo '<tr>';
                            echo $a ? "<td>{$a['codigo']} — ".limpiar($a['nombre'])."</td><td class='text-end'>".number_format($a['monto'],2)."</td>"
                                     : "<td></td><td></td>";
                            echo $p ? "<td>{$p['codigo']} — ".limpiar($p['nombre'])."</td><td class='text-end'>".number_format($p['monto'],2)."</td>"
                                     : "<td></td><td></td>";
                            echo '</tr>';
                        }
                        ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td><strong>Total activos</strong></td>
                                <td class="text-end"><strong><?= number_format($totAct,2) ?></strong></td>
                                <td><strong>Total pasivo + patrimonio</strong></td>
                                <td class="text-end"><strong><?= number_format($totPasPat,2) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="alert <?= abs($diferencia)<0.01 ? 'alert-success' : 'alert-warning' ?> mb-0 rounded-0 small">
                    <?= abs($diferencia)<0.01
                        ? '<i class="fa-solid fa-check me-1"></i>El balance CUADRA: Activos = Pasivo + Patrimonio.'
                        : '<i class="fa-solid fa-triangle-exclamation me-1"></i>Diferencia de ' . number_format($diferencia,2) .
                          ' — revisa los asientos del período.' ?>
                </div>
            </div>
        </div>
    </div>
</div>