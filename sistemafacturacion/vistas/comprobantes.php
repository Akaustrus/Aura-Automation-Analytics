<?php
[$desde, $hasta] = rangoFechas();
$query = 'SELECT c.*, cl.razon_social, cl.tipo_doc, cl.ruc_dni, cl.direccion, cl.telefono
          FROM comprobantes c JOIN clientes cl ON cl.id = c.cliente_id WHERE c.fecha_emision BETWEEN :d AND :h';
$params = [':d' => $desde, ':h' => $hasta];
if (!empty($_GET['tipo_f'])) {
    $query .= ' AND c.tipo = :t';
    $params[':t'] = $_GET['tipo_f'];
}
if (!empty($_GET['estado_f'])) {
    $query .= ' AND c.estado = :e';
    $params[':e'] = $_GET['estado_f'];
}
$stmt = $db->prepare($query . ' ORDER BY c.fecha_emision DESC, c.id DESC');
$stmt->execute($params);
$comprobantes = $stmt->fetchAll();

$editando = null;
if (!empty($_GET['editar'])) {
    $stmt = $db->prepare('SELECT c.*, cl.razon_social, cl.tipo_doc, cl.ruc_dni, cl.direccion, cl.telefono
                          FROM comprobantes c JOIN clientes cl ON cl.id = c.cliente_id WHERE c.id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch();
}
$editandoDetalle = [];
if ($editando) {
    $stmtD = $db->prepare('SELECT * FROM comprobante_detalle WHERE comprobante_id = ? ORDER BY id');
    $stmtD->execute([$editando['id']]);
    $editandoDetalle = $stmtD->fetchAll();
}

$clientes = $db->query('SELECT * FROM clientes WHERE activo=1 ORDER BY razon_social')->fetchAll();
$productos = $db->query('SELECT * FROM productos WHERE activo=1 ORDER BY descripcion')->fetchAll();
$tipos = tiposComprobante();

$totSub = array_sum(array_map(fn($x) => $x['estado']==='emitido' ? (float) $x['subtotal'] : 0, $comprobantes));
$totIgv = array_sum(array_map(fn($x) => $x['estado']==='emitido' ? (float) $x['igv'] : 0, $comprobantes));
$totTtl = array_sum(array_map(fn($x) => $x['estado']==='emitido' ? (float) $x['total'] : 0, $comprobantes));

$tasaIgv = porcentajeIgv($db);
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-file-invoice me-2"></i>Comprobantes de venta
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalComp"><i class="fa-solid fa-plus me-1"></i>Nuevo comprobante</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="comprobantes">
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
            <label class="form-label small">Tipo</label>
            <select name="tipo_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($tipos as $t): ?>
                    <option value="<?= $t ?>" <?= ($_GET['tipo_f'] ?? '')===$t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Estado</label>
            <select name="estado_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="emitido" <?= ($_GET['estado_f'] ?? '')==='emitido' ? 'selected' : '' ?>>Emitido</option>
                <option value="anulado" <?= ($_GET['estado_f'] ?? '')==='anulado' ? 'selected' : '' ?>>Anulado</option>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
        </div>
    </div>
</form>

<div class="alert alert-light border small py-2 d-flex justify-content-between">
    <span>Resumen del período <strong><?= formatoFecha($desde) ?> → <?= formatoFecha($hasta) ?></strong>:</span>
    <span>Subtotal <strong>S/ <?= number_format($totSub, 2) ?></strong> ·
          IGV <strong>S/ <?= number_format($totIgv, 2) ?></strong> ·
          Total <strong>S/ <?= number_format($totTtl, 2) ?></strong></span>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Tipo</th><th>Número</th><th>Fecha</th><th>Cliente</th><th>Condición</th>
                        <th class="text-end">Subtotal</th><th class="text-end">IGV</th><th class="text-end">Total</th>
                        <th>Estado</th><th class="text-end" style="width:230px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($comprobantes as $c): ?>
                    <tr class="<?= $c['estado']==='anulado' ? 'table-warning text-muted' : '' ?>">
                        <td><span class="badge bg-secondary"><?= limpiar($c['tipo']) ?></span></td>
                        <td><strong><?= numeroComprobante($c['serie'], (int) $c['numero']) ?></strong></td>
                        <td><?= formatoFecha($c['fecha_emision']) ?></td>
                        <td class="text-truncate" style="max-width:160px" title="<?= limpiar($c['razon_social']) ?>"><?= limpiar($c['razon_social']) ?></td>
                        <td class="small"><?= limpiar($c['condicion_pago']) ?></td>
                        <td class="text-end"><?= number_format($c['subtotal'], 2) ?></td>
                        <td class="text-end"><?= number_format($c['igv'], 2) ?></td>
                        <td class="text-end"><strong><?= number_format($c['total'], 2) ?></strong></td>
                        <td><?= badgeEstado($c['estado']) ?></td>
                        <td class="text-end">
                            <a href="?vista=ver&id=<?= $c['id'] ?>&sin_shell=1" target="_blank" class="btn btn-sm btn-outline-info" title="Ver / Imprimir"><i class="fa-solid fa-print"></i></a>
                            <a href="?action=pdf&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Descargar PDF"><i class="fa-solid fa-file-pdf"></i></a>
                            <?php if ($c['estado'] !== 'anulado'): ?>
                                <a href="?vista=comprobantes&editar=<?= $c['id'] ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>" class="btn btn-sm btn-outline-warning" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <?php endif; ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Anular el comprobante <?= numeroComprobante($c['serie'], (int) $c['numero']) ?>?')">
                                <input type="hidden" name="action" value="anular_comprobante">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular"><i class="fa-solid fa-ban"></i></button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar definitivamente?')">
                                <input type="hidden" name="action" value="eliminar_comprobante">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$comprobantes): ?>
                    <tr><td colspan="10" class="text-center text-muted py-3">Sin comprobantes en el período.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= Modal crear / editar comprobante ================= -->
