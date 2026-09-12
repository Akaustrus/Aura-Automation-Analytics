<?php
$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT d.*, p.periodo, p.tipo_planilla, p.estado, p.fecha_generacion,
                             e.dni, e.nombres, e.apellidos, e.cargo, e.area, e.regimen, e.sueldo_base,
                             e.banco, e.cuenta_bancaria, e.cuspp, e.tipo_contrato, e.fecha_ingreso, e.portal_token
                      FROM detalle_planilla d
                      JOIN planillas p ON p.id = d.planilla_id
                      JOIN empleados e ON e.id = d.empleado_id
                      WHERE d.id = ?');
$stmt->execute([$id]);
$b = $stmt->fetch();

if (!$b) {
    echo '<div class="container py-5"><div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>Boleta no encontrada.</div></div>';
    return;
}

$detalle = json_decode((string) $b['detalle'], true) ?: [];
$bonosDet  = $detalle['bonos_det'] ?? [];
$otrosDet  = $detalle['otros_det'] ?? [];

$ingresos = [
    ['Sueldo base', (float) $b['sueldo_base']],
    ['Asignación familiar (10% RMV)', (float) $b['asignacion_familiar']],
    ['Horas extras (' . number_format($b['horas_extra'],2) . ' h)', (float) $b['horas_extra_monto']],
];
foreach ($bonosDet as $txt) $ingresos[] = [$txt, 0.0];
$ingresos[] = ['Bonos y conceptos', (float) $b['bonos']];
$totalIngreso = (float) $b['sueldo_bruto'];

$tasaTxt = match ($b['regimen']) {
    'onp' => 'ONP ' . numeroTasa(parametro($db, 'TASA_ONP')) . '%',
    'afp' => 'AFP ' . numeroTasa(parametro($db, 'TASA_AFP')) . '%',
    default => 'Sin descuento',
};
$descuentos = [];
if ($b['descuento'] > 0) $descuentos[] = ['Descuento de ley — ' . $tasaTxt, (float) $b['descuento']];
if ($b['renta'] > 0) $descuentos[] = ['Impuesto a la renta (5ta categoría)', (float) $b['renta']];
foreach ($otrosDet as $txt) $descuentos[] = [$txt, 0.0];
if ($b['adelantos'] > 0) $descuentos[] = ['Adelantos / préstamos', (float) $b['adelantos']];
$totalDescuento = $b['descuento'] + $b['renta'] + $b['adelantos'];

