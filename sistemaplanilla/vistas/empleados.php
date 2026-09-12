<?php
$empleados = $db->query('SELECT * FROM empleados ORDER BY apellidos, nombres')->fetchAll();
$areas = areasDeTrabajo();
$tipos = tiposContrato();

$editando = null;
if (!empty($_GET['editar'])) {
    $stmt = $db->prepare('SELECT * FROM empleados WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch();
}

// Estadísticas rápidas por régimen y área
$porRegimen = [];
$porArea = [];
foreach ($db->query('SELECT regimen, COUNT(*) n FROM empleados WHERE activo=1 GROUP BY regimen') as $r) $porRegimen[$r['regimen']] = $r['n'];
foreach ($db->query('SELECT area, COUNT(*) n FROM empleados WHERE activo=1 GROUP BY area') as $r) $porArea[$r['area']] = $r['n'];
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-users me-2"></i>Empleados
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalEmp"><i class="fa-solid fa-plus me-1"></i>Nuevo empleado</button>
</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-user-check"></i></div>
            <div class="stat-num"><?= array_sum($porRegimen) ?></div>
            <div class="stat-label">Activos</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-landmark"></i></div>
            <div class="stat-num"><?= $porRegimen['onp'] ?? 0 ?></div>
            <div class="stat-label">ONP</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-building-columns"></i></div>
            <div class="stat-num"><?= $porRegimen['afp'] ?? 0 ?></div>
            <div class="stat-label">AFP</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat stat-4">
            <div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div>
            <div class="stat-num"><?= count($porArea) ?></div>
            <div class="stat-label">Áreas</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>DNI</th><th>Nombres</th><th>Cargo / Contrato</th><th>Área</th><th>Ingreso</th>
                        <th class="text-end">Sueldo base</th><th>Régimen</th><th>Estado</th><th class="text-end" style="width:150px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($empleados as $e):
                    $activo = $e['activo'] && ($e['fecha_cese'] === null || $e['fecha_cese'] >= date('Y-m-d')); ?>
                    <tr class="<?= $e['activo'] ? '' : 'table-secondary text-muted' ?>">
                        <td><?= limpiar($e['dni']) ?></td>
                        <td>
                            <?= limpiar($e['apellidos']) ?>, <?= limpiar($e['nombres']) ?>
                            <?php if ($e['asignacion_familiar']): ?><i class="fa-solid fa-baby text-primary ms-1" title="Asignación familiar"></i><?php endif; ?>
                            <div class="small text-muted"><?= limpiar($e['correo']) ?: '—' ?></div>
                        </td>
                        <td>
                            <?= limpiar($e['cargo']) ?>
                            <div class="small text-muted"><?= $tipos[$e['tipo_contrato']] ?? $e['tipo_contrato'] ?></div>
                        </td>
                        <td>
                            <?= limpiar($e['area']) ?: '—' ?>
                            <div class="small text-muted"><?= limpiar($e['banco']) ?><?= $e['banco'] ? ': ' . limpiar($e['cuenta_bancaria']) : '' ?></div>
                        </td>
                        <td><?= formatoFecha($e['fecha_ingreso']) ?></td>
                        <td class="text-end"><strong><?= number_format($e['sueldo_base'],2) ?></strong></td>
                        <td><span class="badge bg-info text-dark"><?= mb_strtoupper($e['regimen']) ?></span></td>
                        <td><?= badgeEstado($activo ? 'activo' : 'inactivo') ?></td>
                        <td class="text-end">
                            <a href="?vista=empleados&editar=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar a <?= limpiar($e['nombres']) ?>?')">
                                <input type="hidden" name="action" value="eliminar_empleado">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    onclick="navigator.clipboard.writeText('<?= limpiar($e['portal_token']) ?>');alert('Token copiado: <?= limpiar($e['portal_token']) ?>')"
                                    title="Copiar token del portal"><i class="fa-solid fa-key"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal crear / editar empleado -->
<div class="modal fade" id="modalEmp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="guardar_empleado">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= $editando ? 'Editar empleado' : 'Registrar empleado' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                <?php endif; ?>

                <h6 class="text-primary fw-semibold mb-3"><i class="fa-solid fa-circle-user me-1"></i>Datos personales</h6>
                <div class="row g-3 mb-2">
                    <div class="col-md-2">
                        <label class="form-label">DNI <span class="text-danger">*</span></label>
                        <input type="text" name="dni" class="form-control" required maxlength="8" value="<?= limpiar($editando['dni'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nombres <span class="text-danger">*</span></label>
                        <input type="text" name="nombres" class="form-control" required maxlength="60" value="<?= limpiar($editando['nombres'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Apellidos <span class="text-danger">*</span></label>
                        <input type="text" name="apellidos" class="form-control" required maxlength="80" value="<?= limpiar($editando['apellidos'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Género</label>
                        <select name="genero" class="form-select">
                            <option value="M" <?= ($editando['genero'] ?? 'M') === 'M' ? 'selected' : '' ?>>Masculino</option>
                            <option value="F" <?= ($editando['genero'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" maxlength="20" value="<?= limpiar($editando['telefono'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" class="form-control" maxlength="120" value="<?= limpiar($editando['correo'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha de ingreso <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_ingreso" class="form-control" required value="<?= $editando['fecha_ingreso'] ?? date('Y-m-d') ?>">
                    </div>
                </div>

                <h6 class="text-primary fw-semibold mb-3 mt-3"><i class="fa-solid fa-briefcase me-1"></i>Laboral y remuneración</h6>
                <div class="row g-3 mb-2">
                    <div class="col-md-4">
                        <label class="form-label">Cargo <span class="text-danger">*</span></label>
                        <input type="text" name="cargo" class="form-control" required maxlength="80" value="<?= limpiar($editando['cargo'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Área</label>
                        <select name="area" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($areas as $a): ?>
                                <option value="<?= $a ?>" <?= ($editando['area'] ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo de contrato</label>
                        <select name="tipo_contrato" class="form-select">
                            <?php foreach ($tipos as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($editando['tipo_contrato'] ?? 'indefinido') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sueldo base (S/) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="sueldo_base" class="form-control" required value="<?= $editando['sueldo_base'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Régimen <span class="text-danger">*</span></label>
                        <select name="regimen" class="form-select">
                            <option value="onp" <?= ($editando['regimen'] ?? 'onp') === 'onp' ? 'selected' : '' ?>>ONP (13%)</option>
                            <option value="afp" <?= ($editando['regimen'] ?? '') === 'afp' ? 'selected' : '' ?>>AFP (12.5%)</option>
                            <option value="ninguno" <?= ($editando['regimen'] ?? '') === 'ninguno' ? 'selected' : '' ?>>Sin descuento</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Jornada (h/día)</label>
                        <input type="number" step="0.5" min="1" name="jornada_horas" class="form-control" value="<?= $editando['jornada_horas'] ?? 8 ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">CUSPP</label>
                        <input type="text" name="cuspp" class="form-control" maxlength="20" value="<?= limpiar($editando['cuspp'] ?? '') ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="asignacion_familiar" id="empAsf" value="1" <?= !empty($editando['asignacion_familiar']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="empAsf">Asignación familiar</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Banco</label>
                        <input type="text" name="banco" class="form-control" maxlength="40" value="<?= limpiar($editando['banco'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cuenta bancaria</label>
                        <input type="text" name="cuenta_bancaria" class="form-control" maxlength="30" value="<?= limpiar($editando['cuenta_bancaria'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha de cese</label>
                        <input type="date" name="fecha_cese" class="form-control" value="<?= $editando['fecha_cese'] ?? '' ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="activo" id="empActivo" value="1" <?= empty($editando) || $editando['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="empActivo">Empleado activo</label>
                        </div>
                    </div>
                </div>

                <?php if ($editando): ?>
                    <div class="alert alert-light border small mb-0">
                        <strong>Token del portal del empleado:</strong> <code><?= limpiar($editando['portal_token']) ?></code>
                        <a href="#" class="ms-2" onclick="navigator.clipboard.writeText('<?= limpiar($editando['portal_token']) ?>');return false;"><i class="fa-solid fa-copy"></i> Copiar</a>
                        &nbsp;·&nbsp;
                        <a href="#" class="text-danger" onclick="if(confirm('¿Regenerar el token?')){const f=document.createElement('form');f.method='POST';const a=document.createElement('input');a.type='hidden';a.name='action';a.value='cambiar_token_empleado';const i=document.createElement('input');i.type='hidden';i.name='id';i.value='<?= $editando['id'] ?>';f.appendChild(a);f.appendChild(i);document.body.appendChild(f);f.submit();}return false;"><i class="fa-solid fa-rotate"></i> Regenerar</a>
                    </div>
                <?php endif; ?>
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
        new bootstrap.Modal(document.getElementById('modalEmp')).show();
    <?php endif; ?>
});
</script>