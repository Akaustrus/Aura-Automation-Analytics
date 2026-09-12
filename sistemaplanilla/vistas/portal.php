<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Cerrar sesión del portal
if (!empty($_GET['salir'])) {
    unset($_SESSION['portal_empleado_id']);
}

// Verificación de credenciales
$verificado = false;
$empleado = null;
if (!empty($_SESSION['portal_empleado_id'])) {
    $stmt = $db->prepare('SELECT * FROM empleados WHERE id=?'); $stmt->execute([$_SESSION['portal_empleado_id']]);
    $empleado = $stmt->fetch();
    $verificado = (bool) $empleado;
}

$error = '';
if (!$verificado && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['portal_accion'] ?? '') === 'verificar') {
    $dni   = trim($_POST['dni'] ?? '');
    $token = trim($_POST['token'] ?? '');
    if ($dni === '' || $token === '') {
        $error = 'Ingrese DNI y token.';
    } else {
        $stmt = $db->prepare('SELECT * FROM empleados WHERE dni=? AND portal_token=?');
        $stmt->execute([$dni, $token]);
        $empleado = $stmt->fetch();
        if ($empleado) {
            $_SESSION['portal_empleado_id'] = (int) $empleado['id'];
            $verificado = true;
        } else {
            $error = 'Credenciales incorrectas. Verifique DNI y token con RR. HH.';
        }
    }
}

if ($verificado) {
    $stmt = $db->prepare('SELECT d.*, p.periodo, p.tipo_planilla, p.fecha_generacion
                          FROM detalle_planilla d JOIN planillas p ON p.id=d.planilla_id
                          WHERE d.empleado_id=? AND p.estado=\'emitida\'
                          ORDER BY p.periodo DESC');
    $stmt->execute([(int) $empleado['id']]);
    $boletas = $stmt->fetchAll();
}
?>

<div class="d-flex justify-content-center py-4">
    <div class="col-md-10 col-lg-8">
        <?php if ($verificado): ?>
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-user-lock me-1"></i>Portal del empleado</span>
                    <a href="?vista=portal&salir=1" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-right-from-bracket me-1"></i>Cerrar sesión</a>
                </div>
                <div class="card-body">
                    <div class="alert alert-success d-flex align-items-center gap-3">
                        <i class="fa-solid fa-circle-check fs-3"></i>
                        <div>
                            <strong><?= limpiar($empleado['apellidos']) ?>, <?= limpiar($empleado['nombres']) ?></strong><br>
                            <span class="small text-muted"><?= limpiar($empleado['cargo']) ?> — <?= limpiar($empleado['area']) ?> · DNI <?= limpiar($empleado['dni']) ?><br>
                            Token personal: <code><?= limpiar($empleado['portal_token']) ?></code></span>
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3"><i class="fa-solid fa-file-invoice me-1"></i>Mis boletas de pago</h6>
                    <div class="list-group">
                        <?php foreach ($boletas as $b): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= nombreTipoPlanilla($b['tipo_planilla']) ?> — <?= nombrePeriodo($b['periodo']) ?></strong>
                                    <div class="small text-muted">Emitida el <?= formatoFecha($b['fecha_generacion']) ?> · Neto: <strong class="text-success">S/ <?= number_format($b['neto'],2) ?></strong></div>
                                </div>
                                <a target="_blank" href="index.php?vista=boleta&id=<?= $b['id'] ?>&sin_shell=1" class="btn btn-sm btn-primary"><i class="fa-solid fa-eye me-1"></i>Ver / Imprimir</a>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($boletas)): ?>
                            <div class="list-group-item text-center text-muted py-4">Aún no tienes boletas emitidas.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-header"><i class="fa-solid fa-user-lock me-1"></i>Portal del empleado — Acceso</div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-1"></i><?= limpiar($error) ?></div>
                    <?php endif; ?>
                    <p class="text-muted small">Ingrese su DNI y el token personal entregado por Recursos Humanos para ver sus boletas de pago.</p>
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="portal_accion" value="verificar">
                        <div class="col-md-6">
                            <label class="form-label">DNI</label>
                            <input type="text" name="dni" class="form-control" required maxlength="8" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Token personal</label>
                            <input type="text" name="token" class="form-control" required maxlength="20" autocomplete="off">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary"><i class="fa-solid fa-right-to-bracket me-1"></i>Ingresar</button>
                        </div>
                    </form>
                    <hr>
                    <div class="small text-muted">
                        <strong>Tokens de prueba (semilla):</strong><br>
                        DNI 40123456 → <code>aa11bb22cc33dd44ee</code><br>
                        DNI 40234567 → <code>bb22cc33dd44ee55ff</code><br>
                        DNI 40345678 → <code>cc33dd44ee55ff66a0</code><br>
                        DNI 40456789 → <code>dd44ee55ff66a0bb1c</code><br>
                        DNI 40567890 → <code>ee55ff66a0bb1c2d3e</code>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>