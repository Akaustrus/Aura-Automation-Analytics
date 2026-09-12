<?php
$portalTitulo = 'Agendar cita';
require __DIR__ . '/_portal_header.php';
$fecha = $fecha ?? '';
$slots = $slots ?? [];
$dias = DIAS_SEMANA;
$error = $error ?? null;
$modalidad = $modalidad ?? 'presencial';
$lleno = (!empty($_GET['lleno']));
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title mb-0"><i class="fa-solid fa-calendar-check me-2"></i>Agendar atención</h2>
        <a href="index.php?ruta=portal-citas" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-list me-1"></i>Mis citas</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="ruta" value="portal-agendar">
                <div class="col-md-4">
                    <label class="form-label">Elige el día</label>
                    <input type="date" name="fecha" class="form-control" value="<?= limpiar($fecha) ?>"
                           min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Modalidad</label>
                    <select name="modalidad" class="form-select">
                        <option value="presencial" <?= $modalidad === 'presencial' ? 'selected' : '' ?>>Presencial</option>
                        <option value="virtual" <?= $modalidad === 'virtual' ? 'selected' : '' ?>>Virtual (videollamada)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Ver horarios</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)): ?>
        <?php if ($lleno): ?>
            <div class="alert alert-danger d-flex align-items-center">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <div>Ese horario se acaba de llenar. Por favor elegí otro disponible.</div>
            </div>
        <?php endif; ?>

        <h5 class="mb-3"><?= formatoFechaLarga($fecha) ?> — <?= $modalidad === 'virtual' ? 'Virtual' : 'Presencial' ?></h5>

        <div class="row g-3">
            <?php $hayLibres = false; ?>
            <?php foreach ($slots as $s): ?>
                <?php
                $horaIni = date('H:i', strtotime($s['hora_inicio']));
                $horaFin = date('H:i', strtotime($s['hora_fin']));
                ?>
                <div class="col-md-4">
                    <div class="card h-100 <?= $s['libre'] ? '' : 'opacity-75' ?>">
                        <div class="card-body d-flex flex-column">
                            <div class="fw-semibold mb-1"><?= $horaIni ?> – <?= $horaFin ?></div>
                            <?php if ($s['libre']): ?>
                                <?php $hayLibres = true; ?>
                                <span class="badge bg-success align-self-start mb-2">
                                    <i class="fa-solid fa-circle-check me-1"></i><?= (int) $s['disponibles'] ?> cupo(s) libre(s)
                                </span>
                                <form method="POST" action="index.php?ruta=cita-reservar" class="mt-auto">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="fecha" value="<?= limpiar($fecha) ?>">
                                    <input type="hidden" name="slot_id" value="<?= (int) $s['id'] ?>">
                                    <input type="hidden" name="modalidad" value="<?= $modalidad ?>">
                                    <div class="mb-2">
                                        <input type="text" name="asunto" class="form-control form-control-sm"
                                               placeholder="Motivo (opcional)" maxlength="200">
                                    </div>
                                    <button class="btn btn-primary w-100 btn-sm"><i class="fa-solid fa-bookmark me-1"></i>Reservar</button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-danger align-self-start"><i class="fa-solid fa-xmark me-1"></i>Lleno</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($slots)): ?>
                <div class="col-12">
                    <div class="alert alert-secondary">No hay horarios de atención definidos para este día.</div>
                </div>
            <?php elseif (!$hayLibres): ?>
                <div class="col-12">
                    <div class="alert alert-warning">Todos los horarios de ese día están llenos. Elegí otro día.</div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
if (!empty($_SESSION['cliente_id'])) { echo "\n</main>"; }
require __DIR__ . '/_portal_footer.php';
?>
