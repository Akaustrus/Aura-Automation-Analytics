<?php
[$desde, $hasta] = rangoFechas();
$query = 'SELECT c.*, p.razon_social, p.tipo_doc, p.ruc_dni FROM compras c JOIN proveedores p ON p.id=c.proveedor_id
          WHERE c.fecha BETWEEN :d AND :h';
$params = [':d' => $desde, ':h' => $hasta];
if (!empty($_GET['prov_f'])) { $query .= ' AND c.proveedor_id = :pv'; $params[':pv'] = (int) $_GET['prov_f']; }
if (!empty($_GET['estado_f'])) { $query .= ' AND c.estado = :e'; $params[':e'] = $_GET['estado_f']; }
$stmt = $db->prepare($query . ' ORDER BY c.fecha DESC, c.id DESC');
$stmt->execute($params);
$compras = $stmt->fetchAll();

$proveedores = $db->query('SELECT * FROM proveedores WHERE activo=1 ORDER BY razon_social')->fetchAll();
$productos = $db->query('SELECT * FROM productos WHERE activo=1 ORDER BY nombre')->fetchAll();

$totEmitidas = array_sum(array_map(fn($c) => $c['estado']==='emitida' ? (float) $c['total'] : 0, $compras));

$detalles = [];
if ($compras) {
    $ids = implode(',', array_map(fn($c) => (int) $c['id'], $compras));
    $rows = $db->query("SELECT * FROM compra_detalle WHERE compra_id IN ($ids) ORDER BY id")->fetchAll();
    foreach ($rows as $d) $detalles[$d['compra_id']][] = $d;
}
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-truck-fast me-2"></i>Compras
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalCompra"><i class="fa-solid fa-plus me-1"></i>Nueva compra</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="compras">
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
            <label class="form-label small">Proveedor</label>
            <select name="prov_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($proveedores as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($_GET['prov_f'] ?? '')==(string) $p['id'] ? 'selected' : '' ?>><?= limpiar($p['razon_social']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Estado</label>
            <select name="estado_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="emitida" <?= ($_GET['estado_f'] ?? '')==='emitida' ? 'selected' : '' ?>>Emitida</option>
                <option value="anulada" <?= ($_GET['estado_f'] ?? '')==='anulada' ? 'selected' : '' ?>>Anulada</option>
            </select>
        </div>
        <div class="col-md-1 d-grid">
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i></button>
        </div>
    </div>
</form>

