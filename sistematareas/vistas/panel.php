<?php
/** Panel de estadísticas: gráfico de estado y prioridad. */
$est = estadisticasTareas($db);
$prioridades = $db->query('SELECT prioridad, COUNT(*) AS total FROM tareas GROUP BY prioridad')->fetchAll();
$porPrioridad = array_fill_keys(prioridadesTarea(), 0);
foreach ($prioridades as $p) {
    $porPrioridad[$p['prioridad']] = (int) $p['total'];
}
?>
<h2 class="page-title mb-4"><i class="fa-solid fa-chart-pie me-2"></i>Estadísticas</h2>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div>
            <div class="stat-num"><?= $est['total'] ?></div>
            <div class="stat-label">Total tareas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat stat-6">
            <div class="stat-icon"><i class="fa-solid fa-fire"></i></div>
            <div class="stat-num"><?= $est['criticas'] ?></div>
            <div class="stat-label">Críticas abiertas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat stat-5">
            <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="stat-num"><?= $est['vencidas'] ?></div>
            <div class="stat-label">Vencidas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat stat-4">
            <div class="stat-icon"><i class="fa-solid fa-percent"></i></div>
            <div class="stat-num"><?= $est['total'] > 0 ? round($est['completada'] * 100 / $est['total']) : 0 ?>%</div>
            <div class="stat-label">Avance total</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header py-3">Por estado</div>
            <div class="card-body">
                <div class="chart-box"><canvas id="chartEstado"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header py-3">Por prioridad</div>
            <div class="card-body">
                <div class="chart-box"><canvas id="chartPrioridad"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartEstado'), {
        type: 'doughnut',
        data: {
            labels: ['Pendientes', 'En proceso', 'Completadas'],
            datasets: [{
                data: [<?= (int)$est['pendiente'] ?>, <?= (int)$est['en_proceso'] ?>, <?= (int)$est['completada'] ?>],
                backgroundColor: ['#0f766e', '#14b8a6', '#64748b'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: { legend: { position: 'bottom' } }
        }
    });

    new Chart(document.getElementById('chartPrioridad'), {
        type: 'bar',
        data: {
            labels: ['Crítica', 'Alta', 'Media', 'Baja'],
            datasets: [{
                label: 'Tareas',
                data: [<?= (int)$porPrioridad['critica'] ?>, <?= (int)$porPrioridad['alta'] ?>, <?= (int)$porPrioridad['media'] ?>, <?= (int)$porPrioridad['baja'] ?>],
                backgroundColor: ['#ef4444', '#f59e0b', '#14b8a6', '#64748b'],
                borderRadius: 8,
                maxBarThickness: 56
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
});
</script>
