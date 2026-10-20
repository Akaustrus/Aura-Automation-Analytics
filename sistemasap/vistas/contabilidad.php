<?php
$cuentas = $db->query('SELECT * FROM cuentas_sap ORDER BY codigo')->fetchAll();
$limit = (int) ($_GET['limit'] ?? 200);
$transacciones = $db->query("SELECT t.*, c.codigo AS cuentac, c.nombre AS cuentanombre FROM transacciones t JOIN cuentas_sap c ON c.id=t.cuenta_id ORDER BY t.fecha DESC, t.id DESC LIMIT $limit")->fetchAll();

$totalActivo = (float) $db->query("SELECT COALESCE(SUM(saldo),0) FROM cuentas_sap WHERE tipo='activo'")->fetchColumn();
$totalPasivo = (float) $db->query("SELECT COALESCE(SUM(saldo),0) FROM cuentas_sap WHERE tipo='pasivo'")->fetchColumn();
$totalPatrimonio = (float) $db->query("SELECT COALESCE(SUM(saldo),0) FROM cuentas_sap WHERE tipo='patrimonio'")->fetchColumn();
$totalIngresos = (float) $db->query("SELECT COALESCE(SUM(saldo),0) FROM cuentas_sap WHERE tipo='ingreso'")->fetchColumn();
$totalEgresos = (float) $db->query("SELECT COALESCE(SUM(saldo),0) FROM cuentas_sap WHERE tipo='egreso'")->fetchColumn();
$resultado = $totalIngresos - $totalEgresos;

$ingresos30 = (float) $db->query("SELECT COALESCE(SUM(monto),0) FROM transacciones WHERE tipo='ingreso' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$egresos30 = (float) $db->query("SELECT COALESCE(SUM(monto),0) FROM transacciones WHERE tipo='egreso' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
?>

<section class="page-heading module-page-heading mb-4">
    <div>
        <span class="page-eyebrow">Contabilidad</span>
        <h1 class="page-title mb-1">Estado financiero</h1>
        <p class="page-subtitle mb-0">Balance integrado, cuentas y transacciones del negocio.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTransaccion"><i class="fa-solid fa-plus me-1"></i>Registrar transacción</button>
</section>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat stat-1"><div class="stat-icon"><i class="fa-solid fa-landmark"></i></div><div class="stat-num"><?= formatoMoneda($totalActivo) ?></div><div class="stat-label">Activos</div></div></div>
    <div class="col-md-3"><div class="stat stat-6"><div class="stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div><div class="stat-num"><?= formatoMoneda($totalPasivo) ?></div><div class="stat-label">Pasivos</div></div></div>
    <div class="col-md-3"><div class="stat stat-3"><div class="stat-icon"><i class="fa-solid fa-chart-pie"></i></div><div class="stat-num"><?= formatoMoneda($totalPatrimonio) ?></div><div class="stat-label">Patrimonio</div></div></div>
    <div class="col-md-3"><div class="stat stat-<?= $resultado >= 0 ? 2 : 6 ?>"><div class="stat-icon"><i class="fa-solid fa-arrow-trend-up"></i></div><div class="stat-num"><?= formatoMoneda($resultado) ?></div><div class="stat-label">Resultado (ingresos - egresos)</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-scale-balanced me-1"></i>Balance de comprobación</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Código</th><th>Cuenta</th><th>Tipo</th><th class="text-end">Saldo</th></tr></thead>
                        <tbody>
                        <?php foreach ($cuentas as $c): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= limpiar($c['codigo']) ?></span></td>
                                <td><?= limpiar($c['nombre']) ?></td>
                                <td><small class="text-uppercase text-muted"><?= limpiar($c['tipo']) ?></small></td>
                                <td class="text-end <?= (float) $c['saldo'] < 0 ? 'text-danger' : '' ?>"><strong><?= formatoMoneda((float) $c['saldo']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-chart-pie me-1"></i>Distribución por tipo de cuenta</div>
            <div class="card-body">
                <canvas id="graficoBalances" style="max-height:200px"></canvas>
            </div>
            <div class="card-body border-top pt-3">
                <div class="row text-center g-2">
                    <div class="col-6">
                        <div style="font-size:1.2rem;font-weight:700;color:#16a34a"><?= formatoMoneda($ingresos30) ?></div>
                        <div style="font-size:.75rem;color:var(--muted)">Ingresos últimos 30 días</div>
                    </div>
                    <div class="col-6">
                        <div style="font-size:1.2rem;font-weight:700;color:#dc2626"><?= formatoMoneda($egresos30) ?></div>
                        <div style="font-size:.75rem;color:var(--muted)">Egresos últimos 30 días</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-money-bill-transfer me-1"></i>Transacciones recientes</span>
        <span class="badge bg-primary-subtle text-primary">últimas <?= $limit ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Cuenta</th><th>Tipo</th><th>Descripción</th><th class="text-end">Monto</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($transacciones as $t): ?>
                    <tr>
                        <td><?= formatoFecha($t['fecha']) ?></td>
                        <td><small><?= limpiar($t['cuentac']) ?> — <?= limpiar($t['cuentanombre']) ?></small></td>
                        <td><?= $t['tipo'] === 'ingreso' ? '<span class="badge bg-success"><i class="fa-solid fa-arrow-down me-1"></i>Ingreso</span>' : '<span class="badge bg-danger"><i class="fa-solid fa-arrow-up me-1"></i>Egreso</span>' ?></td>
                        <td><small><?= limpiar($t['descripcion']) ?></small></td>
                        <td class="text-end"><strong class="<?= $t['tipo'] === 'ingreso' ? 'text-success' : 'text-danger' ?>"><?= $t['tipo'] === 'ingreso' ? '+' : '-' ?><?= formatoMoneda((float) $t['monto']) ?></strong></td>
                        <td class="text-end">
                            <form method="post" class="d-inline" data-confirm="¿Eliminar transacción?">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" name="action" value="eliminar_transaccion" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTransaccion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= campoCsrf() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-plus me-1"></i>Registrar transacción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cuenta</label>
                        <select name="cuenta_id" class="form-select" required>
                            <?php foreach ($cuentas as $c): ?>
                                <option value="<?= $c['id'] ?>">[<?= limpiar($c['codigo']) ?>] <?= limpiar($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select">
                                <option value="ingreso">Ingreso</option>
                                <option value="egreso">Egreso</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Monto *</label><input type="number" name="monto" class="form-control" step="0.01" min="0.01" required></div>
                        <div class="col-md-6"><label class="form-label">Fecha</label><input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                        <div class="col-12"><label class="form-label">Descripción</label><input type="text" name="descripcion" class="form-control" placeholder="Ej: Pago de proveedor, venta al contado..."></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="action" value="guardar_transaccion" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    new Chart(document.getElementById('graficoBalances'), {
        type: 'doughnut',
        data: {
            labels: ['Activos', 'Pasivos', 'Patrimonio'],
            datasets: [{
                data: [<?= $totalActivo ?>, <?= $totalPasivo ?>, <?= $totalPatrimonio ?>],
                backgroundColor: ['#16a34a', '#ef4444', '#4f46e5']
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });
});
</script>