<?php
$tabla = $_GET['tabla'] ?? '';
$sql = 'SELECT * FROM audit_log WHERE 1=1';
$args = [];
if ($tabla !== '') { $sql .= ' AND tabla = ?'; $args[] = $tabla; }
if (!empty($_GET['accion'])) { $sql .= ' AND accion = ?'; $args[] = $_GET['accion']; }
$stmt = $db->prepare($sql . ' ORDER BY id DESC LIMIT 200');
$stmt->execute($args);
$log = $stmt->fetchAll();

$tablas = $db->query('SELECT DISTINCT tabla FROM audit_log ORDER BY tabla')->fetchAll();
$acciones = $db->query('SELECT DISTINCT accion FROM audit_log ORDER BY accion')->fetchAll();
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-file-shield me-2"></i>Auditoría</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="auditoria">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Tabla</label>
            <select name="tabla" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($tablas as $t): ?>
                    <option value="<?= limpiar($t['tabla']) ?>" <?= $tabla===$t['tabla'] ? 'selected' : '' ?>><?= limpiar($t['tabla']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Acción</label>
            <select name="accion" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($acciones as $a): ?>
                    <option value="<?= limpiar($a['accion']) ?>" <?= ($_GET['accion'] ?? '')===$a['accion'] ? 'selected' : '' ?>><?= limpiar($a['accion']) ?></option>
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
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Tabla</th><th>Registro</th><th>Acción</th><th>Resumen</th><th>Usuario</th></tr>
                </thead>
                <tbody>
                <?php foreach ($log as $a): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($a['fecha'])) ?></td>
                        <td><span class="badge bg-info"><?= limpiar($a['tabla']) ?></span></td>
                        <td class="small"><?= $a['registro_id'] ?? '—' ?></td>
                        <td><?= badgeEstado($a['accion']) ?></td>
                        <td class="small"><?= limpiar($a['resumen']) ?></td>
                        <td class="small text-muted"><?= limpiar($a['usuario']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$log): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">Sin registros de auditoría.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>