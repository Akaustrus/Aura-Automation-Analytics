<?php
[$desde, $hasta] = rangoFechas();
$query = 'SELECT c.*, p.razon_social, a.nombre almacen,
          (SELECT COALESCE(SUM(monto),0) FROM compra_pagos cp WHERE cp.compra_id=c.id) pagado
          FROM compras c JOIN proveedores p ON p.id=c.proveedor_id JOIN almacenes a ON a.id=c.almacen_id
          WHERE c.fecha BETWEEN :d AND :h';
$params = [':d' => $desde, ':h' => $hasta];
if (!empty($_GET['proveedor_f'])) { $query .= ' AND c.proveedor_id = :p'; $params[':p'] = (int) $_GET['proveedor_f']; }
if (!empty($_GET['condicion_f'])) { $query .= ' AND c.condicion = :co'; $params[':co'] = $_GET['condicion_f']; }
$stmt = $db->prepare($query . ' ORDER BY c.fecha DESC, c.id DESC');
$stmt->execute($params);
$compras = $stmt->fetchAll();

$proveedores = $db->query('SELECT * FROM proveedores WHERE activo=1 ORDER BY razon_social')->fetchAll();
$almacenes = $db->query('SELECT * FROM almacenes WHERE estado=1 ORDER BY nombre')->fetchAll();
$productos = $db->query('SELECT id, codigo, nombre, precio_compra FROM productos WHERE activo=1 AND controla_stock=1 ORDER BY nombre')->fetchAll();
$ocs = $db->query('SELECT id, numero, proveedor_id FROM ordenes_compra WHERE estado="pendiente" ORDER BY numero')->fetchAll();
$igvPct = igvPorcentaje($db);

