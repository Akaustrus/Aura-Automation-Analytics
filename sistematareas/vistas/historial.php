<?php
/** Historial de auditoría: qué se creó, editó, eliminó o cambió de estado. */
$historial = $db->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 200')->fetchAll();
?>
<h2 class="page-title mb-4"><i class="fa-solid fa-clock-rotate-left me-2"></i>Historial de actividad</h2>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Acción</th><th>Detalle</th><th>Usuario</th></tr>
                </thead>
                <tbody>
                <?php if (empty($historial)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Sin actividad registrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($historial as $h): ?>
                    <?php
                    $clase = ['crear'=>'success','editar'=>'primary','eliminar'=>'danger','estado'=>'warning'][$h['accion']] ?? 'secondary';
                    $icono = ['crear'=>'fa-plus','editar'=>'fa-pen','eliminar'=>'fa-trash','estado'=>'fa-arrows-rotate'][$h['accion']] ?? 'fa-circle-info';
                    ?>
                    <tr>
                        <td class="text-muted small"><?= limpiar($h['fecha']) ?></td>
                        <td><span class="badge bg-<?= $clase ?>"><i class="fa-solid <?= $icono ?> me-1"></i><?= ucfirst($h['accion']) ?></span></td>
                        <td><?= limpiar($h['resumen']) ?></td>
                        <td class="text-muted"><?= limpiar($h['usuario'] ?? 'sistema') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
