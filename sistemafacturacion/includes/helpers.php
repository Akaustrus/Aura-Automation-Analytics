<?php
/**
 * Funciones auxiliares del Sistema de Facturación
 * (independiente del ERP).
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

function formatoFecha(?string $fecha): string
{
    if (empty($fecha)) return '';
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt ? $dt->format('d/m/Y') : $fecha;
}

function setMensaje(string $tipo, string $texto): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['facturacion_msg'] = ['tipo' => $tipo, 'texto' => $texto];
}

function getMensaje(): array
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $m = $_SESSION['facturacion_msg'] ?? null;
    unset($_SESSION['facturacion_msg']);
    return $m ?? [];
}

function tokenCsrf(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['facturacion_csrf'])) $_SESSION['facturacion_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['facturacion_csrf'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . limpiar(tokenCsrf()) . '">';
}

function verificarCsrf(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $dado = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['facturacion_csrf'] ?? '', (string) $dado)) {
        die('Solicitud no válida (CSRF).');
    }
}

function badgeEstado(string $estado): string
{
    $map = [
        'emitido'  => 'success',
        'anulado'  => 'warning',
        'activo'   => 'success',
        'inactivo' => 'secondary',
        'producto' => 'primary',
        'servicio' => 'info',
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
        ':usuario' => $_SESSION['facturacion_usuario'] ?? 'sistema',
    ]);
}

/* ------------------------------------------------------------------ */
/*  Parámetros y datos de la empresa emisora                           */
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

function parametroNum(PDO $db, string $clave, float $def = 0.0): float
{
    return (float) str_replace(',', '.', parametro($db, $clave, (string) $def));
}

/** Porcentaje de IGV configurado (0..100). */
function porcentajeIgv(PDO $db): float
{
    return parametroNum($db, 'IGV_PORC', 18.0);
}

function tasaIgv(PDO $db): float
{
    return porcentajeIgv($db) / 100;
}

/** Datos de la empresa emisora para el comprobante. */
function datosEmpresa(PDO $db): array
{
    return [
        'razon_social' => parametro($db, 'RAZON_SOCIAL', 'Aura Automation & Analytics S.A.C.'),
        'ruc'          => parametro($db, 'RUC', '20612345678'),
        'direccion'    => parametro($db, 'DIRECCION', ''),
        'telefono'     => parametro($db, 'TELEFONO', ''),
        'correo'       => parametro($db, 'CORREO', ''),
    ];
}

/* ------------------------------------------------------------------ */
/*  Comprobantes: tipos, series y numeración correlativa               */
/* ------------------------------------------------------------------ */

function tiposComprobante(): array
{
    return ['FACTURA', 'BOLETA', 'NOTA_CREDITO', 'NOTA_DEBITO'];
}

function seriePorTipo(string $tipo): string
{
    return match ($tipo) {
        'FACTURA'       => 'F001',
        'BOLETA'        => 'B001',
        'NOTA_CREDITO'  => 'NC01',
        'NOTA_DEBITO'   => 'ND01',
        default         => 'C001',
    };
}

function serieActual(PDO $db, string $tipo): string
{
    $v = parametro($db, 'SERIE_' . $tipo);
    return $v !== '' ? $v : seriePorTipo($tipo);
}

/** Siguiente número correlativo para una serie de un tipo. */
function siguienteNumero(PDO $db, string $tipo, string $serie): int
{
    $stmt = $db->prepare('SELECT COALESCE(MAX(numero),0)+1 FROM comprobantes WHERE tipo=? AND serie=?');
    $stmt->execute([$tipo, $serie]);
    return (int) $stmt->fetchColumn();
}

function numeroComprobante(string $serie, int $numero): string
{
    return sprintf('%s-%06d', $serie, $numero);
}

function condicionesPago(): array
{
    return ['Contado', 'Crédito 30 días', 'Crédito 60 días', 'Crédito 90 días'];
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

/* ------------------------------------------------------------------ */
/*  Importe en letras (español)                                        */
/* ------------------------------------------------------------------ */

function numeroEnLetras(float $numero): string
{
    $entero  = (int) floor(abs($numero));
    $decimal = (int) round((abs($numero) - $entero) * 100);
    $texto   = convertirNumero($entero);
    if ($decimal > 0) {
        $texto .= ' CON ' . str_pad((string) $decimal, 2, '0', STR_PAD_LEFT) . '/100';
    }
    return $texto;
}

function convertirNumero(int $n): string
{
    if ($n === 0) return 'CERO';

    $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
                 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE',
                 'DIECIOCHO', 'DIECINUEVE', 'VEINTE'];
    $decenas  = ['', '', 'VEINTI', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
                 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    $hastaMil = function (int $v) use ($unidades, $decenas, $centenas): string {
        if ($v === 0) return '';
        $txt = '';
        $c = intdiv($v, 100);
        $r = $v % 100;
        if ($c > 0) $txt .= ($c === 1 && $r === 0 ? 'CIEN' : $centenas[$c]) . ' ';
        if ($r > 0) {
            if ($r <= 20)          $txt .= $unidades[$r] . ' ';
            elseif ($r < 30)       $txt .= 'VEINTI' . $unidades[$r % 10] . ' ';
            elseif ($r % 10 === 0) $txt .= $decenas[intdiv($r, 10)] . ' ';
            else                   $txt .= $decenas[intdiv($r, 10)] . ' Y ' . $unidades[$r % 10] . ' ';
        }
        return $txt;
    };

    $texto = '';
    if ($n >= 1000000) {
        $m = intdiv($n, 1000000);
        $texto .= ($m === 1 ? 'UN MILLÓN' : convertirNumero($m) . ' MILLONES') . ' ';
        $n %= 1000000;
    }
    if ($n >= 1000) {
        $m = intdiv($n, 1000);
        $texto .= ($m === 1 ? 'MIL' : $hastaMil($m) . 'MIL') . ' ';
        $n %= 1000;
    }
    if ($n > 0) $texto .= $hastaMil($n);

    return trim($texto);
}

function letrasTotales(PDO $db, float $total): string
{
    return 'SON ' . numeroEnLetras($total) . ' SOLES';
}