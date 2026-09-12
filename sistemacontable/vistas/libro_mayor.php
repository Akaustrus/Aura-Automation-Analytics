<?php
[$desde, $hasta] = rangoFechas();
$cuentaFiltro = (int) ($_GET['cuenta_id'] ?? 0);

$cuentas = $db->query('SELECT id, codigo, nombre FROM cuentas ORDER BY codigo')->fetchAll();

$sql = 'SELECT ad.id, ad.debe, ad.haber, c.codigo, c.nombre, c.tipo, a.fecha, a.glosa, a.numero
        FROM asiento_detalle ad
        JOIN asientos a ON a.id = ad.asiento_id
        JOIN cuentas c ON c.id = ad.cuenta_id
        WHERE a.fecha BETWEEN :desde AND :hasta';
$params = [':desde' => $desde, ':hasta' => $hasta];
if ($cuentaFiltro > 0) {
    $sql .= ' AND ad.cuenta_id = :cid';
    $params[':cid'] = $cuentaFiltro;
}
$sql .= ' ORDER BY c.codigo, ad.id';
$movimientos = $db->prepare($sql);
$movimientos->execute($params);
$movimientos = $movimientos->fetchAll();

// Agrupar por cuenta
$cuentasMayor = [];
foreach ($movimientos as $m) {
    $key = $m['codigo'] . ' ' . $m['nombre'];
    if (!isset($cuentasMayor[$key])) {
        $cuentasMayor[$key] = ['codigo' => $m['codigo'], 'nombre' => $m['nombre'], 'tipo' => $m['tipo'], 'movs' => []];
    }
    $cuentasMayor[$key]['movs'][] = $m;
}
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-list me-2"></i>Libro Mayor
    <a class="btn btn-sm btn-outline-success float-end"
       href="?action=exportar&tipo=libro_mayor&formato=xlsx&desde=<?= $desde ?>&hasta=<?= $hasta ?>&cuenta_id=<?= $cuentaFiltro ?>">
        <i class="fa-brands fa-file-excel me-1"></i>Exportar</a>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="libro_mayor">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label small">Cuenta</label>
            <select name="cuenta_id" class="form-select form-select-sm">
                <option value="0">Todas</option>
                <?php foreach ($cuentas as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $cuentaFiltro === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= $c['codigo'] ?> — <?= limpiar($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
        </div>
    </div>
</form>

<?php if (!$cuentasMayor): ?>
    <div class="alert alert-info">No hay movimientos en el período.</div>
<?php else: ?>
    <?php foreach ($cuentasMayor as $key => $cuenta):
        $saldo = 0;
        $totalD = $totalH = 0;
        foreach ($cuenta['movs'] as $m) {
            $totalD += (float) $m['debe'];
            $totalH += (float) $m['haber'];
            $saldo  += (float) $m['debe'] - (float) $m['haber'];
        }
        $saldoTxt = ($saldo > 0 ? 'Deudor' : 'Acreedor') . ' ' . formatoMoneda(abs($saldo));
    ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><strong><?= $cuenta['codigo'] ?></strong> — <?= limpiar($cuenta['nombre']) ?>
                <span class="badge bg-info text-dark ms-2"><?= ucfirst($cuenta['tipo']) ?></span></span>
            <span class="small">Saldo: <strong><?= $saldoTxt ?></strong></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Fec.</th><th>Glosa</th><th>N° Asiento</th><th class="text-end">Debe</th><th class="text-end">Haber</th><th class="text-end">Saldo</th></tr>
                    </thead>
                    <tbody>
                    <?php $corre = 0; ?>
                    <?php foreach ($cuenta['movs'] as $m): $corre += (float)$m['debe'] - (float)$m['haber']; ?>
                        <tr>
                            <td><?= formatoFecha($m['fecha']) ?></td>
                            <td><?= limpiar($m['glosa']) ?></td>
                            <td><?= $m['numero'] ?></td>
                            <td class="text-end"><?= $m['debe']>0 ? number_format($m['debe'],2) : '' ?></td>
                            <td class="text-end"><?= $m['haber']>0 ? number_format($m['haber'],2) : '' ?></td>
                            <td class="text-end"><strong><?= number_format($corre,2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3">Total <?= $cuenta['codigo'] ?></th>
                            <th class="text-end"><?= number_format($totalD,2) ?></th>
                            <th class="text-end"><?= number_format($totalH,2) ?></th>
                            <th class="text-end"><?= number_format($saldo,2) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>