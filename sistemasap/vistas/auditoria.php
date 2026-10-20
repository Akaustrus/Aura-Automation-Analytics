<?php
$logs = $db->query('SELECT * FROM audit_log ORDER BY fecha DESC, id DESC LIMIT 200')->fetchAll();
?>

<section class="page-heading module-page-heading mb-4">
    <div>
        <span class="page-eyebrow">Auditoría</span>
        <h1 class="page-title mb-1">Log de cambios</h1>
        <p class="page-subtitle mb-0">Registro de todas las operaciones realizadas en el módulo SAP.</p>
    </div>
</section>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-clipboard-list me-1"></i>Eventos registrados</span>
        <span class="badge bg-primary-subtle text-primary"><?= count($logs) ?> eventos (últimos 200)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Usuario</th><th>Tabla</th><th>Registro</th><th>Acción</th><th>Resumen</th></tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><small><?= formatoFecha(substr($log['fecha'], 0, 10)) ?> <?= substr($log['fecha'], 11, 8) ?></small></td>
                        <td><small><?= limpiar($log['usuario']) ?></small></td>
                        <td><span class="badge bg-secondary"><?= limpiar($log['tabla']) ?></span> <span class="badge bg-light text-dark">#<?= (int)$log['registro_id'] ?></span></td>
                        <td>
                            <?php if ($log['accion'] === 'crear'): ?><span class="badge bg-success">Crear</span>
                            <?php elseif ($log['accion'] === 'editar'): ?><span class="badge bg-info">Editar</span>
                            <?php else: ?><span class="badge bg-danger">Eliminar</span><?php endif; ?>
                        </td>
                        <td><small><?= limpiar($log['resumen']) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>