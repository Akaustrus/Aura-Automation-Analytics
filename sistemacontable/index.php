<?php
/**
 * Módulo Contable — Aura Automation & Analytics
 * Router principal independiente del ERP.
 *
 * Entrar desde: http://localhost:8001/index.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['contable_usuario'])) {
    $_SESSION['contable_usuario'] = 'usuario-local';
}

$db = ContabilidadDB::conexion();

/* ------------------------------------------------------------------ */
/*  Acciones POST / GET (terminan antes de generar HTML)                */
/* ------------------------------------------------------------------ */

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action !== '') {
    switch ($action) {

        /* -------- PLAN DE CUENTAS -------- */
        case 'guardar_cuenta':
            verificarCsrf();
            $id       = (int) ($_POST['id'] ?? 0);
            $codigo   = trim($_POST['codigo'] ?? '');
            $nombre   = trim($_POST['nombre'] ?? '');
            $tipo     = $_POST['tipo'] ?? '';
            $desc     = trim($_POST['descripcion'] ?? '');
            $activo   = isset($_POST['activo']) ? 1 : 0;

            if ($codigo === '' || $nombre === '' || !in_array($tipo, tiposCuenta(), true)) {
                setMensaje('danger', 'Faltan datos obligatorios.');
                break;
            }

            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM cuentas WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('UPDATE cuentas SET codigo=:codigo,nombre=:nombre,tipo=:tipo,descripcion=:desc,activo=:activo WHERE id=:id')
                   ->execute([':codigo' => $codigo, ':nombre' => $nombre, ':tipo' => $tipo, ':desc' => $desc, ':activo' => $activo, ':id' => $id]);
                if ($ant) registrarAuditoria($db, 'cuentas', $id, 'editar', "Cuenta {$codigo}", $ant, ['codigo'=>$codigo,'nombre'=>$nombre,'tipo'=>$tipo]);
                setMensaje('success', 'Cuenta actualizada.');
            } else {
                try {
                    $db->prepare('INSERT INTO cuentas (codigo,nombre,tipo,descripcion,activo) VALUES (:codigo,:nombre,:tipo,:desc,:activo)')
                       ->execute([':codigo' => $codigo, ':nombre' => $nombre, ':tipo' => $tipo, ':desc' => $desc, ':activo' => $activo]);
                    registrarAuditoria($db, 'cuentas', (int)$db->lastInsertId(), 'crear', "Cuenta {$codigo}");
                    setMensaje('success', 'Cuenta creada.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'Ya existe una cuenta con ese código.');
                }
            }
            redireccionar('index.php?vista=cuentas');

        case 'eliminar_cuenta':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM cuentas WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('DELETE FROM cuentas WHERE id = ?')->execute([$id]);
                if ($ant) registrarAuditoria($db, 'cuentas', $id, 'eliminar', "Cuenta {$ant['codigo']} {$ant['nombre']}", $ant);
                setMensaje('success', 'Cuenta eliminada.');
            }
            redireccionar('index.php?vista=cuentas');

        /* -------- ASIENTOS CONTABLES -------- */
        case 'guardar_asiento':
            verificarCsrf();
            $id     = (int) ($_POST['id'] ?? 0);
            $fecha  = $_POST['fecha'] ?? date('Y-m-d');
            $glosa  = trim($_POST['glosa'] ?? '');
            $estado = $_POST['estado'] ?? 'borrador';
            $cuentas = $_POST['det_cuenta'] ?? [];
            $debes   = $_POST['det_debe']   ?? [];
            $habers  = $_POST['det_haber']  ?? [];

            if ($glosa === '') {
                setMensaje('danger', 'La glosa es obligatoria.');
                break;
            }
            if (count($cuentas) < 2) {
                setMensaje('danger', 'Un asiento debe tener al menos 2 movimientos.');
                break;
            }

            $db->beginTransaction();
            try {
                if ($id > 0) {
                    $ant = $db->prepare('SELECT * FROM asientos WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                    $db->prepare('UPDATE asientos SET fecha=:f,glosa=:g,estado=:e WHERE id=:id')
                       ->execute([':f' => $fecha, ':g' => $glosa, ':e' => $estado, ':id' => $id]);
                    $db->prepare('DELETE FROM asiento_detalle WHERE asiento_id = ?')->execute([$id]);
                    $asientoId = $id;
                } else {
                    $numero = siguienteNumeroAsiento($db);
                    $db->prepare('INSERT INTO asientos (numero,fecha,glosa,estado) VALUES (:n,:f,:g,:e)')
                       ->execute([':n' => $numero, ':f' => $fecha, ':g' => $glosa, ':e' => $estado]);
                    $asientoId = (int) $db->lastInsertId();
                }

                $stmt = $db->prepare('INSERT INTO asiento_detalle (asiento_id,cuenta_id,debe,haber) VALUES (:a,:c,:d,:h)');
                $totalDebe = 0;
                $totalHaber = 0;
                $detalle = [];
                for ($i = 0; $i < count($cuentas); $i++) {
                    $cuentaId = (int) ($cuentas[$i] ?? 0);
                    $debe     = (float) ($debes[$i] ?? 0);
                    $haber    = (float) ($habers[$i] ?? 0);
                    if ($cuentaId <= 0 || ($debe == 0 && $haber == 0)) continue;
                    $stmt->execute([':a' => $asientoId, ':c' => $cuentaId, ':d' => $debe, ':h' => $haber]);
                    $totalDebe  += $debe;
                    $totalHaber += $haber;
                    $detalle[] = ['cuenta_id'=>$cuentaId, 'debe'=>$debe, 'haber'=>$haber];
                }

                if (abs($totalDebe - $totalHaber) > 0.01) {
                    $db->rollBack();
                    setMensaje('danger', "El asiento no cuadra: Debe={$totalDebe} | Haber={$totalHaber}");
                    break;
                }

                $db->commit();
                registrarAuditoria($db, 'asientos', $asientoId, $id > 0 ? 'editar' : 'crear', "Asiento N° {$asientoId} {$glosa}", [], ['fecha'=>$fecha,'glosa'=>$glosa,'detalle'=>$detalle]);
                setMensaje('success', 'Asiento guardado correctamente.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al guardar el asiento: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=asientos');

        case 'eliminar_asiento':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM asientos WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('DELETE FROM asientos WHERE id = ?')->execute([$id]);
                if ($ant) registrarAuditoria($db, 'asientos', $id, 'eliminar', "Asiento N° {$ant['numero']} {$ant['glosa']}", $ant);
                setMensaje('success', 'Asiento eliminado.');
            }
            redireccionar('index.php?vista=asientos');

        /* -------- DOCUMENTOS / BOLETAS (con IGV) -------- */
        case 'guardar_documento':
            verificarCsrf();
            $id      = (int) ($_POST['id'] ?? 0);
            $tipo    = $_POST['tipo'] ?? 'BOLETA';
            $numero  = trim($_POST['numero'] ?? '');
            $fecha   = $_POST['fecha'] ?? date('Y-m-d');
            $emisor  = trim($_POST['emisor'] ?? '');
            $ruc     = trim($_POST['ruc_dni'] ?? '');
            $concepto= trim($_POST['concepto'] ?? '');
            $importe = (float) ($_POST['importe'] ?? 0);            // base imponible
            $estado  = $_POST['estado'] ?? 'registrado';
            $tieneIgv = isset($_POST['aplicar_igv']) && $_POST['aplicar_igv'] === '1';

            $igv    = $tieneIgv ? calcularIgv($importe) : 0.00;
            $total  = round($importe + $igv, 2);

            if ($numero === '' || $emisor === '' || $ruc === '' || $concepto === '') {
                setMensaje('danger', 'Faltan campos obligatorios.');
                break;
            }

            $archivoGuardada = $archivoOriginal = '';
            if (!empty($_FILES['archivo']['name'])) {
                $archivoOriginal = $_FILES['archivo']['name'];
                try {
                    $archivoGuardada = guardarArchivoAdjunto($_FILES['archivo']);
                } catch (RuntimeException $e) {
                    setMensaje('danger', 'Archivo: ' . $e->getMessage());
                    break;
                }
            }

            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM documentos WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $stmt = $db->prepare(
                    'UPDATE documentos SET tipo=:tipo,numero=:num,fecha=:f,emisor=:e,ruc_dni=:r,concepto=:c,importe=:i,igv=:igv,total=:tt,estado=:es'
                    . ($archivoGuardada ? ',archivo_guardado=:ag,archivo_original=:ao' : '')
                    . ' WHERE id=:id'
                );
                $params = [
                    ':tipo' => $tipo, ':num' => $numero, ':f' => $fecha,
                    ':e' => $emisor, ':r' => $ruc, ':c' => $concepto,
                    ':i' => $importe, ':igv' => $igv, ':tt' => $total, ':es' => $estado, ':id' => $id
                ];
                if ($archivoGuardada) {
                    $antArc = $ant['archivo_guardado'] ?? '';
                    if ($antArc) borrarArchivoAdjunto($antArc);
                    $params[':ag'] = $archivoGuardada;
                    $params[':ao'] = $archivoOriginal;
                }
                $stmt->execute($params);
                if ($ant) registrarAuditoria($db, 'documentos', $id, 'editar', "Doc {$numero}", $ant, ['tipo'=>$tipo,'numero'=>$numero,'importe'=>$importe,'igv'=>$igv,'total'=>$total]);
                setMensaje('success', 'Documento actualizado.');
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO documentos (tipo,numero,fecha,emisor,ruc_dni,concepto,importe,igv,total,archivo_guardado,archivo_original,estado)
                     VALUES (:tipo,:num,:f,:e,:r,:c,:i,:igv,:tt,:ag,:ao,:es)'
                );
                $stmt->execute([
                    ':tipo' => $tipo, ':num' => $numero, ':f' => $fecha,
                    ':e' => $emisor, ':r' => $ruc, ':c' => $concepto,
                    ':i' => $importe, ':igv' => $igv, ':tt' => $total,
                    ':ag' => $archivoGuardada, ':ao' => $archivoOriginal, ':es' => $estado
                ]);
                $nuevoId = (int) $db->lastInsertId();
                registrarAuditoria($db, 'documentos', $nuevoId, 'crear', "Doc {$numero} - {$emisor}", [], ['tipo'=>$tipo,'numero'=>$numero,'importe'=>$importe,'igv'=>$igv,'total'=>$total]);
                setMensaje('success', 'Documento registrado.');
            }
            redireccionar('index.php?vista=documentos');

        case 'eliminar_documento':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM documentos WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                if ($ant && $ant['archivo_guardado']) borrarArchivoAdjunto($ant['archivo_guardado']);
                $db->prepare('DELETE FROM documentos WHERE id = ?')->execute([$id]);
                if ($ant) registrarAuditoria($db, 'documentos', $id, 'eliminar', "Doc {$ant['numero']} - {$ant['emisor']}", $ant);
                setMensaje('success', 'Documento eliminado.');
            }
            redireccionar('index.php?vista=documentos');

        /* -------- IMPORTACIÓN CSV -------- */
        case 'importar_csv':
            verificarCsrf();
            $tipoImport = $_POST['tipo_import'] ?? 'documentos';
            if (empty($_FILES['csv']['name'])) {
                setMensaje('danger', 'Selecciona un archivo CSV.');
            } else {
                try {
                    $n = importarCsv($db, $tipoImport, $_FILES['csv']['tmp_name']);
                    setMensaje('success', "Importación completada: $n registro(s) creados.");
                    registrarAuditoria($db, 'importaciones', 0, 'crear', "Importación CSV {$tipoImport}: {$n} registros");
                } catch (RuntimeException $e) {
                    setMensaje('danger', $e->getMessage());
                }
            }
            redireccionar('index.php?vista=importar');

        /* -------- ANULAR / REABRIR DOCUMENTO -------- */
        case 'anular_documento':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $a = $_POST['estado'] === 'registrado' ? 'registrado' : 'anulado';
            $db->prepare('UPDATE documentos SET estado=? WHERE id=?')->execute([$a, $id]);
            setMensaje('success', $a === 'anulado' ? 'Documento anulado.' : 'Documento reabierto.');
            redireccionar('index.php?vista=documentos');

        case 'download':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) redireccionar('index.php?vista=documentos');
            $row = $db->prepare('SELECT archivo_guardado, archivo_original FROM documentos WHERE id = ?');
            $row->execute([$id]);
            $row = $row->fetch();
            if (!$row || empty($row['archivo_guardado'])) {
                setMensaje('warning', 'No hay archivo adjunto.');
                redireccionar('index.php?vista=documentos');
            }
            $ruta = __DIR__ . '/archivos/' . $row['archivo_guardado'];
            if (!is_file($ruta)) {
                setMensaje('danger', 'El archivo no se encuentra en disco.');
                redireccionar('index.php?vista=documentos');
            }
            header('Content-Type: ' . (mime_content_type($ruta) ?: 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . $row['archivo_original'] . '"');
            header('Content-Length: ' . filesize($ruta));
            readfile($ruta);
            exit;

        /* -------- PRESUPUESTOS -------- */
        case 'guardar_presupuesto':
            verificarCsrf();
            $cuentaId = (int) ($_POST['cuenta_id'] ?? 0);
            $anio     = (int) ($_POST['anio'] ?? date('Y'));
            $monto    = (float) ($_POST['monto'] ?? 0);
            if ($cuentaId > 0) {
                $db->prepare('INSERT INTO presupuestos (cuenta_id,anio,monto) VALUES (:c,:a,:m)
                              ON DUPLICATE KEY UPDATE monto=VALUES(monto)')
                   ->execute([':c'=>$cuentaId, ':a'=>$anio, ':m'=>$monto]);
                setMensaje('success', 'Presupuesto guardado.');
            }
            redireccionar('index.php?vista=presupuestos');

        /* -------- EXPORTAR / REPORTES -------- */
        case 'exportar':
            require_once __DIR__ . '/includes/Exportar.php';
            $tipoR   = $_GET['tipo'] ?? $_POST['tipo'] ?? 'documentos';
            $formato = $_GET['formato'] ?? $_POST['formato'] ?? 'xlsx';
            $desde   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-01-01');
            $hasta   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m-d');

            switch ($tipoR) {
                case 'documentos':
                    $titulo = 'Listado de Documentos / Boletas';
                    $cab = ['ID','Tipo','Número','Fecha','Emisor','RUC/DNI','Concepto','Base','IGV','Total','Estado'];
                    $stmt = $db->prepare('SELECT id,tipo,numero,fecha,emisor,ruc_dni,concepto,importe,igv,total,estado FROM documentos WHERE fecha BETWEEN ? AND ? ORDER BY fecha,id');
                    $stmt->execute([$desde,$hasta]);
                    $filas = array_map(fn($r)=>[$r['id'],$r['tipo'],$r['numero'],formatoFecha($r['fecha']),$r['emisor'],$r['ruc_dni'],$r['concepto'],number_format((float)$r['importe'],2),number_format((float)$r['igv'],2),number_format((float)$r['total'],2),$r['estado']], $stmt->fetchAll());
                    $s = $db->prepare('SELECT COALESCE(SUM(CASE WHEN estado="registrado" THEN total END),0) t FROM documentos WHERE fecha BETWEEN ? AND ?'); $s->execute([$desde,$hasta]);
                    $sum = (float)$s->fetchColumn();
                    Exportar::generar($titulo, $cab, $filas, "reporte_documentos_{$desde}_{$hasta}", [['Total registrados (con IGV)', '', '', '', '', '', '', '', '', number_format($sum,2), '']]);
                    break;

                case 'asientos':
                    $titulo = 'Libro Diario de Asientos';
                    $cab = ['N°','Fecha','Glosa','Cuenta','Debe','Haber'];
                    $stmt = $db->prepare('SELECT a.numero,a.fecha,a.glosa,ad.debe,ad.haber,c.codigo,c.nombre FROM asientos a JOIN asiento_detalle ad ON ad.asiento_id=a.id JOIN cuentas c ON c.id=ad.cuenta_id WHERE a.fecha BETWEEN ? AND ? ORDER BY a.numero,a.id');
                    $stmt->execute([$desde,$hasta]);
                    $totalD = 0; $totalH = 0;
                    foreach ($stmt as $r) {
                        $filas[] = [$r['numero'],formatoFecha($r['fecha']),$r['glosa'],$r['codigo'].' - '.$r['nombre'],number_format($r['debe'],2),number_format($r['haber'],2)];
                        $totalD += (float)$r['debe'];
                        $totalH += (float)$r['haber'];
                    }
                    Exportar::generar($titulo, $cab, $filas ?? [], "libro_diario_{$desde}_{$hasta}", [['TOTAL','','','',number_format($totalD,2),number_format($totalH,2)]]);
                    break;

                case 'cuentas':
                    $titulo = 'Plan de Cuentas';
                    $cab = ['Código','Nombre','Tipo','Activo'];
                    $filas = array_map(fn($r)=>[$r['codigo'],$r['nombre'],$r['tipo'],$r['activo']?'Sí':'No'],
                        $db->query('SELECT codigo,nombre,tipo,activo FROM cuentas ORDER BY codigo')->fetchAll());
                    Exportar::generar($titulo, $cab, $filas, "plan_cuentas");
                    break;

                case 'libro_mayor':
                    $titulo = 'Libro Mayor';
                    $cab = ['Cuenta','Fecha','Glosa','Debe','Haber','Saldo'];
                    $cuentaFiltro = (int) ($_GET['cuenta_id'] ?? 0);
                    $sqlLM = 'SELECT c.codigo,c.nombre,a.fecha,a.glosa,ad.debe,ad.haber FROM asiento_detalle ad JOIN asientos a ON a.id=ad.asiento_id JOIN cuentas c ON c.id=ad.cuenta_id WHERE a.fecha BETWEEN ? AND ?';
                    $argsLM = [$desde,$hasta];
                    if ($cuentaFiltro > 0) { $sqlLM .= ' AND ad.cuenta_id = ?'; $argsLM[] = $cuentaFiltro; }
                    $sqlLM .= ' ORDER BY c.codigo,a.id';
                    $stmt = $db->prepare($sqlLM);
                    $stmt->execute($argsLM);
                    $filas = [];
                    foreach ($stmt as $r) {
                        $saldo = (float)$r['debe'] - (float)$r['haber'];
                        $filas[] = [$r['codigo'].' - '.$r['nombre'], formatoFecha($r['fecha']), $r['glosa'], number_format($r['debe'],2), number_format($r['haber'],2), number_format($saldo,2)];
                    }
                    Exportar::generar($titulo, $cab, $filas, "libro_mayor_{$desde}_{$hasta}");
                    break;

                case 'balance_comprob':
                    $titulo = 'Balance de Comprobación';
                    $cab = ['Código','Cuenta','Debe','Haber','Saldo Deudor','Saldo Acreedor'];
                    $stmt = $db->prepare('SELECT c.codigo,c.nombre,COALESCE(SUM(ad.debe),0) debe,COALESCE(SUM(ad.haber),0) haber FROM asiento_detalle ad JOIN cuentas c ON c.id=ad.cuenta_id JOIN asientos a ON a.id=ad.asiento_id WHERE a.fecha BETWEEN ? AND ? GROUP BY c.id ORDER BY c.codigo');
                    $stmt->execute([$desde,$hasta]);
                    $filas = []; $tD=0;$tH=0;
                    foreach ($stmt as $r) {
                        $sald = round((float)$r['debe'] - (float)$r['haber'],2);
                        $filas[] = [$r['codigo'],$r['nombre'],number_format($r['debe'],2),number_format($r['haber'],2),
                                    $sald>0?number_format($sald,2):'', $sald<0?number_format(abs($sald),2):''];
                        $tD+=(float)$r['debe']; $tH+=(float)$r['haber'];
                    }
                    Exportar::generar($titulo, $cab, $filas, "balance_comprobacion_{$desde}_{$hasta}", [['TOTAL','',number_format($tD,2),number_format($tH,2),'','']]);
                    break;

                case 'estados':
                    $titulo = 'Estados Financieros';
                    $cab = ['Concepto','Importe'];
                    $filas = [];
                    $totI = (float)$db->query("SELECT COALESCE(SUM(ad.haber-ad.debe),0) FROM asiento_detalle ad JOIN asientos a ON a.id=ad.asiento_id JOIN cuentas c ON c.id=ad.cuenta_id WHERE c.tipo='ingreso' AND a.fecha BETWEEN '$desde' AND '$hasta'")->fetchColumn();
                    $totE = (float)$db->query("SELECT COALESCE(SUM(ad.debe-ad.haber),0) FROM asiento_detalle ad JOIN asientos a ON a.id=ad.asiento_id JOIN cuentas c ON c.id=ad.cuenta_id WHERE c.tipo='egreso' AND a.fecha BETWEEN '$desde' AND '$hasta'")->fetchColumn();
                    $filas[] = ['Ingresos totales', number_format($totI,2)];
                    $filas[] = ['Gastos totales', number_format($totE,2)];
                    $filas[] = ['Resultado (utilidad/(pérdida))', number_format($totI-$totE,2)];
                    $totA = (float)$db->query("SELECT COALESCE(SUM(ad.debe-ad.haber),0) FROM asiento_detalle ad JOIN asientos a ON a.id=ad.asiento_id JOIN cuentas c ON c.id=ad.cuenta_id WHERE c.tipo='activo' AND a.fecha BETWEEN '$desde' AND '$hasta'")->fetchColumn();
                    $totP = (float)$db->query("SELECT COALESCE(SUM(ad.haber-ad.debe),0) FROM asiento_detalle ad JOIN asientos a ON a.id=ad.asiento_id JOIN cuentas c ON c.id=ad.cuenta_id WHERE c.tipo IN ('pasivo','patrimonio') AND a.fecha BETWEEN '$desde' AND '$hasta'")->fetchColumn();
                    $filas[] = ['Total activos', number_format($totA,2)];
                    $filas[] = ['Total pasivo + patrimonio', number_format($totP,2)];
                    $filas[] = ['Diferencia (debe cuadrar)', number_format($totA-$totP,2)];
                    Exportar::generar($titulo, $cab, $filas, "estados_financieros_{$desde}_{$hasta}");
                    break;

                case 'aging':
                    $titulo = 'Aging de Cuentas por Pagar / Cobrar';
                    $cab = ['Emisor / Cliente','Documento','Fecha','Antigüedad','0-30 días','31-60 días','61-90 días','+90 días','Total'];
                    $filas = [];
                    $stmt = $db->query("SELECT emisor,tipo,numero,fecha,total,DATEDIFF(CURDATE(),fecha) dias FROM documentos WHERE estado='registrado' ORDER BY fecha");
                    $tot1=0; $tot2=0; $tot3=0; $tot4=0; $totT=0;
                    foreach ($stmt as $r) {
                        $d = (int)$r['dias'];
                        $b1 = ($d>=0 && $d<=30) ? (float)$r['total'] : 0;
                        $b2 = ($d>30 && $d<=60) ? (float)$r['total'] : 0;
                        $b3 = ($d>60 && $d<=90) ? (float)$r['total'] : 0;
                        $b4 = $d>90 ? (float)$r['total'] : 0;
                        $tot1+=$b1; $tot2+=$b2; $tot3+=$b3; $tot4+=$b4; $totT+=(float)$r['total'];
                        $filas[] = [$r['emisor'], $r['tipo'].' '.$r['numero'], formatoFecha($r['fecha']), "{$d} días",
                                    number_format($b1,2), number_format($b2,2), number_format($b3,2), number_format($b4,2), number_format($r['total'],2)];
                    }
                    Exportar::generar($titulo, $cab, $filas, "aging_cuentas_{$desde}_{$hasta}",
                        [['TOTAL','','','',number_format($tot1,2),number_format($tot2,2),number_format($tot3,2),number_format($tot4,2),number_format($totT,2)]]);
                    break;

                default:
                    setMensaje('warning', 'Tipo de reporte no válido.');
            }
            redireccionar('index.php?vista=reportes');
            break;
    }
}

/* ------------------------------------------------------------------ */
/*  Renderizar la vista solicitada                                     */
/* ------------------------------------------------------------------ */

$vistaActual = preg_replace('/[^a-z0-9_]/i', '', $_GET['vista'] ?? 'dashboard');
$vistaActual = $vistaActual === '' ? 'dashboard' : $vistaActual;

require_once __DIR__ . '/includes/encabezado.php';

$vistaRuta = __DIR__ . '/vistas/' . $vistaActual . '.php';
if (is_file($vistaRuta)) {
    require_once $vistaRuta;
} else {
    echo '<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>Vista no encontrada.</div>';
}

require_once __DIR__ . '/includes/pie.php';