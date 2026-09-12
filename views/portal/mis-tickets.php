<?php
$portalTitulo = 'Mis Tickets';
require __DIR__ . '/_portal_header.php';
$documentos = $documentos ?? [];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title mb-0"><i class="fa-solid fa-ticket me-2"></i>Mis incidencias</h2>
        <div class="text-end d-flex align-items-center gap-2">
            <a href="index.php?ruta=portal-nueva-incidencia" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-plus me-1"></i>Nueva incidencia
            </a>
            <div>
                <small class="text-muted d-block"><?= limpiar($_SESSION['cliente_empresa']) ?></small>
            </div>
        </div>
    </div>

    <div class="alert alert-info d-flex align-items-center">
        <i class="fa-solid fa-circle-info me-2"></i>
        <div>Reporta una nueva incidencia en cualquier momento desde el portal. También puedes llamarnos
        por teléfono con tu <strong>RUC</strong> y <strong>clave</strong>. Aquí puedes dar
        <strong>seguimiento</strong> y <strong>calificar tu satisfacción</strong>.</div>
    </div>

    <div class="card">
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
                        <td class="text-muted">#<?= (int) $t['id'] ?></td>
                        <td class="fw-semibold"><?= limpiar($t['asunto']) ?></td>
                        <td>
                            <?php if ($t['plan_nombre']): ?>
                                <span class="badge text-bg-light"><i class="fa-solid <?= limpiar($t['plan_icono']) ?> me-1"></i><?= limpiar($t['plan_nombre']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge text-bg-light"><?= etiquetaCategoria($t['categoria']) ?></span></td>
                        <td><?= badgePrioridad($t['prioridad']) ?></td>
                        <td><?= badgeEstado($t['estado']) ?></td>
                        <td><?= $t['encargado_nombre'] ? limpiar($t['encargado_nombre']) : '<span class="text-muted">Por asignar</span>' ?></td>
                        <td><a href="index.php?ruta=portal-ticket&id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($documentos)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">Todavía no tienes incidencias registradas.</td></tr>
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
