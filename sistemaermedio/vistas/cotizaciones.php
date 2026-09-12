<?php
[$desde, $hasta] = rangoFechas();
$query = 'SELECT c.*, cl.razon_social, v.numero venta_num
          FROM cotizaciones c JOIN clientes cl ON cl.id=c.cliente_id
          LEFT JOIN ventas v ON v.id=c.venta_id
          WHERE c.fecha BETWEEN :d AND :h';
$params = [':d' => $desde, ':h' => $hasta];
if (!empty($_GET['cliente_f'])) { $query .= ' AND c.cliente_id = :cl'; $params[':cl'] = (int) $_GET['cliente_f']; }
if (!empty($_GET['estado_f']))  { $query .= ' AND c.estado = :e';   $params[':e'] = $_GET['estado_f']; }
$stmt = $db->prepare($query . ' ORDER BY c.fecha DESC, c.id DESC');
$stmt->execute($params);
$cots = $stmt->fetchAll();

$clientes = $db->query('SELECT * FROM clientes WHERE activo=1 ORDER BY razon_social')->fetchAll();
$almacenes = $db->query('SELECT * FROM almacenes WHERE estado=1 ORDER BY nombre')->fetchAll();
$productos = $db->query('SELECT id, codigo, nombre, precio_venta FROM productos WHERE activo=1 ORDER BY nombre')->fetchAll();
$igvPct = igvPorcentaje($db);