$detalles = [];
if ($compras) {
    $ids = implode(',', array_map(fn($c) => (int) $c['id'], $compras));
    $rows = $db->query("SELECT * FROM compra_detalle WHERE compra_id IN ($ids) ORDER BY id")->fetchAll();
    foreach ($rows as $d) $detalles[$d['compra_id']][] = $d;
}
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-cart-flatbed me-2"></i>Compras
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalCompra"><i class="fa-solid fa-plus me-1"></i>Nueva compra</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="compras">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>"></div>
        <div class="col-md-3"><label class="form-label small">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>"></div>
        <div class="col-md-3"><label class="form-label small">Condición</label>
            <select name="condicion_f" class="form-select form-select-sm">
                <option value="">Todas</option>
                <option value="contado" <?= ($_GET['condicion_f'] ?? '')==='contado' ? 'selected' : '' ?>>Contado</option>
                <option value="credito" <?= ($_GET['condicion_f'] ?? '')==='credito' ? 'selected' : '' ?>>Crédito</option>
            </select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i></button></div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Número</th><th>Fecha</th><th>Proveedor</th><th>Tipo doc.</th><th>Almacén</th>
                        <th class="text-end">Total</th><th>Condición</th><th class="text-end">Pagado</th>
                        <th class="text-end">Saldo</th><th>Estado</th><th class="text-end" style="width:90px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($compras as $c): ?>
                    <?php $saldo = (float) $c['total'] - (float) $c['pagado']; ?>
                    <tr class="<?= $c['estado']==='anulada' ? 'table-warning text-muted' : '' ?>">
                        <td><strong><?= numeroCompra((int) $c['numero']) ?></strong></td>
                        <td><?= formatoFecha($c['fecha']) ?></td>
                        <td class="text-truncate" style="max-width:150px" title="<?= limpiar($c['razon_social']) ?>"><?= limpiar($c['razon_social']) ?></td>
                        <td><?= badgeEstado($c['tipo_doc']) ?></td>
                        <td class="text-truncate" style="max-width:110px" title="<?= limpiar($c['almacen']) ?>"><?= limpiar($c['almacen']) ?></td>
                        <td class="text-end"><strong><?= number_format($c['total'], 2) ?></strong></td>
                        <td><?= badgeEstado($c['condicion']) ?></td>
                        <td class="text-end"><?= number_format($c['pagado'], 2) ?></td>
                        <td class="text-end text-<?= $saldo > 0 ? 'danger' : 'success' ?>"><strong><?= number_format(max(0, $saldo), 2) ?></strong></td>
                        <td><?= badgeEstado($c['estado']) ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-info" title="Ver" onclick="verCompra(<?= (int) $c['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                            <?php if ($c['estado'] === 'emitida'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Anular la compra <?= numeroCompra((int) $c['numero']) ?>? Se restará el stock ingresado.')">
                                    <input type="hidden" name="action" value="anular_compra">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <?= campoCsrf() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular"><i class="fa-solid fa-ban"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$compras): ?><tr><td colspan="11" class="text-center text-muted py-3">Sin compras.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal nueva compra -->
<div class="modal fade" id="modalCompra" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <form method="POST" class="modal-content" onsubmit="return prepararItems()">
            <input type="hidden" name="action" value="guardar_compra">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title">Nueva compra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-2"><label class="form-label">Tipo doc.</label>
                        <select name="tipo_doc" class="form-select">
                            <option value="factura">Factura</option>
                            <option value="boleta">Boleta</option>
                        </select></div>
                    <div class="col-md-2"><label class="form-label">N. documento</label>
                        <input type="text" name="nro_doc" class="form-control" placeholder="Ej. F001-0042"></div>
                    <div class="col-md-3"><label class="form-label">Proveedor <span class="text-danger">*</span></label>
                        <select name="proveedor_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= limpiar($p['razon_social']) ?> (<?= $p['ruc_dni'] ?>)</option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-3"><label class="form-label">Almacén <span class="text-danger">*</span></label>
                        <select name="almacen_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($almacenes as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= limpiar($a['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-2"><label class="form-label">Condición</label>
                        <select name="condicion" class="form-select">
                            <option value="contado">Contado</option>
                            <option value="credito">Crédito</option>
                        </select></div>
                    <div class="col-md-3"><label class="form-label">Orden de compra (opcional)</label>
                        <select name="orden_id" class="form-select" id="ocSelect">
                            <option value="">— Ninguna —</option>
                            <?php foreach ($ocs as $o): ?>
                                <option value="<?= $o['id'] ?>" data-proveedor="<?= $o['proveedor_id'] ?>"><?= numeroOrden((int) $o['numero']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-9"><label class="form-label">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control" maxlength="255"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-primary fw-semibold mb-0"><i class="fa-solid fa-list me-1"></i>Detalle (productos con stock; los precios incluyen IGV)</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()"><i class="fa-solid fa-plus me-1"></i>Agregar ítem</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-2">
                        <thead class="table-light">
                            <tr><th style="width:240px">Producto</th><th style="width:100px">Cant.</th>
                                <th style="width:130px">P. Unitario</th><th style="width:120px" class="text-end">Subtotal</th><th style="width:40px"></th></tr>
                        </thead>
                        <tbody id="itemsBody"></tbody>
                    </table>
                </div>
                <div class="row g-2 justify-content-end">
                    <div class="col-md-3 d-flex justify-content-between"><span>Subtotal (sin IGV)</span><strong id="subFinal">0.00</strong></div>
                </div>
                <div class="row g-2 justify-content-end">
                    <div class="col-md-3 d-flex justify-content-between"><span>IGV (<?= number_format($igvPct, 0) ?>%)</span><strong id="igvFinal">0.00</strong></div>
                </div>
                <div class="row g-2 justify-content-end">
                    <div class="col-md-3 d-flex justify-content-between border-top pt-1"><span>Total</span><strong class="text-primary fs-5" id="totFinal">0.00</strong></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Guardar compra</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal ver -->
<div class="modal fade" id="modalVer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="verTitulo"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="small text-muted mb-2" id="verCabecera"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light"><tr><th>Descripción</th><th class="text-end">Cant.</th>
                            <th class="text-end">P. unitario</th><th class="text-end">Subtotal</th></tr></thead>
                        <tbody id="verItems"></tbody>
                        <tfoot>
                            <tr><th colspan="3" class="text-end">Subtotal</th><th class="text-end" id="verSub"></th></tr>
                            <tr><th colspan="3" class="text-end">IGV</th><th class="text-end" id="verIgv"></th></tr>
                            <tr><th colspan="3" class="text-end">Total</th><th class="text-end" id="verTotal"></th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>

<script>
const PRODUCTOS = <?= json_encode($productos) ?>;
const OCS = <?= json_encode($ocs) ?>;
const DETALLES = <?= json_encode($detalles) ?>;
const COMPRAS = <?= json_encode($compras) ?>;
const IGV = <?= $igvPct ?>;

function filaPlantilla(){
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm item-prod">
                <option value="">— Manual —</option>
                ${PRODUCTOS.map(p => `<option value="${p.id}">${p.codigo} — ${p.nombre}</option>`).join('')}
            </select>
        </td>
        <td><input type="number" class="form-control form-control-sm item-cant" value="1" min="0.001" step="any" oninput="recalcular()"></td>
        <td><input type="number" class="form-control form-control-sm item-precio" value="0" min="0" step="0.01" oninput="recalcular()"></td>
        <td class="text-end item-sub align-middle">0.00</td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalcular();"><i class="fa-solid fa-xmark"></i></button></td>`;
    return tr;
}

function addItem(){
    const tbody = document.getElementById('itemsBody');
    const tr = filaPlantilla();
    tbody.appendChild(tr);
    tr.querySelector('.item-prod').addEventListener('change', function(){
        const p = PRODUCTOS.find(x => String(x.id) === String(this.value));
        if (p) tr.querySelector('.item-precio').value = p.precio_compra;
        recalcular();
    });
    if (tbody.querySelectorAll('tr').length === 1) recalcular();
}

function recalcular(){
    let base = 0;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const cant = parseFloat(tr.querySelector('.item-cant').value) || 0;
        const precio = parseFloat(tr.querySelector('.item-precio').value) || 0;
        const sub = Math.round(cant * precio * 100) / 100;
        tr.querySelector('.item-sub').textContent = sub.toFixed(2);
        base += sub;
    });
    const igv = Math.round(base * IGV) / 100;
    document.getElementById('subFinal').textContent = base.toFixed(2);
    document.getElementById('igvFinal').textContent = igv.toFixed(2);
    document.getElementById('totFinal').textContent = (base + igv).toFixed(2);
}