<div class="modal fade" id="modalComp" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <form method="POST" class="modal-content" onsubmit="return prepararItems()">
            <input type="hidden" name="action" value="guardar_comprobante">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= $editando ? 'Editar comprobante ' . numeroComprobante($editando['serie'], (int) $editando['numero']) : 'Registrar comprobante' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <div class="col-md-2">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo" id="compTipo" class="form-select" required onchange="serieComp()">
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?= $t ?>" <?= ($editando['tipo'] ?? 'BOLETA')===$t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Serie</label>
                        <input type="text" name="serie_manual" id="compSerie" class="form-control" readonly value="<?= $editando ? limpiar($editando['serie']) : '' ?>" placeholder="Automática">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">N° correlativo</label>
                        <input type="text" id="compNumero" class="form-control" readonly value="<?= $editando ? numeroComprobante($editando['serie'], (int) $editando['numero']) : 'Automático' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha de emisión <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_emision" class="form-control" required value="<?= $editando['fecha_emision'] ?? date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha de vencimiento</label>
                        <input type="date" name="fecha_vencimiento" class="form-control" value="<?= $editando['fecha_vencimiento'] ?? '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($clientes as $cl): ?>
                                <option value="<?= $cl['id'] ?>" <?= ($editando['cliente_id'] ?? '') == $cl['id'] ? 'selected' : '' ?>><?= limpiar($cl['razon_social']) ?> (<?= $cl['tipo_doc'] ?> <?= $cl['ruc_dni'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Condición de pago</label>
                        <select name="condicion_pago" class="form-select">
                            <?php foreach (condicionesPago() as $cp): ?>
                                <option value="<?= $cp ?>" <?= ($editando['condicion_pago'] ?? 'Contado')===$cp ? 'selected' : '' ?>><?= $cp ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control" maxlength="255" value="<?= limpiar($editando['observaciones'] ?? '') ?>">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-primary fw-semibold mb-0"><i class="fa-solid fa-list me-1"></i>Detalle de ítems</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()"><i class="fa-solid fa-plus me-1"></i>Agregar ítem</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-2">
                        <thead class="table-light">
                            <tr><th style="width:160px">Producto / Servicio</th><th>Descripción</th>
                                <th style="width:80px">Cant.</th><th style="width:110px">P. Unitario</th>
                                <th style="width:70px">IGV</th><th style="width:100px" class="text-end">Importe</th>
                                <th style="width:100px" class="text-end">Total</th><th style="width:40px"></th></tr>
                        </thead>
                        <tbody id="itemsBody"></tbody>
                    </table>
                </div>

                <div class="row g-2 justify-content-end">
                    <div class="col-md-2 offset-md-6 d-flex justify-content-between"><span>Subtotal</span><strong id="totSubtotal">0.00</strong></div>
                    <div class="col-md-2 d-flex justify-content-between"><span>IGV (<?= rtrim(rtrim(number_format($tasaIgv, 2, '.', ''), '0'), '.') ?>%)</span><strong id="totIgv">0.00</strong></div>
                    <div class="col-md-2 d-flex justify-content-between"><span class="text-primary fw-semibold">TOTAL</span><strong id="totFinal" class="text-primary">0.00</strong></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i><?= $editando ? 'Guardar cambios' : 'Registrar comprobante' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
const IGV_RATE = <?= json_encode($tasaIgv / 100) ?>;
const PRODUCTOS = <?= json_encode($productos) ?>;
const EDIT_ITEMS = <?= json_encode($editandoDetalle) ?>;
let itemSeq = 0;

function filaPlantilla(){
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm item-prod">
                <option value="">— Manual —</option>
                ${PRODUCTOS.map(p => `<option value="${p.id}">${p.codigo} — ${p.descripcion}</option>`).join('')}
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm item-desc"></td>
        <td><input type="number" class="form-control form-control-sm item-cant" value="1" min="0.001" step="any" oninput="recalcular()"></td>
        <td><input type="number" class="form-control form-control-sm item-precio" value="0" min="0" step="0.01" oninput="recalcular()"></td>
        <td>
            <select class="form-select form-select-sm item-afecto" onchange="recalcular()">
                <option value="1">Sí</option><option value="0">No</option>
            </select>
        </td>
        <td class="text-end item-importe small align-middle">0.00</td>
        <td class="text-end item-total small align-middle">0.00</td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalcular();"><i class="fa-solid fa-xmark"></i></button></td>`;
    return tr;
}

function addItem(datos){
    const tbody = document.getElementById('itemsBody');
    const tr = filaPlantilla();
    tbody.appendChild(tr);
    const sel = tr.querySelector('.item-prod');
    sel.addEventListener('change', function(){
        const p = PRODUCTOS.find(x => String(x.id) === String(this.value));
        if (p) {
            tr.querySelector('.item-desc').value = p.descripcion;
            tr.querySelector('.item-precio').value = p.precio;
            tr.querySelector('.item-afecto').value = p.afecto_igv ? '1' : '0';
        }
        recalcular();
    });
    if (datos) {
        sel.value = datos.producto_id || '';
        tr.querySelector('.item-desc').value = datos.descripcion || '';
        tr.querySelector('.item-cant').value = datos.cantidad;
        tr.querySelector('.item-precio').value = datos.precio_unitario;
        tr.querySelector('.item-afecto').value = String(datos.afecto ?? 1);
    }
    recalcular();
    return tr;
}

function recalcular(){
    let sub = 0, igv = 0, tot = 0;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const cant = parseFloat(tr.querySelector('.item-cant').value) || 0;
        const precio = parseFloat(tr.querySelector('.item-precio').value) || 0;
        const afecto = tr.querySelector('.item-afecto').value === '1';
        const importe = Math.round(cant * precio * 100) / 100;
        const lIgv = afecto ? Math.round(importe * IGV_RATE * 100) / 100 : 0;
        tr.querySelector('.item-importe').textContent = importe.toFixed(2);
        tr.querySelector('.item-total').textContent = (importe + lIgv).toFixed(2);
        sub += importe; igv += lIgv; tot += importe + lIgv;
    });
    document.getElementById('totSubtotal').textContent = sub.toFixed(2);
    document.getElementById('totIgv').textContent = igv.toFixed(2);
    document.getElementById('totFinal').textContent = tot.toFixed(2);
}

