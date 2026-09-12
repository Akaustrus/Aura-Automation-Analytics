<?php
$portalTitulo = 'Nueva incidencia';
require __DIR__ . '/_portal_header.php';
$misPlanes = $misPlanes ?? [];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title mb-0"><i class="fa-solid fa-plus me-2"></i>Reportar nueva incidencia</h2>
        <a href="index.php?ruta=portal-mis-tickets" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Mis incidencias
        </a>
    </div>

    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="index.php?ruta=portal-crear-incidencia" id="ticket-form">
                <?= campoCsrf() ?>

                <h6 class="fw-semibold text-uppercase mb-3" style="color: var(--teal);">
                    <i class="fa-solid fa-circle-info me-2"></i>¿Qué sucede?
                </h6>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">¿En qué sistema?</label>
                        <select name="plan_id" class="form-select">
                            <option value="">No especifica sistema</option>
                            <?php foreach ($misPlanes as $p): ?>
                                <option value="<?= (int) $p['plan_id'] ?>">
                                    <?= limpiar($p['plan_nombre']) ?> (<?= etiquetaPlanEstado($p['estado']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Motivo principal</label>
                        <select name="categoria" class="form-select" required>
                            <option value="">Selecciona un motivo...</option>
                            <option value="rendimiento">Rendimiento (la página carga lento)</option>
                            <option value="error">Error / no carga / no funciona</option>
                            <option value="consulta">Consulta / dudas</option>
                            <option value="funcion_nueva">Pedido de nueva función</option>
                            <option value="actualizacion">Solicitud de actualización</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Impacto del problema</label>
                        <select name="impacto" class="form-select urg-input" required>
                            <option value="">Selecciona el impacto...</option>
                            <option value="ninguno">Ninguno / informativo</option>
                            <option value="parcial">Parcial (afecta una parte)</option>
                            <option value="total">Total (no funciona nada)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">¿Detuvo tus operaciones?</label>
                        <select name="detener_operaciones" class="form-select urg-input">
                            <option value="no">No</option>
                            <option value="si">Sí</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">¿Es un problema recurrente?</label>
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

                <hr>

                <div class="mb-3">
                    <label class="form-label">Asunto</label>
                    <input type="text" name="asunto" maxlength="200" class="form-control"
                           placeholder="Ej. La página de ventas carga muy lento" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Describe el problema</label>
                    <textarea name="descripcion" rows="5" class="form-control"
                              placeholder="Cuéntanos qué pasa, desde cuándo y qué esperabas que sucediera..." required></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Enviar incidencia</button>
                    <a href="index.php?ruta=portal-mis-tickets" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_portal_footer.php'; ?>
