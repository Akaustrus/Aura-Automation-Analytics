<?php
$portalTitulo = 'Mis sistemas';
require __DIR__ . '/_portal_header.php';
$misPlanes = $misPlanes ?? [];
$porEstado = $porEstado ?? [];
?>

<div class="container portal-container py-4 py-lg-5">
    <div class="page-heading mb-4">
        <div>
            <span class="page-eyebrow">Suscripciones</span>
            <h1 class="page-title mb-1">Mis sistemas contratados</h1>
            <p class="page-subtitle mb-0">Licencias, vigencias e incidencias de tus soluciones Aura.</p>
        </div>
        <a href="index.php?ruta=portal-mis-tickets" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-ticket me-1"></i>Mis incidencias
        </a>
    </div>

    <div class="alert alert-info d-flex align-items-center">
        <i class="fa-solid fa-circle-info me-2"></i>
        <div>Estos son los <strong>sistemas de <?= limpiar($_SESSION['cliente_empresa']) ?></strong> y el estado de su
            suscripción. Reporta cualquier falla o solicitud desde <strong>Nueva incidencia</strong>.
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat stat-1">
                <i class="fa-solid fa-boxes-stacked stat-icon"></i>
                <div class="stat-num"><?= count($misPlanes) ?></div>
                <div class="stat-label">Sistemas contratados</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat stat-2">
                <i class="fa-solid fa-circle-check stat-icon"></i>
                <div class="stat-num"><?= (int) ($porEstado['activo'] ?? 0) ?></div>
                <div class="stat-label">Activos</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat stat-3">
                <i class="fa-solid fa-clock stat-icon"></i>
                <div class="stat-num"><?= (int) ($porEstado['pendiente'] ?? 0) ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat stat-4">
                <i class="fa-solid fa-triangle-exclamation stat-icon"></i>
                <div class="stat-num"><?= (int) ($porEstado['vencido'] ?? 0) ?></div>
                <div class="stat-label">Por renovar</div>
            </div>
        </div>
    </div>

    <section class="systems-launcher" aria-labelledby="systems-launcher-title">
        <div class="section-heading mb-3">
            <div>
                <span class="section-kicker">Centro de aplicaciones</span>
                <h2 class="section-title" id="systems-launcher-title">Ingresa a tus sistemas</h2>
                <p class="page-subtitle mb-0">Selecciona una solución activa para comenzar a trabajar.</p>
            </div>
        </div>

        <div class="row g-3">
        <?php foreach ($misPlanes as $indice => $p):
            $accesoSistema = urlSistemaPortal($p['plan_slug']);
            $puedeIngresar = $p['estado'] === 'activo' && $accesoSistema !== null;
        ?>
            <div class="col-md-6 col-xl-4">
                <article class="card h-100 portal-system-card portal-system-card--<?= limpiar($p['plan_slug']) ?>">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-3">
                                <span class="app-logo"><i class="fa-solid <?= limpiar($p['icono']) ?>"></i></span>
                                <div>
                                    <span class="portal-system-card__order"><?= str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                    <h5 class="mb-0 fw-semibold"><?= limpiar($p['plan_nombre']) ?></h5>
                                    <small class="text-muted"><?= (int) $p['plan_modulos'] ?> módulos incluidos</small>
                                </div>
                            </div>
                            <?= badgePlanEstado($p['estado']) ?>
                        </div>

                        <p class="text-muted small"><?= limpiar($p['plan_descripcion']) ?></p>

                        <div class="row g-2 mb-3 small">
                            <div class="col-6">
                                <div class="text-muted">Usuarios / licencias</div>
                                <div class="fw-semibold"><?= (int) $p['usuarios'] ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">Precio mensual</div>
                                <div class="fw-semibold"><?= formatoMoneda($p['precio_mensual']) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">Inicio</div>
                                <div class="fw-semibold"><?= formatoFechaCorta($p['fecha_inicio']) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">Vencimiento</div>
                                <div class="fw-semibold"><?= formatoFechaCorta($p['fecha_vencimiento']) ?></div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 small mb-3">
                            <i class="fa-solid fa-ticket text-muted"></i>
                            <span><?= (int) $p['tickets'] ?> incidencias en este sistema</span>
                        </div>

                        <div class="mt-auto d-grid gap-2">
                            <?php if ($puedeIngresar): ?>
                                <a href="<?= limpiar($accesoSistema) ?>" class="btn btn-primary portal-system-card__launch">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Abrir sistema
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary" disabled>
                                    <i class="fa-solid fa-lock me-1"></i>Sistema no disponible
                                </button>
                            <?php endif; ?>
                            <a href="index.php?ruta=portal-plan&id=<?= (int) $p['id'] ?>" class="btn btn-link btn-sm portal-system-card__detail">
                                Ver detalle e incidencias
                            </a>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>

        <?php if (empty($misPlanes)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="fa-solid fa-box-open d-block mb-2" style="font-size: 2.5rem;"></i>
                        Tu empresa aún no tiene sistemas contratados. Ponte en contacto con nosotros
                        para adquirir un plan.
                    </div>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </section>
</div>

<?php
if (!empty($_SESSION['cliente_id'])) { echo "\n</main>"; }
require __DIR__ . '/_portal_footer.php';
?>
