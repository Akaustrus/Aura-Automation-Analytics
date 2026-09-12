<?php require __DIR__ . '/_header.php';
$empresas = $empresas ?? [];
$planes = $planes ?? [];
$edita = $_GET['edita'] ?? null;
$error = $_GET['error'] ?? null;
$eliminada = $_GET['eliminada'] ?? null;
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h2 class="page-title mb-0"><i class="fa-solid fa-building me-2"></i>Empresas clientes</h2>
        <small class="text-muted">Gestiona a las empresas, los sistemas que usan y sus incidencias.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?ruta=planes" class="btn btn-outline-secondary">
            <i class="fa-solid fa-layer-group me-1"></i>Sistemas
        </a>
        <a href="index.php?ruta=empresa-nueva" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>Nueva empresa
        </a>
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalClave" onclick="verFormClave()">
            <i class="fa-solid fa-key me-1"></i>Ver / fijar clave
        </button>
    </div>
</div>

<?php if ($edita): ?>
    <div class="alert alert-success d-flex align-items-center">
        <i class="fa-solid fa-circle-check me-2"></i>
        <div>Clave actualizada correctamente para la empresa #<?= (int) $edita ?>. Comunícala al cliente.</div>
    </div>
<?php endif; ?>

<?php if ($eliminada): ?>
    <div class="alert alert-success d-flex align-items-center">
        <i class="fa-solid fa-circle-check me-2"></i><div>Empresa eliminada correctamente.</div>
    </div>
<?php endif; ?>

<?php if ($error === 'clave_vacia'): ?>
    <div class="alert alert-danger">Debes escribir una clave.</div>
<?php elseif ($error === 'clave_corta'): ?>
    <div class="alert alert-danger">La clave debe tener al menos 4 caracteres.</div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Empresa</th>
                <th>Contacto</th>
                <th>Sistemas contratados</th>
                <th>Incidencias</th>
                <th>Clave</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($empresas as $c): ?>
                <tr>
                    <td class="text-muted">#<?= (int) $c['id'] ?></td>
                    <td>
                        <a href="index.php?ruta=empresa-detalle&id=<?= (int) $c['id'] ?>" class="fw-semibold text-reset text-decoration-none"><?= limpiar($c['empresa']) ?></a>
                        <small class="text-muted d-block font-monospace">RUC <?= limpiar($c['ruc']) ?></small>
                    </td>
                    <td>
                        <?= $c['contacto'] ? limpiar($c['contacto']) : '<span class="text-muted">—</span>' ?>
                        <small class="text-muted d-block"><?= $c['telefono'] ? limpiar($c['telefono']) : '' ?></small>
                    </td>
                    <td>
                        <?php if ((int) $c['total_planes'] > 0): ?>
                            <span class="badge text-bg-light"><?= (int) $c['total_planes'] ?> sistemas</span>
                            <small class="d-block text-muted"><?= (int) $c['planes_activos'] ?> activos</small>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Sin planes</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-light"><?= (int) $c['total_tickets'] ?></span></td>
                    <td>
                        <span class="badge text-bg-secondary" title="La clave se guarda de forma segura.">
                            <i class="fa-solid fa-lock me-1"></i><?= str_repeat('•', 8) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a href="index.php?ruta=empresa-detalle&id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                            <a href="index.php?ruta=empresa-editar&id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-pen"></i></a>
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#modalClave"
                                    onclick="verFormClave(<?= (int) $c['id'] ?>, '<?= addslashes($c['empresa']) ?>')">
                                <i class="fa-solid fa-key"></i>
                            </button>
                            <form method="POST" action="index.php?ruta=empresa-eliminar" class="d-inline m-0"
                                  onsubmit="return confirm('¿Eliminar esta empresa y todos sus registros?');">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($empresas)): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">No hay empresas registradas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para ver/fijar clave -->
<div class="modal fade" id="modalClave" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="index.php?ruta=empresa-clave" class="modal-content">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-key me-2"></i>Ver / fijar clave de la empresa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    La clave se guarda de forma segura (no se puede leer). Elegí una clave predefinida para la empresa
                    y comunícala al cliente por teléfono. Esta será su contraseña para el portal.
                </p>
                <div class="mb-3">
                    <label class="form-label">Empresa</label>
                    <input type="text" class="form-control" id="empresa_label" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nueva clave de acceso</label>
                    <div class="input-group">
                        <input type="text" name="clave" id="clave_input" class="form-control" minlength="4" required
                               placeholder="Escribe la clave de la empresa">
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleClave()"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="id" id="cliente_id">
                <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar clave</button>
            </div>
        </form>
    </div>
</div>

<script>
function verFormClave(id, empresa) {
    document.getElementById('cliente_id').value = id || '';
    document.getElementById('empresa_label').value = empresa || 'Nueva empresa / selecciona en el listado';
    document.getElementById('clave_input').value = '';
}

function toggleClave() {
    var inp = document.getElementById('clave_input');
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>