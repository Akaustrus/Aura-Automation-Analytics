<?php
$empleados = $db->query('SELECT e.*, c.nombre AS cargo, d.nombre AS departamento
                         FROM empleados e JOIN cargos c ON c.id=e.cargo_id JOIN departamentos d ON d.id=c.departamento_id
                         ORDER BY e.fecha_contratacion')->fetchAll();
$cargos = $db->query('SELECT c.*, d.nombre AS departamento FROM cargos c JOIN departamentos d ON d.id=c.departamento_id ORDER BY d.nombre, c.nombre')->fetchAll();
$vacantes = $db->query('SELECT v.*, c.nombre AS cargo, c.salario_base FROM vacantes v JOIN cargos c ON c.id=v.cargo_id ORDER BY FIELD(v.estado,"abierta","en_proceso","cubierta","cerrada"), v.fecha_apertura')->fetchAll();

$edadProm = (int) $db->query('SELECT ROUND(AVG(TIMESTAMPDIFF(YEAR, fecha_contratacion, CURDATE())),0) FROM empleados WHERE estado="activo"')->fetchColumn();
$nombres = $db->query('SELECT COUNT(*) n FROM empleados WHERE estado="activo" AND (nombre LIKE "A%" OR nombre LIKE "C%" OR nombre LIKE "M%" OR nombre LIKE "R%")')->fetchColumn();
?>

<section class="page-heading module-page-heading mb-4">
    <div>
        <span class="page-eyebrow">Recursos Humanos</span>
        <h1 class="page-title mb-1">Empleados y contratación</h1>
        <p class="page-subtitle mb-0">Gestión del talento, vacantes y predicción de cuántas personas necesitas.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEmpleado"><i class="fa-solid fa-user-plus me-1"></i>Nuevo empleado</button>
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalVacante"><i class="fa-solid fa-bullhorn me-1"></i>Nueva vacante</button>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat stat-1"><div class="stat-icon"><i class="fa-solid fa-users"></i></div><div class="stat-num"><?= count(array_filter($empleados, fn($e) => $e['estado'] === 'activo')) ?></div><div class="stat-label">Empleados activos</div></div></div>
    <div class="col-md-3"><div class="stat stat-2"><div class="stat-icon"><i class="fa-solid fa-user-clock"></i></div><div class="stat-num"><?= $edadProm ?> años</div><div class="stat-label">Antigüedad promedio</div></div></div>
    <div class="col-md-3"><div class="stat stat-5"><div class="stat-icon"><i class="fa-solid fa-bullhorn"></i></div><div class="stat-num"><?= count(array_filter($vacantes, fn($v) => $v['estado'] === 'abierta')) ?></div><div class="stat-label">Vacantes abiertas</div></div></div>
    <div class="col-md-3"><div class="stat stat-3"><div class="stat-icon"><i class="fa-solid fa-brain"></i></div><div class="stat-num"><?= array_sum(array_map(function ($c) use ($empleados) { $n = count(array_filter($empleados, fn($e) => $e['cargo_id'] == $c['id'] && $e['estado'] === 'activo')); return max(0, (int)$c['requeridos'] - $n); }, $cargos)) ?></div><div class="stat-label">Personas por contratar</div></div></div>
</div>

<!-- Empleados -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-user-tie me-1"></i>Plantilla actual</span>
        <span class="badge bg-primary-subtle text-primary"><?= count($empleados) ?> registros</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Código</th><th>Nombre</th><th>DNI</th><th>Cargo</th><th>Departamento</th><th class="text-end">Salario</th><th>Contrato</th><th>Contratación</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($empleados as $e): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= limpiar($e['codigo']) ?></span></td>
                        <td><strong><?= limpiar($e['nombre']) ?> <?= limpiar($e['apellidos']) ?></strong></td>
                        <td><?= limpiar($e['dni']) ?></td>
                        <td><?= limpiar($e['cargo']) ?></td>
                        <td><small class="text-muted"><?= limpiar($e['departamento']) ?></small></td>
                        <td class="text-end"><?= formatoMoneda((float) $e['salario']) ?></td>
                        <td><?= badgeEstado($e['tipo_contrato']) ?></td>
                        <td><?= formatoFecha($e['fecha_contratacion']) ?></td>
                        <td><?= badgeEstado($e['estado']) ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEmpleado"
                                    data-id="<?= $e['id'] ?>" data-nombre="<?= limpiar($e['nombre']) ?>" data-apellidos="<?= limpiar($e['apellidos']) ?>"
                                    data-dni="<?= limpiar($e['dni']) ?>" data-cargo="<?= $e['cargo_id'] ?>" data-salario="<?= $e['salario'] ?>"
                                    data-fecha="<?= $e['fecha_contratacion'] ?>" data-tipo="<?= $e['tipo_contrato'] ?>" data-estado="<?= $e['estado'] ?>"
                                    data-email="<?= limpiar($e['email']) ?>" data-telefono="<?= limpiar($e['telefono']) ?>" data-horas="<?= $e['horas_semanales'] ?>"><i class="fa-solid fa-pen"></i></button>
                            <form method="post" class="d-inline" data-confirm="¿Eliminar a <?= limpiar($e['nombre']) ?>?">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <button type="submit" name="action" value="eliminar_empleado" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Vacantes -->
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-bullhorn me-1"></i>Vacantes y convocatorias</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Cargo</th><th>Cantidad</th><th>Salario base</th><th>Motivo</th><th>Apertura</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($vacantes as $v): ?>
                    <tr>
                        <td><strong><?= limpiar($v['cargo']) ?></strong></td>
                        <td><?= (int) $v['cantidad'] ?></td>
                        <td><?= formatoMoneda((float) $v['salario_base']) ?></td>
                        <td><small><?= limpiar($v['motivo']) ?></small></td>
                        <td><?= formatoFecha($v['fecha_apertura']) ?></td>
                        <td><?= badgeEstado($v['estado']) ?></td>
                        <td class="text-end">
                            <form method="post" class="d-inline" data-confirm="¿Eliminar vacante?">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                <button type="submit" name="action" value="eliminar_vacante" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($vacantes)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Sin vacantes registradas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Empleado -->
<div class="modal fade" id="modalEmpleado" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <?= campoCsrf() ?>
                <input type="hidden" name="id" id="emp_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-user-plus me-1"></i><span id="emp_titulo">Nuevo empleado</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="emp_nombre" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Apellidos *</label><input type="text" name="apellidos" id="emp_apellidos" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">DNI *</label><input type="text" name="dni" id="emp_dni" class="form-control" required maxlength="12"></div>
                        <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" id="emp_email" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Teléfono</label><input type="text" name="telefono" id="emp_telefono" class="form-control"></div>
                        <div class="col-md-6">
                            <label class="form-label">Cargo *</label>
                            <select name="cargo_id" id="emp_cargo" class="form-select" required>
                                <?php foreach ($cargos as $c): ?><option value="<?= $c['id'] ?>"><?= limpiar($c['nombre']) ?> — <?= limpiar($c['departamento']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label">Salario *</label><input type="number" name="salario" id="emp_salario" class="form-control" step="0.01" min="0" required></div>
                        <div class="col-md-3"><label class="form-label">Horas semanales</label><input type="number" name="horas_semanales" id="emp_horas" class="form-control" value="40"></div>
                        <div class="col-md-4"><label class="form-label">Fecha contratación</label><input type="date" name="fecha_contratacion" id="emp_fecha" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo de contrato</label>
                            <select name="tipo_contrato" id="emp_tipo" class="form-select">
                                <option value="indefinido">Indefinido</option>
                                <option value="temporal">Temporal</option>
                                <option value="practicas">Prácticas</option>
                                <option value="terceros">Terceros</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select name="estado" id="emp_estado" class="form-select">
                                <option value="activo">Activo</option>
                                <option value="vacaciones">Vacaciones</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="action" value="guardar_empleado" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Vacante -->
<div class="modal fade" id="modalVacante" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= campoCsrf() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-bullhorn me-1"></i>Nueva vacante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cargo</label>
                        <select name="cargo_id" class="form-select" required>
                            <?php foreach ($cargos as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= limpiar($c['nombre']) ?> (<?= limpiar($c['departamento']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cantidad</label>
                        <input type="number" name="cantidad" class="form-control" value="1" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <textarea name="motivo" class="form-control" rows="2" placeholder="Ej: crecimiento de demanda, reemplazo, nueva línea..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="abierta">Abierta</option>
                            <option value="en_proceso">En proceso</option>
                            <option value="cerrada">Cerrada</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="action" value="guardar_vacante" class="btn btn-primary">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('modalEmpleado').addEventListener('show.bs.modal', function (ev) {
    const btn = ev.relatedTarget;
    if (!btn.dataset.id) return;
    document.getElementById('emp_titulo').textContent = 'Editar empleado';
    document.getElementById('emp_id').value = btn.dataset.id;
    document.getElementById('emp_nombre').value = btn.dataset.nombre;
    document.getElementById('emp_apellidos').value = btn.dataset.apellidos;
    document.getElementById('emp_dni').value = btn.dataset.dni;
    document.getElementById('emp_cargo').value = btn.dataset.cargo;
    document.getElementById('emp_salario').value = btn.dataset.salario;
    document.getElementById('emp_fecha').value = btn.dataset.fecha;
    document.getElementById('emp_tipo').value = btn.dataset.tipo;
    document.getElementById('emp_estado').value = btn.dataset.estado;
    document.getElementById('emp_email').value = btn.dataset.email || '';
    document.getElementById('emp_telefono').value = btn.dataset.telefono || '';
    document.getElementById('emp_horas').value = btn.dataset.horas || 40;
});
document.getElementById('modalEmpleado').addEventListener('hidden.bs.modal', function () {
    if (!document.getElementById('emp_id').value) { document.getElementById('emp_titulo').textContent = 'Nuevo empleado'; return; }
});
</script>