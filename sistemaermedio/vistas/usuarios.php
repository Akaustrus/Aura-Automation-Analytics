<?php
$usuarios = $db->query('SELECT * FROM usuarios ORDER BY creado DESC')->fetchAll();
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-users-gear me-2"></i>Usuarios
    <button class="btn btn-sm btn-primary float-end" onclick="nuevo()"><i class="fa-solid fa-plus me-1"></i>Nuevo usuario</button>
</h2>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Estado</th><th class="text-end" style="width:120px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><i class="fa-solid fa-user me-1 text-muted"></i><strong><?= limpiar($u['usuario']) ?></strong></td>
                        <td><?= limpiar($u['nombre']) ?></td>
                        <td><?= badgeEstado($u['rol']) ?></td>
                        <td><?= $u['estado'] ? badgeEstado('activo') : badgeEstado('inactivo') ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-info" title="Editar" onclick="editar(<?= (int) $u['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                            <?php if (($usuario['id'] ?? 0) != $u['id']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar al usuario <?= limpiar($u['usuario']) ?>?')">
                                    <input type="hidden" name="action" value="eliminar_usuario">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <?= campoCsrf() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_usuario">
            <input type="hidden" name="id" id="u_id">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title">Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Usuario <span class="text-danger">*</span></label>
                        <input type="text" name="usuario" id="u_usuario" class="form-control" maxlength="60" required></div>
                    <div class="col-6"><label class="form-label">Rol</label>
                        <select name="rol" id="u_rol" class="form-select">
                            <?php foreach (rolesDisponibles() as $r): ?>
                                <option value="<?= $r ?>"><?= rolNombre($r) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-12"><label class="form-label">Nombre completo</label>
                        <input type="text" name="nombre" id="u_nombre" class="form-control" maxlength="120"></div>
                    <div class="col-8"><label class="form-label">Clave <span class="text-danger" id="claveReq">*</span></label>
                        <input type="password" name="clave" id="u_clave" class="form-control" autocomplete="new-password"></div>
                    <div class="col-4"><label class="form-label">Activo</label>
                        <select name="estado" id="u_estado" class="form-select">
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select></div>
                    <div class="col-12"><small class="text-muted" id="claveHint">Mínimo 4 caracteres. Déjela en blanco al <strong>editar</strong> para conservar la actual.</small></div>
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
const USUARIOS = <?= json_encode($usuarios) ?>;

function nuevo(){
    document.getElementById('u_id').value = '';
    document.getElementById('u_usuario').value = '';
    document.getElementById('u_nombre').value = '';
    document.getElementById('u_clave').value = '';
    document.getElementById('u_rol').value = 'ventas';
    document.getElementById('u_estado').value = '1';
    document.getElementById('claveReq').textContent = '*';
    new bootstrap.Modal(document.getElementById('modalUsuario')).show();
}

function editar(id){
    const u = USUARIOS.find(x => String(x.id) === String(id));
    if (!u) return;
    document.getElementById('u_id').value = u.id;
    document.getElementById('u_usuario').value = u.usuario;
    document.getElementById('u_nombre').value = u.nombre || '';
    document.getElementById('u_clave').value = '';
    document.getElementById('u_rol').value = u.rol;
    document.getElementById('u_estado').value = String(u.estado);
    document.getElementById('claveReq').textContent = '';
    new bootstrap.Modal(document.getElementById('modalUsuario')).show();
}
</script>