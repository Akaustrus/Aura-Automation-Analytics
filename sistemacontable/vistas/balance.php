<?php
[$desde, $hasta] = rangoFechas();

$stmt = $db->prepare('SELECT c.id,c.codigo,c.nombre,c.tipo,
        COALESCE(SUM(ad.debe),0) debe, COALESCE(SUM(ad.haber),0) haber
      FROM asiento_detalle ad
      JOIN cuentas c ON c.id = ad.cuenta_id
      JOIN asientos a ON a.id = ad.asiento_id
      WHERE a.fecha BETWEEN :desde AND :hasta
      GROUP BY c.id
      ORDER BY c.codigo');
$stmt->execute([':desde'=>$desde, ':hasta'=>$hasta]);
$rows = $stmt->fetchAll();

$tD = $tH = $tSald = $tSalA = 0;
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-scale-balanced me-2"></i>Balance de Comprobación
    <a class="btn btn-sm btn-outline-success float-end"
       href="?action=exportar&tipo=balance_comprob&formato=xlsx&desde=<?= $desde ?>&hasta=<?= $hasta ?>">
        <i class="fa-brands fa-file-excel me-1"></i>Exportar</a>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="balance">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
        </div>
        <div class="col-md-4 text-end">
            <?php foreach (['pdf'=>'PDF','doc'=>'Word','csv'=>'CSV'] as $f=>$l): ?>
                <a class="btn btn-sm btn-outline-secondary"
                   href="?action=exportar&tipo=balance_comprob&formato=<?= $f ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>">
                    <i class="fa-solid fa-file me-1"></i><?= $l ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</form>

<div class="alert alert-light border small">
    Suma Debe = Suma Haber = <strong>S/ <?= number_format(array_sum(array_map(fn($r)=>(float)$r['debe'],$rows)),2) ?></strong>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Código</th><th>Cuenta</th><th>Tipo</th>
                        <th class="text-end">Debe</th><th class="text-end">Haber</th>
                        <th class="text-end">Saldo deudor</th><th class="text-end">Saldo acreedor</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $sald = (float)$r['debe'] - (float)$r['haber'];
                    $tD += (float)$r['debe']; $tH += (float)$r['haber'];
                    if ($sald >= 0) $tSald += $sald; else $tSalA += abs($sald);
                ?>
                    <tr>
                        <td><strong><?= $r['codigo'] ?></strong></td>
                        <td><?= limpiar($r['nombre']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= ucfirst($r['tipo']) ?></span></td>
                        <td class="text-end"><?= number_format($r['debe'],2) ?></td>
                        <td class="text-end"><?= number_format($r['haber'],2) ?></td>
                        <td class="text-end"><?= $sald>0 ? number_format($sald,2) : '' ?></td>
                        <td class="text-end"><?= $sald<0 ? number_format(abs($sald),2) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3">TOTAL</th>
                        <th class="text-end"><?= number_format($tD,2) ?></th>
                        <th class="text-end"><?= number_format($tH,2) ?></th>
                        <th class="text-end"><?= number_format($tSald,2) ?></th>
                        <th class="text-end"><?= number_format($tSalA,2) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>