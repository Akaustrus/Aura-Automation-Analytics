<?php
[$desde, $hasta] = rangoFechas();
$query = 'SELECT * FROM documentos WHERE fecha BETWEEN :d AND :h';
$params = [':d'=>$desde, ':h'=>$hasta];
if (!empty($_GET['tipo_f'])) {
    $query .= ' AND tipo = :t';
    $params[':t'] = $_GET['tipo_f'];
}
$stmt = $db->prepare($query . ' ORDER BY fecha DESC, id DESC');
$stmt->execute($params);
$documentos = $stmt->fetchAll();

$editando = null;
if (!empty($_GET['editar'])) {
    $stmt = $db->prepare('SELECT * FROM documentos WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch();
}

$totBase = array_sum(array_map(fn($d)=>$d['estado']==='registrado'?(float)$d['importe']:0, $documentos));
$totIgv  = array_sum(array_map(fn($d)=>$d['estado']==='registrado'?(float)$d['igv']:0, $documentos));
$totTtl  = array_sum(array_map(fn($d)=>$d['estado']==='registrado'?(float)$d['total']:0, $documentos));
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-file-invoice me-2"></i>Documentos / Boletas
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalDoc"><i class="fa-solid fa-plus me-1"></i>Nuevo documento</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="documentos">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Tipo</label>
            <select name="tipo_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach (tiposDocumento() as $t): ?>
                    <option value="<?= $t ?>" <?= ($_GET['tipo_f'] ?? '')===$t?'selected':'' ?>><?= str_replace('_',' ',$t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-grid">
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
        </div>
    </div>
</form>

<div class="alert alert-light border small py-2 d-flex justify-content-between">
    <span>Resumen del período <strong><?= formatoFecha($desde) ?> → <?= formatoFecha($hasta) ?></strong>:</span>
    <span>Base <strong>S/ <?= number_format($totBase,2) ?></strong> ·
          IGV <strong>S/ <?= number_format($totIgv,2) ?></strong> ·
          Total <strong>S/ <?= number_format($totTtl,2) ?></strong></span>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>ID</th><th>Tipo</th><th>Número</th><th>Fecha</th><th>Emisor</th><th>RUC/DNI</th><th>Concepto</th>
                        <th class="text-end">Base</th><th class="text-end">IGV</th><th class="text-end">Total</th>
                        <th>Adjunto</th><th>Estado</th><th class="text-end" style="width:190px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($documentos as $d): ?>
                    <tr class="<?= $d['estado']==='anulado' ? 'table-warning text-muted' : '' ?>">
                        <td><?= $d['id'] ?></td>
                        <td><span class="badge bg-secondary"><?= limpiar($d['tipo']) ?></span></td>
                        <td><?= limpiar($d['numero']) ?></td>
                        <td><?= formatoFecha($d['fecha']) ?></td>
                        <td><?= limpiar($d['emisor']) ?></td>
                        <td><?= limpiar($d['ruc_dni']) ?></td>
                        <td class="text-truncate" style="max-width:150px" title="<?= limpiar($d['concepto']) ?>"><?= limpiar($d['concepto']) ?></td>
                        <td class="text-end"><?= number_format($d['importe'],2) ?></td>
                        <td class="text-end"><?= number_format($d['igv'],2) ?></td>
                        <td class="text-end"><strong><?= number_format($d['total'],2) ?></strong></td>
                        <td>
                            <?php if ($d['archivo_guardado']): ?>
                                <a href="?action=download&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-info" title="Descargar"><i class="fa-solid fa-download"></i></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= badgeEstado($d['estado']) ?></td>
                        <td class="text-end">
                            <?php $accionAnular = $d['estado']==='anulado' ? 'Reabrir este documento?' : '¿Anular este documento?'; ?>
                            <a href="?vista=documentos&editar=<?= $d['id'] ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('<?= $accionAnular ?>')">
                                <input type="hidden" name="action" value="anular_documento">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <input type="hidden" name="estado" value="<?= $d['estado']==='anulado' ? 'registrado' : 'anulado' ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Anular/Reabrir"><i class="fa-solid fa-ban"></i></button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar definitivamente?')">
                                <input type="hidden" name="action" value="eliminar_documento">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal crear / editar documento con IGV -->
<div class="modal fade" id="modalDoc" tabindex="-1" aria-labelledby="modalDocLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" enctype="multipart/form-data">
            <input type="hidden" name="action" value="guardar_documento">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalDocLabel"><?= $editando ? 'Editar documento' : 'Registrar nuevo documento' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo de documento <span class="text-danger">*</span></label>
                        <select name="tipo" class="form-select" required>
                            <?php foreach (tiposDocumento() as $t): ?>
                                <option value="<?= $t ?>" <?= ($editando['tipo'] ?? 'BOLETA') === $t ? 'selected' : '' ?>><?= str_replace('_',' ',$t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Número <span class="text-danger">*</span></label>
                        <input type="text" name="numero" class="form-control" required maxlength="30" value="<?= limpiar($editando['numero'] ?? '') ?>" placeholder="Ej. B001-000001">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" class="form-control" required value="<?= $editando['fecha'] ?? date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Emisor / Proveedor <span class="text-danger">*</span></label>
                        <input type="text" name="emisor" class="form-control" required maxlength="150" value="<?= limpiar($editando['emisor'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">RUC / DNI <span class="text-danger">*</span></label>
                        <input type="text" name="ruc_dni" class="form-control" required maxlength="20" value="<?= limpiar($editando['ruc_dni'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Base imponible (S/) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="importe" id="baseImp" class="form-control" required value="<?= $editando['importe'] ?? '' ?>" oninput="calcIgv()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">IGV (18%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="monto_igv" id="igvCalc" class="form-control" value="<?= $editando['igv'] ?? '0.00' ?>" readonly>
                            <button type="button" class="btn btn-outline-secondary" id="btnIgvToggle" onclick="calcIgv()" title="Aplicar IGV">Aplicar</button>
                        </div>
                        <input type="hidden" name="aplicar_igv" id="aplicarIgv" value="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total</label>
                        <input type="text" id="totCalc" class="form-control" value="" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="registrado" <?= ($editando['estado'] ?? 'registrado')==='registrado' ? 'selected' : '' ?>>Registrado</option>
                            <option value="anulado" <?= ($editando['estado'] ?? '')==='anulado' ? 'selected' : '' ?>>Anulado</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Concepto / Descripción <span class="text-danger">*</span></label>
                        <input type="text" name="concepto" class="form-control" required maxlength="255" value="<?= limpiar($editando['concepto'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Archivo adjunto (máx 10 MB)</label>
                        <input type="file" name="archivo" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx">
                        <?php if (!empty($editando['archivo_original'])): ?>
                            <small class="text-muted">Actual: <?= limpiar($editando['archivo_original']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function calcIgv(){
    const base = parseFloat(document.getElementById('baseImp').value) || 0;
    const igv = Math.round(base * 0.18 * 100) / 100;
    document.getElementById('igvCalc').value = igv.toFixed(2);
    document.getElementById('totCalc').value = 'S/ ' + (base + igv).toFixed(2);
    document.getElementById('aplicarIgv').value = '1';
}
document.addEventListener('DOMContentLoaded', function(){
    calcIgv();
    <?php if ($editando): ?>
        new bootstrap.Modal(document.getElementById('modalDoc')).show();
    <?php endif; ?>
});
</script>