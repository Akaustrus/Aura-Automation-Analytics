<?php
$editables = [
    'EMPRESA_NOMBRE'    => 'Razón social de la empresa',
    'EMPRESA_RUC'       => 'RUC',
    'EMPRESA_DIRECCION' => 'Dirección fiscal',
    'EMPRESA_TELEFONO'  => 'Teléfono',
    'EMPRESA_CORREO'    => 'Correo de contacto',
    'MONEDA'            => 'Moneda',
    'IGV'               => 'IGV (%)',
    'EMPRESA_MENSAJE'   => 'Mensaje en documentos',
];
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-gears me-2"></i>Configuración de la empresa</h2>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-building me-1"></i>Datos de la empresa</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="guardar_parametros">
                    <?= campoCsrf() ?>
                    <div class="row g-3">
                        <?php foreach ($editables as $clave => $label): ?>
                            <div class="col-md-12">
                                <label class="form-label"><?= $label ?></label>
                                <?php if ($clave === 'MONEDA'): ?>
                                    <select name="<?= $clave ?>" class="form-select">
                                        <?php foreach (['SOLES', 'DOLARES'] as $mon): ?>
                                            <option value="<?= $mon ?>" <?= parametro($db, $clave, 'SOLES')===$mon ? 'selected' : '' ?>><?= $mon ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($clave === 'IGV'): ?>
                                    <input type="number" name="<?= $clave ?>" class="form-control" value="<?= igvPorcentaje($db) ?>" step="0.01" min="0" max="100">
                                <?php else: ?>
                                    <input type="text" name="<?= $clave ?>" class="form-control" value="<?= limpiar(parametro($db, $clave)) ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-md-12">
                            <button class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Guardar configuración</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-circle-info me-1"></i>Ayuda rápida del ERP</div>
            <div class="card-body">
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><i class="fa-solid fa-cart-shopping text-primary me-2"></i><strong>Ventas:</strong> se selecciona el almacén, se valida el stock y se descuenta automáticamente; aplica IGV según la configuración.</li>
                    <li class="mb-2"><i class="fa-solid fa-file-lines text-primary me-2"></i><strong>Cotizaciones:</strong> al aceptarlas se genera automáticamente la venta (descuenta stock) y quedan vinculadas.</li>
                    <li class="mb-2"><i class="fa-solid fa-truck-fast text-primary me-2"></i><strong>Compras:</strong> registran ingreso de mercadería al almacén indicado; anular una compra descuenta el stock. Al recibir una orden de compra se genera la compra a crédito.</li>
                    <li class="mb-2"><i class="fa-solid fa-ban text-danger me-2"></i><strong>Anulaciones:</strong> una venta anulada restituye el stock; los documentos anulados no se pueden editar.</li>
                    <li class="mb-2"><i class="fa-solid fa-right-left text-primary me-2"></i><strong>Almacenes:</strong> el stock se controla por almacén y se pueden hacer traspasos entre ellos.</li>
                    <li class="mb-2"><i class="fa-solid fa-hand-holding-dollar text-primary me-2"></i><strong>Cuentas por cobrar/pagar:</strong> se calculan desde las ventas/compras a crédito menos los cobros/pagos registrados.</li>
                    <li class="mb-2"><i class="fa-solid fa-coins text-primary me-2"></i><strong>Caja:</strong> registra ingresos y egresos reales; el saldo se calcula automáticamente.</li>
                    <li class="mb-2"><i class="fa-solid fa-clipboard-check text-primary me-2"></i><strong>Exportación:</strong> reportes en Excel, PDF, Word y CSV desde Reportes.</li>
                    <li><i class="fa-solid fa-file-shield text-primary me-2"></i><strong>Auditoría:</strong> todas las acciones importantes quedan registradas.</li>
                </ul>
            </div>
        </div>
    </div>
</div>