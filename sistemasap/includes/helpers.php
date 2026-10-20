<?php
/**
 * Funciones auxiliares del Sistema SAP (Aura Automation & Analytics).
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
    $_SESSION['sap_msg'] = ['tipo' => $tipo, 'texto' => $texto];
}

function getMensaje(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $m = $_SESSION['sap_msg'] ?? null;
    unset($_SESSION['sap_msg']);
    return $m ?? [];
}

function tokenCsrf(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['sap_csrf'])) {
        $_SESSION['sap_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['sap_csrf'];
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
    if (!hash_equals($_SESSION['sap_csrf'] ?? '', (string) $dado)) {
        die('Solicitud no válida (CSRF).');
    }
}

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
        ':usuario' => $_SESSION['sap_usuario'] ?? 'sistema',
    ]);
}

function badgeEstado(string $estado): string
{
    $map = [
        'activo'      => 'success',
        'inactivo'    => 'secondary',
        'abierta'     => 'success',
        'cerrada'     => 'danger',
        'en_proceso'  => 'info',
        'cubierta'    => 'success',
        'pendiente'   => 'warning',
        'aprobada'    => 'info',
        'recibida'    => 'success',
        'cancelada'   => 'danger',
        'generado'    => 'warning',
        'pagado'      => 'success',
        'anulado'     => 'danger',
        'emitido'     => 'info',
        'abierto'     => 'success',
        'borrador'    => 'secondary',
        'vacaciones'  => 'warning',
        'indefinido'  => 'primary',
        'temporal'    => 'warning',
        'practicas'   => 'info',
        'terceros'    => 'secondary',
        'normal'      => 'success',
        'permiso'     => 'info',
        'feriado'     => 'primary',
        'falta'       => 'danger',
    ];
    return '<span class="badge bg-' . ($map[$estado] ?? 'secondary') . '">' . ucfirst(limpiar($estado)) . '</span>';
}

function parametro(PDO $db, string $clave, string $default = ''): string
{
    $stmt = $db->prepare('SELECT valor FROM parametros WHERE clave = ?');
    $stmt->execute([$clave]);
    $v = $stmt->fetchColumn();
    return $v !== false ? (string) $v : $default;
}

/** Tasa IGV desde parametros. */
function tasaIGV(PDO $db): float
{
    return (float) parametro($db, 'igv', '0.18');
}

/** Promedio movil simple para predicciones. */
function promedioMovil(array $valores, int $ventana): float
{
    $n = count($valores);
    if ($n === 0) return 0;
    if ($ventana <= 0) $ventana = 1;
    $inicio = max(0, $n - $ventana);
    $subset = array_slice(array_values($valores), $inicio);
    return array_sum($subset) / count($subset);
}

/** Regresion lineal simple: y = a + b*x  (devuelve [a, b, r2]) */
function regresionLineal(array $x, array $y): array
{
    $n = count($x);
    if ($n !== count($y) || $n < 2) {
        return [0, 0, 0];
    }
    $mx = array_sum($x) / $n;
    $my = array_sum($y) / $n;
    $sxy = 0; $sxx = 0; $syy = 0;
    for ($i = 0; $i < $n; $i++) {
        $sxy += ($x[$i] - $mx) * ($y[$i] - $my);
        $sxx += ($x[$i] - $mx) ** 2;
        $syy += ($y[$i] - $my) ** 2;
    }
    if ($sxx === 0) return [0, 0, 0];
    $b = $sxy / $sxx;
    $a = $my - $b * $mx;
    $r2 = $syy === 0 ? 0 : ($sxy * $sxy) / ($sxx * $syy);
    return [$a, $b, $r2];
}

