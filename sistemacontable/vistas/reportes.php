<?php
[$desde, $hasta] = rangoFechas();
$reportesSimples = [
    'documentos' => ['titulo' => 'Listado de documentos / boletas', 'icono' => 'fa-file-invoice', 'desc' => 'Todos los documentos con Base, IGV y Total del período.'],
    'asientos'   => ['titulo' => 'Libro diario', 'icono' => 'fa-book', 'desc' => 'Movimientos contables del período con totales Debe y Haber.'],
    'cuentas'    => ['titulo' => 'Plan de cuentas', 'icono' => 'fa-sitemap', 'desc' => 'Listado completo del catálogo de cuentas.'],
];
$formatos = [
    'xlsx' => ['label' => 'Excel', 'icono' => 'fa-file-excel', 'cls' => 'success'],
    'pdf'  => ['label' => 'PDF',   'icono' => 'fa-file-pdf',   'cls' => 'danger'],
    'doc'  => ['label' => 'Word',  'icono' => 'fa-file-word',  'cls' => 'primary'],
    'csv'  => ['label' => 'CSV',   'icono' => 'fa-file-csv',   'cls' => 'secondary'],
];
?>

<h2 class="page-title mb-4"><i class="fa-solid fa-chart-bar me-2"></i>Reportes y exportación</h2>

<div class="alert alert-light border small mb-4">
    Rango de fechas para los reportes contables:
    <strong><input type="date" id="fDesde" class="form-control form-control-sm d-inline-block w-auto mx-1" value="<?= $desde ?>"></strong> →
    <strong><input type="date" id="fHasta" class="form-control form-control-sm d-inline-block w-auto mx-1" value="<?= $hasta ?>"></strong>
    <button class="btn btn-sm btn-outline-secondary btn-fecha"><i class="fa-solid fa-check me-1"></i>Aplicar</button>
</div>

