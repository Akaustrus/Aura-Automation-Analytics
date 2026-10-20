<?php
$filtro = $_GET['filtro'] ?? 'todos';
$clientes = $db->query('SELECT * FROM clientes ORDER BY nombre')->fetchAll();

$where = '';
if ($filtro === 'emitido') $where = ' WHERE c.estado="emitido"';
if ($filtro === 'pagado') $where = ' WHERE c.estado="pagado"';
if ($filtro === 'anulado') $where = ' WHERE c.estado="anulado"';

$comprobantes = $db->query("SELECT c.*, cl.nombre AS cliente FROM comprobantes c JOIN clientes cl ON cl.id=c.cliente_id$where ORDER BY c.fecha DESC, c.id DESC LIMIT 50")->fetchAll();

$ventasTotal = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM comprobantes WHERE estado!='anulado'")->fetchColumn();
$ventas30 = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM comprobantes WHERE estado='pagado' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$porCobrar = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM comprobantes WHERE estado='emitido'")->fetchColumn();
$docsTotal = (int) $db->query('SELECT COUNT(*) FROM comprobantes WHERE estado!="anulado"')->fetchColumn();
?>

<section class="page-heading module-page-heading mb-4">
    <div>
        <span class="page-eyebrow">Facturación</span>
        <h1 class="page-title mb-1">Boletas y facturas</h1>
        <p class="page-subtitle mb-0">Emisión de comprobantes, gestión de clientes y cobranza.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalComprobante"><i class="fa-solid fa-file-circle-plus me-1"></i>Nuevo comprobante</button>
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCliente"><i class="fa-solid fa-user-plus me-1"></i>Nuevo cliente</button>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat stat-1"><div class="stat-icon"><i class="fa-solid fa-file-invoice"></i></div><div class="stat-num"><?= $docsTotal ?></div><div class="stat-label">Comprobantes emitidos</div></div></div>
    <div class="col-md-3"><div class="stat stat-2"><div class="stat-icon"><i class="fa-solid fa-coins"></i></div><div class="stat-num"><?= formatoMoneda($ventasTotal) ?></div><div class="stat-label">Ventas totales</div></div></div>
    <div class="col-md-3"><div class="stat stat-3"><div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div><div class="stat-num"><?= formatoMoneda($ventas30) ?></div><div class="stat-label">Cobrado últimos 30 días</div></div></div>
    <div class="col-md-3"><div class="stat stat-5"><div class="stat-icon"><i class="fa-solid fa-clock"></i></div><div class="stat-num"><?= formatoMoneda($porCobrar) ?></div><div class="stat-label">Por cobrar</div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="filtros">
            <?php foreach (['todos' => 'Todos', 'emitido' => 'Emitidos', 'pagado' => 'Pagados', 'anulado' => 'Anulados'] as $k => $label): ?>
                <a href="index.php?vista=facturacion&filtro=<?= $k ?>" class="filtro-btn <?= $filtro === $k ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <a href="index.php?vista=planificacion" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-robot me-1"></i>Predicción de ventas</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Comprobante</th><th>Cliente</th><th>Fecha</th><th class="text-end">Subtotal</th><th class="text-end">IGV</th><th class="text-end">Total</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($comprobantes as $c): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= limpiar($c['tipo']) ?></span> <strong><?= limpiar($c['serie']) ?>-<?= str_pad((string)$c['numero'], 8, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= limpiar($c['cliente']) ?></td>
                        <td><?= formatoFecha($c['fecha']) ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $c['subtotal']) ?></td>
                        <td class="text-end"><?= formatoMoneda((float) $c['igv']) ?></td>
                        <td class="text-end"><strong><?= formatoMoneda((float) $c['total']) ?></strong></td>
                        <td><?= badgeEstado($c['estado']) ?></td>
                        <td class="text-end">
                            <?php if ($c['estado'] !== 'anulado'): ?>
                                <form method="post" class="d-inline" data-confirm="¿Anular comprobante <?= limpiar($c['serie']) ?>-<?= $c['numero'] ?>?">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" name="action" value="anular_comprobante" class="btn btn-sm btn-outline-danger" title="Anular"><i class="fa-solid fa-ban"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($comprobantes)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Sin comprobantes para este filtro.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Comprobante -->
<div class="modal fade" id="modalComprobante" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="post">
                <?= campoCsrf() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-file-circle-plus me-1"></i>Emitir comprobante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" id="comp_tipo" class="form-select">
                                <option value="BOLETA">Boleta</option>
                                <option value="FACTURA">Factura</option>
                                <option value="NOTA_CREDITO">Nota crédito</option>
                                <option value="NOTA_DEBITO">Nota débito</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Serie</label>
                            <input type="text" name="serie" value="B001" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cliente</label>
                            <select name="cliente_id" class="form-select" required>
                                <?php foreach ($clientes as $c): ?><option value="<?= $c['id'] ?>"><?= limpiar($c['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="fa-solid fa-list-ul me-1"></i>Detalle del comprobante</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="agregarLinea"><i class="fa-solid fa-plus me-1"></i>Agregar línea</button>
                    </div>
                    <div id="lineasDetalle">
                        <div class="row g-2 mb-2 linea-item">
                            <div class="col-md-8"><input type="text" name="det[]" class="form-control form-control-sm" placeholder="Descripción / producto" required></div>
                            <div class="col-md-1"><input type="number" name="cant[]" class="form-control form-control-sm" value="1" min="1"><small class="text-muted">Cant.</small></div>
                            <div class="col-md-2"><input type="number" name="precio[]" class="form-control form-control-sm" step="0.01" min="0" placeholder="Precio"><small class="text-muted">Precio S/</small></div>
                            <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger quitarLinea"><i class="fa-solid fa-xmark"></i></button></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="action" value="guardar_comprobante" class="btn btn-primary">Emitir comprobante</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Cliente -->
<div class="modal fade" id="modalCliente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= campoCsrf() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-user-plus me-1"></i>Nuevo cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nombre / Razón social *</label><input type="text" name="nombre" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">RUC / DNI *</label><input type="text" name="ruc_dni" class="form-control" required maxlength="11"></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Teléfono</label><input type="text" name="telefono" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="action" value="guardar_cliente" class="btn btn-primary">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('comp_tipo').addEventListener('change', function(){
    document.querySelector('input[name="serie"]').value = this.value === 'BOLETA' ? 'B001' : this.value === 'FACTURA' ? 'F001' : this.value === 'NOTA_CREDITO' ? 'NC01' : 'ND01';
});
document.getElementById('agregarLinea').addEventListener('click', function(){
    const row = document.querySelector('.linea-item').cloneNode(true);
    row.querySelectorAll('input').forEach(i => { i.value = i.type === 'number' && i.classList.contains('cant-keep') ? i.value : ''; });
    row.querySelectorAll('input[name="cant[]"]').forEach(i => i.value = 1);
    document.getElementById('lineasDetalle').appendChild(row);
});
document.getElementById('lineasDetalle').addEventListener('click', function(e){
    if (e.target.closest('.quitarLinea')) {
        if (document.querySelectorAll('.linea-item').length > 1) e.target.closest('.linea-item').remove();
    }
});
</script>