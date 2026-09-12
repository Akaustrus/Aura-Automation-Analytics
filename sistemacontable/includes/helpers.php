<?php
/**
 * Funciones auxiliares del Módulo Contable (independiente del ERP principal).
 */

function limpiar(?string $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function redireccionar(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function formatoMoneda(float $monto): string
{
    return 'S/ ' . number_format($monto, 2, '.', ',');
}

function formatoFecha(?string $fecha): string
{
    if (empty($fecha)) {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt ? $dt->format('d/m/Y') : $fecha;
}

function setMensaje(string $tipo, string $texto): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['contable_msg'] = ['tipo' => $tipo, 'texto' => $texto];
}

function getMensaje(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $m = $_SESSION['contable_msg'] ?? null;
    unset($_SESSION['contable_msg']);
    return $m ?? [];
}

function tokenCsrf(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['contable_csrf'])) {
        $_SESSION['contable_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['contable_csrf'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . limpiar(tokenCsrf()) . '">';
}

function verificarCsrf(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $dado = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['contable_csrf'] ?? '', (string) $dado)) {
        die('Solicitud no válida (CSRF).');
    }
}

/** Siguiente numero correlativo de asiento. */
function siguienteNumeroAsiento(PDO $db): int
{
    $n = (int) $db->query('SELECT COALESCE(MAX(numero), 0) AS n FROM asientos')->fetchColumn();
    return $n + 1;
}

/** Lista de tipos de documento para boletas. */
function tiposDocumento(): array
{
    return ['BOLETA', 'FACTURA', 'NOTA_CREDITO', 'NOTA_DEBITO', 'GUIA_REMISION', 'TICKET', 'OTRO'];
}

function tiposCuenta(): array
{
    return ['activo', 'pasivo', 'patrimonio', 'ingreso', 'egreso'];
}

/** IGV = 18% de la base imponible. */
const TASA_IGV = 0.18;

function calcularIgv(float $importe): float
{
    return round($importe * TASA_IGV, 2);
}

function calcularTotalConIgv(float $importe): float
{
    return round($importe + calcularIgv($importe), 2);
}

/** Registra un evento en el log de auditoría. */
function registrarAuditoria(
    PDO $db,
    string $tabla,
    int $registroId,
    string $accion,
    string $resumen = '',
    array $antes = [],
    array $despues = []
): void {
    $stmt = $db->prepare(
        'INSERT INTO audit_log (tabla, registro_id, accion, resumen, datos_anteriores, datos_nuevos, usuario)
         VALUES (:tabla, :r, :accion, :resumen, :antes, :despues, :usuario)'
    );
    $stmt->execute([
        ':tabla'   => $tabla,
        ':r'       => $registroId,
        ':accion'  => $accion,
        ':resumen' => $resumen,
        ':antes'   => $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
        ':despues' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
        ':usuario' => $_SESSION['contable_usuario'] ?? 'sistema',
    ]);
}

/** Rango de fechas del filtro (desde / hasta) o periodo por defecto. */
function rangoFechas(): array
{
    $desde = $_GET['desde'] ?? '';
    $hasta = $_GET['hasta'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $desde)) $desde = date('Y-01-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $hasta)) $hasta = date('Y-m-d');
    if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
    return [$desde, $hasta];
}

function badgeEstado(string $estado): string
{
    $map = [
        'registrado'  => 'success',
        'borrador'    => 'secondary',
        'anulado'     => 'warning',
        'activo'      => 'success',
        'inactivo'    => 'secondary',
    ];
    return '<span class="badge bg-' . ($map[$estado] ?? 'secondary') . '">' . ucfirst(limpiar($estado)) . '</span>';
}

/**
 * Importa un archivo CSV (sep. punto y coma o coma) hacia documentos o asientos.
 * Devuelve la cantidad de registros insertados.
 */
