<?php
$clientes = $db->query('SELECT * FROM clientes ORDER BY razon_social')->fetchAll();
$busq = trim($_GET['q'] ?? '');
if ($busq !== '') {
    $clientes = array_values(array_filter($clientes, fn($c) =>
        stripos($c['razon_social'], $busq) !== false || stripos($c['ruc_dni'], $busq) !== false));
}
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-users me-2"></i>Clientes
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalCliente"><i class="fa-solid fa-plus me-1"></i>Nuevo cliente</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="clientes">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small">Buscar (razón social / documento)</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?= limpiar($busq) ?>">
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i>Buscar</button></div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Documento</th><th>Razón social</th><th>Dirección</th><th>Contacto</th>
                        <th class="text-end">Límite crédito</th><th>Estado</th><th class="text-end" style="width:110px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td><span class="badge bg-light text-dark border"><?= $c['tipo_doc'] ?></span> <?= $c['ruc_dni'] ?></td>
                        <td class="text-truncate" style="max-width:220px" title="<?= limpiar($c['razon_social']) ?>"><strong><?= limpiar($c['razon_social']) ?></strong></td>
                        <td class="small"><?= limpiar($c['direccion']) ?></td>
                        <td class="small"><?= limpiar($c['correo']) ?><br><?= limpiar($c['telefono']) ?></td>
                        <td class="text-end"><?= number_format($c['limite_credito'], 2) ?></td>
                        <td><?= badgeEstado($c['activo'] ? 'activo' : 'inactivo') ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" title="Editar" onclick="editarCliente(<?= $c['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar al cliente <?= addslashes($c['razon_social']) ?>?')">
                                <input type="hidden" name="action" value="eliminar_cliente">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clientes): ?><tr><td colspan="7" class="text-center text-muted py-3">Sin clientes.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_cliente">
            <input type="hidden" name="id" id="cl_id" value="0">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title" id="cl_titulo">Nuevo cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-5"><label class="form-label">Tipo doc.</label>
                        <select name="tipo_doc" id="cl_tipo" class="form-select">
                            <option value="RUC">RUC</option><option value="DNI">DNI</option></select></div>
                    <div class="col-7"><label class="form-label">N° documento <span class="text-danger">*</span></label>
                        <input type="text" name="ruc_dni" id="cl_ruc" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Razón social <span class="text-danger">*</span></label>
                        <input type="text" name="razon_social" id="cl_rs" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Dirección</label>
                        <input type="text" name="direccion" id="cl_dir" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Correo</label>
                        <input type="email" name="correo" id="cl_correo" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" id="cl_tel" class="form-control"></div>
                    <div class="col-8"><label class="form-label">Límite de crédito</label>
                        <input type="number" name="limite_credito" id="cl_lim" class="form-control" step="0.01" min="0" value="0"></div>
                    <div class="col-4 d-flex align-items-end"><div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="activo" id="cl_act" checked><label class="form-check-label small" for="cl_act">Activo</label></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
const CLIENTES = <?= json_encode($clientes) ?>;
function editarCliente(id){
    const c = CLIENTES.find(x => String(x.id) === String(id));
    if (!c) return;
    document.getElementById('cl_id').value = c.id;
    document.getElementById('cl_titulo').textContent = 'Editar cliente';
    document.getElementById('cl_tipo').value = c.tipo_doc;
    document.getElementById('cl_ruc').value = c.ruc_dni;
    document.getElementById('cl_rs').value = c.razon_social;
    document.getElementById('cl_dir').value = c.direccion;
    document.getElementById('cl_correo').value = c.correo;
    document.getElementById('cl_tel').value = c.telefono;
    document.getElementById('cl_lim').value = c.limite_credito;
    document.getElementById('cl_act').checked = !!Number(c.activo);
    new bootstrap.Modal(document.getElementById('modalCliente')).show();
}
</script>