$detalles = [];
if ($cots) {
    $ids = implode(',', array_map(fn($c) => (int) $c['id'], $cots));
    $rows = $db->query("SELECT * FROM cotizacion_detalle WHERE cotizacion_id IN ($ids) ORDER BY id")->fetchAll();
    foreach ($rows as $d) $detalles[$d['cotizacion_id']][] = $d;
}
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-file-lines me-2"></i>Cotizaciones
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalCot"><i class="fa-solid fa-plus me-1"></i>Nueva cotización</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="cotizaciones">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>"></div>
        <div class="col-md-3"><label class="form-label small">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>"></div>
        <div class="col-md-3"><label class="form-label small">Estado</label>
            <select name="estado_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="vigente" <?= ($_GET['estado_f'] ?? '')==='vigente' ? 'selected' : '' ?>>Vigente</option>
                <option value="aceptada" <?= ($_GET['estado_f'] ?? '')==='aceptada' ? 'selected' : '' ?>>Aceptada</option>
                <option value="anulada" <?= ($_GET['estado_f'] ?? '')==='anulada' ? 'selected' : '' ?>>Anulada</option>
            </select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i></button></div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Condición</th><th>Válida h.</th>
                        <th class="text-end">Total</th><th>Estado</th><th class="text-end" style="width:190px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($cots as $c): ?>
                    <tr class="<?= $c['estado']==='anulada' ? 'table-warning text-muted' : '' ?>">
                        <td><strong><?= numeroCotizacion((int) $c['numero']) ?></strong></td>
                        <td><?= formatoFecha($c['fecha']) ?></td>
                        <td class="text-truncate" style="max-width:180px" title="<?= limpiar($c['razon_social']) ?>"><?= limpiar($c['razon_social']) ?></td>
                        <td><?= badgeEstado($c['condicion']) ?></td>
                        <td><?= $c['validez'] ?> días</td>
                        <td class="text-end"><strong><?= number_format($c['total'], 2) ?></strong></td>
                        <td><?= badgeEstado($c['estado']) ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-info" title="Ver" onclick="verCot(<?= (int) $c['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                            <?php if ($c['estado'] === 'vigente'): ?>
                                <button type="button" class="btn btn-sm btn-outline-success" title="Aceptar (genera venta)" onclick="aceptarCot(<?= (int) $c['id'] ?>)"><i class="fa-solid fa-check"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Anular la cotización <?= numeroCotizacion((int) $c['numero']) ?>?')">
                                    <input type="hidden" name="action" value="anular_cotizacion">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <?= campoCsrf() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular"><i class="fa-solid fa-ban"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($c['venta_num']): ?>
                                <span class="badge bg-success ms-1" title="Generó la venta <?= numeroVenta((int) $c['venta_num']) ?>"><?= numeroVenta((int) $c['venta_num']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$cots): ?><tr><td colspan="8" class="text-center text-muted py-3">Sin cotizaciones.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal nueva cotización -->
<div class="modal fade" id="modalCot" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <form method="POST" class="modal-content" onsubmit="return prepararItems()">
            <input type="hidden" name="action" value="guardar_cotizacion">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title">Nueva cotización</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-2"><label class="form-label">Válida (días)</label>
                        <input type="number" name="validez" class="form-control" value="7" min="1"></div>
                    <div class="col-md-6"><label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($clientes as $cl): ?>
                                <option value="<?= $cl['id'] ?>"><?= limpiar($cl['razon_social']) ?> (<?= $cl['tipo_doc'] ?> <?= $cl['ruc_dni'] ?>)</option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-2"><label class="form-label">Condición</label>
                        <select name="condicion" class="form-select">
                            <option value="contado">Contado</option>
                            <option value="credito">Crédito</option>
                        </select></div>
                    <div class="col-md-12"><label class="form-label">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control" maxlength="255"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-primary fw-semibold mb-0"><i class="fa-solid fa-list me-1"></i>Detalle de la cotización</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()"><i class="fa-solid fa-plus me-1"></i>Agregar ítem</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-2">
                        <thead class="table-light">
                            <tr><th style="width:220px">Producto / Servicio</th><th>Descripción</th>
                                <th style="width:90px">Cant.</th><th style="width:120px">P. Unitario</th>
                                <th style="width:110px" class="text-end">Subtotal</th><th style="width:40px"></th></tr>
                        </thead>
                        <tbody id="itemsBody"></tbody>
                    </table>
                </div>
                <div class="row g-2 justify-content-end">
                    <div class="col-md-3 d-flex justify-content-between"><span>Subtotal</span><strong id="subFinal">0.00</strong></div>
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
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Guardar cotización</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal aceptar cotización -->
<div class="modal fade" id="modalAceptar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="aceptar_cotizacion">
            <input type="hidden" name="id" id="ac_id">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title">Aceptar cotización</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Se generará una <strong>venta</strong> con el detalle de la cotización y se descontará stock.</p>
                <label class="form-label">Almacén destino <span class="text-danger">*</span></label>
                <select name="almacen_id" class="form-select">
                    <?php foreach ($almacenes as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= limpiar($a['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-check me-1"></i>Aceptar y generar venta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal ver detalle -->
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
const IGV = <?= $igvPct ?>;
const DETALLES = <?= json_encode($detalles) ?>;
const COTS = <?= json_encode($cots) ?>;

function filaPlantilla(){
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm item-prod">
                <option value="">— Manual —</option>
                ${PRODUCTOS.map(p => `<option value="${p.id}">${p.codigo} — ${p.nombre}</option>`).join('')}
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm item-desc"></td>
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
        if (p) { tr.querySelector('.item-desc').value = p.nombre; tr.querySelector('.item-precio').value = p.precio_venta; }
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
        kit('item_desc', tr.querySelector('.item-desc').value);
        kit('item_cant', tr.querySelector('.item-cant').value);
        kit('item_precio', tr.querySelector('.item-precio').value);
    });
    return true;
}

function aceptarCot(id){ document.getElementById('ac_id').value = id; new bootstrap.Modal(document.getElementById('modalAceptar')).show(); }

function verCot(id){
    const c = COTS.find(x => String(x.id) === String(id));
    if (!c) return;
    document.getElementById('verTitulo').innerHTML = 'Cotización ' + 'CO-' + String(c.numero).padStart(4, '0') + ' — ' + c.razon_social;
    document.getElementById('verCabecera').innerHTML =
        'Fecha: <strong>' + fmtFecha(c.fecha) + '</strong> · Condición: <strong>' + (c.condicion === 'contado' ? 'Contado' : 'Crédito') + '</strong> · Estado: ' + c.estado + (c.observaciones ? ' · Obs.: ' + c.observaciones : '');
    const tbody = document.getElementById('verItems');
    tbody.innerHTML = '';
    let sub = 0;
    (DETALLES[id] || []).forEach(d => {
        sub += parseFloat(d.subtotal) || 0;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${d.descripcion}</td><td class="text-end">${fmtNum(d.cantidad)}</td>
                        <td class="text-end">${parseFloat(d.precio_unitario).toFixed(2)}</td>
                        <td class="text-end">${parseFloat(d.subtotal).toFixed(2)}</td>`;
        tbody.appendChild(tr);
    });
    document.getElementById('verSub').textContent = sub.toFixed(2);
    document.getElementById('verTotal').textContent = parseFloat(c.total).toFixed(2);
    new bootstrap.Modal(document.getElementById('modalVer')).show();
}

function fmtFecha(f){ const p = f.split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : f; }
function fmtNum(n){ n = parseFloat(n); return (n % 1 === 0) ? n.toFixed(0) : n.toFixed(3).replace(/0+$/, '').replace(/\.$/, ''); }

document.addEventListener('DOMContentLoaded', function(){ if (document.getElementById('itemsBody')) { addItem(); recalcular(); } });
</script>