<?php require __DIR__ . '/_header.php';
$fecha = $fecha ?? date('Y-m-d');
$slots = $slots ?? [];
$citas = $citas ?? [];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="page-title mb-0"><i class="fa-solid fa-calendar-day me-2"></i>Agenda de atención</h2>
    <a href="index.php?ruta=horarios" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-sliders me-1"></i>Grilla de horarios</a>
</div>

<form method="GET" action="index.php" class="mb-3">
    <input type="hidden" name="ruta" value="agenda">
    <div class="input-group" style="max-width:320px">
        <span class="input-group-text"><i class="fa-solid fa-calendar"></i></span>
        <input type="date" name="fecha" class="form-control" value="<?= limpiar($fecha) ?>" onchange="this.form.submit()">
    </div>
</form>

<h6 class="mb-3 text-muted"><?= formatoFechaLarga($fecha) ?></h6>

<?php if (empty($slots)): ?>
    <div class="alert alert-secondary">No hay horarios definidos para este día. Configúralos en <a href="index.php?ruta=horarios">Grilla de horarios</a>.</div>
<?php endif; ?>

<?php foreach ($slots as $s): ?>
    <?php
    $horaIni = date('H:i', strtotime($s['hora_inicio']));
    $horaFin = date('H:i', strtotime($s['hora_fin']));
    $citasSlot = array_values(array_filter($citas, fn($c) => (int) $c['slot_id'] === (int) $s['id']));
    $ocupadas = count(array_filter($citasSlot, fn($c) => $c['estado'] !== 'cancelada'));
    $libres = max(0, (int) $s['cupos'] - $ocupadas);
    ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><?= $horaIni ?> – <?= $horaFin ?></span>
            <span class="badge <?= $libres > 0 ? 'bg-success' : 'bg-danger' ?>"><?= $libres ?> libre(s) / <?= (int) $s['cupos'] ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($citasSlot)): ?>
                <p class="text-muted mb-0">Sin citas para este horario.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Empresa</th><th>RUC</th><th>Modalidad</th><th>Motivo</th><th>Estado</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($citasSlot as $c): ?>
                            <tr>
                                <td class="fw-semibold"><?= limpiar($c['cliente_nombre']) ?></td>
                                <td class="font-monospace"><?= limpiar($c['ruc']) ?></td>
                                <td><?= badgeModalidad($c['modalidad']) ?></td>
                                <td><?= $c['asunto'] ? limpiar($c['asunto']) : '<span class="text-muted">—</span>' ?></td>
                                <td><?= badgeCita($c['estado']) ?></td>
                                <td class="text-end">
                                    <?php if ($c['estado'] === 'reservada'): ?>
                                        <form method="POST" action="index.php?ruta=cita-estado" class="d-inline">
                                            <?= campoCsrf() ?>
                                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                            <input type="hidden" name="estado" value="confirmada">
                                            <input type="hidden" name="fecha" value="<?= limpiar($fecha) ?>">
                                            <button class="btn btn-sm btn-outline-primary">Confirmar</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="index.php?ruta=cita-estado" class="d-inline">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <input type="hidden" name="estado" value="realizada">
                                        <input type="hidden" name="fecha" value="<?= limpiar($fecha) ?>">
                                        <button class="btn btn-sm btn-outline-success">Realizada</button>
                                    </form>
                                    <form method="POST" action="index.php?ruta=cita-estado" class="d-inline">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <input type="hidden" name="estado" value="cancelada">
                                        <input type="hidden" name="fecha" value="<?= limpiar($fecha) ?>">
                                        <button class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('¿Cancelar esta cita?')">Cancelar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . '/_footer.php'; ?>
