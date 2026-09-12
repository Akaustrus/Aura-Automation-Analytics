<?php
$clientes = $db->query('SELECT * FROM clientes ORDER BY razon_social')->fetchAll();

$editando = null;
if (!empty($_GET['editar'])) {
    $stmt = $db->prepare('SELECT * FROM clientes WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch();
}

$nTotal = count($clientes);
$nRuc = count(array_filter($clientes, fn($c) => $c['tipo_doc'] === 'RUC'));
$nDni = count(array_filter($clientes, fn($c) => $c['tipo_doc'] === 'DNI'));
$nAct = count(array_filter($clientes, fn($c) => $c['activo']));
$ventasCli = $db->query('SELECT c.id, COALESCE(SUM(co.total),0) total FROM clientes c LEFT JOIN comprobantes co ON co.cliente_id=c.id AND co.estado="emitido" GROUP BY c.id')->fetchAll();
$ventasMap = [];
foreach ($ventasCli as $v) $ventasMap[$v['id']] = (float) $v['total'];
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-users me-2"></i>Clientes
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalCli"><i class="fa-solid fa-plus me-1"></i>Nuevo cliente</button>
</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-user-check"></i></div>
            <div class="stat-num"><?= $nAct ?></div>
            <div class="stat-label">Activos</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-landmark"></i></div>
            <div class="stat-num"><?= $nRuc ?></div>
            <div class="stat-label">Con RUC</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-id-card"></i></div>
            <div class="stat-num"><?= $nDni ?></div>
            <div class="stat-label">Con DNI</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-4">
            <div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div>
            <div class="stat-num"><?= $nTotal ?></div>
            <div class="stat-label">Registrados</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Documento</th><th>Razón social</th><th>Dirección</th><th>Contacto</th>
                        <th class="text-end">Ventas emitidas</th><th>Estado</th><th class="text-end" style="width:140px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($clientes as $c): ?>
                    <tr class="<?= $c['activo'] ? '' : 'table-secondary text-muted' ?>">
                        <td><span class="badge bg-info text-dark"><?= $c['tipo_doc'] ?></span> <?= limpiar($c['ruc_dni']) ?></td>
                        <td>
                            <strong><?= limpiar($c['razon_social']) ?></strong>
                            <?php if (!$c['activo']): ?><span class="text-muted small">(inactivo)</span><?php endif; ?>
                        </td>
                        <td class="text-truncate" style="max-width:180px" title="<?= limpiar($c['direccion']) ?>"><?= limpiar($c['direccion']) ?: '—' ?></td>
                        <td>
                            <?= limpiar($c['correo']) ?: '—' ?>
                            <div class="small text-muted"><?= limpiar($c['telefono']) ?: '' ?></div>
                        </td>
                        <td class="text-end"><strong>S/ <?= number_format($ventasMap[$c['id']] ?? 0, 2) ?></strong></td>
                        <td><?= badgeEstado($c['activo'] ? 'activo' : 'inactivo') ?></td>
                        <td class="text-end">
                            <a href="?vista=clientes&editar=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar a <?= limpiar($c['razon_social']) ?>?')">
                                <input type="hidden" name="action" value="eliminar_cliente">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clientes): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Sin clientes registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal crear / editar cliente -->
<div class="modal fade" id="modalCli" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_cliente">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= $editando ? 'Editar cliente' : 'Registrar cliente' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tipo de documento <span class="text-danger">*</span></label>
                        <select name="tipo_doc" class="form-select">
                            <option value="RUC" <?= ($editando['tipo_doc'] ?? 'RUC')==='RUC' ? 'selected' : '' ?>>RUC</option>
                            <option value="DNI" <?= ($editando['tipo_doc'] ?? '')==='DNI' ? 'selected' : '' ?>>DNI</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">RUC / DNI <span class="text-danger">*</span></label>
                        <input type="text" name="ruc_dni" class="form-control" required maxlength="11" value="<?= limpiar($editando['ruc_dni'] ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Razón social / Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="razon_social" class="form-control" required maxlength="150" value="<?= limpiar($editando['razon_social'] ?? '') ?>">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control" maxlength="200" value="<?= limpiar($editando['direccion'] ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" class="form-control" maxlength="120" value="<?= limpiar($editando['correo'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" maxlength="20" value="<?= limpiar($editando['telefono'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="activo" id="cliActivo" value="1" <?= empty($editando) || $editando['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="cliActivo">Cliente activo</label>
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
    <?php if ($editando): ?>
        new bootstrap.Modal(document.getElementById('modalCli')).show();
    <?php endif; ?>
});
</script>