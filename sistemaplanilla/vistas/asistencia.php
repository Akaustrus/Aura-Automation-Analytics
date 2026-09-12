<?php
$mes   = $_GET['mes'] ?? periodoActual();
$mes   = validarPeriodo($mes) ? $mes : periodoActual();
$empSel = (int) ($_GET['empleado'] ?? 0);

$filtroEmp  = $empSel > 0 ? ' AND a.empleado_id = ' . $empSel : '';
$registros = $db->query("SELECT a.*, e.dni, e.nombres, e.apellidos, e.cargo
                         FROM asistencia a JOIN empleados e ON e.id = a.empleado_id
                         WHERE DATE_FORMAT(a.fecha, '%Y-%m') = '$mes'$filtroEmp
                         ORDER BY a.fecha DESC, e.apellidos")->fetchAll();
$empleados = $db->query('SELECT id, dni, nombres, apellidos, cargo FROM empleados ORDER BY apellidos')->fetchAll();

// Resumen mensual por empleado
$resumen = $db->query("SELECT e.id, e.apellidos, e.nombres,
                              SUM(a.estado='asistio') asis, SUM(a.estado='falta') faltas,
                              SUM(a.estado='permiso') permisos, SUM(a.estado='vacaciones') vacaciones,
                              COALESCE(SUM(a.horas_extra),0) hx
                       FROM empleados e LEFT JOIN asistencia a ON a.empleado_id=e.id
                                        AND DATE_FORMAT(a.fecha,'%Y-%m')='$mes'
                       WHERE e.activo=1 GROUP BY e.id ORDER BY e.apellidos")->fetchAll();

$edicion = null;
if (!empty($_GET['editar'])) {
    $stmt = $db->prepare('SELECT * FROM asistencia WHERE id=?'); $stmt->execute([(int)$_GET['editar']]);
    $edicion = $stmt->fetch();
}

$dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-clipboard-user me-2"></i>Asistencia</h2>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <input type="hidden" name="vista" value="asistencia">
            <div class="col-md-3">
                <label class="form-label">Mes</label>
                <input type="month" name="mes" class="form-control" value="<?= $mes ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Empleado</label>
                <select name="empleado" class="form-select">
                    <option value="0">Todos</option>
                    <?php foreach ($empleados as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $empSel === (int)$e['id'] ? 'selected' : '' ?>><?= $e['apellidos'] ?>, <?= $e['nombres'] ?> (<?= $e['dni'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
            </div>
            <div class="col-md-3 text-md-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAsis"><i class="fa-solid fa-plus me-1"></i>Registrar</button>
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalImport"><i class="fa-solid fa-file-import me-1"></i>Importar CSV</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($resumen as $r): ?>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between">
                        <strong class="small"><?= limpiar($r['apellidos']) ?>, <?= limpiar($r['nombres']) ?></strong>
                        <span class="badge bg-secondary"><?= $r['asis'] + $r['faltas'] + $r['permisos'] + $r['vacaciones'] ?> días</span>
                    </div>
                    <div class="small text-muted mt-2">
                        <span class="text-success"><i class="fa-solid fa-circle-check"></i> <?= $r['asis'] ?> asist.</span>
                        <span class="text-danger ms-2"><i class="fa-solid fa-circle-xmark"></i> <?= $r['faltas'] ?> faltas</span>
                        <span class="text-info ms-2"><i class="fa-solid fa-circle-pause"></i> <?= $r['permisos'] ?></span>
                        <span class="ms-2">HE: <strong><?= number_format($r['hx'],2) ?> h</strong></span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-list me-1"></i>Registros de <?= nombrePeriodo($mes) ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Día</th><th>Empleado</th><th>Estado</th><th class="text-end">Entrada</th>
                        <th class="text-end">Salida</th><th class="text-end">H. extras</th><th>Justificación</th>
                        <th class="text-end" style="width:110px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($registros as $a):
                    $d = date('N', strtotime($a['fecha'])); ?>
                    <tr>
                        <td><?= formatoFecha($a['fecha']) ?></td>
                        <td class="small text-muted"><?= $dias[$d] ?></td>
                        <td><?= limpiar($a['apellidos']) ?>, <?= limpiar($a['nombres']) ?></td>
                        <td><?= badgeEstado($a['estado']) ?></td>
                        <td class="text-end"><?= $a['entrada'] ? substr($a['entrada'],0,5) : '—' ?></td>
                        <td class="text-end"><?= $a['salida'] ? substr($a['salida'],0,5) : '—' ?></td>
                        <td class="text-end"><strong><?= number_format($a['horas_extra'],2) ?></strong></td>
                        <td class="small"><?= limpiar($a['justificacion']) ?: '—' ?></td>
                        <td class="text-end">
                            <a href="?vista=asistencia&editar=<?= $a['id'] ?>&mes=<?= $mes ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar registro del <?= formatoFecha($a['fecha']) ?>?')">
                                <input type="hidden" name="action" value="eliminar_asistencia">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <?= campoCsrf() ?>
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$registros): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Sin registros para el mes seleccionado.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal registrar / editar asistencia -->
<div class="modal fade" id="modalAsis" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_asistencia">
            <input type="hidden" name="id" value="<?= $edicion['id'] ?? '' ?>">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= $edicion ? 'Editar asistencia' : 'Registrar asistencia' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Empleado <span class="text-danger">*</span></label>
                        <select name="empleado_id" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($empleados as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= (int)($edicion['empleado_id'] ?? 0) === (int)$e['id'] ? 'selected' : '' ?>><?= $e['apellidos'] ?>, <?= $e['nombres'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" class="form-control" required value="<?= $edicion['fecha'] ?? date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Estado <span class="text-danger">*</span></label>
                        <select name="estado" class="form-select">
                            <?php foreach (['asistio','falta','permiso','vacaciones'] as $es): ?>
                                <option value="<?= $es ?>" <?= ($edicion['estado'] ?? 'asistio') === $es ? 'selected' : '' ?>><?= ucfirst($es) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Entrada</label>
                        <input type="time" name="entrada" class="form-control" value="<?= $edicion['entrada'] ?? '08:00' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Salida</label>
                        <input type="time" name="salida" class="form-control" value="<?= $edicion['salida'] ?? '17:00' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Horas extra</label>
                        <input type="number" step="0.5" min="0" name="horas_extra" class="form-control" value="<?= $edicion['horas_extra'] ?? 0 ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Justificación</label>
                        <input type="text" name="justificacion" class="form-control" maxlength="255" value="<?= limpiar($edicion['justificacion'] ?? '') ?>">
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

<!-- Modal importar CSV -->
<div class="modal fade" id="modalImport" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" enctype="multipart/form-data">
            <input type="hidden" name="action" value="importar_asistencia">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-file-import me-1"></i>Importar asistencia (CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Formato esperado (primera línea = cabecera, se omite):</p>
                <pre class="bg-light p-2 rounded small">dni,fecha,estado,entrada,salida,horas_extra,justificacion
40123456,2026-09-01,asistio,08:00,17:00,0,</pre>
                <div class="mb-3">
                    <label class="form-label">Archivo CSV</label>
                    <input type="file" name="archivo" accept=".csv,.txt" class="form-control" required>
                </div>
                <div class="form-text">Estados válidos: asistio, falta, permiso, vacaciones. Los duplicados se omiten.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload me-1"></i>Importar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    <?php if ($edicion): ?>
        new bootstrap.Modal(document.getElementById('modalAsis')).show();
    <?php endif; ?>
});
</script>