<?php require __DIR__ . '/_header.php';
$horarios = $horarios ?? [];
$dias = DIAS_SEMANA;
$agrupados = [];
foreach ($horarios as $h) {
    $agrupados[(int) $h['dia_semana']][] = $h;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="page-title mb-0"><i class="fa-solid fa-sliders me-2"></i>Grilla de horarios</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSlot">Agregar horario</button>
</div>

<p class="text-muted">Define los horarios de atención por día de la semana y cuántos cupos tiene cada uno. Cuando un día está lleno, el cliente no podrá elegir ese horario.</p>

<?php foreach ($dias as $i => $nombre): ?>
    <?php if (!empty($agrupados[$i])): ?>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= $nombre ?></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>Hora inicio</th><th>Hora fin</th><th>Cupos</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($agrupados[$i] as $s): ?>
                        <tr>
                            <td class="font-monospace"><?= date('H:i', strtotime($s['hora_inicio'])) ?></td>
                            <td class="font-monospace"><?= date('H:i', strtotime($s['hora_fin'])) ?></td>
                            <td><?= (int) $s['cupos'] ?></td>
                            <td>
                                <?php if ((int) $s['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="index.php?ruta=horario-alternar" class="d-inline">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <input type="hidden" name="activo" value="<?= (int) $s['activo'] ? 0 : 1 ?>">
                                    <button class="btn btn-sm btn-outline-secondary">
                                        <?= (int) $s['activo'] ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalSlot"
                                        onclick="editarSlot(<?= (int) $s['id'] ?>, <?= (int) $s['dia_semana'] ?>,
                                        '<?= $s['hora_inicio'] ?>', '<?= $s['hora_fin'] ?>', <?= (int) $s['cupos'] ?>, <?= (int) $s['activo'] ?>)">
                                    Editar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center card mb-2 p-3">
            <span><?= $nombre ?></span>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalSlot"
                    onclick="nuevoSlot(<?= $i ?>)">Agregar horario</button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<div class="modal fade" id="modalSlot" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="index.php?ruta=horario-guardar" class="modal-content">
        <?= campoCsrf() ?>
        <div class="modal-header">
            <h5 class="modal-title">Horario de atención</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id" id="slot_id" value="">
            <div class="mb-3">
                <label class="form-label">Día de la semana</label>
                <select name="dia_semana" id="slot_dia" class="form-select">
                    <?php foreach ($dias as $i => $n): ?>
                        <option value="<?= $i ?>"><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label">Hora inicio</label>
                    <input type="time" name="hora_inicio" id="slot_ini" class="form-control" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Hora fin</label>
                    <input type="time" name="hora_fin" id="slot_fin" class="form-control" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Cupos (citas por horario)</label>
                <input type="number" name="cupos" id="slot_cupos" class="form-control" min="1" value="1" required>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="activo" id="slot_activo" value="1" checked>
                <label class="form-check-label" for="slot_activo">Horario activo</label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar</button>
        </div>
        </form>
    </div>
</div>

<script>
function nuevoSlot(dia) {
    document.getElementById('slot_id').value = '';
    document.getElementById('slot_dia').value = dia;
    document.getElementById('slot_ini').value = '09:00';
    document.getElementById('slot_fin').value = '09:45';
    document.getElementById('slot_cupos').value = '1';
    document.getElementById('slot_activo').checked = true;
}

function editarSlot(id, dia, ini, fin, cupos, activo) {
    document.getElementById('slot_id').value = id;
    document.getElementById('slot_dia').value = dia;
    document.getElementById('slot_ini').value = ini.slice(0, 5);
    document.getElementById('slot_fin').value = fin.slice(0, 5);
    document.getElementById('slot_cupos').value = cupos;
    document.getElementById('slot_activo').checked = activo === 1;
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>
