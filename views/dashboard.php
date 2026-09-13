<?php require __DIR__ . '/_header.php';
$porEstado = $porEstado ?? [];
$notificaciones = $notificaciones ?? [];
$promedioSatisfaccion = $promedioSatisfaccion ?? null;
$sistemas = $sistemas ?? [];
$totalEmpresas = $totalEmpresas ?? 0;
$empresasSinPlan = $empresasSinPlan ?? 0;
$porEstadoPlan = $porEstadoPlan ?? [];
$metricasSla = $metricasSla ?? null;
?>

<section class="page-heading admin-hero mb-4">
    <div>
        <span class="page-eyebrow">Operaciones</span>
        <h1 class="page-title mb-1">Panel de control</h1>
        <p class="page-subtitle mb-0">Hola, <?= limpiar($_SESSION['usuario_nombre'] ?? 'equipo') ?>. Aquí tienes el estado operativo de Aura.</p>
    </div>
    <div class="page-actions">
        <a href="index.php?ruta=planes" class="btn btn-outline-secondary"><i class="fa-solid fa-layer-group me-1"></i>Sistemas</a>
        <a href="index.php?ruta=empresa-nueva" class="btn btn-outline-secondary"><i class="fa-solid fa-building-plus me-1"></i>Nueva empresa</a>
        <a href="index.php?ruta=atender" class="btn btn-primary"><i class="fa-solid fa-headset me-1"></i>Atender llamada</a>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="stat stat-1">
            <i class="fa-solid fa-ticket stat-icon"></i>
            <div class="stat-num"><?= array_sum(array_column($porEstado, 'total')) ?></div>
            <div class="stat-label">Incidencias totales</div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat stat-2">
            <i class="fa-solid fa-clipboard-check stat-icon"></i>
            <div class="stat-num"><?= (int) ($porEstado['culminado']['total'] ?? 0) ?></div>
            <div class="stat-label">Culminados por validar</div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat stat-3">
            <i class="fa-solid fa-briefcase stat-icon"></i>
            <div class="stat-num"><?= (int) ($porEstado['nuevo']['total'] ?? 0) + (int) ($porEstado['en_curso']['total'] ?? 0) ?></div>
            <div class="stat-label">En atención</div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat stat-4">
            <i class="fa-solid fa-star stat-icon"></i>
            <div class="stat-num"><?= $promedioSatisfaccion !== null ? number_format($promedioSatisfaccion, 1) . '/5' : '—' ?></div>
            <div class="stat-label">Satisfacción promedio</div>
        </div>
    </div>
</div>

<?php if ($metricasSla !== null):
    $cumplimiento = $metricasSla['cumplimiento'];
    $cumpleObjetivo = $cumplimiento !== null && $cumplimiento >= $metricasSla['objetivo'];
