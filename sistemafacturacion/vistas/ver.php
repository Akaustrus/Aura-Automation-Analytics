<?php
$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT c.*, cl.razon_social, cl.tipo_doc, cl.ruc_dni, cl.direccion, cl.telefono
                      FROM comprobantes c JOIN clientes cl ON cl.id = c.cliente_id WHERE c.id = ?');
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    echo '<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>Comprobante no encontrado.</div>';
    return;
}
$detalle = [];
if ($c) {
    $stmtD = $db->prepare('SELECT * FROM comprobante_detalle WHERE comprobante_id = ? ORDER BY id');
    $stmtD->execute([$c['id']]);
    $detalle = $stmtD->fetchAll();
}
$empresa = datosEmpresa($db);
$anulado = $c['estado'] === 'anulado';
?>
<style>
    @media print { .no-print { display: none !important; } }
    body { background: #fff; }
    .factura { max-width: 820px; margin: 0 auto; padding: 24px; font-size: 0.9rem; }
    .factura .doc-box { border: 1px solid #dee2e6; border-radius: 10px; }
    .factura table.items td, .factura table.items th { padding: 6px 8px; border: 1px solid #dee2e6; }
    .factura table.items th { background: #f0fdfa; color: #0f766e; font-size: 0.8rem; text-transform: uppercase; }
    .anulado-stamp { color: #dc2626; font-size: 3rem; font-weight: 800; opacity: .25; position: absolute; top: 40%; left: 50%; transform: translate(-50%,-50%) rotate(-18deg); }
</style>

<div class="factura position-relative">
    <?php if ($anulado): ?><div class="anulado-stamp">ANULADO</div><?php endif; ?>

    <div class="d-flex justify-content-between no-print mb-3">
        <a href="?vista=comprobantes" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        <div>
            <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Imprimir</button>
            <a href="?action=pdf&id=<?= $c['id'] ?>" class="btn btn-outline-primary"><i class="fa-solid fa-file-pdf me-1"></i>Descargar PDF</a>
        </div>
    </div>

    <div class="doc-box p-4">
        <!-- Encabezado -->
        <div class="row mb-4">
            <div class="col-7">
                <h4 class="text-teal fw-bold mb-1" style="color:#0d9488"><?= limpiar($empresa['razon_social']) ?></h4>
                <div class="text-muted small">RUC: <?= limpiar($empresa['ruc']) ?></div>
                <?php if ($empresa['direccion']): ?><div class="text-muted small"><?= limpiar($empresa['direccion']) ?></div><?php endif; ?>
                <?php if ($empresa['telefono'] || $empresa['correo']): ?>
                    <div class="text-muted small">Tel: <?= limpiar($empresa['telefono']) ?> · <?= limpiar($empresa['correo']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-5 text-end">
                <div class="p-3 rounded" style="background:#0d9488;color:#fff">
                    <div class="fw-bold fs-5"><?= limpiar($c['tipo']) ?></div>
                    <div class="fs-6"><?= numeroComprobante($c['serie'], (int) $c['numero']) ?></div>
                </div>
                <table class="table table-sm table-borderless mb-0 mt-2 small text-end">
                    <tr><td class="text-start text-muted">Fecha de emisión</td><td><strong><?= formatoFecha($c['fecha_emision']) ?></strong></td></tr>
                    <tr><td class="text-start text-muted">Vencimiento</td><td><strong><?= $c['fecha_vencimiento'] ? formatoFecha($c['fecha_vencimiento']) : '—' ?></strong></td></tr>
                    <tr><td class="text-start text-muted">Condición de pago</td><td><strong><?= limpiar($c['condicion_pago']) ?></strong></td></tr>
                </table>
            </div>
        </div>

        <!-- Cliente -->
        <div class="mb-4 p-3 rounded" style="background:#f0fdfa">
            <div class="fw-semibold text-primary mb-1">DATOS DEL CLIENTE</div>
            <div class="row small">
                <div class="col-5"><strong>Señor(es):</strong> <?= limpiar($c['razon_social']) ?></div>
                <div class="col-3"><strong><?= $c['tipo_doc'] ?>:</strong> <?= limpiar($c['ruc_dni']) ?></div>
                <div class="col-4"><strong>Teléfono:</strong> <?= limpiar($c['telefono']) ?: '—' ?></div>
                <div class="col-12 mt-1"><strong>Dirección:</strong> <?= limpiar($c['direccion']) ?: '—' ?></div>
            </div>
        </div>

        <!-- Ítems -->
        <table class="table items mb-3">
            <thead>
                <tr><th style="width:40px">#</th><th>Descripción</th><th class="text-center" style="width:70px">Cant.</th>
                    <th class="text-end" style="width:110px">P. Unitario</th><th class="text-end" style="width:100px">Importe</th>
                    <th class="text-end" style="width:90px">IGV</th><th class="text-end" style="width:110px">Total</th></tr>
            </thead>
            <tbody>
            <?php $n = 0; foreach ($detalle as $d): $n++; ?>
                <tr>
                    <td class="text-center"><?= $n ?></td>
                    <td><?= limpiar($d['descripcion']) ?></td>
                    <td class="text-center"><?= rtrim(rtrim(number_format($d['cantidad'], 3, '.', ''), '0'), '.') ?></td>
                    <td class="text-end"><?= number_format($d['precio_unitario'], 2) ?></td>
                    <td class="text-end"><?= number_format($d['importe'], 2) ?></td>
                    <td class="text-end"><?= number_format($d['igv'], 2) ?></td>
                    <td class="text-end"><?= number_format($d['total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totales -->
        <div class="row">
            <div class="col-6">
                <div class="small text-muted mb-2"><strong>SON:</strong> <?= limpiar($c['letras']) ?></div>
                <?php if ($c['observaciones']): ?>
                    <div class="small text-muted"><strong>Observaciones:</strong> <?= limpiar($c['observaciones']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Subtotal</td><td class="text-end">S/ <?= number_format($c['subtotal'], 2) ?></td></tr>
                    <tr><td class="text-muted">IGV (<?= rtrim(rtrim(number_format(porcentajeIgv($db), 2, '.', ''), '0'), '.') ?>%)</td><td class="text-end">S/ <?= number_format($c['igv'], 2) ?></td></tr>
                    <tr class="fw-bold"><td class="text-primary" style="color:#0d9488">TOTAL</td><td class="text-end fs-5" style="color:#0d9488">S/ <?= number_format($c['total'], 2) ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="text-center text-muted small mt-4">
        Aura Automation &amp; Analytics — Sistema de Facturación · Generado el <?= date('d/m/Y H:i') ?>
    </div>
</div>