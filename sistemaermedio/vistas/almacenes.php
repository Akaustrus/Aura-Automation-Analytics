<?php
$almacenes = $db->query('SELECT a.*, (SELECT COUNT(DISTINCT s.producto_id) FROM stock s WHERE s.almacen_id=a.id) nproductos,
                                (SELECT COALESCE(SUM(s.cantidad),0) FROM stock s WHERE s.almacen_id=a.id) totalunidades
                         FROM almacenes a ORDER BY a.nombre')->fetchAll();
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-industry me-2"></i>Almacenes
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalAlmacen"><i class="fa-solid fa-plus me-1"></i>Nuevo almacén</button>
</h2>

<div class="row g-4">
    <?php foreach ($almacenes as $a): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="card-title mb-1"><i class="fa-solid fa-warehouse me-2 text-primary"></i><?= limpiar($a['nombre']) ?></h5>
                            <p class="text-muted small mb-2"><?= limpiar($a['ubicacion']) ?> · <?= badgeEstado($a['estado'] ? 'activo' : 'inactivo') ?></p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" title="Editar" onclick="editarAlmacen(<?= $a['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-3">
                        <div><div class="stat-label">Productos</div><div class="stat-value fs-4"><?= $a['nproductos'] ?></div></div>
                        <div class="text-end"><div class="stat-label">Unidades</div><div class="stat-value fs-4"><?= formatoCantidad((float) $a['totalunidades']) ?></div></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="modalAlmacen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_almacen">
            <input type="hidden" name="id" id="al_id" value="0">
            <?= campoCsrf() ?>
            <div class="modal-header"><h5 class="modal-title" id="al_titulo">Nuevo almacén</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="al_nom" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Ubicación</label>
                        <input type="text" name="ubicacion" id="al_ubi" class="form-control"></div>
                    <div class="col-12"><div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="estado" id="al_est" checked>
                        <label class="form-check-label" for="al_est">Activo</label></div></div>
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
const ALMACENES = <?= json_encode($almacenes) ?>;
function editarAlmacen(id){
    const a = ALMACENES.find(x => String(x.id) === String(id));
    if (!a) return;
    document.getElementById('al_id').value = a.id;
    document.getElementById('al_titulo').textContent = 'Editar almacén';
    document.getElementById('al_nom').value = a.nombre;
    document.getElementById('al_ubi').value = a.ubicacion;
    document.getElementById('al_est').checked = !!Number(a.estado);
    new bootstrap.Modal(document.getElementById('modalAlmacen')).show();
}
</script>