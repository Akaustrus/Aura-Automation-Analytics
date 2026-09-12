<?php
$pars = [];
foreach ($db->query('SELECT * FROM parametros ORDER BY id') as $r) $pars[$r['clave']] = $r['valor'];
$siguiente = [];
foreach (tiposComprobante() as $t) {
    $serie = serieActual($db, $t);
    $siguiente[$t] = numeroComprobante($serie, siguienteNumero($db, $t, $serie));
}
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-gears me-2"></i>Configuración</h2>

<form method="POST" class="card mb-4">
    <input type="hidden" name="action" value="guardar_parametros">
    <?= campoCsrf() ?>
    <div class="card-header"><i class="fa-solid fa-building me-1"></i>Datos de la empresa emisora</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Razón social <span class="text-danger">*</span></label>
                <input type="text" name="RAZON_SOCIAL" class="form-control" required maxlength="150" value="<?= limpiar($pars['RAZON_SOCIAL'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">RUC <span class="text-danger">*</span></label>
                <input type="text" name="RUC" class="form-control" required maxlength="11" value="<?= limpiar($pars['RUC'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Dirección fiscal</label>
                <input type="text" name="DIRECCION" class="form-control" maxlength="200" value="<?= limpiar($pars['DIRECCION'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Teléfono</label>
                <input type="text" name="TELEFONO" class="form-control" maxlength="20" value="<?= limpiar($pars['TELEFONO'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Igv (%) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" max="100" name="IGV_PORC" class="form-control" required value="<?= limpiar($pars['IGV_PORC'] ?? '18') ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Correo de contacto</label>
                <input type="email" name="CORREO" class="form-control" maxlength="120" value="<?= limpiar($pars['CORREO'] ?? '') ?>">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i>Estos datos aparecen en el encabezado de facturas, boletas y PDFs.</small>
        <button class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Guardar</button>
    </div>
</form>

<div class="card">
    <div class="card-header"><i class="fa-solid fa-list-ol me-1"></i>Series de comprobantes</div>
    <div class="card-body">
        <p class="small text-muted">
            La numeración es <strong>correlativa automática</strong> por serie. Cambie la serie solo si comienza
            a utilizar un nuevo talonario.
        </p>
        <div class="row g-3">
            <?php foreach (tiposComprobante() as $t): ?>
                <div class="col-md-3">
                    <label class="form-label"><?= $t ?></label>
                    <input type="text" name="SERIE_<?= $t ?>" class="form-control" maxlength="4" value="<?= limpiar($pars['SERIE_' . $t] ?? '') ?>" placeholder="<?= seriePorTipo($t) ?>">
                    <small class="text-muted">Siguiente: <strong><?= $siguiente[$t] ?></strong></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-footer">
        <button form="formSeries" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Guardar series</button>
    </div>
</div>

<form method="POST" id="formSeries" hidden>
    <input type="hidden" name="action" value="guardar_parametros">
    <?= campoCsrf() ?>
</form>

<script>
// Permitir guardar solo las series con el formulario oculto
(function(){
    const tipoSeries = ['FACTURA','BOLETA','NOTA_CREDITO','NOTA_DEBITO'];
    document.querySelectorAll('input[name^="SERIE_"]').forEach(inp => {
        inp.addEventListener('change', function(){
            const f = document.getElementById('formSeries');
            tipoSeries.forEach(t => {
                const source = document.querySelector('input[name="SERIE_' + t + '"]');
                const field = document.createElement('input');
                field.type = 'hidden'; field.name = 'SERIE_' + t; field.value = source.value;
                f.appendChild(field);
            });
        });
    });
})();
</script>