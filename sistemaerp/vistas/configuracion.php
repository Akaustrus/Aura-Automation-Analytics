<?php
$editables = [
    'EMPRESA_NOMBRE'    => 'Razón social de la empresa',
    'EMPRESA_RUC'       => 'RUC',
    'EMPRESA_DIRECCION' => 'Dirección fiscal',
    'EMPRESA_TELEFONO'  => 'Teléfono',
    'EMPRESA_CORREO'    => 'Correo de contacto',
    'MONEDA'            => 'Moneda (SOLES / DOLARES)',
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
                    <li class="mb-2"><i class="fa-solid fa-cart-shopping text-primary me-2"></i><strong>Ventas:</strong> el sistema valida el stock disponible y lo descuenta automáticamente al registrar.</li>
                    <li class="mb-2"><i class="fa-solid fa-truck-fast text-primary me-2"></i><strong>Compras:</strong> al registrar ingresas mercadería con su costo y al anular la compra se descuenta el stock.</li>
                    <li class="mb-2"><i class="fa-solid fa-ban text-danger me-2"></i><strong>Anulaciones:</strong> una venta anulada restituye el stock; una compra anulada lo descuenta. Los documentos anulados no se pueden editar.</li>
                    <li class="mb-2"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i><strong>Stock mínimo:</strong> se configura por producto; el panel y el inventario avisan cuando un ítem cae por debajo del mínimo.</li>
                    <li class="mb-2"><i class="fa-solid fa-coins text-primary me-2"></i><strong>Caja:</strong> registra ingresos y egresos reales; el saldo se calcula automáticamente.</li>
                    <li class="mb-2"><i class="fa-solid fa-clipboard-check text-primary me-2"></i><strong>Exportación:</strong> ventas, compras, inventario, movimientos y caja se pueden descargar en Excel, PDF, Word y CSV desde Reportes.</li>
                    <li><i class="fa-solid fa-file-shield text-primary me-2"></i><strong>Auditoría:</strong> todas las acciones importantes quedan registradas.</li>
                </ul>
            </div>
        </div>
    </div>
</div>