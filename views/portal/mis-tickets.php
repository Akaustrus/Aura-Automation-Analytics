<?php
$portalTitulo = 'Mis Tickets';
require __DIR__ . '/_portal_header.php';
$documentos = $documentos ?? [];
$totalIncidencias = count($documentos);
$incidenciasAbiertas = count(array_filter($documentos, fn ($t) => in_array($t['estado'], ['nuevo', 'en_curso', 'pausado', 'culminado'], true)));
$incidenciasResueltas = count(array_filter($documentos, fn ($t) => in_array($t['estado'], ['resuelto', 'cerrado'], true)));
?>

<div class="container portal-container py-4 py-lg-5">
    <section class="portal-welcome mb-4">
        <div class="portal-welcome__content">
            <span class="page-eyebrow">Centro de soporte</span>
            <h1>Hola, <?= limpiar($_SESSION['cliente_empresa']) ?></h1>
            <p>Consulta el avance de tus solicitudes o cuéntanos cómo podemos ayudarte.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="index.php?ruta=portal-nueva-incidencia" class="btn btn-primary">
                    <i class="fa-solid fa-plus me-1"></i>Reportar incidencia
                </a>
                <a href="index.php?ruta=portal-agendar" class="btn btn-outline-secondary">
                    <i class="fa-regular fa-calendar me-1"></i>Agendar atención
                </a>
                <a href="index.php?ruta=portal-planes" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-table-cells-large me-1"></i>Abrir mis sistemas
                </a>
            </div>
        </div>
        <div class="portal-welcome__visual" aria-hidden="true">
            <span><i class="fa-solid fa-headset"></i></span>
            <small>Soporte Aura</small>
            <strong>Estamos para ayudarte</strong>
        </div>
    </section>

    <div class="portal-summary mb-4" aria-label="Resumen de incidencias">
        <div class="portal-summary__item">
            <span class="portal-summary__icon"><i class="fa-solid fa-inbox"></i></span>
            <div><strong><?= $totalIncidencias ?></strong><span>Total</span></div>
        </div>
        <div class="portal-summary__item">
            <span class="portal-summary__icon portal-summary__icon--warning"><i class="fa-solid fa-spinner"></i></span>
            <div><strong><?= $incidenciasAbiertas ?></strong><span>En seguimiento</span></div>
        </div>
        <div class="portal-summary__item">
            <span class="portal-summary__icon portal-summary__icon--success"><i class="fa-solid fa-circle-check"></i></span>
            <div><strong><?= $incidenciasResueltas ?></strong><span>Resueltas</span></div>
        </div>
    </div>

    <div class="section-heading mb-3">
        <div>
            <span class="section-kicker">Historial</span>
            <h2 class="section-title">Mis incidencias</h2>
        </div>
        <span class="text-muted small">Selecciona una incidencia para ver sus mensajes y detalles.</span>
    </div>

    <div class="card portal-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Asunto</th>
                    <th>Sistema</th>
                    <th>Categoría</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Encargado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($documentos as $t): ?>
                    <tr>
                        <td data-label="Ticket" class="text-muted">#<?= (int) $t['id'] ?></td>
                        <td data-label="Asunto" class="fw-semibold"><?= limpiar($t['asunto']) ?></td>
                        <td data-label="Sistema">
                            <?php if ($t['plan_nombre']): ?>
                                <span class="badge text-bg-light"><i class="fa-solid <?= limpiar($t['plan_icono']) ?> me-1"></i><?= limpiar($t['plan_nombre']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Categoría"><span class="badge text-bg-light"><?= etiquetaCategoria($t['categoria']) ?></span></td>
                        <td data-label="Prioridad"><?= badgePrioridad($t['prioridad']) ?></td>
                        <td data-label="Estado"><?= badgeEstado($t['estado']) ?></td>
                        <td data-label="Encargado"><?= $t['encargado_nombre'] ? limpiar($t['encargado_nombre']) : '<span class="text-muted">Por asignar</span>' ?></td>
                        <td class="portal-table-action"><a href="index.php?ruta=portal-ticket&id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary">Ver detalle</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($documentos)): ?>
                    <tr><td colspan="8" class="text-center py-5">
                        <div class="empty-state">
                            <span class="empty-state__icon"><i class="fa-regular fa-circle-check"></i></span>
                            <strong>Todo está en orden</strong>
                            <p>No tienes incidencias registradas por el momento.</p>
                            <a href="index.php?ruta=portal-nueva-incidencia" class="btn btn-primary btn-sm">Reportar incidencia</a>
                        </div>
                    </td></tr>
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
