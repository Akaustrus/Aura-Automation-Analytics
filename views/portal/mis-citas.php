<?php
$portalTitulo = 'Mis citas';
require __DIR__ . '/_portal_header.php';
$citas = $citas ?? [];
$reservada = (!empty($_GET['reservada']));
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title mb-0"><i class="fa-solid fa-calendar-days me-2"></i>Mis citas agendadas</h2>
        <a href="index.php?ruta=portal-agendar" class="btn btn-primary"><i class="fa-solid fa-calendar-plus me-1"></i>Agendar nueva</a>
    </div>

    <?php if ($reservada): ?>
        <div class="alert alert-success d-flex align-items-center">
            <i class="fa-solid fa-circle-check me-2"></i>
            <div><strong>¡Cita agendada!</strong> Te esperamos en el horario elegido.</div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Modalidad</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($citas as $c): ?>
                    <tr>
                        <td class="text-muted">#<?= (int) $c['id'] ?></td>
                        <td class="fw-semibold"><?= formatoFechaLarga($c['fecha']) ?></td>
                        <td class="font-monospace"><?= date('H:i', strtotime($c['hora_inicio'])) ?> – <?= date('H:i', strtotime($c['hora_fin'])) ?></td>
                        <td><?= badgeModalidad($c['modalidad']) ?></td>
                        <td><?= $c['asunto'] ? limpiar($c['asunto']) : '<span class="text-muted">—</span>' ?></td>
                        <td><?= badgeCita($c['estado']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($citas)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">Todavía no tienes citas agendadas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
if (!empty($_SESSION['cliente_id'])) { echo "\n</main>"; }
require __DIR__ . '/_portal_footer.php';
?>