$empresa = json_encode([
    'r' => $b['dni'], 'p' => $b['periodo'], 't' => $b['tipo_planilla'],
    'n' => number_format((float) $b['neto'], 2),
], JSON_UNESCAPED_UNICODE);
?>
<div class="container py-4">
    <div class="d-flex justify-content-end gap-2 mb-3 no-print">
        <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Imprimir</button>
        <?php if ($b['estado'] === 'emitida'): ?>
            <a href="?action=exportar&tipo=boleta&id=<?= $b['id'] ?>&formato=pdf" class="btn btn-outline-danger"><i class="fa-solid fa-file-pdf me-1"></i>Boleta PDF</a>
            <a href="?action=exportar&tipo=boleta&id=<?= $b['id'] ?>&formato=xlsx" class="btn btn-outline-success"><i class="fa-solid fa-file-excel me-1"></i>Excel</a>
        <?php endif; ?>
        <a href="?vista=planillas<?= $b['estado'] === 'emitida' ? '' : '' ?>" class="btn btn-secondary"><i class="fa-solid fa-xmark me-1"></i>Cerrar</a>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body p-4">
            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="app-logo" style="width:52px;height:52px;font-size:22px"><i class="fa-solid fa-building-columns"></i></div>
                    <div>
                        <h4 class="mb-0">Aura Automation &amp; Analytics S.A.C.</h4>
                        <div class="text-muted small">RUC 20512345678 · Av. Principal 123, Miraflores, Lima<br>Tel. (01) 555-0000 · planillas@auraautomation.com</div>
                    </div>
                </div>
                <div class="text-end">
                    <h3 class="text-primary mb-1">BOLETA DE PAGO</h3>
                    <span class="badge bg-secondary"><?= nombreTipoPlanilla($b['tipo_planilla']) ?></span>
                    <div class="text-muted small mt-1">Período: <strong><?= nombrePeriodo($b['periodo']) ?></strong></div>
                    <div class="text-muted small">Emisión: <?= formatoFecha($b['fecha_generacion']) ?></div>
                </div>
            </div>

            <!-- Datos del trabajador -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="small text-muted">Trabajador</div>
                    <div class="fw-semibold"><?= limpiar($b['apellidos']) ?>, <?= limpiar($b['nombres']) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="small text-muted">DNI</div>
                    <div class="fw-semibold"><?= limpiar($b['dni']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Cargo</div>
                    <div class="fw-semibold"><?= limpiar($b['cargo']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Área</div>
                    <div class="fw-semibold"><?= limpiar($b['area']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">Régimen de pensiones</div>
                    <div class="fw-semibold"><?= nombreRegimen($b['regimen']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">CUSPP</div>
                    <div class="fw-semibold"><?= limpiar($b['cuspp']) ?: '—' ?></div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">Depósito de pago</div>
                    <div class="fw-semibold"><?= limpiar($b['banco']) ?><?= $b['banco'] ? ': ' . limpiar($b['cuenta_bancaria']) : '' ?></div>
                </div>
            </div>

            <!-- Desglose -->
            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="text-success fw-semibold"><i class="fa-solid fa-arrow-trend-up me-1"></i>Ingresos</h6>
                    <table class="table table-sm mb-0">
                        <tbody>
                        <?php foreach ($ingresos as [$c, $m]): ?>
                            <tr>
                                <td><?= limpiar($c) ?></td>
                                <td class="text-end"><?= $m > 0 ? number_format($m,2) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-success fw-bold">
                            <td>TOTAL INGRESOS (Sueldo bruto)</td>
                            <td class="text-end"><?= number_format($totalIngreso,2) ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="text-danger fw-semibold"><i class="fa-solid fa-arrow-trend-down me-1"></i>Descuentos</h6>
                    <table class="table table-sm mb-0">
                        <tbody>
                        <?php foreach ($descuentos as [$c, $m]): ?>
                            <tr>
                                <td><?= limpiar($c) ?></td>
                                <td class="text-end"><?= $m > 0 ? '(' . number_format($m,2) . ')' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-danger fw-bold">
                            <td>TOTAL DESCUENTOS</td>
                            <td class="text-end">(<?= number_format($totalDescuento,2) ?>)</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Aportes empleador y neto -->
            <div class="row g-3 mt-3">
                <div class="col-md-4">
                    <div class="border rounded p-2 bg-light small">
                        <span class="text-muted">ESSALUD (aporte empleador, <?= parametro($db,'TASA_ESSALUD') ?>%):</span>
                        <strong>S/ <?= number_format($b['essalud'],2) ?></strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-2 bg-light small">
                        <span class="text-muted">Días laborados / faltas:</span>
                        <strong><?= (int)$b['dias_laborados'] ?> / <?= (int)$b['dias_faltas'] ?></strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 bg-success-subtle text-center">
                        <div class="small text-muted">NETO A PAGAR</div>
                        <div class="fs-3 fw-bold text-success">S/ <?= number_format($b['neto'],2) ?></div>
                    </div>
                </div>
            </div>

            <!-- QR + firma -->
            <div class="d-flex justify-content-between align-items-end mt-4">
                <div class="d-flex align-items-center gap-3">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=<?= rawurlencode($empresa) ?>&color=0f766e" alt="QR" width="90" height="90" class="border rounded p-1">
                    <div class="small text-muted">Escanea el código con tu<br>celular para verificar la boleta.<br>Código: <?= strtoupper(substr(sha1($b['id'] . $b['periodo'] . $b['dni']),0,10)) ?></div>
                </div>
                <div class="text-center me-4">
                    <div class="border-bottom border-dark d-inline-block px-5 pb-0 mb-1"></div>
                    <div class="small text-muted">Firma del emisor (RR. HH.)</div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center small text-muted no-print">
        Documento de consulta generado por el Sistema de Planilla — Aura Automation &amp; Analytics<br>
        Este documento no requiere firma digital para fines de exhibición interna.
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: #fff !important; }
    .card { box-shadow: none !important; border: 1px solid #ccc; }
}
</style>