<?php
$proveedores = $db->query('SELECT * FROM proveedores ORDER BY razon_social')->fetchAll();
$busq = trim($_GET['q'] ?? '');
if ($busq !== '') {
    $proveedores = array_values(array_filter($proveedores, fn($p) =>
        stripos($p['razon_social'], $busq) !== false || stripos($p['ruc_dni'], $busq) !== false));
}
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-truck me-2"></i>Proveedores
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalProveedor"><i class="fa-solid fa-plus me-1"></i>Nuevo proveedor</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="proveedores">
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
                        <th class="text-end">Plazo crédito</th><th>Estado</th><th class="text-end" style="width:110px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($proveedores as $p): ?>
                    <tr>
                        <td><span class="badge bg-light text-dark border"><?= $p['tipo_doc'] ?></span> <?= $p['ruc_dni'] ?></td>
                        <td class="text-truncate" style="max-width:220px" title="<?= limpiar($p['razon_social']) ?>"><strong><?= limpiar($p['razon_social']) ?></strong></td>
                        <td class="small"><?= limpiar($p['direccion']) ?></td>
                        <td class="small"><?= limpiar($p['correo']) ?><br><?= limpiar($p['telefono']) ?></td>
                        <td class="text-end"><?= $p['plazo_credito'] ?> días</td>
                        <td><?= badgeEstado($p['activo'] ? 'activo' : 'inactivo') ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" title="Editar" onclick="editarProveedor(<?= $p['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar al proveedor <?= addslashes($p['razon_social']) ?>?')">
                                <input type="hidden" name="action" value="eliminar_proveedor">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$proveedores): ?><tr><td colspan="7" class="text-center text-muted py-3">Sin proveedores.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProveedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_proveedor">
            <input type="hidden" name="id" id="pr_id" value="0">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title" id="pr_titulo">Nuevo proveedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-5"><label class="form-label">Tipo doc.</label>
                        <select name="tipo_doc" id="pr_tipo" class="form-select">
                            <option value="RUC">RUC</option><option value="DNI">DNI</option></select></div>
                    <div class="col-7"><label class="form-label">N° documento <span class="text-danger">*</span></label>
                        <input type="text" name="ruc_dni" id="pr_ruc" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Razón social <span class="text-danger">*</span></label>
                        <input type="text" name="razon_social" id="pr_rs" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Dirección</label>
                        <input type="text" name="direccion" id="pr_dir" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Correo</label>
                        <input type="email" name="correo" id="pr_correo" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" id="pr_tel" class="form-control"></div>
                    <div class="col-8"><label class="form-label">Plazo de crédito (días)</label>
                        <input type="number" name="plazo_credito" id="pr_plazo" class="form-control" min="0" value="0"></div>
                    <div class="col-4 d-flex align-items-end"><div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="activo" id="pr_act" checked><label class="form-check-label small" for="pr_act">Activo</label></div></div>
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
const PROVEEDORES = <?= json_encode($proveedores) ?>;
function editarProveedor(id){
    const p = PROVEEDORES.find(x => String(x.id) === String(id));
    if (!p) return;
    document.getElementById('pr_id').value = p.id;
    document.getElementById('pr_titulo').textContent = 'Editar proveedor';
    document.getElementById('pr_tipo').value = p.tipo_doc;
    document.getElementById('pr_ruc').value = p.ruc_dni;
    document.getElementById('pr_rs').value = p.razon_social;
    document.getElementById('pr_dir').value = p.direccion;
    document.getElementById('pr_correo').value = p.correo;
    document.getElementById('pr_tel').value = p.telefono;
    document.getElementById('pr_plazo').value = p.plazo_credito;
    document.getElementById('pr_act').checked = !!Number(p.activo);
    new bootstrap.Modal(document.getElementById('modalProveedor')).show();
}
</script>