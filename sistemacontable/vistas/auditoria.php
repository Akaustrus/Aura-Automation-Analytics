<?php
$tabla = $_GET['tabla'] ?? '';
$perm = ['', 'cuentas', 'asientos', 'documentos', 'importaciones'];
if (!in_array($tabla, $perm, true)) $tabla = '';

$sql = 'SELECT * FROM audit_log';
$params = [];
if ($tabla !== '') {
    $sql .= ' WHERE tabla = :t';
    $params[':t'] = $tabla;
}
$sql .= ' ORDER BY fecha DESC, id DESC LIMIT 200';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$etiquetaTabla = ['cuentas'=>'Plan de cuentas', 'asientos'=>'Asientos', 'documentos'=>'Documentos', 'importaciones'=>'Importaciones'];
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-clipboard-list me-2"></i>Auditoría — log de cambios</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="auditoria">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Filtrar por tabla</label>
            <select name="tabla" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($etiquetaTabla as $k=>$l): ?>
                    <option value="<?= $k ?>" <?= $tabla===$k?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Tabla</th><th>ID</th><th>Acción</th><th>Resumen</th><th>Usuario</th></tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($l['fecha'])) ?></td>
                        <td><?= $etiquetaTabla[$l['tabla']] ?? $l['tabla'] ?></td>
                        <td><?= $l['registro_id'] ?: '—' ?></td>
                        <td>
                            <?php $m = ['crear'=>'success','editar'=>'primary','eliminar'=>'danger']; ?>
                            <span class="badge bg-<?= $m[$l['accion']] ?? 'secondary' ?>"><?= ucfirst($l['accion']) ?></span>
                        </td>
                        <td><?= limpiar($l['resumen']) ?></td>
                        <td><?= limpiar($l['usuario']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="text-muted small mt-2">Se muestran los últimos 200 eventos. Solo lectura.</div>