<div class="alert alert-light border small py-2 d-flex justify-content-between">
    <span>Compras del período <strong><?= formatoFecha($desde) ?> → <?= formatoFecha($hasta) ?></strong>:</span>
    <span>Total emitido <strong><?= formatoMoneda($totEmitidas) ?></strong></span>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Número</th><th>Fecha</th><th>Proveedor</th><th>Condición</th>
                        <th class="text-end">Total</th><th>Estado</th><th class="text-end" style="width:160px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($compras as $c): ?>
                    <tr class="<?= $c['estado']==='anulada' ? 'table-warning text-muted' : '' ?>">
                        <td><strong><?= numeroCompra((int) $c['numero']) ?></strong></td>
                        <td><?= formatoFecha($c['fecha']) ?></td>
                        <td class="text-truncate" style="max-width:180px" title="<?= limpiar($c['razon_social']) ?>"><?= limpiar($c['razon_social']) ?></td>
                        <td><?= badgeEstado($c['condicion']) ?></td>
                        <td class="text-end"><strong><?= number_format($c['total'], 2) ?></strong></td>
                        <td><?= badgeEstado($c['estado']) ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-info" title="Ver detalle" onclick="verCompra(<?= (int) $c['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                            <?php if ($c['estado'] !== 'anulada'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Anular la compra <?= numeroCompra((int) $c['numero']) ?>? Se descontará el stock.')">
                                    <input type="hidden" name="action" value="anular_compra">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <?= campoCsrf() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular"><i class="fa-solid fa-ban"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$compras): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Sin compras en el período.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============ Modal nueva compra ============ -->
<div class="modal fade" id="modalCompra" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <form method="POST" class="modal-content" onsubmit="return prepararItems()">
            <input type="hidden" name="action" value="guardar_compra">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title">Registrar compra (ingresa stock)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                        <select name="proveedor_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= limpiar($p['razon_social']) ?> (<?= $p['tipo_doc'] ?> <?= $p['ruc_dni'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Condición</label>
                        <select name="condicion" class="form-select">
                            <option value="contado">Contado</option>
                            <option value="credito">Crédito</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control" maxlength="255">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-primary fw-semibold mb-0"><i class="fa-solid fa-list me-1"></i>Ítems de la compra</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()"><i class="fa-solid fa-plus me-1"></i>Agregar ítem</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-2">
                        <thead class="table-light">
                            <tr><th style="width:260px">Producto</th><th>Descripción</th>
                                <th style="width:90px">Cant.</th><th style="width:120px">P. Unitario</th>
                                <th style="width:110px" class="text-end">Subtotal</th><th style="width:40px"></th></tr>
                        </thead>
                        <tbody id="itemsBody"></tbody>
                    </table>
                </div>

                <div class="row g-2 justify-content-end">
                    <div class="col-md-3 d-flex justify-content-between"><span>Total</span><strong class="text-primary" id="totFinal">0.00</strong></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Registrar compra</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ Modal ver detalle ============ -->
<div class="modal fade" id="modalVer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verTitulo"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2" id="verCabecera"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light"><tr><th>Descripción</th><th class="text-end">Cant.</th>
                            <th class="text-end">P. unitario</th><th class="text-end">Subtotal</th></tr></thead>
                        <tbody id="verItems"></tbody>
                        <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end" id="verTotal"></th></tr></tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
const PRODUCTOS = <?= json_encode($productos) ?>;
const DETALLES = <?= json_encode($detalles) ?>;
const COMPRAS = <?= json_encode($compras) ?>;

function filaPlantilla(){
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm item-prod" required>
                <option value="">— Seleccione producto —</option>
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
        if (p) {
            tr.querySelector('.item-desc').value = p.nombre;
            tr.querySelector('.item-precio').value = p.precio_compra;
        }
        recalcular();
    });
}

function recalcular(){
    let tot = 0;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const cant = parseFloat(tr.querySelector('.item-cant').value) || 0;
        const precio = parseFloat(tr.querySelector('.item-precio').value) || 0;
        const sub = Math.round(cant * precio * 100) / 100;
        tr.querySelector('.item-sub').textContent = sub.toFixed(2);
        tot += sub;
    });
    document.getElementById('totFinal').textContent = tot.toFixed(2);
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

function verCompra(id){
    const c = COMPRAS.find(x => String(x.id) === String(id));
    if (!c) return;
    document.getElementById('verTitulo').innerHTML = 'Compra ' + campoDelPez('C-', c.numero) + ' — ' + c.razon_social;
    document.getElementById('verCabecera').innerHTML =
        'Fecha: <strong>' + fmtFecha(c.fecha) + '</strong> · Condición: <strong>' + (c.condicion === 'contado' ? 'Contado' : 'Crédito') + '</strong> · Estado: ' + (c.estado === 'emitida' ? 'Emitida' : 'Anulada') + '<br>' + (c.observaciones ? 'Obs.: ' + c.observaciones : '');
    const tbody = document.getElementById('verItems');
    tbody.innerHTML = '';
    let total = 0;
    (DETALLES[id] || []).forEach(d => {
        total += parseFloat(d.subtotal) || 0;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${d.descripcion}</td><td class="text-end">${fmtNum(d.cantidad)}</td>
                        <td class="text-end">${parseFloat(d.precio_unitario).toFixed(2)}</td>
                        <td class="text-end">${parseFloat(d.subtotal).toFixed(2)}</td>`;
        tbody.appendChild(tr);
    });
    document.getElementById('verTotal').textContent = total.toFixed(2);
    new bootstrap.Modal(document.getElementById('modalVer')).show();
}

function campoDelPez(prefix, n){ return prefix + String(n).padStart(4, '0'); }
function fmtFecha(f){ const p = f.split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : f; }
function fmtNum(n){ n = parseFloat(n); return (n % 1 === 0) ? n.toFixed(0) : n.toFixed(3).replace(/0+$/, '').replace(/\.$/, ''); }

document.addEventListener('DOMContentLoaded', function(){ addItem(); recalcular(); });
</script>