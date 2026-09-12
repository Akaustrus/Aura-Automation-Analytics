<?php require __DIR__ . '/../_header.php';
$cliente = $cliente ?? null;
$planesCliente = $planesCliente ?? [];
$error = $error ?? '';
$claveCapturada = $_REQUEST['clave'] ?? '';
?>

<h2 class="page-title"><i class="fa-solid fa-headset me-2"></i>Atención telefónica</h2>

<?php if ($cliente): ?>
    <div class="alert alert-success d-flex align-items-center">
        <i class="fa-solid fa-circle-check me-2 fs-5"></i>
        <div>
            <strong>Cliente verificado:</strong> <?= limpiar($cliente['empresa']) ?>
            (RUC <?= limpiar($cliente['ruc']) ?>) ·
            <span class="text-muted"><?= limpiar($cliente['contacto'] ?? '') ?> — <?= limpiar($cliente['telefono']) ?></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold"><i class="fa-solid fa-ticket me-2"></i>Registrar incidencia ERP</div>
        <div class="card-body p-4">
            <form method="POST" action="index.php?ruta=atender-crear" id="ticket-form">
                <?= campoCsrf() ?>
                <input type="hidden" name="ruc" value="<?= limpiar($cliente['ruc']) ?>">
                <input type="hidden" name="clave" value="<?= htmlspecialchars($claveCapturada, ENT_QUOTES, 'UTF-8') ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sistema afectado</label>
                        <select name="plan_id" class="form-select">
                            <option value="">No especifica sistema</option>
                            <?php foreach ($planesCliente as $pc): ?>
                                <option value="<?= (int) $pc['plan_id'] ?>">
                                    <i class="fa-solid <?= limpiar($pc['icono']) ?>"></i>
                                    <?= limpiar($pc['plan_nombre']) ?><?= $pc['estado'] !== 'activo' ? ' (' . etiquetaPlanEstado($pc['estado']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Categoría</label>
                        <select name="categoria" class="form-select" required>
                            <option value="rendimiento">Rendimiento (lento)</option>
                            <option value="error">Error / no carga</option>
                            <option value="funcion_nueva">Nueva función</option>
                            <option value="actualizacion">Actualización</option>
                            <option value="consulta">Consulta</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Canal</label>
                        <select name="canal" class="form-select">
                            <option value="telefono">Teléfono</option>
                            <option value="correo">Correo</option>
                            <option value="portal">Portal</option>
                        </select>
                    </div>
                </div>

                <hr>
                <h6 class="fw-semibold"><i class="fa-solid fa-gauge-high me-2"></i>Evaluación de urgencia (en tiempo real)</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">¿Cuál es el impacto del problema?</label>
                        <select name="impacto" class="form-select urg-input" required>
                            <option value="ninguno">Ninguno / informativo</option>
                            <option value="parcial">Parcial (afecta una parte)</option>
                            <option value="total">Total (no funciona nada)</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">¿Detuvo las operaciones?</label>
                        <select name="detener_operaciones" class="form-select urg-input">
                            <option value="no">No</option>
                            <option value="si">Sí</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">¿Problema recurrente?</label>
                        <select name="reapertura" class="form-select urg-input">
                            <option value="no">No</option>
                            <option value="si">Sí</option>
                        </select>
                    </div>
                </div>

                <div class="alert alert-info d-flex justify-content-between align-items-center" id="urg-result">
                    <div class="small text-muted">La prioridad se calcula automáticamente al responder.</div>
                    <div class="text-end">
                        <span class="text-muted small d-block">Prioridad sugerida</span>
                        <span class="badge text-bg-secondary fs-6" id="urg-prioridad">—</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Asunto</label>
                    <input type="text" name="asunto" maxlength="200" class="form-control" placeholder="Ej. El dashboard está lento / No cargan los usuarios" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" rows="4" class="form-control" placeholder="Detalla lo reportado por la empresa..." required></textarea>
                </div>

                <button class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Registrar y abrir ticket</button>
                <a href="index.php?ruta=tickets" class="btn btn-light">Cancelar</a>
            </form>
        </div>
    </div>

<?php else: ?>
    <div class="alert alert-warning d-flex align-items-center">
        <i class="fa-solid fa-phone me-2"></i>
        <div>Pide a la empresa su <strong>RUC</strong> y su <strong>clave</strong> para validar sus datos antes de agendar la incidencia.</div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="fa-solid fa-user-check me-2"></i>Validar empresa</div>
                <div class="card-body p-4">
                    <?php if (!empty($error) && ($_POST['accion'] ?? '') !== 'registrar'): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-2"></i><span><?= limpiar($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php?ruta=atender" class="row g-3">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="accion" value="validar">
                        <div class="col-12">
                            <label class="form-label">RUC de la empresa</label>
                            <input type="text" name="ruc" maxlength="20" class="form-control" placeholder="Ej. 20612345678" required autofocus>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Clave de la empresa</label>
                            <input type="password" name="clave" class="form-control" placeholder="Clave que da por teléfono" required>
                        </div>
                        <button class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Validar</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="fa-solid fa-building-circle-plus me-2"></i>Registrar nueva empresa</div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">¿El cliente aún no está en el sistema? Regístralo con sus datos para poder agendar sus incidencias.</p>

                    <?php if (!empty($error) && ($_POST['accion'] ?? '') === 'registrar'): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-2"></i><span><?= limpiar($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php?ruta=atender" class="row g-3">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="accion" value="registrar">
                        <div class="col-12">
                            <label class="form-label">Nombre de la empresa</label>
                            <input type="text" name="empresa" class="form-control" placeholder="Ej. Chamitos S.A.C." required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">RUC</label>
                            <input type="text" name="ruc" maxlength="20" class="form-control" placeholder="Ej. 20612345678" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Clave de acceso</label>
                            <input type="password" name="clave" class="form-control" placeholder="Define una clave" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Persona de contacto</label>
                            <input type="text" name="contacto" class="form-control" placeholder="Ej. Pedro Chamito">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" placeholder="Ej. 900999000">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Correo (opcional)</label>
                            <input type="email" name="email" class="form-control" placeholder="info@empresa.com">
                        </div>
                        <button class="btn btn-outline-primary w-100"><i class="fa-solid fa-plus me-1"></i>Registrar empresa</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../_footer.php'; ?>
