<?php
$portalTitulo = 'Ticket #' . (int) ($ticket['id'] ?? 0);
require __DIR__ . '/_portal_header.php';
$ticket = $ticket ?? null;
$satisfaccion = $satisfaccion ?? null;
$comentarios = $comentarios ?? [];

if (!$ticket) {
    if (!empty($_SESSION['cliente_id'])) { echo "\n</main>"; }
    require __DIR__ . '/_portal_footer.php';
    return;
}
$calificado = (!empty($_GET['calificado']) && $satisfaccion);
$creada = !empty($_GET['creada']);
$puedeCalificar = in_array($ticket['estado'], ['culminado', 'resuelto', 'cerrado'], true) && !$satisfaccion;
?>

<div class="container py-4">
    <?php if ($creada): ?>
        <div class="alert alert-success d-flex align-items-center">
            <i class="fa-solid fa-circle-check me-2"></i>
            <div><strong>¡Incidencia registrada con éxito!</strong> El ticket #<?= (int) $ticket['id'] ?> ya fue enviado y está pendiente de atención.</div>
        </div>
    <?php endif; ?>

    <a href="index.php?ruta=portal-mis-tickets" class="btn btn-link btn-sm mb-2 px-0"><i class="fa-solid fa-arrow-left me-1"></i>Mis incidencias</a>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="page-title mb-0"><i class="fa-solid fa-ticket me-2"></i>Ticket #<?= (int) $ticket['id'] ?> — <?= limpiar($ticket['asunto']) ?></h2>
        <?= badgeEstado($ticket['estado']) ?>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="fa-solid fa-circle-info me-2"></i>Detalles de la incidencia</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6">
                    <small class="text-muted d-block">Sistema</small>
                    <p class="fw-semibold mb-0">
                        <?php if ($ticket['plan_nombre']): ?>
                            <span class="badge text-bg-light"><i class="fa-solid <?= limpiar($ticket['plan_icono']) ?> me-1"></i><?= limpiar($ticket['plan_nombre']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">No especificado</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Categoría</small>
                    <p class="fw-semibold mb-0"><?= etiquetaCategoria($ticket['categoria']) ?></p>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Prioridad</small>
                    <p class="mb-0"><?= badgePrioridad($ticket['prioridad']) ?></p>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Encargado</small>
                    <p class="mb-0"><?= $ticket['encargado_nombre'] ? limpiar($ticket['encargado_nombre']) : '<span class="text-muted">Por asignar</span>' ?></p>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Registrado</small>
                    <p class="mb-0"><?= formatoFecha($ticket['fecha_creacion']) ?></p>
                </div>
                <div class="col-12">
                    <small class="text-muted d-block">Descripción</small>
                    <p class="mb-0" style="white-space:pre-line"><?= nl2br(limpiar($ticket['descripcion'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="fa-solid fa-comments me-2"></i>Mensajes con nuestro equipo</div>
        <div class="card-body">
            <?php if (empty($comentarios)): ?>
                <p class="text-muted text-center mb-0">Sin mensajes todavía. Escríbenos si necesitas algo.</p>
            <?php else: ?>
                <?php foreach ($comentarios as $c): ?>
                    <?php $esDelCliente = $c['autor_rol'] === 'cliente'; ?>
                    <div class="d-flex mb-3 <?= $esDelCliente ? 'justify-content-end' : '' ?>">
                        <div class="comment-bubble <?= $esDelCliente ? 'bg-primary text-white' : 'bg-light' ?>" style="max-width:85%">
                            <div class="d-flex justify-content-between gap-3">
                                <strong><i class="fa-solid <?= $esDelCliente ? 'fa-building' : 'fa-headset' ?> me-1"></i><?= limpiar($c['autor_nombre']) ?></strong>
                                <small class="<?= $esDelCliente ? 'text-white-50' : 'text-muted' ?>"><?= formatoFecha($c['fecha']) ?></small>
                            </div>
                            <div class="mt-1" style="white-space:pre-line"><?= nl2br(limpiar($c['mensaje'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST" action="index.php?ruta=portal-ticket-comentar" class="mt-3">
                <?= campoCsrf() ?>
                <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                <textarea name="mensaje" rows="2" class="form-control mb-2" placeholder="Escribe un mensaje para nuestro equipo..." required></textarea>
                <button class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Enviar mensaje</button>
            </form>
        </div>
    </div>

    <?php if ($calificado): ?>
        <div class="alert alert-success d-flex align-items-center">
            <i class="fa-solid fa-star me-2"></i>
            <div><strong>¡Gracias por tu calificación!</strong> Tu opinión nos ayuda a mejorar.</div>
        </div>
    <?php endif; ?>

    <?php if ($puedeCalificar): ?>
        <div class="card">
            <div class="card-header fw-semibold"><i class="fa-solid fa-star me-2"></i>Evalúa nuestra atención</div>
            <div class="card-body">
                <p class="text-muted mb-3">Tu incidencia ha sido atendida. Cuéntanos tu nivel de satisfacción:</p>
                <form method="POST" action="index.php?ruta=portal-ticket" class="row g-3">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                    <div class="col-md-6">
                        <label class="form-label">Nivel de satisfacción</label>
                        <select name="nivel" class="form-select" required>
                            <option value="">Selecciona...</option>
                            <option value="muy_insatisfecho">Muy insatisfecho</option>
                            <option value="insatisfecho">Insatisfecho</option>
                            <option value="neutral">Neutral</option>
                            <option value="satisfecho">Satisfecho</option>
                            <option value="muy_satisfecho">Muy satisfecho</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Comentario (opcional)</label>
                        <input type="text" name="comentario" maxlength="500" class="form-control" placeholder="Comparte tus comentarios">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Enviar calificación</button>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($satisfaccion): ?>
        <div class="card">
            <div class="card-header fw-semibold"><i class="fa-solid fa-star me-2"></i>Tu calificación</div>
            <div class="card-body">
                <p class="mb-1"><?= badgeSatisfaccion($satisfaccion['nivel']) ?></p>
                <?php if ($satisfaccion['comentario']): ?>
                    <p class="text-muted mb-0 mt-2">"<?= limpiar($satisfaccion['comentario']) ?>"</p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary d-flex align-items-center">
            <i class="fa-solid fa-hourglass-half me-2"></i>
            <span>Podrás calificar tu satisfacción cuando tu incidencia esté culminada o resuelta.</span>
        </div>
    <?php endif; ?>
</div>

<?php
if (!empty($_SESSION['cliente_id'])) { echo "\n</main>"; }
require __DIR__ . '/_portal_footer.php';
?>
