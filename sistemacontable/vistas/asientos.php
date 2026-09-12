<?php
[$desde, $hasta] = rangoFechas();
$where = 'WHERE a.fecha BETWEEN :desde AND :hasta';
$asientos = $db->prepare('SELECT a.*, (SELECT COALESCE(SUM(debe),0) FROM asiento_detalle WHERE asiento_id=a.id) AS total_debe,
    (SELECT COALESCE(SUM(haber),0) FROM asiento_detalle WHERE asiento_id=a.id) AS total_haber
    FROM asientos a ' . $where . ' ORDER BY a.numero DESC, a.id DESC');
$asientos->execute([':desde'=>$desde, ':hasta'=>$hasta]);
$asientos = $asientos->fetchAll();

$editando = null;
$detalles = [];
if (!empty($_GET['editar'])) {
    $stmt = $db->prepare('SELECT * FROM asientos WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch();
    if ($editando) {
        $detStmt = $db->prepare('SELECT ad.*, c.codigo, c.nombre FROM asiento_detalle ad JOIN cuentas c ON c.id=ad.cuenta_id WHERE ad.asiento_id = ?');
        $detStmt->execute([$editando['id']]);
        $detalles = $detStmt->fetchAll();
    }
}
$cuentas = $db->query('SELECT id, codigo, nombre FROM cuentas WHERE activo=1 ORDER BY codigo')->fetchAll();
?>

<h2 class="page-title mb-4">
    <i class="fa-solid fa-book me-2"></i>Asientos contables
    <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalAsiento"><i class="fa-solid fa-plus me-1"></i>Nuevo asiento</button>
</h2>

<form method="GET" class="card p-3 mb-4">
    <input type="hidden" name="vista" value="asientos">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>">
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>N°</th><th>Fecha</th><th>Glosa</th><th class="text-end">Debe</th><th class="text-end">Haber</th><th>Estado</th><th class="text-end" style="width:140px">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($asientos as $a): ?>
                    <tr>
                        <td><?= $a['numero'] ?></td>
                        <td><?= formatoFecha($a['fecha']) ?></td>
                        <td><?= limpiar($a['glosa']) ?></td>
                        <td class="text-end"><?= number_format((float) $a['total_debe'], 2) ?></td>
                        <td class="text-end"><?= number_format((float) $a['total_haber'], 2) ?></td>
                        <td><span class="badge <?= $a['estado']==='registrado'?'bg-success':'bg-secondary' ?>"><?= ucfirst($a['estado']) ?></span></td>
                        <td class="text-end">
                            <a href="?vista=asientos&editar=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este asiento?')">
                                <input type="hidden" name="action" value="eliminar_asiento">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <?= campoCsrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal crear / editar asiento -->
<div class="modal fade" id="modalAsiento" tabindex="-1" aria-labelledby="modalAsientoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" id="formAsiento">
            <input type="hidden" name="action" value="guardar_asiento">
            <?= campoCsrf() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalAsientoLabel"><?= $editando ? 'Editar asiento N° ' . $editando['numero'] : 'Nuevo asiento contable' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                <?php endif; ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" class="form-control" required value="<?= $editando['fecha'] ?? date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Glosa <span class="text-danger">*</span></label>
                        <input type="text" name="glosa" class="form-control" required maxlength="255" value="<?= limpiar($editando['glosa'] ?? '') ?>" placeholder="Descripción del asiento">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select w-25">
                        <option value="borrador" <?= ($editando['estado'] ?? 'borrador')==='borrador' ? 'selected' : '' ?>>Borrador</option>
                        <option value="registrado" <?= ($editando['estado'] ?? '')==='registrado' ? 'selected' : '' ?>>Registrado</option>
                    </select>
                </div>

                <h6 class="mb-2">Movimientos (debe / haber)</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="tablaDetAsiento">
                        <thead class="table-light">
                            <tr><th style="width:45%">Cuenta</th><th class="text-end" style="width:20%">Debe (S/)</th><th class="text-end" style="width:20%">Haber (S/)</th><th style="width:15%"></th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $detInicio = !empty($detalles) ? $detalles : [['cuenta_id' => '', 'debe' => '', 'haber' => '']];
                            foreach ($detInicio as $i => $det):
                            ?>
                            <tr>
                                <td>
                                    <select name="det_cuenta[]" class="form-select form-select-sm" required>
                                        <option value="">— Cuenta —</option>
                                        <?php foreach ($cuentas as $ct): ?>
                                            <option value="<?= $ct['id'] ?>" <?= (string)$ct['id']===(string)($det['cuenta_id'] ?? '') ? 'selected' : '' ?>><?= $ct['codigo'] ?> — <?= limpiar($ct['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" min="0" name="det_debe[]" class="form-control form-control-sm text-end" value="<?= $det['debe'] ?: '' ?>" placeholder="0.00"></td>
                                <td><input type="number" step="0.01" min="0" name="det_haber[]" class="form-control form-control-sm text-end" value="<?= $det['haber'] ?: '' ?>" placeholder="0.00"></td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger quitar-fila"><i class="fa-solid fa-xmark"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success" id="agregarFila"><i class="fa-solid fa-plus me-1"></i>Agregar línea</button>
                <div class="text-end mt-2">
                    <strong>Debe: <span id="totalDebe">0.00</span></strong> &nbsp;|&nbsp;
                    <strong>Haber: <span id="totalHaber">0.00</span></strong> &nbsp;
                    <span id="balanceOk" class="badge bg-success d-none">Cuadra</span>
                    <span id="balanceNo" class="badge bg-danger d-none">No cuadra</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Guardar asiento</button>
            </div>
        </form>
    </div>
</div>

<?php if ($editando): ?>
<script>document.addEventListener('DOMContentLoaded', function(){ new bootstrap.Modal(document.getElementById('modalAsiento')).show(); });</script>
<?php endif; ?>

<script>
(function(){
    const tbody = document.querySelector('#tablaDetAsiento tbody');
    const cuentasHtml = `<select name="det_cuenta[]" class="form-select form-select-sm" required>
        <option value="">— Cuenta —</option>
        <?php foreach ($cuentas as $ct): ?>
        <option value="<?= $ct['id'] ?>"><?= $ct['codigo'] ?> — <?= limpiar($ct['nombre']) ?></option>
        <?php endforeach; ?>
    </select>`;

    function recalcular(){
        let debe = 0, haber = 0;
        tbody.querySelectorAll('tr').forEach(function(r){
            const d = parseFloat(r.querySelector('input[name="det_debe[]"]').value) || 0;
            const h = parseFloat(r.querySelector('input[name="det_haber[]"]').value) || 0;
            debe += d; haber += h;
        });
        document.getElementById('totalDebe').textContent = debe.toFixed(2);
        document.getElementById('totalHaber').textContent = haber.toFixed(2);
        document.getElementById('balanceOk').classList.toggle('d-none', Math.abs(debe - haber) > 0.01 || (debe === 0 && haber === 0));
        document.getElementById('balanceNo').classList.toggle('d-none', Math.abs(debe - haber) <= 0.01 || (debe === 0 && haber === 0));
    }

    function bindEvents(){
        tbody.querySelectorAll('input[name="det_debe[]"], input[name="det_haber[]"]').forEach(function(inp){
            if (!inp.dataset.bound) { inp.addEventListener('input', recalcular); inp.dataset.bound = '1'; }
        });
    }

    document.getElementById('agregarFila').addEventListener('click', function(){
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${cuentasHtml}</td>
            <td><input type="number" step="0.01" min="0" name="det_debe[]" class="form-control form-control-sm text-end" placeholder="0.00"></td>
            <td><input type="number" step="0.01" min="0" name="det_haber[]" class="form-control form-control-sm text-end" placeholder="0.00"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger quitar-fila"><i class="fa-solid fa-xmark"></i></button></td>`;
        tbody.appendChild(tr);
        bindEvents(); recalcular();
    });

    tbody.addEventListener('click', function(e){
        if (e.target.closest('.quitar-fila')) {
            const row = e.target.closest('tr');
            if (tbody.querySelectorAll('tr').length > 1) { row.remove(); recalcular(); }
        }
    });

    bindEvents(); recalcular();
})();
</script>