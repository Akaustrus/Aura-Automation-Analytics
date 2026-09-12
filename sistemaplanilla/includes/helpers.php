<?php
/**
 * Funciones auxiliares y motor de cálculo del Sistema de Planilla
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
    $_SESSION['planilla_msg'] = ['tipo' => $tipo, 'texto' => $texto];
}

function getMensaje(): array
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $m = $_SESSION['planilla_msg'] ?? null;
    unset($_SESSION['planilla_msg']);
    return $m ?? [];
}

function tokenCsrf(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['planilla_csrf'])) $_SESSION['planilla_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['planilla_csrf'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . limpiar(tokenCsrf()) . '">';
}

function verificarCsrf(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $dado = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['planilla_csrf'] ?? '', (string) $dado)) {
        die('Solicitud no válida (CSRF).');
    }
}

function badgeEstado(string $estado): string
{
    $map = [
        'emitida'   => 'success',
        'borrador'  => 'secondary',
        'anulada'   => 'warning',
        'pendiente' => 'warning',
        'cobrado'   => 'success',
        'activo'    => 'success',
        'inactivo'  => 'secondary',
        'asistio'   => 'success',
        'falta'     => 'danger',
        'permiso'   => 'info',
        'vacaciones'=> 'primary',
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
        ':usuario' => $_SESSION['planilla_usuario'] ?? 'sistema',
    ]);
}

/* ------------------------------------------------------------------ */
/*  Períodos y nombres                                                 */
/* ------------------------------------------------------------------ */

function periodoActual(): string
{
    return date('Y-m');
}

function validarPeriodo(string $p): ?string
{
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p) ? $p : null;
}

function nombrePeriodo(string $periodo): string
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $periodo, $m)) return $periodo;
    $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
              '07'=>'Julio','08'=>'Agosto','09'=>'Setiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
    return $meses[$m[2]] . ' ' . $m[1];
}

function nombreTipoPlanilla(string $tipo): string
{
    return match ($tipo) {
        'mensual'       => 'Planilla mensual',
        'gratificacion' => 'Gratificación',
        'cts'           => 'CTS',
        default         => ucfirst($tipo),
    };
}

/** Días del período mensual (28-31). */
function diasDelPeriodo(string $periodo): int
{
    $dt = DateTime::createFromFormat('Y-m', $periodo);
    return $dt ? (int) $dt->format('t') : 30;
}

function areasDeTrabajo(): array
{
    return ['Administración', 'TI', 'Finanzas', 'Ventas', 'Soporte', 'RRHH', 'Operaciones'];
}

function tiposContrato(): array
{
    return ['indefinido' => 'Contrato indefinido', 'plazo_fijo' => 'Plazo fijo', 'parcial' => 'Tiempo parcial', 'practicante' => 'Practicante'];
}

function nombreRegimen(string $regimen): string
{
    return match ($regimen) {
        'onp' => 'ONP (13%)',
        'afp' => 'AFP (12.5%)',
        default => 'Sin descuento',
    };
}

function generarTokenPortal(): string
{
    return strtolower(bin2hex(random_bytes(10)));
}

function numeroTasa(float $valor): string
{
    return $valor == floor($valor) ? (string) (int) $valor : number_format($valor, 2);
}

/* ------------------------------------------------------------------ */
/*  Parámetros del sistema                                             */
/* ------------------------------------------------------------------ */

function parametro(PDO $db, string $clave, float $def = 0.0): float
{
    static $cache = [];
    if (!isset($cache[$clave])) {
        $stmt = $db->prepare('SELECT valor FROM parametros WHERE clave = ?');
        $stmt->execute([$clave]);
        $v = $stmt->fetchColumn();
        $cache[$clave] = $v === false ? $def : (float) $v;
    }
    return $cache[$clave];
}

/** Tasa de descuento al trabajador según régimen (0..1). */
function tasaDescuento(PDO $db, string $regimen): float
{
    return match ($regimen) {
        'afp' => parametro($db, 'TASA_AFP') / 100,
        'onp' => parametro($db, 'TASA_ONP') / 100,
        default => 0.00,
    };
}

/* ------------------------------------------------------------------ */
/*  Asistencia                                                         */
/* ------------------------------------------------------------------ */

