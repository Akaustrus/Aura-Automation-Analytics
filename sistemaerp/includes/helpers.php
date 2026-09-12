<?php
/**
 * Funciones auxiliares del Sistema ERP básico para micro empresas
 * (independiente del ERP principal de incidencias).
 */

/* ------------------------------------------------------------------ */
/*  Utilidades                                                         */
/* ------------------------------------------------------------------ */

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

function formatoCantidad(float $cantidad): string
{
    return rtrim(rtrim(number_format($cantidad, 3, '.', ','), '0'), '.');
}

function formatoFecha(?string $fecha): string
{
    if (empty($fecha)) return '';
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt ? $dt->format('d/m/Y') : $fecha;
}

function setMensaje(string $tipo, string $texto): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['erp_msg'] = ['tipo' => $tipo, 'texto' => $texto];
}

function getMensaje(): array
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $m = $_SESSION['erp_msg'] ?? null;
    unset($_SESSION['erp_msg']);
    return $m ?? [];
}

function tokenCsrf(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['erp_csrf'])) $_SESSION['erp_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['erp_csrf'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . limpiar(tokenCsrf()) . '">';
}

function verificarCsrf(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $dado = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['erp_csrf'] ?? '', (string) $dado)) {
        die('Solicitud no válida (CSRF).');
    }
}

function badgeEstado(string $estado): string
{
    $map = [
        'emitido'   => 'success',
        'anulado'   => 'warning',
        'activo'    => 'success',
        'inactivo'  => 'secondary',
        'entrada'   => 'info',
        'salida'    => 'danger',
        'ajuste'    => 'secondary',
        'ingreso'   => 'success',
        'egreso'    => 'danger',
        'contado'   => 'primary',
        'credito'   => 'warning',
        'ok'        => 'success',
        'bajo'      => 'warning',
        'agotado'   => 'danger',
        'servicio'  => 'info',
    ];
    return '<span class="badge bg-' . ($map[$estado] ?? 'secondary') . '">' . ucfirst(limpiar($estado)) . '</span>';
}

function registrarAuditoria(
    PDO $db, string $tabla, int $registroId, string $accion,
    string $resumen = '', array $antes = [], array $despues = []
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
        ':usuario' => $_SESSION['erp_usuario'] ?? 'sistema',
    ]);
}

/* ------------------------------------------------------------------ */
/*  Parámetros de la empresa                                           */
/* ------------------------------------------------------------------ */

function parametro(PDO $db, string $clave, string $def = ''): string
{
    static $cache = [];
    if (!isset($cache[$clave])) {
        $stmt = $db->prepare('SELECT valor FROM parametros WHERE clave = ?');
        $stmt->execute([$clave]);
        $v = $stmt->fetchColumn();
        $cache[$clave] = $v === false ? $def : (string) $v;
    }
    return $cache[$clave];
}

function nombreEmpresa(PDO $db): string
{
    return parametro($db, 'EMPRESA_NOMBRE', 'Mi Micro Empresa S.A.C.');
}

function simboloMoneda(PDO $db): string
{
    return parametro($db, 'MONEDA', 'SOLES') === 'DOLARES' ? '$' : 'S/';
}

/* ------------------------------------------------------------------ */
/*  Numeración correlativa de documentos                               */
/* ------------------------------------------------------------------ */

function siguienteNumero(PDO $db, string $tabla): int
{
    $stmt = $db->prepare("SELECT COALESCE(MAX(numero),0)+1 FROM $tabla");
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function numeroVenta(int $n): string
{
    return sprintf('V-%04d', $n);
}

function numeroCompra(int $n): string
{
    return sprintf('C-%04d', $n);
}

/* ------------------------------------------------------------------ */
/*  Inventario                                                         */
/* ------------------------------------------------------------------ */

/** Estado de stock de un producto: ok | bajo | agotado | servicio */
function estadoStock(array $p): string
{
    if (!$p['controla_stock']) return 'servicio';
    if ((float) $p['stock'] <= 0.0005) return 'agotado';
    if ((float) $p['stock'] < (float) $p['stock_minimo']) return 'bajo';
    return 'ok';
}

function mediosPago(): array
{
    return ['efectivo', 'transferencia', 'tarjeta', 'cheque'];
}

function nombreMes(int $mes): string
{
    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
              'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return $meses[$mes - 1] ?? (string) $mes;
}

/* ------------------------------------------------------------------ */
/*  Rango de fechas del filtro                                         */
/* ------------------------------------------------------------------ */

function rangoFechas(): array
{
    $desde = $_GET['desde'] ?? '';
    $hasta = $_GET['hasta'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $desde)) $desde = date('Y-01-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $hasta)) $hasta = date('Y-m-d');
    if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
    return [$desde, $hasta];
}