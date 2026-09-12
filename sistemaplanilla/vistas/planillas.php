<?php
$tipos = ['mensual' => 'Planilla mensual', 'gratificacion' => 'Gratificación', 'cts' => 'CTS'];

$verId = (int) ($_GET['ver'] ?? 0);
$planilla = null;
$boletas  = [];
if ($verId > 0) {
    $stmt = $db->prepare('SELECT * FROM planillas WHERE id=?'); $stmt->execute([$verId]);
    $planilla = $stmt->fetch();
    if ($planilla) {
        $stmt = $db->prepare('SELECT d.*, e.dni, e.nombres, e.apellidos, e.cargo, e.area, e.regimen
                              FROM detalle_planilla d JOIN empleados e ON e.id=d.empleado_id
                              WHERE d.planilla_id=? ORDER BY e.apellidos');
        $stmt->execute([$verId]);
        $boletas = $stmt->fetchAll();
    }
}

$planillas = $db->query('SELECT * FROM planillas ORDER BY periodo DESC, id DESC')->fetchAll();
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-money-check-dollar me-2"></i>Planillas
    <?php if ($planilla): ?>
        <a href="?vista=planillas" class="btn btn-sm btn-outline-secondary float-end"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
    <?php endif; ?>
</h2>

<?php if (!$planilla): ?>

<!-- Generador -->
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Generar planilla</div>
    <div class="card-body">
        <form method="POST" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="generar_planilla">
            <?= campoCsrf() ?>
            <div class="col-md-2">
                <label class="form-label">Período</label>
                <input type="month" name="periodo" class="form-control" required value="<?= periodoActual() ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <?php foreach ($tipos as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <div class="form-text">Genera en <b>borrador</b> el cálculo completo (base, ASF, horas extra, bonos, ESSALUD, ONP/AFP, renta 5ta y adelantos) para todos los empleados activos.</div>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100"><i class="fa-solid fa-calculator me-1"></i>Generar</button>
            </div>
        </form>
    </div>
</div>

<!-- Listado -->
<div class="row g-3">
    <?php foreach ($planillas as $pl): ?>
        <div class="col-lg-6">
            <div class="card h-100 border-<?= $pl['estado'] === 'anulada' ? 'warning' : ($pl['estado'] === 'emitida' ? 'success' : 'secondary') ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="mb-1"><?= limpiar($tipos[$pl['tipo_planilla']] ?? $pl['tipo_planilla']) ?> — <?= nombrePeriodo($pl['periodo']) ?></h5>
                            <span class="text-muted small">Generada: <?= formatoFecha($pl['fecha_generacion']) ?><?= $pl['glosa'] ? ' · ' : '' ?><?= limpiar($pl['glosa']) ?></span>
                        </div>
                        <?= badgeEstado($pl['estado']) ?>
                    </div>
                    <div class="row text-center my-2 g-2">
                        <div class="col-3"><div class="small text-muted">Bruto</div><strong><?= number_format($pl['total_bruto'],0) ?></strong></div>
                        <div class="col-3"><div class="small text-muted">ESSALUD</div><strong><?= number_format($pl['total_essalud'],0) ?></strong></div>
                        <div class="col-3"><div class="small text-muted">Descuento</div><strong><?= number_format($pl['total_descuento'],0) ?></strong></div>
                        <div class="col-3"><div class="small text-muted">Neto</div><strong class="text-success"><?= number_format($pl['total_neto'],0) ?></strong></div>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <a href="?vista=planillas&ver=<?= $pl['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-list me-1"></i>Ver boletas</a>
                        <?php if ($pl['estado'] === 'borrador'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="emitir_planilla">
                                <input type="hidden" name="id" value="<?= $pl['id'] ?>">
                                <?= campoCsrf() ?>
                                <button class="btn btn-sm btn-success"><i class="fa-solid fa-check me-1"></i>Emitir</button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Anular esta planilla?')">
                                <input type="hidden" name="action" value="anular_planilla">
                                <input type="hidden" name="id" value="<?= $pl['id'] ?>">
                                <?= campoCsrf() ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-ban me-1"></i>Anular</button>
                            </form>
                        <?php elseif ($pl['estado'] === 'emitida'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Anular esta planilla? Las boletas quedarán anuladas.')">
                                <input type="hidden" name="action" value="anular_planilla">
                                <input type="hidden" name="id" value="<?= $pl['id'] ?>">
                                <?= campoCsrf() ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-ban me-1"></i>Anular</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="reabrir_planilla">
                                <input type="hidden" name="id" value="<?= $pl['id'] ?>">
                                <?= campoCsrf() ?>
                                <button class="btn btn-sm btn-outline-warning"><i class="fa-solid fa-rotate-left me-1"></i>Reabrir</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($pl['estado'] === 'emitida'): ?>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=exportar&tipo=planilla&periodo=<?= $pl['periodo'] ?>&tipo_planilla=<?= $pl['tipo_planilla'] ?>&formato=xlsx" class="btn btn-outline-primary" title="Excel"><i class="fa-solid fa-file-excel"></i></a>
                                <a href="?action=exportar&tipo=planilla&periodo=<?= $pl['periodo'] ?>&tipo_planilla=<?= $pl['tipo_planilla'] ?>&formato=pdf" class="btn btn-outline-danger" title="PDF"><i class="fa-solid fa-file-pdf"></i></a>
                                <a href="?action=exportar&tipo=planilla&periodo=<?= $pl['periodo'] ?>&tipo_planilla=<?= $pl['tipo_planilla'] ?>&formato=csv" class="btn btn-outline-secondary" title="CSV"><i class="fa-solid fa-file-csv"></i></a>
                            </div>
                        <?php endif; ?>
                        <a href="index.php?action=tregistro_txt&periodo=<?= $pl['periodo'] ?>" class="btn btn-sm btn-outline-dark" title="T-Registro PLAME (archivo plano)"><i class="fa-solid fa-file-lines me-1"></i>T-Registro</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$planillas): ?>
        <div class="col-12"><div class="alert alert-light border text-center py-5">Aún no se generó ninguna planilla. Use el formulario superior.</div></div>
    <?php endif; ?>
</div>

<?php else: ?>

<!-- Detalle / boletas -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-1"><?= limpiar($tipos[$planilla['tipo_planilla']] ?? $planilla['tipo_planilla']) ?> — <?= nombrePeriodo($planilla['periodo']) ?></h5>
                <span><?= badgeEstado($planilla['estado']) ?> <span class="text-muted ms-2"><?= limpiar($planilla['glosa']) ?></span></span>
            </div>
            <div class="text-end">
                <div>Bruto <strong><?= number_format($planilla['total_bruto'],2) ?></strong></div>
                <div>Descuento <strong><?= number_format($planilla['total_descuento'],2) ?></strong></div>
                <div>Neto <strong class="text-success"><?= number_format($planilla['total_neto'],2) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-file-invoice me-1"></i>Boletas (<?= count($boletas) ?>)</span>
        <?php if ($planilla['estado'] === 'emitida'): ?>
            <span class="small text-muted">Regímenes: <?= count(array_unique(array_column($boletas, 'regimen'))) ?> tipos de descuento aplicados automáticamente.</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>DNI</th><th>Empleado</th><th>Cargo</th><th class="text-end">Sueldo base</th><th class="text-end">Bruto</th>
                        <th class="text-end">ESSALUD</th><th class="text-end">Descuento</th><th class="text-end">Renta</th><th class="text-end">Adel.</th>
                        <th class="text-end">Neto</th><th class="text-end" style="width:180px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($boletas as $b): ?>
                    <tr>
                        <td><?= limpiar($b['dni']) ?></td>
                        <td><?= limpiar($b['apellidos']) ?>, <?= limpiar($b['nombres']) ?></td>
                        <td><?= limpiar($b['cargo']) ?></td>
                        <td class="text-end"><?= number_format($b['sueldo_base'],2) ?></td>
                        <td class="text-end"><?= number_format($b['sueldo_bruto'],2) ?></td>
                        <td class="text-end"><?= number_format($b['essalud'],2) ?></td>
                        <td class="text-end"><?= number_format($b['descuento'],2) ?></td>
                        <td class="text-end"><?= number_format($b['renta'],2) ?></td>
                        <td class="text-end"><?= number_format($b['adelantos'],2) ?></td>
                        <td class="text-end"><strong class="text-success"><?= number_format($b['neto'],2) ?></strong></td>
                        <td class="text-end">
                            <?php if ($planilla['estado'] === 'emitida'): ?>
                                <a href="?vista=boleta&id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver boleta"><i class="fa-solid fa-file-invoice"></i></a>
                                <a href="?action=exportar&tipo=boleta&id=<?= $b['id'] ?>&formato=pdf" class="btn btn-sm btn-outline-danger" title="Boleta PDF"><i class="fa-solid fa-file-pdf"></i></a>
                                <a href="?action=exportar&tipo=boleta&id=<?= $b['id'] ?>&formato=xlsx" class="btn btn-sm btn-outline-success" title="Boleta Excel"><i class="fa-solid fa-file-excel"></i></a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$boletas): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">Sin boletas. Regenere la planilla.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>