/** Resumen de asistencia mensual del empleado: [asistio,faltas,permiso,vacaciones,horas_extra]. */
function resumenAsistencia(PDO $db, int $empleadoId, string $periodo): array
{
    $consulta = $db->prepare("SELECT
        SUM(estado='asistio') a,
        SUM(estado='falta') f,
        SUM(estado='permiso') p,
        SUM(estado='vacaciones') v,
        COALESCE(SUM(horas_extra),0) hx
        FROM asistencia WHERE empleado_id = :e AND DATE_FORMAT(fecha,'%Y-%m') = :per");
    $consulta->execute([':e'=>$empleadoId, ':per'=>$periodo]);
    $r = $consulta->fetch();
    return [
        'asistio'    => (int) ($r['a'] ?? 0),
        'falta'      => (int) ($r['f'] ?? 0),
        'permiso'    => (int) ($r['p'] ?? 0),
        'vacaciones' => (int) ($r['v'] ?? 0),
        'horas_extra'=> round((float) ($r['hx'] ?? 0), 2),
    ];
}

/* ------------------------------------------------------------------ */
/*  Conceptos configurables                                            */
/* ------------------------------------------------------------------ */

/** Suma monetaria de los conceptos activos según filtros. */
function aplicarConceptos(PDO $db, string $tipo, string $ambito, string $tipoPlanilla, float $base): array
{
    $aplica = in_array($tipoPlanilla, ['gratificacion', 'cts'], true) ? ['mensual', $tipoPlanilla, 'todos'] : ['mensual', 'todos'];
    $in = implode(',', array_fill(0, count($aplica), '?'));
    $stmt = $db->prepare("SELECT nombre, naturaleza, monto, porcentaje FROM conceptos
                          WHERE tipo = ? AND ambito = ? AND activo = 1 AND aplica IN ($in) ORDER BY id");
    $stmt->execute(array_merge([$tipo, $ambito], $aplica));
    $total = 0.0;
    $detalle = [];
    foreach ($stmt as $c) {
        $v = $c['naturaleza'] === 'porcentaje' ? round($base * $c['porcentaje'], 2) : (float) $c['monto'];
        if ($v <= 0) continue;
        $total += $v;
        $detalle[] = $c['nombre'] . ': S/ ' . number_format($v, 2);
    }
    return [$total, $detalle];
}

/* ------------------------------------------------------------------ */
/*  Renta de 5ta categoría (progresiva, anual)                         */
/* ------------------------------------------------------------------ */

function calcularRentaQuintaAnual(float $brutoAnual, float $uit): float
{
    $base = max(0.0, $brutoAnual - (7 * $uit));   // deducción general = 7 UIT
    $tramos = [
        [5,  0.08],
        [15, 0.14],
        [15, 0.17],
        [10, 0.20],
        [PHP_FLOAT_MAX, 0.30],
    ];
    $imp = 0.0;
    $resto = $base;
    foreach ($tramos as [$fact, $tasa]) {
        $tramo = $fact * $uit;
        $afecto = min($resto, $tramo);
        if ($afecto <= 0) break;
        $imp += $afecto * $tasa;
        $resto -= $afecto;
    }
    return round($imp, 2);
}

/* ------------------------------------------------------------------ */
/*  Motor de cálculo por tipo de planilla                              */
/* ------------------------------------------------------------------ */

/**
 * Calcula la boleta mensual de un empleado para un período.
 * Devuelve el array del desglose completo.
 */
function calcularBoletaMensual(PDO $db, array $e, string $periodo): array
{
    $diasMes   = diasDelPeriodo($periodo);
    $rmv       = parametro($db, 'RMV');
    $jornada   = max(1.0, parametro($db, 'JORNADA_HORAS'));
    $asis      = resumenAsistencia($db, (int) $e['id'], $periodo);

    $faltas   = $asis['falta'];
    $diasLab  = max(0, $diasMes - $faltas);
    $base     = round((float) $e['sueldo_base'] / $diasMes * $diasLab, 2);
    $asf      = $e['asignacion_familiar'] ? round($rmv * 0.10 / $diasMes * $diasLab, 2) : 0.00;

    // Horas extras: valor hora + 25% (primeras 2h/día) / +35% (siguientes)
    $valorHora  = (float) $e['sueldo_base'] / ($diasMes * $jornada);
    $heSobret  = $asis['horas_extra'];
    // Aproximación estándar: aplicar 25% de recargo sobre las horas registradas.
    $heMonto   = round($heSobret * $valorHora * 1.25, 2);

    // Conceptos adicionales configurables (ingresos del trabajador)
    [$bonos, $bonosDet] = aplicarConceptos($db, 'ingreso', 'trabajador', 'mensual', $base);

    $sueldoBruto = round($base + $asf + $heMonto + $bonos, 2);

    // Base contributiva / rentable: excluye asignación familiar
    $rentable = round($base + $heMonto + $bonos, 2);

    // Descuentos de ley
    $tasa     = tasaDescuento($db, $e['regimen']);
    $desc     = round($rentable * $tasa, 2);
    $uit      = parametro($db, 'UIT');
    $renta    = round(calcularRentaQuintaAnual($rentable * 12, $uit) / 12, 2);

    // Otros descuentos configurables del trabajador
    [$otrosDesc, $otrosDet] = aplicarConceptos($db, 'descuento', 'trabajador', 'mensual', $rentable);

    // Adelantos pendientes
    $stmtAd = $db->prepare("SELECT COALESCE(SUM(monto),0) FROM adelantos WHERE empleado_id=:e AND estado='pendiente'");
    $stmtAd->execute([':e' => (int) $e['id']]);
    $adelantos = round((float) $stmtAd->fetchColumn(), 2);

    // Aporte empleador (ESSALUD)
    $essalud  = round($rentable * parametro($db, 'TASA_ESSALUD') / 100, 2);

    $neto = round($sueldoBruto - $desc - $otrosDesc - $renta - $adelantos, 2);

    return [
        'dias_laborados' => $diasLab,
        'dias_faltas'    => $faltas,
        'horas_extra'    => $heSobret,
        'sueldo_base'    => $base,
        'asignacion_familiar' => $asf,
        'horas_extra_monto'   => $heMonto,
        'bonos'          => $bonos,
        'sueldo_bruto'   => $sueldoBruto,
        'essalud'        => $essalud,
        'descuento'      => $desc,
        'otros_descuento'=> $otrosDesc,
        'renta'          => $renta,
        'adelantos'      => $adelantos,
        'neto'           => $neto,
        'detalle'        => json_encode([
            'base'       => $base,
            'asf'        => $asf,
            'he'         => $heMonto,
            'bonos'      => $bonos,
            'bonos_det'  => $bonosDet,
            'otros_desc' => $otrosDesc,
            'otros_det'  => $otrosDet,
        ], JSON_UNESCAPED_UNICODE),
    ];
}

/** Meses calendario que el empleado trabajó dentro de la ventana $inicio..$fin (0..6). */
function mesesEnVentana(array $e, string $inicio, string $fin): int
{
    $meses = 0;
    if (($e['fecha_cese'] ?? null) && $e['fecha_cese'] < $fin) $fin = $e['fecha_cese'];
    if ($e['fecha_ingreso'] > $fin) return 0;
    $ini = max($e['fecha_ingreso'], $inicio);
    $total = floor((strtotime($fin) - strtotime($ini)) / 86400 / 30);
    $meses = (int) $total + 1;
    return max(0, min(6, $meses));
}

/** Gratificación semestral (julio/diciembre): (RM + 1 ASF) * meses/6 + bonificación exonerada. */
function calcularGratificacion(PDO $db, array $e, string $periodo): array
{
    $mes  = (int) substr($periodo, 5, 2);
    $anio = (int) substr($periodo, 0, 4);
    $inicio = $mes >= 7 ? "$anio-01" : "$anio-07";
    $fin    = $mes >= 7 ? "$anio-06" : "$anio-12";
    $meses  = mesesEnVentana($e, $inicio, $fin);

    $rmv   = parametro($db, 'RMV');
    $base  = (float) $e['sueldo_base'];
    $asf   = $e['asignacion_familiar'] ? round($rmv * 0.10, 2) : 0.00;
    $grat  = round(($base + $asf) * $meses / 6, 2);
    $bonoG = round($grat * parametro($db, 'GRATIF_BONO') / 100, 2);

    $tasa   = tasaDescuento($db, $e['regimen']);
    $desc   = round($base * $tasa, 2);              // la remuneración habitual sí aporta
    $essalud = round($base * parametro($db, 'TASA_ESSALUD') / 100, 2);

    return [
        'dias_laborados' => (int) diasDelPeriodo($periodo),
        'dias_faltas'    => 0,
        'horas_extra'    => 0.0,
        'sueldo_base'    => $grat,
        'asignacion_familiar' => $bonoG,
        'horas_extra_monto'   => 0.00,
        'bonos'          => 0.00,
        'sueldo_bruto'   => round($grat + $bonoG, 2),
        'essalud'        => $essalud,
        'descuento'      => $desc,
        'otros_descuento'=> 0.00,
        'renta'          => 0.00,
        'adelantos'      => 0.00,
        'neto'           => round($grat + $bonoG - $desc, 2),
        'detalle'        => json_encode([
            'gratif'     => $grat,
            'meses'      => $meses,
            'bono_9'     => $bonoG,
            'base'       => $base,
            'asf'        => $asf,
            'ventana'    => "$inicio/$fin",
        ], JSON_UNESCAPED_UNICODE),
    ];
}

/** CTS (depósito semestral): (RM + 1/6 gratif) * meses/12 * 50%. */
function calcularCts(PDO $db, array $e, string $periodo): array
{
    $mes  = (int) substr($periodo, 5, 2);
    $anio = (int) substr($periodo, 0, 4);
    $semestre = ($mes >= 5 && $mes <= 10);
    $inicio = $semestre ? "$anio-05" : "$anio-11";
    $fin    = $semestre ? "$anio-10" : ($anio + 1) . "-04";
    $meses  = mesesEnVentana($e, $inicio, $fin);

    $rmv  = parametro($db, 'RMV');
    $base = (float) $e['sueldo_base'];
    $asf  = $e['asignacion_familiar'] ? round($rmv * 0.10, 2) : 0.00;
    $grat = round(($base + $asf) / 6, 2);                        // 1/6 de gratificación
    $deposito = round(($base + $asf + $grat) * $meses / 12 * 0.50, 2);

    return [
        'dias_laborados' => (int) diasDelPeriodo($periodo),
        'dias_faltas'    => 0,
        'horas_extra'    => 0.0,
        'sueldo_base'    => $deposito,
        'asignacion_familiar' => 0.00,
        'horas_extra_monto'   => 0.00,
        'bonos'          => 0.00,
        'sueldo_bruto'   => $deposito,
        'essalud'        => 0.00,
        'descuento'      => 0.00,
        'otros_descuento'=> 0.00,
        'renta'          => 0.00,
        'adelantos'      => 0.00,
        'neto'           => $deposito,
        'detalle'        => json_encode([
            'cts_deposito' => $deposito,
            'meses'        => $meses,
            'base'         => $base,
            'asf'          => $asf,
            'gratif_sexta' => $grat,
            'ventana'      => "$inicio/$fin",
        ], JSON_UNESCAPED_UNICODE),
    ];
}

/** Dispatcher del motor de cálculo. */
function calcularBoleta(PDO $db, array $empleado, string $periodo, string $tipo): array
{
    return match ($tipo) {
        'gratificacion' => calcularGratificacion($db, $empleado, $periodo),
        'cts'           => calcularCts($db, $empleado, $periodo),
        default         => calcularBoletaMensual($db, $empleado, $periodo),
    };
}

/* ------------------------------------------------------------------ */
/*  Generación de planillas                                            */
/* ------------------------------------------------------------------ */

function generarPlanilla(PDO $db, string $periodo, string $tipo = 'mensual'): array
{
    $tipo = in_array($tipo, ['mensual', 'gratificacion', 'cts'], true) ? $tipo : 'mensual';

    $stmt = $db->prepare('SELECT id, estado FROM planillas WHERE periodo = :p AND tipo_planilla = :t');
    $stmt->execute([':p' => $periodo, ':t' => $tipo]);
    $existente = $stmt->fetch();

    if ($existente && $existente['estado'] === 'emitida') {
        return [false, 'Ya existe una planilla ' . nombreTipoPlanilla($tipo) . ' EMITIDA para ' . $periodo . '. Anúlala primero.'];
    }

    // Si es mensual, solo empleados activos. Para gratif/cts, los que trabajaron ese año.
    $empleados = $db->query('SELECT * FROM empleados WHERE activo = 1 AND (fecha_cese IS NULL OR fecha_cese >= NOW()) ORDER BY apellidos')->fetchAll();
    if (!$empleados) {
        return [false, 'No hay empleados activos para generar la planilla.'];
    }

    $tB = $tE = $tD = $tR = $tA = $tN = 0.0;
    $detalles = [];
    foreach ($empleados as $e) {
        $b = calcularBoleta($db, $e, $periodo, $tipo);
        $tB += $b['sueldo_bruto'];
        $tE += $b['essalud'];
        $tD += $b['descuento'] + $b['otros_descuento'];
        $tR += $b['renta'];
        $tA += $b['adelantos'];
        $tN += $b['neto'];
        $detalles[] = ['empleado' => $e, 'b' => $b];
    }

    $estadoInicial = $tipo === 'mensual' ? 'borrador' : 'borrador';

    $db->beginTransaction();
    try {
        $glosa = nombreTipoPlanilla($tipo) . ' ' . nombrePeriodo($periodo);
        if ($existente) {
            $db->prepare('DELETE FROM detalle_planilla WHERE planilla_id = ?')->execute([$existente['id']]);
            $db->prepare('UPDATE planillas SET estado=:es, fecha_generacion=CURDATE(), total_bruto=:b, total_essalud=:e, total_descuento=:d, total_renta=:r, total_adelantos=:a, total_neto=:n, glosa=:g WHERE id=:id')
               ->execute([':es'=>$estadoInicial, ':b'=>round($tB,2), ':e'=>round($tE,2), ':d'=>round($tD+$tR+$tA,2), ':r'=>round($tR,2), ':a'=>round($tA,2), ':n'=>round($tN,2), ':g'=>$glosa, ':id'=>$existente['id']]);
            $planillaId = (int) $existente['id'];
        } else {
            $db->prepare('INSERT INTO planillas (periodo, tipo_planilla, estado, fecha_generacion, total_bruto, total_essalud, total_descuento, total_renta, total_adelantos, total_neto, glosa)
                          VALUES (:p,:t,:es,CURDATE(),:b,:e,:d,:r,:a,:n,:g)')
               ->execute([':p'=>$periodo, ':t'=>$tipo, ':es'=>$estadoInicial, ':b'=>round($tB,2), ':e'=>round($tE,2), ':d'=>round($tD+$tR+$tA,2), ':r'=>round($tR,2), ':a'=>round($tA,2), ':n'=>round($tN,2), ':g'=>$glosa]);
            $planillaId = (int) $db->lastInsertId();
        }

        $stmtDet = $db->prepare('INSERT INTO detalle_planilla
            (planilla_id, empleado_id, dias_laborados, dias_faltas, horas_extra, sueldo_base, asignacion_familiar,
             horas_extra_monto, bonos, sueldo_bruto, essalud, descuento, renta, adelantos, neto, detalle)
            VALUES (:p,:e,:dl,:df,:hx,:sb,:asf,:he,:bn,:sbr,:ess,:de,:re,:ad,:ne,:det)');
        foreach ($detalles as $d) {
            $e = $d['empleado']; $b = $d['b'];
            $stmtDet->execute([
                ':p'=>$planillaId, ':e'=>(int)$e['id'], ':dl'=>$b['dias_laborados'], ':df'=>$b['dias_faltas'],
                ':hx'=>$b['horas_extra'], ':sb'=>$b['sueldo_base'], ':asf'=>$b['asignacion_familiar'],
                ':he'=>$b['horas_extra_monto'], ':bn'=>$b['bonos'], ':sbr'=>$b['sueldo_bruto'],
                ':ess'=>$b['essalud'], ':de'=>round($b['descuento']+$b['otros_descuento'],2),
                ':re'=>$b['renta'], ':ad'=>$b['adelantos'], ':ne'=>$b['neto'], ':det'=>$b['detalle'],
            ]);
        }

        // Marcar adelantos como cobrados cuando la planilla mensual queda generada
        if ($tipo === 'mensual' && $tA > 0) {
            $db->prepare("UPDATE adelantos SET estado='cobrado' WHERE estado='pendiente'")->execute();
        }

        $db->commit();
        registrarAuditoria($db, 'planillas', $planillaId, $existente ? 'editar' : 'crear', "$glosa ({$glosa})", [], ['periodo'=>$periodo,'tipo'=>$tipo,'neto'=>round($tN,2)]);
        return [true, $glosa . ' generada en BORRADOR (' . count($detalles) . ' boletas). Emítela para disponer las boletas.'];
    } catch (Throwable $e) {
        $db->rollBack();
        return [false, 'Error al generar la planilla: ' . $e->getMessage()];
    }
}