?>
<section class="dashboard-section mb-4" aria-labelledby="sla-title">
    <div class="section-heading">
        <div>
            <span class="section-kicker">Calidad de servicio</span>
            <h2 id="sla-title" class="section-title">Acuerdo de nivel de servicio (SLA)</h2>
        </div>
        <span class="status-pill <?= $cumpleObjetivo ? 'status-pill--success' : 'status-pill--warning' ?>">
            <i class="fa-solid <?= $cumpleObjetivo ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            Meta <?= (int) $metricasSla['objetivo'] ?>%
        </span>
    </div>

    <div class="kpi-grid">
        <article class="kpi-card <?= $cumpleObjetivo ? 'kpi-card--success' : 'kpi-card--warning' ?>">
            <div class="kpi-card__icon"><i class="fa-solid fa-gauge-high"></i></div>
            <div class="kpi-card__value"><?= $cumplimiento !== null ? number_format($cumplimiento, 1) . '%' : '—' ?></div>
            <div class="kpi-card__label">Cumplimiento SLA</div>
            <div class="kpi-card__meta"><?= (int) $metricasSla['cumplidos'] ?> de <?= (int) $metricasSla['evaluados'] ?> finalizados a tiempo</div>
        </article>
        <article class="kpi-card kpi-card--danger">
            <div class="kpi-card__icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="kpi-card__value"><?= (int) $metricasSla['vencidos'] ?></div>
            <div class="kpi-card__label">Fuera de SLA</div>
            <div class="kpi-card__meta">Abiertos o finalizados después del límite</div>
        </article>
        <article class="kpi-card kpi-card--warning">
            <div class="kpi-card__icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="kpi-card__value"><?= (int) $metricasSla['en_riesgo'] ?></div>
            <div class="kpi-card__label">En riesgo</div>
            <div class="kpi-card__meta">Consumieron al menos 75% del tiempo</div>
        </article>
        <article class="kpi-card kpi-card--info">
            <div class="kpi-card__icon"><i class="fa-solid fa-stopwatch"></i></div>
            <div class="kpi-card__value"><?= $metricasSla['tiempo_medio_horas'] !== null ? number_format($metricasSla['tiempo_medio_horas'], 1) . ' h' : '—' ?></div>
            <div class="kpi-card__label">Tiempo medio de resolución</div>
            <div class="kpi-card__meta">Sobre incidencias finalizadas</div>
        </article>
    </div>

    <div class="sla-breakdown mt-3" aria-label="Objetivos SLA por prioridad">
        <?php foreach ($metricasSla['por_prioridad'] as $prioridad => $datos): ?>
            <div class="sla-row">
                <span class="sla-priority"><?= badgePrioridad($prioridad) ?></span>
                <span class="sla-target"><?= (int) $datos['objetivo_horas'] ?> h objetivo</span>
                <span class="sla-summary"><?= (int) $datos['cumplidos'] ?> a tiempo · <?= (int) $datos['vencidos'] ?> vencidos · <?= (int) $datos['en_riesgo'] ?> en riesgo</span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-layer-group me-2"></i>Gestión de sistemas por empresa</span>
        <a href="index.php?ruta=planes" class="btn btn-sm btn-outline-primary">Ir a sistemas</a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="metric-tile">
                    <div class="metric-tile__value"><?= (int) $totalEmpresas ?></div>
                    <small class="text-muted">empresas clientes</small>
                    <a href="index.php?ruta=empresas" class="d-block small mt-1">Ver listado</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-tile">
                    <div class="metric-tile__value"><?= (int) ($porEstadoPlan['activo'] ?? 0) ?></div>
                    <small class="text-muted">suscripciones activas</small>
                    <a href="index.php?ruta=planes" class="d-block small mt-1">Ver detalle</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-tile">
                    <div class="metric-tile__value <?= ($porEstadoPlan['vencido'] ?? 0) ? 'text-danger' : 'text-warning' ?>"><?= (int) ($porEstadoPlan['pendiente'] ?? 0) + (int) ($porEstadoPlan['vencido'] ?? 0) ?></div>
                    <small class="text-muted">pendientes + vencidos</small>
                    <a href="index.php?ruta=planes" class="d-block small mt-1">Revisar</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-tile">
                    <div class="metric-tile__value <?= (int) $empresasSinPlan ? 'text-danger' : '' ?>"><?= (int) $empresasSinPlan ?></div>
                    <small class="text-muted">empresas sin planes</small>
                    <a href="index.php?ruta=empresas" class="d-block small mt-1">Asignar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="fa-solid fa-chart-simple me-2"></i>Incidencias por estado</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($porEstado as $fila): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= badgeEstado($fila['estado']) ?></span>
                        <span class="badge text-bg-light"><?= (int) $fila['total'] ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($porEstado)): ?>
                    <li class="list-group-item text-muted text-center">Sin datos</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-bell me-2"></i>Notificaciones de culminado</span>
                <?php if (count($notificaciones) > 0): ?>
                    <form method="POST" action="index.php?ruta=notificaciones-leidas" class="m-0">
                        <?= campoCsrf() ?>
                        <button class="btn btn-sm btn-outline-secondary">Marcar todas leídas</button>
                    </form>
                <?php endif; ?>
            </div>
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
                        <a href="index.php?ruta=ticket-detalle&id=<?= (int) $n['ticket_id'] ?>" class="btn btn-sm btn-outline-primary ms-2 flex-shrink-0">
                            <i class="fa-solid fa-eye me-1"></i>Ver
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($notificaciones)): ?>
                    <li class="list-group-item notif-empty text-center">
                        <i class="fa-regular fa-bell-slash d-block mb-2" style="font-size: 2rem;"></i>
                        Sin notificaciones pendientes.
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
