<?php
[$desde, $hasta] = rangoFechas();
$query = 'SELECT o.*, p.razon_social, a.nombre almacen, c.compranum
          FROM ordenes_compra o JOIN proveedores p ON p.id=o.proveedor_id JOIN almacenes a ON a.id=o.almacen_id
          LEFT JOIN (SELECT id, numero compranum FROM compras) c ON c.id=o.compra_id
          WHERE o.fecha BETWEEN :d AND :h';
$params = [':d' => $desde, ':h' => $hasta];
if (!empty($_GET['proveedor_f'])) { $query .= ' AND o.proveedor_id = :p'; $params[':p'] = (int) $_GET['proveedor_f']; }
if (!empty($_GET['estado_f']))  { $query .= ' AND o.estado = :e';   $params[':e'] = $_GET['estado_f']; }
$stmt = $db->prepare($query . ' ORDER BY o.fecha DESC, o.id DESC');
$stmt->execute($params);
$ocs = $stmt->fetchAll();

$proveedores = $db->query('SELECT * FROM proveedores WHERE activo=1 ORDER BY razon_social')->fetchAll();
$almacenes = $db->query('SELECT * FROM almacenes WHERE estado=1 ORDER BY nombre')->fetchAll();
$productos = $db->query('SELECT id, codigo, nombre, precio_compra FROM productos WHERE activo=1 AND controla_stock=1 ORDER BY nombre')->fetchAll();

$detalles = [];
if ($ocs) {
    $ids = implode(',', array_map(fn($o) => (int) $o['id'], $ocs));
    $rows = $db->query("SELECT * FROM orden_compra_detalle WHERE orden_id IN ($ids) ORDER BY id")->fetchAll();
    foreach ($rows as $d) $detalles[$d['orden_id']][] = $d;
}
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-clipboard-list me-2"></i>Órdenes de compra
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalOC"><i class="fa-solid fa-plus me-1"></i>Nueva orden</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="ordenes_compra">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>"></div>
        <div class="col-md-3"><label class="form-label small">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>"></div>
        <div class="col-md-3"><label class="form-label small">Estado</label>
            <select name="estado_f" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="pendiente" <?= ($_GET['estado_f'] ?? '')==='pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="recibida" <?= ($_GET['estado_f'] ?? '')==='recibida' ? 'selected' : '' ?>>Recibida</option>
                <option value="anulada" <?= ($_GET['estado_f'] ?? '')==='anulada' ? 'selected' : '' ?>>Anulada</option>
            </select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i></button></div>
    </div>
</form>

<?php if ($ocs): ?>
    <?php foreach ($ocs as $o): ?>
        <div class="card mb-4 <?= $o['estado']==='anulada' ? 'opacity-75' : '' ?>">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <i class="fa-solid fa-clipboard-list me-2"></i>
                    <strong><?= numeroOrden((int) $o['numero']) ?></strong>
                    <span class="text-muted small ms-2"><?= formatoFecha($o['fecha']) ?> · <?= limpiar($o['razon_social']) ?> · <?= limpiar($o['almacen']) ?></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?= badgeEstado($o['estado']) ?>
                    <?php if ($o['compranum']): ?>
                        <span class="badge bg-success"><?= numeroCompra((int) $o['compranum']) ?></span>
                    <?php endif; ?>
                    <?php if ($o['estado'] === 'pendiente'): ?>
                        <button class="btn btn-sm btn-outline-success" onclick="recibirOC(<?= (int) $o['id'] ?>)"><i class="fa-solid fa-download me-1"></i>Recibir</button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Anular la orden <?= numeroOrden((int) $o['numero']) ?>?')">
                            <input type="hidden" name="action" value="anular_orden_compra">
                            <input type="hidden" name="id" value="<?= $o['id'] ?>">
                            <?= campoCsrf() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular"><i class="fa-solid fa-ban"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Producto</th><th class="text-end">Cant.</th>
                        <th class="text-end">P. unitario</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($detalles[$o['id']] ?? [] as $d): ?>
                        <tr>
                            <td><?= limpiar($d['descripcion']) ?></td>
                            <td class="text-end"><?= formatoCantidad((float) $d['cantidad']) ?></td>
                            <td class="text-end"><?= number_format($d['precio_unitario'], 2) ?></td>
                            <td class="text-end"><strong><?= number_format($d['subtotal'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="alert alert-light border">No hay órdenes de compra.</div>
<?php endif; ?>

<!-- Modal nueva OC -->
<div class="modal fade" id="modalOC" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <form method="POST" class="modal-content" onsubmit="return prepararItems()">
            <input type="hidden" name="action" value="guardar_orden_compra">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title">Nueva orden de compra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-5"><label class="form-label">Proveedor <span class="text-danger">*</span></label>
                        <select name="proveedor_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= limpiar($p['razon_social']) ?> (<?= $p['ruc_dni'] ?>)</option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-4"><label class="form-label">Almacén destino <span class="text-danger">*</span></label>
                        <select name="almacen_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($almacenes as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= limpiar($a['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-12"><label class="form-label">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control" maxlength="255"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-primary fw-semibold mb-0"><i class="fa-solid fa-list me-1"></i>Detalle (productos del catálogo con precio de compra)</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()"><i class="fa-solid fa-plus me-1"></i>Agregar ítem</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-2">
                        <thead class="table-light">
                            <tr><th style="width:260px">Producto</th><th style="width:110px">Cant.</th>
                                <th style="width:130px">P. Unitario</th><th style="width:120px" class="text-end">Subtotal</th><th style="width:40px"></th></tr>
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
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Guardar orden</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal confirmar recepción -->
<div class="modal fade" id="modalRecibir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="recibir_orden_compra">
            <input type="hidden" name="id" id="rc_id">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title">Recibir orden de compra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted mb-0">Se generará la <strong>compra a crédito</strong> con el detalle de la orden,
                se <strong>ingresará stock al almacén</strong> y la orden pasará a estado <strong>recibida</strong>.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-download me-1"></i>Confirmar recepción</button>
            </div>
        </form>
    </div>
</div>

<script>
const PRODUCTOS = <?= json_encode($productos) ?>;

function filaPlantilla(){
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm item-prod">
                <option value="">— Seleccione —</option>
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
        kit('item_desc', tr.querySelector('.item-prod').selectedOptions[0]?.text || '');
        kit('item_cant', tr.querySelector('.item-cant').value);
        kit('item_precio', tr.querySelector('.item-precio').value);
    });
    return true;
}

function recibirOC(id){ document.getElementById('rc_id').value = id; new bootstrap.Modal(document.getElementById('modalRecibir')).show(); }

document.addEventListener('DOMContentLoaded', function(){ if (document.getElementById('itemsBody')) { addItem(); recalcular(); } });
</script>