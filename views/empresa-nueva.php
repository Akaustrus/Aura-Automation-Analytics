<?php require __DIR__ . '/_header.php';
$planes = $planes ?? [];
$error = $_GET['error'] ?? null;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="page-title mb-0"><i class="fa-solid fa-building-circle-plus me-2"></i>Registrar nueva empresa</h2>
    <a href="index.php?ruta=empresas" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver a empresas
    </a>
</div>

<?php if ($error === 'faltan_datos'): ?>
    <div class="alert alert-danger d-flex align-items-center"><i class="fa-solid fa-circle-exclamation me-2"></i>Completa al menos el nombre y el RUC de la empresa.</div>
<?php elseif ($error === 'ruc_existe'): ?>
    <div class="alert alert-danger d-flex align-items-center"><i class="fa-solid fa-circle-exclamation me-2"></i>Ya existe una empresa con ese RUC.</div>
<?php endif; ?>

<form method="POST" action="index.php?ruta=empresa-guardar">
    <?= campoCsrf() ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="fa-solid fa-building me-2"></i>Datos de la empresa</div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Razón social</label>
                            <input type="text" name="empresa" class="form-control" placeholder="Ej. Chamitos S.A.C." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">RUC</label>
                            <input type="text" name="ruc" maxlength="20" class="form-control font-monospace" placeholder="Ej. 20612345678" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Clave de acceso al portal</label>
                            <input type="text" name="clave" minlength="4" class="form-control" placeholder="Por defecto: 123456">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Persona de contacto</label>
                            <input type="text" name="contacto" class="form-control" placeholder="Ej. Pedro Chamito">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" placeholder="Ej. 900999000">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Correo</label>
                            <input type="email" name="email" class="form-control" placeholder="info@empresa.com">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="fa-solid fa-layer-group me-2"></i>Sistemas que quiere adquirir</div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">Marca los sistemas que la empresa desea contratar. Se
                        registrarán como <strong>pendientes</strong> hasta que los actives desde sus planes.</p>
                    <?php foreach ($planes as $p): ?>
                        <div class="form-check form-check-inline mb-3 w-100">
                            <input class="form-check-input" type="checkbox" name="planes[]" value="<?= (int) $p['id'] ?>" id="plan_<?= (int) $p['id'] ?>">
                            <label class="form-check-label d-flex align-items-center gap-2" for="plan_<?= (int) $p['id'] ?>">
                                <span class="app-logo app-logo--sm"><i class="fa-solid <?= limpiar($p['icono']) ?>"></i></span>
                                <span>
                                    <span class="d-block fw-semibold"><?= limpiar($p['nombre']) ?></span>
                                    <small class="text-muted"><?= (int) $p['modulos'] ?> módulos</small>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($planes)): ?>
                        <p class="text-muted text-center mb-0">No hay sistemas disponibles.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Registrar empresa</button>
        <a href="index.php?ruta=empresas" class="btn btn-light">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/_footer.php'; ?>