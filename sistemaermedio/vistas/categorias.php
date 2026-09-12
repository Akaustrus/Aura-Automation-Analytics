<?php
$categorias = $db->query('SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id=c.id) nprod
                          FROM categorias c ORDER BY c.nombre')->fetchAll();
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-tags me-2"></i>Categorías
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalCategoria"><i class="fa-solid fa-plus me-1"></i>Nueva categoría</button>
</h2>

<div class="card" style="max-width:640px">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light"><tr><th>#</th><th>Nombre</th><th class="text-end">Productos</th><th class="text-end" style="width:80px">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($categorias as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td><strong><?= limpiar($c['nombre']) ?></strong></td>
                    <td class="text-end"><?= $c['nprod'] ?></td>
                    <td class="text-end">
                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar la categoría <?= addslashes($c['nombre']) ?>?')">
                            <input type="hidden" name="action" value="eliminar_categoria">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <?= campoCsrf() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$categorias): ?><tr><td colspan="4" class="text-center text-muted py-3">Sin categorías.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_categoria">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title">Nueva categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="nombre" class="form-control" required placeholder="Ej. Hardware">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>