<div class="row g-3">
    <?php foreach ($reportesSimples as $clave => $rep): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                <i class="fa-solid <?= $rep['icono'] ?> fa-2x mb-3 text-success"></i>
                <h6 class="card-title"><?= $rep['titulo'] ?></h6>
                <p class="text-muted small mb-3"><?= $rep['desc'] ?></p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <?php foreach ($formatos as $fmt => $f): ?>
                    <a href="#" class="btn btn-sm btn-outline-<?= $f['cls'] ?> export-link"
                       data-tipo="<?= $clave ?>" data-formato="<?= $fmt ?>">
                        <i class="fa-solid <?= $f['icono'] ?> me-1"></i><?= $f['label'] ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<h4 class="mt-5 mb-3 text-secondary"><i class="fa-solid fa-scale-balanced me-2"></i>Reportes contables</h4>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                <i class="fa-solid fa-book-open fa-2x mb-3 text-primary"></i>
                <h6 class="card-title">Libro mayor por cuenta</h6>
                <p class="text-muted small mb-3">Movimientos y saldos acumulados por cuenta del período.</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <?php foreach ($formatos as $fmt => $f): ?>
                    <a href="#" class="btn btn-sm btn-outline-<?= $f['cls'] ?> export-link"
                       data-tipo="libro_mayor" data-formato="<?= $fmt ?>">
                        <i class="fa-solid <?= $f['icono'] ?> me-1"></i><?= $f['label'] ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                <i class="fa-solid fa-list-check fa-2x mb-3 text-primary"></i>
                <h6 class="card-title">Balance de comprobación</h6>
                <p class="text-muted small mb-3">Saldos deudores y acreedores por cuenta del período.</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <?php foreach ($formatos as $fmt => $f): ?>
                    <a href="#" class="btn btn-sm btn-outline-<?= $f['cls'] ?> export-link"
                       data-tipo="balance_comprob" data-formato="<?= $fmt ?>">
                        <i class="fa-solid <?= $f['icono'] ?> me-1"></i><?= $f['label'] ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                <i class="fa-solid fa-chart-pie fa-2x mb-3 text-primary"></i>
                <h6 class="card-title">Estados financieros</h6>
                <p class="text-muted small mb-3">P&G y Balance general consolidado del período.</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <?php foreach ($formatos as $fmt => $f): ?>
                    <a href="#" class="btn btn-sm btn-outline-<?= $f['cls'] ?> export-link"
                       data-tipo="estados" data-formato="<?= $fmt ?>">
                        <i class="fa-solid <?= $f['icono'] ?> me-1"></i><?= $f['label'] ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<h4 class="mt-5 mb-3 text-secondary"><i class="fa-solid fa-clock me-2"></i>Otros reportes</h4>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                <i class="fa-solid fa-hourglass-half fa-2x mb-3 text-warning"></i>
                <h6 class="card-title">Aging cuentas por pagar / cobrar</h6>
                <p class="text-muted small mb-3">Antigüedad de saldos por vencer, 0-30, 31-60, 61-90 y +90 días.</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <?php foreach ($formatos as $fmt => $f): ?>
                    <a href="#" class="btn btn-sm btn-outline-<?= $f['cls'] ?> export-link"
                       data-tipo="aging" data-formato="<?= $fmt ?>">
                        <i class="fa-solid <?= $f['icono'] ?> me-1"></i><?= $f['label'] ?></a>
                    <?php endforeach; ?>
                </div>
                <?php if (true): $porVen = 0; $v30=0; $v60=0; $v90=0; $v90mas=0; $tT=0;
                    foreach ($db->query("SELECT DATEDIFF(CURDATE(),fecha) dias,total FROM documentos WHERE estado='registrado'") as $r) {
                        $d=(int)$r['dias']; $m=(float)$r['total']; $tT+=$m;
                        if ($d<0) $porVen+=$m; elseif ($d<=30) $v30+=$m; elseif ($d<=60) $v60+=$m; elseif ($d<=90) $v90+=$m; else $v90mas+=$m;
                    }
                ?>
                <hr class="my-3">
                <div class="small text-start">
                    <div class="d-flex justify-content-between"><span>Por vencer</span><strong><?= number_format($porVen,2) ?></strong></div>
                    <div class="d-flex justify-content-between"><span>0-30 días</span><strong><?= number_format($v30,2) ?></strong></div>
                    <div class="d-flex justify-content-between"><span>31-60 días</span><strong><?= number_format($v60,2) ?></strong></div>
                    <div class="d-flex justify-content-between"><span>61-90 días</span><strong><?= number_format($v90,2) ?></strong></div>
                    <div class="d-flex justify-content-between"><span>+90 días</span><strong><?= number_format($v90mas,2) ?></strong></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between"><span>Total</span><strong><?= number_format($tT,2) ?></strong></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><i class="fa-solid fa-info-circle me-1"></i>Notas</div>
    <div class="card-body small text-muted">
        <ul class="mb-0">
            <li><strong>Excel (XLSX)</strong> — Archivo .xlsx real con formato. Se abre en Excel o LibreOffice.</li>
            <li><strong>PDF</strong> — Generado con FPDF, listo para imprimir o enviar.</li>
            <li><strong>Word (DOC)</strong> — Tabla formateada compatible con Word.</li>
            <li><strong>CSV</strong> — Texto plano separado por punto y coma.</li>
            <li>Los reportes de la sección superior usan el rango de fechas configurado arriba; el plan de cuentas no depende de fechas.</li>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const aplicar = () => {
        const d = document.getElementById('fDesde').value;
        const h = document.getElementById('fHasta').value;
        document.querySelectorAll('.export-link').forEach(a=>{
            let href = '?action=exportar&tipo=' + a.dataset.tipo + '&formato=' + a.dataset.formato;
            if (['documentos','asientos','libro_mayor','balance_comprob','estados'].includes(a.dataset.tipo)) href += '&desde=' + d + '&hasta=' + h;
            a.href = href;
        });
    };
    aplicar();
    document.querySelectorAll('.btn-fecha').forEach(b=>b.addEventListener('click', aplicar));
    document.querySelectorAll('#fDesde, #fHasta').forEach(i=>i.addEventListener('change', aplicar));
});
</script>