// Al enviar, convertir filas visibles en campos ocultos para el servidor
function prepararItems(){
    document.querySelectorAll('#itemsBody tr').forEach((tr, i) => {
        const kit = (name, value) => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = name + '[]'; inp.value = value;
            tr.appendChild(inp);
        };
        kit('item_prod_id', tr.querySelector('.item-prod').value || '');
        kit('item_desc', tr.querySelector('.item-desc').value);
        kit('item_cant', tr.querySelector('.item-cant').value);
        kit('item_precio', tr.querySelector('.item-precio').value);
        kit('item_afecto', tr.querySelector('.item-afecto').value);
    });
    return true;
}

function serieComp(){
    const tipo = document.getElementById('compTipo').value;
    const series = <?= json_encode(array_combine($tipos, array_map(fn($t) => serieActual($db, $t), $tipos))) ?>;
    document.getElementById('compSerie').value = series[tipo] || '';
}

document.addEventListener('DOMContentLoaded', function(){
    serieComp();
    if (EDIT_ITEMS.length) {
        EDIT_ITEMS.forEach(d => addItem(d));
    } else {
        addItem();
    }
    recalcular();
    <?php if ($editando): ?>
        new bootstrap.Modal(document.getElementById('modalComp')).show();
    <?php endif; ?>
});
</script>