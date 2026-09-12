<?php require __DIR__ . '/../_header.php';
$ticket = $ticket ?? null;
$historial = $historial ?? [];
$agentes = $agentes ?? [];
$satisfaccion = $satisfaccion ?? null;
$comentarios = $comentarios ?? [];

if (!$ticket) {
    require __DIR__ . '/../_footer.php';
    return;
}
$esEncargado = $_SESSION['usuario_id'] === (int) $ticket['encargado_id'];
$puedeAsignar = in_array($_SESSION['usuario_rol'], ['soporte', 'encargado', 'admin'], true);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="page-title mb-0"><i class="fa-solid fa-ticket me-2"></i>Ticket #<?= (int) $ticket['id'] ?> — <?= limpiar($ticket['asunto']) ?></h2>
    <?= badgeEstado($ticket['estado']) ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="fa-solid fa-circle-info me-2"></i>Detalles</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Empresa</small>
                        <p class="fw-semibold mb-0"><?= limpiar($ticket['cliente_nombre']) ?></p>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">RUC</small>
                        <p class="mb-0"><?= limpiar($ticket['ruc']) ?></p>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Contacto</small>
                        <p class="mb-0"><?= limpiar($ticket['contacto'] ?? '—') ?></p>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Sistema afectado</small>
                        <p class="mb-0">
                            <?php if ($ticket['plan_nombre']): ?>
                                <span class="badge text-bg-light"><i class="fa-solid <?= limpiar($ticket['plan_icono']) ?> me-1"></i><?= limpiar($ticket['plan_nombre']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">No especificado</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Categoría</small>
                        <p class="mb-0"><?= etiquetaCategoria($ticket['categoria']) ?></p>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Canal</small>
                        <p class="mb-0 text-capitalize"><?= limpiar($ticket['canal']) ?></p>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Prioridad</small>
                        <p class="mb-0"><?= badgePrioridad($ticket['prioridad']) ?></p>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Encargado de la solución</small>
                        <p class="mb-0"><?= $ticket['encargado_nombre'] ? limpiar($ticket['encargado_nombre']) : '<span class="text-muted">Sin asignar</span>' ?></p>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Creado</small>
                        <p class="mb-0"><?= formatoFecha($ticket['fecha_creacion']) ?></p>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block">Descripción</small>
                        <p class="mb-0" style="white-space:pre-line"><?= nl2br(limpiar($ticket['descripcion'])) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($satisfaccion): ?>
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="fa-solid fa-star me-2"></i>Satisfacción del cliente</div>
                <div class="card-body">
                    <p class="mb-1"><?= badgeSatisfaccion($satisfaccion['nivel']) ?></p>
                    <?php if ($satisfaccion['comentario']): ?>
                        <p class="text-muted mb-0 mt-2">"<?= limpiar($satisfaccion['comentario']) ?>"</p>
                    <?php endif; ?>
                    <small class="text-muted"><?= formatoFecha($satisfaccion['fecha']) ?></small>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="fa-solid fa-comments me-2"></i>Comunicación con el cliente</div>
            <div class="card-body">
                <?php if (empty($comentarios)): ?>
                    <p class="text-muted text-center mb-0">Sin mensajes todavía.</p>
                <?php else: ?>
                    <?php foreach ($comentarios as $c): ?>
                        <?php $esDelCliente = $c['autor_rol'] === 'cliente'; ?>
                        <div class="d-flex mb-3 <?= $esDelCliente ? '' : 'justify-content-end' ?>">
                            <div class="comment-bubble <?= $esDelCliente ? 'bg-light' : 'bg-primary text-white' ?>" style="max-width:85%">
                                <div class="d-flex justify-content-between gap-3">
                                    <strong><i class="fa-solid <?= $esDelCliente ? 'fa-building' : 'fa-headset' ?> me-1"></i><?= limpiar($c['autor_nombre']) ?></strong>
                                    <small class="<?= $esDelCliente ? 'text-muted' : 'text-white-50' ?>"><?= formatoFecha($c['fecha']) ?></small>
                                </div>
                                <div class="mt-1" style="white-space:pre-line"><?= nl2br(limpiar($c['mensaje'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="POST" action="index.php?ruta=ticket-comentar" class="mt-3">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                    <textarea name="mensaje" rows="2" class="form-control mb-2" placeholder="Escribe un mensaje para el cliente..." required></textarea>
                    <button class="btn btn-sm btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Enviar al cliente</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold"><i class="fa-solid fa-timeline me-2"></i>Historial</div>
            <div class="card-body">
                <?php if (empty($historial)): ?>
                    <p class="text-muted text-center mb-0">Sin movimientos.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($historial as $h): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-body">
                                    <div class="d-flex justify-content-between">
                                        <span><?= limpiar($h['estado_anterior'] ?? 'N/A') ?> <i class="fa-solid fa-arrow-right mx-1 text-muted"></i> <strong><?= limpiar($h['estado_nuevo']) ?></strong></span>
                                        <small class="text-muted"><?= formatoFecha($h['fecha']) ?></small>
                                    </div>
                                    <?php if ($h['usuario_nombre']): ?>
                                        <small class="text-muted">por <?= limpiar($h['usuario_nombre']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($h['comentario']): ?>
                                        <div class="timeline-comment"><?= limpiar($h['comentario']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if ($puedeAsignar): ?>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="fa-solid fa-user-check me-2"></i>Asignar solución</div>
            <div class="card-body">
                <form method="POST" action="index.php?ruta=ticket-asignar">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                    <label class="form-label small">Soporte</label>
                    <select name="usuario_id" class="form-select mb-2" required>
                        <option value="">Elige soporte...</option>
                        <?php foreach ($agentes as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) $ticket['usuario_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= limpiar($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label small">Encargado de la solución</label>
                    <select name="encargado_id" class="form-select mb-2">
                        <option value="">Sin encargado</option>
                        <?php foreach ($agentes as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) $ticket['encargado_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= limpiar($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-user-check me-1"></i>Guardar asignación</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header fw-semibold"><i class="fa-solid fa-arrows-rotate me-2"></i>Cambiar estado</div>
            <div class="card-body">
                <form method="POST" action="index.php?ruta=ticket-estado">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                    <label class="form-label small">Encargado de la solución</label>
                    <select name="encargado_id" class="form-select mb-2">
                        <option value="">Sin encargado</option>
                        <?php foreach ($agentes as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) $ticket['encargado_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= limpiar($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label small">Estado</label>
                    <select name="estado" class="form-select mb-2" required>
                        <option value="nuevo" <?= $ticket['estado'] === 'nuevo' ? 'selected' : '' ?>>Nuevo</option>
                        <option value="en_curso" <?= $ticket['estado'] === 'en_curso' ? 'selected' : '' ?>>En curso</option>
                        <option value="pausado" <?= $ticket['estado'] === 'pausado' ? 'selected' : '' ?>>Pausado</option>
                        <option value="culminado" <?= $ticket['estado'] === 'culminado' ? 'selected' : '' ?>>Culminado</option>
                        <option value="resuelto" <?= $ticket['estado'] === 'resuelto' ? 'selected' : '' ?>>Resuelto</option>
                        <option value="cerrado" <?= $ticket['estado'] === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
                    </select>
                    <input type="text" name="comentario" class="form-control mb-2" placeholder="Comentario (opcional)">
                    <?php if ($esEncargado): ?>
                        <div class="form-text text-muted mb-2">
                            <i class="fa-solid fa-bell me-1"></i>Al marcar <strong>Culminado</strong> se notificará a administración.
                        </div>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-primary w-100">Actualizar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../_footer.php'; ?>
