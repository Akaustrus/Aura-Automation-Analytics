<?php require __DIR__ . '/_header.php';
$empresa = $empresa ?? [];
$error = $_GET['error'] ?? null;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="page-title mb-0"><i class="fa-solid fa-pen me-2"></i>Editar empresa</h2>
    <a href="index.php?ruta=empresa-detalle&id=<?= (int) $empresa['id'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver al detalle
    </a>
</div>

<?php if ($error === 'faltan_datos'): ?>
    <div class="alert alert-danger d-flex align-items-center"><i class="fa-solid fa-circle-exclamation me-2"></i>Completa al menos el nombre y el RUC de la empresa.</div>
<?php elseif ($error === 'ruc_existe'): ?>
    <div class="alert alert-danger d-flex align-items-center"><i class="fa-solid fa-circle-exclamation me-2"></i>Ya existe otra empresa con ese RUC.</div>
<?php elseif ($error === 'clave_corta'): ?>
    <div class="alert alert-danger d-flex align-items-center"><i class="fa-solid fa-circle-exclamation me-2"></i>La clave debe tener al menos 4 caracteres.</div>
<?php endif; ?>

<form method="POST" action="index.php?ruta=empresa-actualizar">
    <?= campoCsrf() ?>
    <input type="hidden" name="id" value="<?= (int) $empresa['id'] ?>">

    <div class="card">
        <div class="card-header fw-semibold"><i class="fa-solid fa-building me-2"></i>Datos de <?= limpiar($empresa['empresa']) ?></div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Razón social</label>
                    <input type="text" name="empresa" class="form-control" value="<?= limpiar($empresa['empresa']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">RUC</label>
                    <input type="text" name="ruc" maxlength="20" class="form-control font-monospace" value="<?= limpiar($empresa['ruc']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Persona de contacto</label>
                    <input type="text" name="contacto" class="form-control" value="<?= limpiar($empresa['contacto'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" value="<?= limpiar($empresa['telefono'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Correo</label>
                    <input type="email" name="email" class="form-control" value="<?= limpiar($empresa['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nueva clave (opcional)</label>
                    <input type="text" name="clave" minlength="4" class="form-control" placeholder="Déjala vacía para no cambiarla">
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar cambios</button>
        <a href="index.php?ruta=empresa-detalle&id=<?= (int) $empresa['id'] ?>" class="btn btn-light">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/_footer.php'; ?>