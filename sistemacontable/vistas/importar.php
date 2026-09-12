<h2 class="page-title mb-4"><i class="fa-solid fa-file-import me-2"></i>Importación CSV</h2>

<p class="text-muted">Sube un archivo CSV (punto y coma o coma) con los datos exactos de las columnas.
El sistema los inserta automáticamente. El archivo <strong>debe incluir la fila de encabezados</strong>.</p>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Documentos / Boletas</div>
            <div class="card-body">
                <h6 class="text-muted small">Columnas requeridas</h6>
                <table class="table table-sm small">
                    <tr><th>Columna</th><th>Ejemplo</th></tr>
                    <tr><td>tipo</td><td>FACTURA / BOLETA</td></tr>
                    <tr><td>numero</td><td>F001-000100</td></tr>
                    <tr><td>fecha</td><td>2026-05-12</td></tr>
                    <tr><td>emisor</td><td>Proveedor S.A.C.</td></tr>
                    <tr><td>ruc_dni</td><td>20123456789</td></tr>
                    <tr><td>concepto</td><td>Descripción del servicio</td></tr>
                    <tr><td>importe</td><td>1000.00 (base sin IGV)</td></tr>
                </table>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="importar_csv">
                    <input type="hidden" name="tipo_import" value="documentos">
                    <?= campoCsrf() ?>
                    <input type="file" name="csv" class="form-control mb-2" accept=".csv" required>
                    <button class="btn btn-primary btn-sm"><i class="fa-solid fa-upload me-1"></i>Importar documentos</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Asientos contables</div>
            <div class="card-body">
                <h6 class="text-muted small">Columnas requeridas</h6>
                <table class="table table-sm small">
                    <tr><th>Columna</th><th>Ejemplo</th></tr>
                    <tr><td>fecha</td><td>2026-05-12</td></tr>
                    <tr><td>glosa</td><td>Venta de mercadería</td></tr>
                    <tr><td>cuenta</td><td>101 o nombre de la cuenta</td></tr>
                    <tr><td>debe</td><td>4720.00</td></tr>
                    <tr><td>haber</td><td>0.00</td></tr>
                </table>
                <div class="alert alert-light border small">
                    Una fila = un movimiento. Para un asiento con 2 cuentas, usa <strong>2 filas</strong> con la misma fecha y glosa.
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="importar_csv">
                    <input type="hidden" name="tipo_import" value="asientos">
                    <?= campoCsrf() ?>
                    <input type="file" name="csv" class="form-control mb-2" accept=".csv" required>
                    <button class="btn btn-primary btn-sm"><i class="fa-solid fa-upload me-1"></i>Importar asientos</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><i class="fa-solid fa-circle-info me-1"></i>Ejemplo de archivo</div>
    <div class="card-body small">
        <pre class="bg-light p-3 rounded-3 mb-0">tipo;numero;fecha;emisor;ruc_dni;concepto;importe
FACTURA;F001-000100;2026-05-12;Proveedor SAC;20123456789;Servicio de analytics;1200.00
BOLETA;B001-000010;2026-05-13;Cliente Z;20225555666;Venta de software;800.00</pre>
    </div>
</div>