/** Devuelve la proyeccion de los proximos N dias/periodos desde una serie. */
function proyectarSerie(array $valores, int $pasos): array
{
    $n = count($valores);
    if ($n === 0) return array_fill(0, $pasos, 0);
    if ($n < 3) {
        $promedio = array_sum($valores) / $n;
        return array_fill(0, $pasos, round($promedio, 2));
    }
    $x = range(0, $n - 1);
    [$a, $b] = regresionLineal($x, array_values($valores));
    $out = [];
    for ($i = 0; $i < $pasos; $i++) {
        $v = $a + $b * ($n + $i);
        $out[] = max(0, round($v, 2));
    }
    return $out;
}

/** Dias restantes de stock estimado para un producto. */
function diasRestantesStock(PDO $db, int $productoId): int
{
    $stmt = $db->prepare(
        "SELECT p.stock_actual AS stock,
                p.punto_pedido AS pp,
                COALESCE(SUM(CASE WHEN m.tipo='salida' THEN m.cantidad ELSE 0 END), 0) AS total_salidas,
                COUNT(DISTINCT CASE WHEN m.tipo='salida' THEN m.fecha END) AS dias_con_salida
         FROM productos p
         LEFT JOIN movimientos_inventario m ON m.producto_id = p.id
         WHERE p.id = ? AND m.fecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
         GROUP BY p.id"
    );
    $stmt->execute([$productoId]);
    $r = $stmt->fetch();
    if (!$r) return 999;
    $stock = (int) $r['stock'];
    $salidas = (int) $r['total_salidas'];
    $diasConSalida = (int) $r['dias_con_salida'];
    if ($salidas <= 0) return 999;
    $promedioDiario = $salidas / ($diasConSalida > 0 ? $diasConSalida : 1);
    if ($promedioDiario <= 0) return 999;
    return (int) floor($stock / $promedioDiario);
}