function importarCsv(PDO $db, string $tipo, string $rutaArchivo): int
{
    if (!is_file($rutaArchivo)) {
        throw new RuntimeException('No se pudo leer el archivo.');
    }
    $contenido = file_get_contents($rutaArchivo);
    $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);

    // Detectar separador: ; o ,
    $sep = substr_count($contenido, ';') > substr_count($contenido, ',') ? ';' : ',';

    $filas = str_getcsv($contenido, "\n");
    // Normalizar los saltos si vienen con \r
    $filas = array_map(fn($f) => trim($f), $filas);
    $filas = array_values(array_filter($filas));

    if (count($filas) < 2) {
        throw new RuntimeException('El archivo debe tener encabezados + al menos una fila.');
    }

    $encabezados = str_getcsv(array_shift($filas), $sep);
    $encabezados = array_map(fn($h) => strtolower(trim($h)), $encabezados);

    if ($tipo === 'documentos') {
        $requeridos = ['tipo', 'numero', 'fecha', 'emisor', 'ruc_dni', 'concepto', 'importe'];
        foreach ($requeridos as $r) {
            if (!in_array($r, $encabezados, true)) {
                throw new RuntimeException("Columna '$r' requerida (encabezado: " . implode(', ', $encabezados) . ').');
            }
        }
        $stmt = $db->prepare(
            'INSERT INTO documentos (tipo,numero,fecha,emisor,ruc_dni,concepto,importe,igv,total,estado)
             VALUES (:t,:n,:f,:e,:r,:c,:i,:iv,:tt,:es)'
        );
        $n = 0;
        foreach ($filas as $linea) {
            if (trim($linea) === '') continue;
            $cols = array_values(array_map('trim', str_getcsv($linea, $sep)));
            $data = array_combine($encabezados, $cols);
            if (empty($data['numero'])) continue;
            $base = (float) ($data['importe'] ?? 0);
            $igv  = calcularIgv($base);
            $stmt->execute([
                ':t' => strtoupper($data['tipo'] ?? 'BOLETA'), ':n' => $data['numero'],
                ':f' => $data['fecha'], ':e' => $data['emisor'], ':r' => $data['ruc_dni'],
                ':c' => $data['concepto'], ':i' => $base, ':iv' => $igv, ':tt' => round($base+$igv,2),
                ':es' => 'registrado'
            ]);
            $n++;
        }
        return $n;
    }

    if ($tipo === 'asientos') {
        // Formato: fecha, glosa, cuenta(or codigo), debe, haber
        $requeridos = ['fecha', 'glosa', 'cuenta', 'debe', 'haber'];
        foreach ($requeridos as $r) {
            if (!in_array($r, $encabezados, true)) {
                throw new RuntimeException("Columna '$r' requerida (encabezado: " . implode(', ', $encabezados) . ').');
            }
        }
        $db->beginTransaction();
        try {
            $stmtAsi = $db->prepare('INSERT INTO asientos (numero,fecha,glosa,estado) VALUES (:n,:f,:g,"registrado")');
            $stmtDet = $db->prepare('INSERT INTO asiento_detalle (asiento_id,cuenta_id,debe,haber) VALUES (:a,:c,:d,:h)');
            $stmtCue = $db->prepare('SELECT id FROM cuentas WHERE codigo=? OR nombre=? LIMIT 1');
            $n = 0; $numSeleccionado = siguienteNumeroAsiento($db);
            foreach ($filas as $linea) {
                if (trim($linea) === '') continue;
                $cols = array_map('trim', str_getcsv($linea, $sep));
                $data = array_combine($encabezados, $cols);
                if (empty($data['glosa'])) continue;
                $stmtAsi->execute([':n'=>$numSeleccionado, ':f'=>$data['fecha'], ':g'=>$data['glosa']]);
                $asientoId = (int) $db->lastInsertId();
                $cuentaRef = $data['cuenta'];
                $stmtCue->execute([$cuentaRef, $cuentaRef]);
                $cuentaId = (int) $stmtCue->fetchColumn();
                if ($cuentaId <= 0) {
                    throw new RuntimeException("Cuenta no encontrada en fila: $cuentaRef");
                }
                $stmtDet->execute([
                    ':a' => $asientoId, ':c' => $cuentaId,
                    ':d' => (float)($data['debe'] ?? 0), ':h' => (float)($data['haber'] ?? 0)
                ]);
                $numSeleccionado++;
                $n++;
            }
            $db->commit();
            return $n;
        } catch (Throwable $e) {
            $db->rollBack();
            throw new RuntimeException('Error importando asiento: ' . $e->getMessage());
        }
    }

    throw new RuntimeException('Tipo de importación no soportado.');
}

/** Mueve el archivo subido a la carpeta archivos/ con nombre unico. */
function guardarArchivoAdjunto(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }
    $maxBytes = 10 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('El archivo supera los 10 MB.');
    }
    $original = basename((string) $file['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $perm = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
    if (!in_array($ext, $perm, true)) {
        throw new RuntimeException('Tipo de archivo no permitido (' . $ext . ').');
    }
    $nombre = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destino = __DIR__ . '/../archivos/' . $nombre;
    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        throw new RuntimeException('No se pudo guardar el archivo.');
    }
    return $nombre;
}

function borrarArchivoAdjunto(string $nombre): void
{
    $ruta = __DIR__ . '/../archivos/' . basename($nombre);
    if (is_file($ruta)) {
        @unlink($ruta);
    }
}