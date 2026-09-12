<?php
$busqueda = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM proveedores WHERE 1=1';
$args = [];
if ($busqueda !== '') {
    $sql .= ' AND (razon_social LIKE ? OR ruc_dni LIKE ?)';
    $args[] = '%' . $busqueda . '%';
    $args[] = '%' . $busqueda . '%';
}
$stmt = $db->prepare($sql . ' ORDER BY razon_social');
$stmt->execute($args);
$proveedores = $stmt->fetchAll();

$editando = null;
if (!empty($_GET['editar'])) {
    $s = $db->prepare('SELECT * FROM proveedores WHERE id=?'); $s->execute([(int) $_GET['editar']]);
    $editando = $s->fetch();
}
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-truck me-2"></i>Proveedores
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalProv"><i class="fa-solid fa-plus me-1"></i>Nuevo proveedor</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="proveedores">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small">Buscar (razón social o documento)</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?= limpiar($busqueda) ?>">
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i>Buscar</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Documento</th><th>Razón social</th><th>Dirección</th><th>Correo</th>
                        <th>Teléfono</th><th>Estado</th><th class="text-end" style="width:140px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($proveedores as $c): ?>
                    <tr>
                        <td><?= $c['tipo_doc'] ?> <strong><?= limpiar($c['ruc_dni']) ?></strong></td>
                        <td><?= limpiar($c['razon_social']) ?></td>
                        <td class="small text-muted"><?= limpiar($c['direccion']) ?></td>
                        <td class="small"><?= limpiar($c['correo']) ?></td>
                        <td class="small"><?= limpiar($c['telefono']) ?></td>
                        <td><?= badgeEstado($c['activo'] ? 'activo' : 'inactivo') ?></td>
                        <td class="text-end">
                            <a href="?vista=proveedores&editar=<?= $c['id'] ?>" class="btn btn-sm btn-outline-warning" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar al proveedor <?= limpiar($c['razon_social']) ?>?')">
                                <input type="hidden" name="action" value="eliminar_proveedor">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$proveedores): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Sin proveedores registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProv" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_proveedor">
            <?php if ($editando): ?><input type="hidden" name="id" value="<?= $editando['id'] ?>"><?php endif; ?>
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= $editando ? 'Editar proveedor' : 'Registrar proveedor' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo_doc" class="form-select">
                            <option value="RUC" <?= ($editando['tipo_doc'] ?? 'RUC')==='RUC' ? 'selected' : '' ?>>RUC</option>
                            <option value="DNI" <?= ($editando['tipo_doc'] ?? '')==='DNI' ? 'selected' : '' ?>>DNI</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">N° documento <span class="text-danger">*</span></label>
                        <input type="text" name="ruc_dni" class="form-control" required maxlength="11" value="<?= limpiar($editando['ruc_dni'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Razón social <span class="text-danger">*</span></label>
                        <input type="text" name="razon_social" class="form-control" required value="<?= limpiar($editando['razon_social'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control" value="<?= limpiar($editando['direccion'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" class="form-control" value="<?= limpiar($editando['correo'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" value="<?= limpiar($editando['telefono'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="activo" id="provActivo"
                                   <?= !$editando || $editando['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="provActivo">Proveedor activo</label>
                        </div>
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
document.addEventListener('DOMContentLoaded', function(){
    <?php if ($editando): ?>new bootstrap.Modal(document.getElementById('modalProv')).show();<?php endif; ?>
});
</script>