/** Genera recomendaciones automaticas basadas en los datos actuales. */
function generarRecomendaciones(PDO $db): array
{
    $recomendaciones = [];

    if (method_exists($db, 'prepare') && $db) {
        // 1. Stock bajo / punto de pedido
        $stmt = $db->query(
            "SELECT p.id, p.nombre, p.stock_actual, p.punto_pedido, p.stock_minimo, prov.nombre AS proveedor,
                    (p.punto_pedido - p.stock_actual) AS faltante
             FROM productos p JOIN proveedores prov ON prov.id = p.proveedor_id
             WHERE p.stock_actual <= p.punto_pedido AND p.activo = 1
             ORDER BY faltante DESC"
        );
        foreach ($stmt->fetchAll() as $p) {
            $recomendaciones[] = [
                'area' => 'inventario',
                'titulo' => 'Reabastecer: ' . $p['nombre'],
                'descripcion' => 'Stock actual ' . $p['stock_actual'] . ' unidades, punto de pedido ' . $p['punto_pedido'] . '. Proveedor: ' . $p['proveedor'] . '.',
                'urgencia' => $p['stock_actual'] <= $p['stock_minimo'] ? 'critica' : 'alta',
                'accion_sugerida' => 'Crear orden de compra por ' . max(1, (int) $p['faltante']) . ' unidades.'
            ];
        }

        // 2. Personal por debajo de requerido
        $stmt = $db->query(
            "SELECT c.nombre AS cargo, c.requeridos, COUNT(e.id) AS actuales, c.salario_base
             FROM cargos c
             LEFT JOIN empleados e ON e.cargo_id = c.id AND e.estado = 'activo'
             GROUP BY c.id, c.nombre, c.requeridos, c.salario_base
             HAVING COUNT(e.id) < c.requeridos"
        );
        foreach ($stmt->fetchAll() as $c) {
            $faltan = $c['requeridos'] - $c['actuales'];
            $recomendaciones[] = [
                'area' => 'rrhh',
                'titulo' => 'Faltan ' . $faltan . ' ' . $c['cargo'] . '(s)',
                'descripcion' => 'Se requieren ' . $c['requeridos'] . ' pero hay ' . $c['actuales'] . ' activos. Salario base S/ ' . number_format((float) $c['salario_base'], 2) . '.',
                'urgencia' => $faltan >= 3 ? 'alta' : 'media',
                'accion_sugerida' => 'Abrir ' . $faltan . ' vacante(s) para ' . $c['cargo'] . '.'
            ];
        }

        // 3. Flujo de caja proyectado
        $vMesAnterior = (float) $db->query("SELECT COALESCE(SUM(ventas),0) FROM historico_diario WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
        $gMesAnterior = (float) $db->query("SELECT COALESCE(SUM(gastos),0) FROM historico_diario WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
        if ($gMesAnterior > $vMesAnterior && $vMesAnterior > 0) {
            $recomendaciones[] = [
                'area' => 'finanzas',
                'titulo' => 'Gastos superan a ingresos',
                'descripcion' => 'En los últimos 30 días se gastó S/ ' . number_format($gMesAnterior, 2) . ' frente a ventas de S/ ' . number_format($vMesAnterior, 2) . '.',
                'urgencia' => 'alta',
                'accion_sugerida' => 'Revisar presupuesto mensual y reducir gastos operativos.'
            ];
        }

        // 4. Ventas al alza (tendencia positiva fuerte)
        $ventasMensuales = [];
        $stmt = $db->query("SELECT DATE_FORMAT(fecha, '%Y-%m') mes, SUM(ventas) total FROM historico_diario GROUP BY mes ORDER BY mes DESC LIMIT 6");
        foreach (array_reverse($stmt->fetchAll()) as $m) {
            $ventasMensuales[] = (float) $m['total'];
        }
        if (count($ventasMensuales) >= 3) {
            $x = range(0, count($ventasMensuales) - 1);
            [$a, $b, $r2] = regresionLineal($x, $ventasMensuales);
            if ($b > 0 && $r2 > 0.5) {
                $recomendaciones[] = [
                    'area' => 'ventas',
                    'titulo' => 'Tendencia de ventas al alza',
                    'descripcion' => 'Las ventas crecen ~S/ ' . number_format($b, 2) . ' por mes (R²=' . number_format($r2, 2) . '). Prepárate para más demanda.',
                    'urgencia' => 'media',
                    'accion_sugerida' => 'Aumentar stock de productos top y considerar más personal de ventas en los próximos 60 días.'
                ];
            } elseif ($b < 0 && $r2 > 0.5) {
                $recomendaciones[] = [
                    'area' => 'ventas',
                    'titulo' => 'Tendencia de ventas a la baja',
                    'descripcion' => 'Las ventas caen ~S/ ' . number_format(abs($b), 2) . ' por mes. Atención a la estacionalidad.',
                    'urgencia' => 'media',
                    'accion_sugerida' => 'Reducir compras de stock y planificar campañas de promoción.'
                ];
            }
        }
    }

    return $recomendaciones;
}

/** Registra recomendaciones pendientes en la base de datos (una vez por dia). */
function sincronizarRecomendaciones(PDO $db): int
{
    $hoy = date('Y-m-d');
    $n = (int) $db->query("SELECT COUNT(*) FROM recomendaciones WHERE DATE(fecha_generacion) = '$hoy'")->fetchColumn();
    if ($n > 0) return 0;

    $insertadas = 0;
    $stmt = $db->prepare(
        'INSERT INTO recomendaciones (area, titulo, descripcion, urgencia, accion_sugerida, estado)
         VALUES (:area, :titulo, :descripcion, :urgencia, :accion_sugerida, "pendiente")'
    );
    foreach (generarRecomendaciones($db) as $rec) {
        $stmt->execute($rec);
        $insertadas++;
    }
    return $insertadas;
}

/** Tasa de uso diario (unidades/dia) en los ultimos 90 dias. */
function usoDiarioPromedio(PDO $db, int $productoId): float
{
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(cantidad), 0) / NULLIF(COUNT(DISTINCT fecha), 0) AS tasa
         FROM movimientos_inventario
         WHERE producto_id = ? AND tipo = 'salida' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)"
    );
    $stmt->execute([$productoId]);
    $tasa = (float) $stmt->fetchColumn();
    return $tasa > 0 ? $tasa : 0;
}