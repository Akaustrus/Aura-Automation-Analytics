<?php require __DIR__ . '/_header.php';
$error = $error ?? '';
?>

<div class="login-page">
    <div class="login-card">
        <div class="login-head">
            <span class="login-logo"><i class="fa-solid fa-headset"></i></span>
            <h1>Aura Automation &amp; Analytics</h1>
            <p>Acceso unificado · Equipo interno y clientes</p>
        </div>
        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><span><?= limpiar($error) ?></span>
                </div>
            <?php endif; ?>

            <ul class="nav nav-pills nav-justified mb-3" id="tipoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pane-interno" type="button">
                        <i class="fa-solid fa-user-gear me-1"></i>Equipo interno
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-cliente" type="button">
                        <i class="fa-solid fa-building me-1"></i>Cliente (empresa)
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Equipo interno: correo + contraseña -->
                <div class="tab-pane fade show active" id="pane-interno" role="tabpanel">
                    <form method="POST" action="index.php?ruta=login">
                        <?= campoCsrf() ?>
                        <div class="mb-3">
                            <label class="form-label">Correo</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" name="usuario" class="form-control" placeholder="tu@auraautomation.com" required autofocus>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                    </form>
                    <div class="text-center mt-3">
                        <small class="text-muted">Admin: <code>direccion@auraautomation.com</code> / <code>admin123</code></small>
                    </div>
                </div>

                <!-- Cliente empresa: RUC + clave -->
                <div class="tab-pane fade" id="pane-cliente" role="tabpanel">
                    <form method="POST" action="index.php?ruta=login">
                        <?= campoCsrf() ?>
                        <div class="mb-3">
                            <label class="form-label">RUC de la empresa</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>
                                <input type="text" name="usuario" maxlength="20" class="form-control" placeholder="Ej. 20612345678" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Clave</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="Tu clave" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                    </form>
                    <div class="text-center mt-3">
                        <small class="text-muted">Probá con <code>RUC 20123456789</code> / <code>clave123</code></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