function prepararItems(){
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const kit = (name, value) => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = name + '[]'; inp.value = value; tr.appendChild(inp);
        };
        kit('item_prod_id', tr.querySelector('.item-prod').value || '');
        kit('item_desc', tr.querySelector('.item-prod').selectedOptions[0]?.text || '');
        kit('item_cant', tr.querySelector('.item-cant').value);
        kit('item_precio', tr.querySelector('.item-precio').value);
    });
    return true;
}

document.getElementById && document.getElementById('ocSelect')?.addEventListener?.('change', function(){
    const oc = OCS.find(x => String(x.id) === String(this.value));
    if (oc) {
        const select = document.querySelector('select[name="proveedor_id"]');
        [...select.options].forEach(o => o.selected = String(o.value) === String(oc.proveedor_id));
    }
});

function verCompra(id){
    const c = COMPRAS.find(x => String(x.id) === String(id));
    if (!c) return;
    document.getElementById('verTitulo').innerHTML = 'Compra ' + 'C-' + String(c.numero).padStart(4, '0') + ' — ' + c.razon_social;
    document.getElementById('verCabecera').innerHTML =
        'Fecha: <strong>' + fmtFecha(c.fecha) + '</strong> · Tipo: <strong>' + (c.tipo_doc === 'factura' ? 'Factura' : 'Boleta') + '</strong>' + (c.nro_doc ? ' · N°: ' + c.nro_doc : '') +
        ' · Almacén: <strong>' + c.almacen + '</strong> · Condición: <strong>' + (c.condicion === 'contado' ? 'Contado' : 'Crédito') + '</strong>' + (c.observaciones ? ' · Obs.: ' + c.observaciones : '');
    const tbody = document.getElementById('verItems');
    tbody.innerHTML = '';
    let base = 0;
    (DETALLES[id] || []).forEach(d => {
        base += parseFloat(d.subtotal) || 0;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${d.descripcion}</td><td class="text-end">${fmtNum(d.cantidad)}</td>
                        <td class="text-end">${parseFloat(d.precio_unitario).toFixed(2)}</td>
                        <td class="text-end">${parseFloat(d.subtotal).toFixed(2)}</td>`;
        tbody.appendChild(tr);
    });
    const igv = parseFloat(c.total) - base;
    document.getElementById('verSub').textContent = base.toFixed(2);
    document.getElementById('verIgv').textContent = igv.toFixed(2);
    document.getElementById('verTotal').textContent = parseFloat(c.total).toFixed(2);
    new bootstrap.Modal(document.getElementById('modalVer')).show();
}

function fmtFecha(f){ const p = f.split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : f; }
function fmtNum(n){ n = parseFloat(n); return (n % 1 === 0) ? n.toFixed(0) : n.toFixed(3).replace(/0+$/, '').replace(/\.$/, ''); }

document.addEventListener('DOMContentLoaded', function(){ if (document.getElementById('itemsBody')) { addItem(); recalcular(); } });
</script>