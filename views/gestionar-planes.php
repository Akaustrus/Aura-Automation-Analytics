<?php require __DIR__ . '/_header.php';
$empresa = $empresa ?? [];
$suscritos = $suscritos ?? [];
$disponibles = $disponibles ?? [];
$editar = $_GET['editar'] ?? null;
$error = $_GET['error'] ?? null;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="page-title mb-0"><i class="fa-solid fa-sliders me-2"></i>Planes de <?= limpiar($empresa['empresa']) ?></h2>
    <a href="index.php?ruta=empresa-detalle&id=<?= (int) $empresa['id'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver al detalle
    </a>
</div>

<?php if ($error === 'ya_existe'): ?>
    <div class="alert alert-warning d-flex align-items-center"><i class="fa-solid fa-circle-exclamation me-2"></i>La empresa ya tiene asignado ese sistema.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="fa-solid fa-list-check me-2"></i>Sistemas contratados</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Sistema</th>
                        <th>Estado</th>
                        <th>Usuarios</th>
                        <th>Precio</th>
                        <th>Incidencias</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($suscritos as $p): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="app-logo app-logo--sm"><i class="fa-solid <?= limpiar($p['icono']) ?>"></i></span>
                                    <div>
                                        <div class="fw-semibold"><?= limpiar($p['plan_nombre']) ?></div>
                                        <small class="text-muted"><?= formatoFechaCorta($p['fecha_inicio']) ?> → <?= formatoFechaCorta($p['fecha_vencimiento']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= badgePlanEstado($p['estado']) ?></td>
                            <td><?= (int) $p['usuarios'] ?></td>
                            <td><?= formatoMoneda($p['precio_mensual']) ?></td>
                            <td><span class="badge text-bg-light"><?= (int) $p['tickets'] ?></span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditar<?= (int) $p['id'] ?>">
                                    <i class="fa-solid fa-pen me-1"></i>Editar
                                </button>
                                <form method="POST" action="index.php?ruta=plan-eliminar" class="d-inline m-0"
                                      onsubmit="return confirm('¿Quitar este sistema de la empresa?');">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark"></i></button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal editar suscripción -->
                        <div class="modal fade" id="modalEditar<?= (int) $p['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" action="index.php?ruta=plan-actualizar" class="modal-content">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Editar: <?= limpiar($p['plan_nombre']) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label">Sistema</label>
                                                <select name="plan_id" class="form-select">
                                                    <?php foreach ($suscritos as $sp): ?>
                                                        <option value="<?= (int) $sp['plan_id'] ?>" <?= $sp['plan_id'] == $p['plan_id'] ? 'selected' : '' ?>><?= limpiar($sp['plan_nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                    <?php foreach ($disponibles as $dp): ?>
                                                        <option value="<?= (int) $dp['id'] ?>"><?= limpiar($dp['nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Estado</label>
                                                <select name="estado" class="form-select">
                                                    <?php foreach (ESTADOS_PLAN as $est): ?>
                                                        <option value="<?= $est ?>" <?= $p['estado'] === $est ? 'selected' : '' ?>><?= etiquetaPlanEstado($est) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Usuarios / licencias</label>
                                                <input type="number" name="usuarios" min="1" class="form-control" value="<?= (int) $p['usuarios'] ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Precio mensual (S/)</label>
                                                <input type="text" name="precio_mensual" class="form-control" value="<?= number_format((float) $p['precio_mensual'], 2, '.', '') ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Fecha de inicio</label>
                                                <input type="date" name="fecha_inicio" class="form-control" value="<?= $p['fecha_inicio'] ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Fecha de vencimiento</label>
                                                <input type="date" name="fecha_vencimiento" class="form-control" value="<?= $p['fecha_vencimiento'] ?>">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Notas</label>
                                                <textarea name="notas" rows="2" class="form-control" maxlength="500"><?= limpiar($p['notas'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancelar</button>
                                        <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($suscritos)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Sin sistemas contratados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="fa-solid fa-plus me-2"></i>Asignar un sistema</div>
            <div class="card-body p-4">
                <?php if (!empty($disponibles)): ?>
                    <p class="text-muted small">Elige cuál de los 5 sistemas se va a contratar para esta empresa.</p>
                    <form method="POST" action="index.php?ruta=plan-asignar">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="cliente_id" value="<?= (int) $empresa['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Sistema</label>
                            <select name="plan_id" class="form-select" required>
                                <option value="">Selecciona un sistema...</option>
                                <?php foreach ($disponibles as $dp): ?>
                                    <option value="<?= (int) $dp['id'] ?>"><i class="fa-solid <?= limpiar($dp['icono']) ?>"></i>
                                        <?= limpiar($dp['nombre']) ?> · <?= (int) $dp['modulos'] ?> módulos
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Estado</label>
                                <select name="estado" class="form-select">
                                    <?php foreach (ESTADOS_PLAN as $est): ?>
                                        <option value="<?= $est ?>" <?= $est === 'pendiente' ? 'selected' : '' ?>><?= etiquetaPlanEstado($est) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Usuarios</label>
                                <input type="number" name="usuarios" min="1" value="1" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Precio mensual (S/)</label>
                                <input type="text" name="precio_mensual" value="0.00" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Inicio</label>
                                <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Vencimiento</label>
                                <input type="date" name="fecha_vencimiento" value="<?= date('Y-12-31') ?>" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3 mt-2">
                            <label class="form-label">Notas</label>
                            <textarea name="notas" rows="2" class="form-control" maxlength="500" placeholder="Detalla contrato, implementación, etc."></textarea>
                        </div>
                        <button class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i>Asignar sistema</button>
                    </form>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fa-solid fa-check-double d-block mb-2" style="font-size: 2rem; color: var(--teal);"></i>
                        Esta empresa ya tiene <strong>todos</strong> los sistemas disponibles.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>