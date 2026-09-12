<?php
require_once __DIR__ . '/../../modelo/Notificacion.php';
require __DIR__ . '/../_header.php';
exigirLogin();
$notificaciones = (new Notificacion())->listarNoLeidas((int) $_SESSION['usuario_id']);
$total = count($notificaciones);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <h2 class="page-title mb-0"><i class="fa-solid fa-bell me-2"></i>Notificaciones</h2>
        <?php if ($total > 0): ?>
            <span class="notif-count-badge"><i class="fa-solid fa-circle"></i><?= $total ?> sin leer</span>
        <?php endif; ?>
    </div>
    <?php if ($total > 0): ?>
        <form method="POST" action="index.php?ruta=notificaciones-leidas" class="m-0">
            <?= campoCsrf() ?>
            <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-check-double me-1"></i>Marcar todas leídas</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($total > 0): ?>
    <div class="card">
        <ul class="list-group list-group-flush">
            <?php foreach ($notificaciones as $n): ?>
                <li class="list-group-item notif-item d-flex justify-content-between align-items-start <?= !$n['leida'] ? 'notif-item--unread' : '' ?>">
                    <div class="d-flex gap-3">
                        <?php if (!$n['leida']): ?>
                            <span class="notif-dot"></span>
                        <?php endif; ?>
                        <div class="notif-icon notif-icon--alert">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </div>
                        <div>
                            <div class="<?= $n['leida'] ? 'text-muted' : 'fw-semibold' ?>"><?= limpiar($n['mensaje']) ?></div>
                            <small class="text-muted"><i class="fa-regular fa-clock me-1"></i><?= formatoFecha($n['fecha']) ?></small>
                        </div>
                    </div>
                    <div class="d-flex gap-2 ms-2 flex-shrink-0">
                        <a href="index.php?ruta=ticket-detalle&id=<?= (int) $n['ticket_id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-eye me-1"></i>Ver ticket
                        </a>
                        <?php if (!$n['leida']): ?>
                            <form method="POST" action="index.php?ruta=notificacion-leida" class="m-0">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" title="Marcar como leída"><i class="fa-solid fa-check"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php else: ?>
    <div class="card">
        <div class="notif-empty text-center">
            <i class="fa-regular fa-bell-slash d-block mb-3" style="font-size: 3rem;"></i>
            <h5 class="fw-semibold mb-1">Todo al día</h5>
            <p class="text-muted mb-0">No tienes notificaciones pendientes por revisar.</p>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../_footer.php'; ?>
