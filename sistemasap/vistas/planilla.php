<?php
$periodos = $db->query('SELECT * FROM periodos_planilla ORDER BY periodo DESC')->fetchAll();
$periodoAct = $_GET['periodo'] ?? date('Y-m');

$stmtP = $db->prepare('SELECT * FROM periodos_planilla WHERE periodo=?');
$stmtP->execute([$periodoAct]);
$periodoInfo = $stmtP->fetch();

$boletas = [];
if ($periodoInfo) {
    $stmtB = $db->prepare('SELECT b.*, e.nombre, e.apellidos, e.codigo, c.nombre AS cargo FROM boletas_pago b
                           JOIN empleados e ON e.id=b.empleado_id JOIN cargos c ON c.id=e.cargo_id
                           WHERE b.periodo_id=? ORDER BY e.apellidos');
    $stmtB->execute([$periodoInfo['id']]);
    $boletas = $stmtB->fetchAll();
}
$totalNomina = (float) $db->query('SELECT COALESCE(SUM(salario),0) FROM empleados WHERE estado="activo"')->fetchColumn();
$periodosCerrados = (int) $db->query('SELECT COUNT(*) FROM periodos_planilla WHERE estado="cerrado"')->fetchColumn();
$ultimaPlanilla = (float) $db->query('SELECT COALESCE(SUM(total_neto),0) FROM periodos_planilla ORDER BY periodo DESC LIMIT 1')->fetchColumn();
?>

<section class="page-heading module-page-heading mb-4">
    <div>
        <span class="page-eyebrow">Planilla</span>
        <h1 class="page-title mb-1">Pagos y remuneraciones</h1>
        <p class="page-subtitle mb-0">Generación de planillas mensuales, aportes, descuentos y boletas de pago.</p>
    </div>
    <form method="post" class="d-flex gap-2">
        <?= campoCsrf() ?>
        <input type="month" name="periodo" class="form-control form-control-sm w-auto" value="<?= $periodoAct ?>">
        <button type="submit" name="action" value="generar_planilla" class="btn btn-primary btn-sm"><i class="fa-solid fa-refresh me-1"></i>Generar planilla</button>
    </form>
</section>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat stat-2"><div class="stat-icon"><i class="fa-solid fa-user-tie"></i></div><div class="stat-num"><?= (int) $db->query('SELECT COUNT(*) FROM empleados WHERE estado="activo"')->fetchColumn() ?></div><div class="stat-label">Trabajadores activos</div></div></div>
    <div class="col-md-3"><div class="stat stat-1"><div class="stat-icon"><i class="fa-solid fa-wallet"></i></div><div class="stat-num"><?= formatoMoneda($totalNomina) ?></div><div class="stat-label">Nómina mensual bruta</div></div></div>
    <div class="col-md-3"><div class="stat stat-3"><div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div><div class="stat-num"><?= formatoMoneda($ultimaPlanilla) ?></div><div class="stat-label">Última planilla neta</div></div></div>
    <div class="col-md-3"><div class="stat stat-5"><div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div><div class="stat-num"><?= $periodosCerrados ?></div><div class="stat-label">Períodos cerrados</div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-calendar-days me-1"></i>Períodos de planilla</span>
        <a href="index.php?vista=planilla" class="btn btn-sm btn-outline-secondary">Actual</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Período</th><th>Desde</th><th>Hasta</th><th class="text-end">Bruto</th><th class="text-end">Descuentos</th><th class="text-end">Neto</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($periodos as $per): ?>
                    <tr class="<?= $per['periodo'] === $periodoAct ? 'table-primary' : '' ?>">
                        <td><strong><?= limpiar($per['periodo']) ?></strong></td>
                        <td><?= formatoFecha($per['inicio']) ?></td>
                        <td><?= formatoFecha($per['fin']) ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $per['total_bruto']) ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $per['total_descuentos']) ?></td>
                        <td class="text-end"><strong><?= formatoMoneda((float) $per['total_neto']) ?></strong></td>
                        <td><?= badgeEstado($per['estado']) ?></td>
                        <td class="text-end">
                            <a href="index.php?vista=planilla&periodo=<?= $per['periodo'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-eye"></i></a>
                            <?php if ($per['estado'] === 'abierto'): ?>
                                <form method="post" class="d-inline" data-confirm="¿Cerrar período <?= $per['periodo'] ?>?">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="id" value="<?= $per['id'] ?>">
                                    <button type="submit" name="action" value="cerrar_periodo" class="btn btn-sm btn-outline-warning"><i class="fa-solid fa-lock"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($periodoInfo): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-file-invoice-dollar me-1"></i>Boletas de pago — <?= limpiar($periodoAct) ?></span>
        <span class="badge bg-primary-subtle text-primary"><?= count($boletas) ?> boletas</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Empleado</th><th>Cargo</th><th class="text-end">Días</th><th class="text-end">Base</th><th class="text-end">Bono</th><th class="text-end">Gratif.</th><th class="text-end">CTS</th><th class="text-end">Essalud</th><th class="text-end">ONP</th><th class="text-end">Neto</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($boletas as $b): ?>
                    <tr>
                        <td><strong><?= limpiar($b['nombre']) ?> <?= limpiar($b['apellidos']) ?></strong><br><small class="text-muted"><?= limpiar($b['codigo']) ?></small></td>
                        <td><small><?= limpiar($b['cargo']) ?></small></td>
                        <td class="text-end"><?= (int)$b['dias_laborados'] ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $b['sueldo_base']) ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $b['bono']) ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $b['gratificacion']) ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $b['cts']) ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $b['essalud']) ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $b['onp']) ?></td>
                        <td class="text-end"><strong><?= formatoMoneda((float) $b['neto_pagar']) ?></strong></td>
                        <td><?= badgeEstado($b['estado']) ?></td>
                        <td class="text-end">
                            <?php if ($b['estado'] === 'generado'): ?>
                                <form method="post" class="d-inline">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                    <button type="submit" name="action" value="marcar_planilla_pagada" class="btn btn-sm btn-outline-success" title="Marcar pagada"><i class="fa-solid fa-money-bill-wave"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($boletas)): ?>
                    <tr><td colspan="12" class="text-center text-muted py-4">Sin boletas para este período. Pulsa "Generar planilla".</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
    <div class="alert alert-info"><i class="fa-solid fa-circle-info me-2"></i>No existe planilla para <strong><?= limpiar($periodoAct) ?></strong>. Pulsa "Generar planilla".</div>
<?php endif; ?>