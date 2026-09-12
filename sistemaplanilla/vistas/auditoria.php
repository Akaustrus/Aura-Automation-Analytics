<?php
$fTabla  = $_GET['tabla'] ?? '';
$fBusca  = trim($_GET['busca'] ?? '');
$fAccion = $_GET['accion'] ?? '';

$sql = 'SELECT a.* FROM audit_log a WHERE 1=1';
$param = [];
if ($fTabla !== '') { $sql .= ' AND a.tabla = ?'; $param[] = $fTabla; }
if ($fAccion !== '') { $sql .= ' AND a.accion = ?'; $param[] = $fAccion; }
if ($fBusca !== '') { $sql .= ' AND (a.resumen LIKE ? OR a.usuario LIKE ?)'; $param[] = "%$fBusca%"; $param[] = "%$fBusca%"; }
$sql .= ' ORDER BY a.id DESC LIMIT 300';
$stmt = $db->prepare($sql);
$stmt->execute($param);
$logs = $stmt->fetchAll();

$tablas = $db->query('SELECT DISTINCT tabla FROM audit_log ORDER BY tabla')->fetchAll(PDO::FETCH_COLUMN);
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-file-shield me-2"></i>Auditoría</h2>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <input type="hidden" name="vista" value="auditoria">
            <div class="col-md-3">
                <label class="form-label">Tabla</label>
                <select name="tabla" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($tablas as $t): ?>
                        <option value="<?= limpiar($t) ?>" <?= $fTabla === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Acción</label>
                <select name="accion" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach (['crear','editar','eliminar'] as $a): ?>
                        <option value="<?= $a ?>" <?= $fAccion === $a ? 'selected' : '' ?>><?= ucfirst($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" name="busca" class="form-control" placeholder="Texto en el resumen o usuario" value="<?= limpiar($fBusca) ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-list me-1"></i>Eventos registrados (últimos 300)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Tabla</th><th>Registro</th><th>Acción</th><th>Resumen</th><th>Usuario</th></tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="text-nowrap small"><?= date('d/m/Y H:i', strtotime($l['fecha'])) ?></td>
                        <td><span class="badge bg-light border text-dark"><?= ucfirst($l['tabla']) ?></span></td>
                        <td class="small">#<?= $l['registro_id'] ?? '—' ?></td>
                        <td><?= $l['accion'] === 'crear' ? '<span class="badge bg-success">Crear</span>' : ($l['accion'] === 'eliminar' ? '<span class="badge bg-danger">Eliminar</span>' : '<span class="badge bg-secondary">Editar</span>') ?></td>
                        <td><?= limpiar($l['resumen']) ?></td>
                        <td class="small"><?= limpiar($l['usuario']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Sin eventos registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>