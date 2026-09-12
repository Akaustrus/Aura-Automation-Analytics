<?php
$cuentas = $db->query('SELECT * FROM cuentas ORDER BY codigo')->fetchAll();
$editando = null;
if (!empty($_GET['editar'])) {
    $stmt = $db->prepare('SELECT * FROM cuentas WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch();
}
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-sitemap me-2"></i>Plan de cuentas
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalCuenta"><i class="fa-solid fa-plus me-1"></i>Nueva cuenta</button>
</h2>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Descripción</th><th>Activo</th><th class="text-end" style="width:140px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($cuentas as $c): ?>
                    <tr>
                        <td><strong><?= limpiar($c['codigo']) ?></strong></td>
                        <td><?= limpiar($c['nombre']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= ucfirst($c['tipo']) ?></span></td>
                        <td><?= limpiar($c['descripcion']) ?></td>
                        <td><?= $c['activo'] ? '<span class="text-success"><i class="fa-solid fa-check"></i></span>' : '<span class="text-danger"><i class="fa-solid fa-xmark"></i></span>' ?></td>
                        <td class="text-end">
                            <a href="?vista=cuentas&editar=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta cuenta?')">
                                <input type="hidden" name="action" value="eliminar_cuenta">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
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

<!-- Modal crear / editar cuenta -->
<div class="modal fade" id="modalCuenta" tabindex="-1" aria-labelledby="modalCuentaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_cuenta">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalCuentaLabel">
                    <?= $editando ? 'Editar cuenta' : 'Nueva cuenta' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label">Código <span class="text-danger">*</span></label>
                    <input type="text" name="codigo" class="form-control" required maxlength="10" value="<?= limpiar($editando['codigo'] ?? '') ?>" placeholder="Ej. 101">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required maxlength="120" value="<?= limpiar($editando['nombre'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select name="tipo" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        <?php foreach (tiposCuenta() as $t): ?>
                            <option value="<?= $t ?>" <?= ($editando['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" class="form-control" maxlength="255" value="<?= limpiar($editando['descripcion'] ?? '') ?>">
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="activo" value="1" id="activoCheck" <?= ($editando['activo'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activoCheck">Activa</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($editando): ?>
<script>
    document.addEventListener('DOMContentLoaded', function(){
        var modal = new bootstrap.Modal(document.getElementById('modalCuenta'));
        modal.show();
    });
</script>
<?php endif; ?>