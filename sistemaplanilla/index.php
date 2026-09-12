<?php
/**
 * Sistema de Planilla — Aura Automation & Analytics
 * Router principal independiente del ERP.
 *
 * Entrar desde: http://localhost:8002/index.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['planilla_usuario'])) {
    $_SESSION['planilla_usuario'] = 'usuario-local';
}

$db = PlanillaDB::conexion();

/* ------------------------------------------------------------------ */
/*  Acciones POST / GET                                                */
/* ------------------------------------------------------------------ */

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action !== '') {
    switch ($action) {

        /* -------- EMPLEADOS -------- */
        case 'guardar_empleado':
            verificarCsrf();
            $id            = (int) ($_POST['id'] ?? 0);
            $dni           = trim($_POST['dni'] ?? '');
            $nombres       = trim($_POST['nombres'] ?? '');
            $apellidos     = trim($_POST['apellidos'] ?? '');
            $genero        = in_array($_POST['genero'] ?? '', ['M', 'F'], true) ? $_POST['genero'] : 'M';
            $telefono      = trim($_POST['telefono'] ?? '');
            $correo        = trim($_POST['correo'] ?? '');
            $cargo         = trim($_POST['cargo'] ?? '');
            $area          = trim($_POST['area'] ?? '');
            $tipoContrato  = $_POST['tipo_contrato'] ?? 'indefinido';
            $fechaIng      = $_POST['fecha_ingreso'] ?? date('Y-m-d');
            $sueldo        = (float) ($_POST['sueldo_base'] ?? 0);
            $regimen       = in_array($_POST['regimen'] ?? '', ['onp','afp','ninguno'], true) ? $_POST['regimen'] : 'onp';
            $asf           = isset($_POST['asignacion_familiar']) ? 1 : 0;
            $jornada       = (float) ($_POST['jornada_horas'] ?? 8);
            $cuspp         = trim($_POST['cuspp'] ?? '');
            $banco         = trim($_POST['banco'] ?? '');
            $cuenta        = trim($_POST['cuenta_bancaria'] ?? '');
            $fechaCese     = $_POST['fecha_cese'] ?? null;
            $activo        = isset($_POST['activo']) ? 1 : 0;

            if ($dni === '' || $nombres === '' || $apellidos === '' || $cargo === '' || $sueldo <= 0) {
                setMensaje('danger', 'Faltan datos obligatorios (DNI, nombres, apellidos, cargo y sueldo mayor a 0).');
                break;
            }

            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM empleados WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('UPDATE empleados SET dni=:dni,nombres=:n,apellidos=:a,genero=:g,telefono=:t,correo=:co,
                              cargo=:c,area=:area,tipo_contrato=:tc,fecha_ingreso=:f,sueldo_base=:s,regimen=:r,
                              asignacion_familiar=:asf,jornada_horas=:j,cuspp=:cu,banco=:ba,cuenta_bancaria=:cb,
                              fecha_cese=:fc,activo=:act WHERE id=:id')
                   ->execute([':dni'=>$dni,':n'=>$nombres,':a'=>$apellidos,':g'=>$genero,':t'=>$telefono,
                              ':co'=>$correo,':c'=>$cargo,':area'=>$area,':tc'=>$tipoContrato,':f'=>$fechaIng,':s'=>$sueldo,
                              ':r'=>$regimen,':asf'=>$asf,':j'=>$jornada,':cu'=>$cuspp,':ba'=>$banco,
                              ':cb'=>$cuenta,':fc'=>$fechaCese,':act'=>$activo,':id'=>$id]);
                if ($ant) registrarAuditoria($db, 'empleados', $id, 'editar', "Empleado {$nombres} {$apellidos}",
                                             $ant, ['sueldo_base'=>$sueldo,'regimen'=>$regimen,'dni'=>$dni]);
                setMensaje('success', 'Empleado actualizado.');
            } else {
                try {
                    $token = generarTokenPortal();
                    $db->prepare('INSERT INTO empleados (dni,nombres,apellidos,genero,telefono,correo,cargo,area,
                                  tipo_contrato,fecha_ingreso,sueldo_base,regimen,asignacion_familiar,jornada_horas,
                                  cuspp,banco,cuenta_bancaria,fecha_cese,activo,portal_token)
                                  VALUES (:dni,:n,:a,:g,:t,:co,:c,:area,:tc,:f,:s,:r,:asf,:j,:cu,:ba,:cb,:fc,:act,:tk)')
                       ->execute([':dni'=>$dni,':n'=>$nombres,':a'=>$apellidos,':g'=>$genero,':t'=>$telefono,
                                  ':co'=>$correo,':c'=>$cargo,':area'=>$area,':tc'=>$tipoContrato,':f'=>$fechaIng,':s'=>$sueldo,
                                  ':r'=>$regimen,':asf'=>$asf,':j'=>$jornada,':cu'=>$cuspp,':ba'=>$banco,
                                  ':cb'=>$cuenta,':fc'=>$fechaCese,':act'=>$activo,':tk'=>$token]);
                    $nuevoId = (int) $db->lastInsertId();
                    registrarAuditoria($db, 'empleados', $nuevoId, 'crear', "Empleado {$nombres} {$apellidos}",
                                       [], ['sueldo_base'=>$sueldo,'regimen'=>$regimen,'dni'=>$dni,'token'=>$token]);
                    setMensaje('success', 'Empleado registrado. Token del portal: <b>' . $token . '</b> (guárdelo para entregarle al trabajador).');
                } catch (PDOException $e) {
                    setMensaje('danger', 'Ya existe un empleado con ese DNI.');
                }
            }
            redireccionar('index.php?vista=empleados');

        case 'eliminar_empleado':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM empleados WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('DELETE FROM empleados WHERE id = ?')->execute([$id]);
                if ($ant) registrarAuditoria($db, 'empleados', $id, 'eliminar', "Empleado {$ant['nombres']} {$ant['apellidos']}", $ant);
                setMensaje('success', 'Empleado eliminado.');
            }
            redireccionar('index.php?vista=empleados');

        case 'cambiar_token_empleado':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $tk = generarTokenPortal();
                $db->prepare('UPDATE empleados SET portal_token=? WHERE id=?')->execute([$tk, $id]);
                setMensaje('success', 'Token regenerado: <b>' . $tk . '</b>');
            }
            redireccionar('index.php?vista=empleados');

        /* -------- ASISTENCIA -------- */
        case 'guardar_asistencia':
            verificarCsrf();
            $emp   = (int) ($_POST['empleado_id'] ?? 0);
            $fecha = $_POST['fecha'] ?? date('Y-m-d');
            $estado= in_array($_POST['estado'] ?? '', ['asistio','falta','permiso','vacaciones'], true) ? $_POST['estado'] : 'asistio';
            $entrada  = $_POST['entrada'] ?? '08:00';
            $salida   = $_POST['salida'] ?? '17:00';
            $horasEx  = (float) ($_POST['horas_extra'] ?? 0);
            $just     = trim($_POST['justificacion'] ?? '');
            $id       = (int) ($_POST['id'] ?? 0);

            if ($emp <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                setMensaje('danger', 'Datos de asistencia no válidos.');
                break;
            }
            if ($id > 0) {
                $db->prepare('UPDATE asistencia SET empleado_id=:e,fecha=:f,estado=:es,entrada=:en,salida=:sa,horas_extra=:hx,justificacion=:j WHERE id=:id')
                   ->execute([':e'=>$emp,':f'=>$fecha,':es'=>$estado,':en'=>$entrada,':sa'=>$salida,':hx'=>$horasEx,':j'=>$just,':id'=>$id]);
            } else {
                try {
                    $db->prepare('INSERT INTO asistencia (empleado_id,fecha,estado,entrada,salida,horas_extra,justificacion)
                                  VALUES (:e,:f,:es,:en,:sa,:hx,:j)')
                       ->execute([':e'=>$emp,':f'=>$fecha,':es'=>$estado,':en'=>$entrada,':sa'=>$salida,':hx'=>$horasEx,':j'=>$just]);
                } catch (PDOException $e) {
                    setMensaje('danger', 'Ya existe un registro para ese empleado en esa fecha. Use el mes para filtrar y edite.');
                }
            }
            setMensaje('success', 'Asistencia guardada.');
            redireccionar('index.php?vista=asistencia');

        case 'eliminar_asistencia':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare('DELETE FROM asistencia WHERE id=?')->execute([$id]);
                setMensaje('success', 'Registro de asistencia eliminado.');
            }
            redireccionar('index.php?vista=asistencia');

        case 'importar_asistencia':
            verificarCsrf();
            if (!isset($_FILES['archivo']) || ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                setMensaje('danger', 'Seleccione un archivo CSV para importar.');
                break;
            }
            $filas = array_map('str_getcsv', file($_FILES['archivo']['tmp_name']));
            array_shift($filas); // quitar cabecera
            $cargados = 0;
            $ignorados = 0;
            foreach ($filas as $linea) {
                if (count($linea) < 3 || trim((string)($linea[0] ?? '')) === '') continue;
                $dni    = trim($linea[0]);
                $fecha  = trim($linea[1]);
                $estado = in_array(trim($linea[2] ?? ''), ['asistio','falta','permiso','vacaciones'], true) ? trim($linea[2]) : 'asistio';
                $entrada= trim($linea[3] ?? '08:00');
                $salida = trim($linea[4] ?? '17:00');
                $hx     = (float) ($linea[5] ?? 0);
                $just   = trim($linea[6] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { $ignorados++; continue; }
                $e = $db->prepare('SELECT id FROM empleados WHERE dni=?'); $e->execute([$dni]);
                $empId = $e->fetchColumn();
                if (!$empId) { $ignorados++; continue; }
                try {
                    $db->prepare('INSERT INTO asistencia (empleado_id,fecha,estado,entrada,salida,horas_extra,justificacion)
                                  VALUES (:e,:f,:es,:en,:sa,:hx,:j)')
                       ->execute([':e'=>(int)$empId,':f'=>$fecha,':es'=>$estado,':en'=>$entrada,':sa'=>$salida,':hx'=>$hx,':j'=>$just]);
                    $cargados++;
                } catch (PDOException $ex) { $ignorados++; }
            }
            setMensaje('success', "Importación completada: $cargados registros cargados, $ignorados omitidos (duplicados/inexistentes).");
            redireccionar('index.php?vista=asistencia');

        /* -------- ADELANTOS -------- */
        case 'guardar_adelanto':
            verificarCsrf();
            $emp    = (int) ($_POST['empleado_id'] ?? 0);
            $fecha  = $_POST['fecha'] ?? date('Y-m-d');
            $monto  = (float) ($_POST['monto'] ?? 0);
            $concepto = trim($_POST['concepto'] ?? 'Adelanto de sueldo');
            $id     = (int) ($_POST['id'] ?? 0);
            $estado = in_array($_POST['estado'] ?? '', ['pendiente','cobrado'], true) ? $_POST['estado'] : 'pendiente';

            if ($emp <= 0 || $monto <= 0) {
                setMensaje('danger', 'Datos del adelanto no válidos (monto mayor a 0).');
                break;
            }
            if ($id > 0) {
                $db->prepare('UPDATE adelantos SET empleado_id=:e,fecha=:f,monto=:m,concepto=:c,estado=:es WHERE id=:id')
                   ->execute([':e'=>$emp,':f'=>$fecha,':m'=>$monto,':c'=>$concepto,':es'=>$estado,':id'=>$id]);
                setMensaje('success', 'Adelanto actualizado.');
            } else {
                $db->prepare('INSERT INTO adelantos (empleado_id,fecha,monto,concepto,estado) VALUES (:e,:f,:m,:c,:es)')
                   ->execute([':e'=>$emp,':f'=>$fecha,':m'=>$monto,':c'=>$concepto,':es'=>$estado]);
                setMensaje('success', 'Adelanto registrado.');
            }
            redireccionar('index.php?vista=adelantos');

        case 'eliminar_adelanto':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare('DELETE FROM adelantos WHERE id=?')->execute([$id]);
                setMensaje('success', 'Adelanto eliminado.');
            }
            redireccionar('index.php?vista=adelantos');

        /* -------- CONCEPTOS (configuración) -------- */
        case 'guardar_concepto':
            verificarCsrf();
            $id       = (int) ($_POST['id'] ?? 0);
            $nombre   = trim($_POST['nombre'] ?? '');
            $tipo     = in_array($_POST['tipo'] ?? '', ['ingreso','descuento'], true) ? $_POST['tipo'] : 'ingreso';
            $ambito   = in_array($_POST['ambito'] ?? '', ['trabajador','empleador'], true) ? $_POST['ambito'] : 'trabajador';
            $naturaleza = in_array($_POST['naturaleza'] ?? '', ['fijo','porcentaje'], true) ? $_POST['naturaleza'] : 'fijo';
            $monto    = (float) ($_POST['monto'] ?? 0);
            $porc     = (float) ($_POST['porcentaje'] ?? 0);
            $aplica   = $_POST['aplica'] ?? 'mensual';
            $activo   = isset($_POST['activo']) ? 1 : 0;
            if ($nombre === '') {
                setMensaje('danger', 'Ingrese el nombre del concepto.');
                break;
            }
            if ($id > 0) {
                $db->prepare('UPDATE conceptos SET nombre=:n,tipo=:t,ambito=:a,naturaleza=:na,monto=:m,porcentaje=:p,aplica=:ap,activo=:ac WHERE id=:id')
                   ->execute([':n'=>$nombre,':t'=>$tipo,':a'=>$ambito,':na'=>$naturaleza,':m'=>$monto,':p'=>$porc,':ap'=>$aplica,':ac'=>$activo,':id'=>$id]);
            } else {
                $db->prepare('INSERT INTO conceptos (nombre,tipo,ambito,naturaleza,monto,porcentaje,aplica,activo)
                              VALUES (:n,:t,:a,:na,:m,:p,:ap,:ac)')
                   ->execute([':n'=>$nombre,':t'=>$tipo,':a'=>$ambito,':na'=>$naturaleza,':m'=>$monto,':p'=>$porc,':ap'=>$aplica,':ac'=>$activo]);
            }
            setMensaje('success', 'Concepto guardado.');
            redireccionar('index.php?vista=configuracion');

        case 'eliminar_concepto':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare('DELETE FROM conceptos WHERE id=?')->execute([$id]);
                setMensaje('success', 'Concepto eliminado.');
            }
            redireccionar('index.php?vista=configuracion');

        /* -------- PARÁMETROS -------- */
        case 'guardar_parametro':
            verificarCsrf();
            foreach ($_POST as $clave => $valor) {
                if ($clave !== 'action' && $clave !== 'csrf') {
                    $db->prepare('UPDATE parametros SET valor=? WHERE clave=?')->execute([(float)$valor, $clave]);
                }
            }
            setMensaje('success', 'Parámetros actualizados.');
            redireccionar('index.php?vista=configuracion');

        /* -------- PLANILLAS -------- */
        case 'generar_planilla':
            verificarCsrf();
            $periodo = validarPeriodo((string) ($_POST['periodo'] ?? ''));
            $tipo    = in_array($_POST['tipo'] ?? '', ['mensual','gratificacion','cts'], true) ? $_POST['tipo'] : 'mensual';
            if (!$periodo) {
                setMensaje('danger', 'Período no válido (formato YYYY-MM).');
            } else {
                [$ok, $msg] = generarPlanilla($db, $periodo, $tipo);
                setMensaje($ok ? 'success' : 'danger', $msg);
            }
            redireccionar('index.php?vista=planillas');

        case 'emitir_planilla':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $ant = $db->prepare('SELECT * FROM planillas WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
            if ($ant && $ant['estado'] === 'borrador') {
                $db->prepare('UPDATE planillas SET estado=? WHERE id=?')->execute(['emitida', $id]);
                registrarAuditoria($db, 'planillas', $id, 'editar', "Planilla {$ant['periodo']} emitida", $ant, ['estado'=>'emitida']);
                setMensaje('success', nombreTipoPlanilla($ant['tipo_planilla']) . ' ' . $ant['periodo'] . ' emitida. Ya se pueden descargar las boletas.');
            } else {
                setMensaje('warning', 'Solo se pueden emitir planillas en estado borrador.');
            }
            redireccionar('index.php?vista=planillas');

        case 'reabrir_planilla':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $ant = $db->prepare('SELECT * FROM planillas WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
            if ($ant && $ant['estado'] === 'anulada') {
                $db->prepare('UPDATE planillas SET estado=? WHERE id=?')->execute(['borrador', $id]);
                registrarAuditoria($db, 'planillas', $id, 'editar', "Planilla {$ant['periodo']} reabierta", $ant, ['estado'=>'borrador']);
                setMensaje('success', 'Planilla reabierta en borrador. Puede regenerarla.');
            } else {
                setMensaje('warning', 'Solo se pueden reabrir planillas anuladas.');
            }
            redireccionar('index.php?vista=planillas');

        case 'anular_planilla':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $ant = $db->prepare('SELECT * FROM planillas WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
            if ($ant) {
                if ($ant['estado'] !== 'anulada') {
                    $db->prepare('UPDATE planillas SET estado=? WHERE id=?')->execute(['anulada', $id]);
                }
                registrarAuditoria($db, 'planillas', $id, 'editar', "Planilla {$ant['periodo']} anulada", $ant, ['estado'=>'anulada']);
                setMensaje('success', 'Planilla anulada.');
            }
            redireccionar('index.php?vista=planillas');

        /* -------- EXPORTAR / REPORTES -------- */
        case 'exportar':
            require_once __DIR__ . '/includes/Exportar.php';
            $tipoR   = $_GET['tipo'] ?? $_POST['tipo'] ?? 'planilla';
            $formato = $_GET['formato'] ?? $_POST['formato'] ?? 'xlsx';

            if ($tipoR === 'planilla') {
                $periodo = validarPeriodo((string) ($_GET['periodo'] ?? periodoActual()));
                $tipoP   = in_array($_GET['tipo_planilla'] ?? '', ['mensual','gratificacion','cts'], true) ? ($_GET['tipo_planilla'] ?? 'mensual') : 'mensual';
                $stmt = $db->prepare('SELECT p.* FROM planillas p WHERE p.periodo = ? AND p.tipo_planilla = ?');
                $stmt->execute([$periodo, $tipoP]);
                $planilla = $stmt->fetch();
                if (!$planilla) {
                    setMensaje('warning', 'No hay ' . strtolower(nombreTipoPlanilla($tipoP)) . ' para ' . nombrePeriodo($periodo) . '.');
                    redireccionar('index.php?vista=planillas');
                }

                $titulo = nombreTipoPlanilla($tipoP) . ' ' . nombrePeriodo($periodo);
                $cab = ['DNI','Empleado','Cargo','Área','Régimen','Días','Sueldo base','ASF','H. extras','Bonos','Bruto','ESSALUD','Descuento','Renta','Adelantos','Neto'];
                $stmt = $db->prepare('SELECT e.dni,e.nombres,e.apellidos,e.cargo,e.area,e.regimen,
                                      d.dias_laborados,d.sueldo_base,d.asignacion_familiar,d.horas_extra_monto,d.bonos,
                                      d.sueldo_bruto,d.essalud,d.descuento,d.renta,d.adelantos,d.neto
                                      FROM detalle_planilla d JOIN empleados e ON e.id=d.empleado_id
                                      WHERE d.planilla_id=? ORDER BY e.apellidos');
                $stmt->execute([$planilla['id']]);
                $filas = [];
                foreach ($stmt as $r) {
                    $filas[] = [$r['dni'], $r['apellidos'] . ', ' . $r['nombres'], $r['cargo'], $r['area'],
                                strtoupper($r['regimen']), $r['dias_laborados'], number_format($r['sueldo_base'],2),
                                number_format($r['asignacion_familiar'],2), number_format($r['horas_extra_monto'],2),
                                number_format($r['bonos'],2), number_format($r['sueldo_bruto'],2),
                                number_format($r['essalud'],2), number_format($r['descuento'],2),
                                number_format($r['renta'],2), number_format($r['adelantos'],2), number_format($r['neto'],2)];
                }
                $resumen = [['TOTAL', '', '', '', '', '', number_format($planilla['total_bruto'],2), '', '', '',
                             number_format($planilla['total_essalud'],2), number_format($planilla['total_descuento'],2),
                             number_format($planilla['total_renta'],2), number_format($planilla['total_adelantos'],2),
                             number_format($planilla['total_neto'],2)]];
                Exportar::generar($titulo, $cab, $filas, "planilla_{$tipoP}_{$periodo}", $resumen);
            }

            if ($tipoR === 'boleta') {
                $detId = (int) ($_GET['id'] ?? 0);
                $stmt = $db->prepare('SELECT d.*, p.periodo, p.tipo_planilla, e.dni, e.nombres, e.apellidos, e.cargo, e.area, e.regimen, e.sueldo_base
                                      FROM detalle_planilla d
                                      JOIN planillas p ON p.id = d.planilla_id
                                      JOIN empleados e ON e.id = d.empleado_id
                                      WHERE d.id = ?');
                $stmt->execute([$detId]);
                $b = $stmt->fetch();
                if (!$b) {
                    setMensaje('warning', 'Boleta no encontrada.');
                    redireccionar('index.php?vista=planillas');
                }
                $nombre = $b['apellidos'] . '_' . $b['nombres'];
                $titulo = 'Boleta de Pago — ' . nombreTipoPlanilla($b['tipo_planilla']) . ' — ' . $b['apellidos'] . ', ' . $b['nombres'];
                $cab = ['Concepto', 'Detalle'];
                $filas = [
                    ['Trabajador', $b['apellidos'] . ', ' . $b['nombres']],
                    ['DNI', $b['dni']],
                    ['Cargo', $b['cargo']],
                    ['Área', $b['area']],
                    ['Período', nombrePeriodo($b['periodo']) . ' — ' . nombreTipoPlanilla($b['tipo_planilla'])],
                    ['Régimen', strtoupper($b['regimen'])],
                    ['Días laborados', $b['dias_laborados'] . ' (faltas: ' . $b['dias_faltas'] . ')'],
                    ['Sueldo base', 'S/ ' . number_format($b['sueldo_base'],2)],
                    ['Asignación familiar', 'S/ ' . number_format($b['asignacion_familiar'],2)],
                    ['Horas extras (' . number_format($b['horas_extra'],2) . ' h)', 'S/ ' . number_format($b['horas_extra_monto'],2)],
                    ['Bonos', 'S/ ' . number_format($b['bonos'],2)],
                    ['SUELDO BRUTO', 'S/ ' . number_format($b['sueldo_bruto'],2)],
                    ['ESSALUD (aporte empleador)', 'S/ ' . number_format($b['essalud'],2)],
                    ['Descuento de ley', '(S/ ' . number_format($b['descuento'],2) . ')'],
                    ['Renta de 5ta', '(S/ ' . number_format($b['renta'],2) . ')'],
                    ['Adelantos', '(S/ ' . number_format($b['adelantos'],2) . ')'],
                    ['NETO A PAGAR', 'S/ ' . number_format($b['neto'],2)],
                ];
                Exportar::generar($titulo, $cab, $filas, "boleta_{$b['dni']}_{$b['tipo_planilla']}_{$b['periodo']}");
            }

            if ($tipoR === 'resumen_area') {
                $periodo = validarPeriodo((string) ($_GET['periodo'] ?? periodoActual()));
                $tipoP   = in_array($_GET['tipo_planilla'] ?? '', ['mensual','gratificacion','cts'], true) ? ($_GET['tipo_planilla'] ?? 'mensual') : 'mensual';
                $titulo = 'Resumen por área — ' . nombreTipoPlanilla($tipoP) . ' ' . nombrePeriodo($periodo);
                $cab = ['Área', 'N. empleados', 'Total bruto', 'Total descuento', 'Total neto'];
                $stmt = $db->prepare("SELECT e.area, COUNT(*) n, ROUND(SUM(d.sueldo_bruto),2) bruto, ROUND(SUM(d.descuento+d.renta+d.adelantos),2) descuento, ROUND(SUM(d.neto),2) neto
                                      FROM detalle_planilla d JOIN empleados e ON e.id=d.empleado_id JOIN planillas p ON p.id=d.planilla_id
                                      WHERE p.periodo=? AND p.tipo_planilla=?
                                      GROUP BY e.area ORDER BY e.area");
                $stmt->execute([$periodo, $tipoP]);
                $filas = [];
                $tN = $tB = $tD = $tE = 0;
                foreach ($stmt as $r) {
                    $filas[] = [$r['area'], (string) $r['n'], number_format($r['bruto'],2), number_format($r['descuento'],2), number_format($r['neto'],2)];
                    $tE += (int) $r['n']; $tB += (float) $r['bruto']; $tD += (float) $r['descuento']; $tN += (float) $r['neto'];
                }
                $sub = $filas ? [['TOTAL', (string) $tE, number_format($tB,2), number_format($tD,2), number_format($tN,2)]] : [];
                Exportar::generar($titulo, $cab, $filas, "resumen_area_{$tipoP}_{$periodo}", $sub);
            }

            setMensaje('warning', 'Tipo de reporte no válido.');
            redireccionar('index.php?vista=planillas');
            break;

        /* -------- T-REGISTRO (archivo plano TXT) -------- */
        case 'tregistro_txt':
            $periodo = validarPeriodo((string) ($_GET['periodo'] ?? periodoActual()));
            $anio = (int) substr($periodo, 0, 4);
            $mes  = (int) substr($periodo, 5, 2);
            $salida = "T-REGISTRO PLAME — SOL 2012 — $periodo\n";
            $salida .= "Generado desde Sistema de Planilla — " . date('d/m/Y H:i:s') . "\n";
            $salida .= "================================================================================\n";
            $emp = $db->query('SELECT * FROM empleados WHERE activo=1 ORDER BY apellidos')->fetchAll();
            $nRow = 0;
            foreach ($emp as $e) {
                $nRow++;
                $ap = trim(mb_strtoupper(str_replace(['Á','É','Í','Ó','Ú','Ü','Ñ'], ['A','E','I','O','U','U','N'], (string)$e['apellidos'])));
                $nom = trim(mb_strtoupper(str_replace(['Á','É','Í','Ó','Ú','Ü','Ñ'], ['A','E','I','O','U','U','N'], (string)$e['nombres'])));
                $salida .= str_pad('01', 2, '0', STR_PAD_LEFT) . '|'
                        . $e['dni'] . '|'
                        . $e['tipo_contrato'] . '|'
                        . $ap . '|' . $nom . '|'
                        . ($e['genero'] ?? 'M') . '|'
                        . formatoFecha($e['fecha_ingreso']) . '|'
                        . $e['area'] . '|'
                        . strtoupper($e['regimen']) . '|'
                        . PHP_EOL;
                $salida .= str_pad('02', 2, '0', STR_PAD_LEFT) . '|' . $e['dni'] . '|'
                        . number_format((float)$e['sueldo_base'], 2, '.', '') . '|'
                        . (int)$anio . '|' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '|0000' . PHP_EOL;
            }
            $salida .= "TOTAL BLOQUES TIPO 01: $nRow\n";
            header('Content-Type: text/plain; charset=UTF-8');
            header('Content-Disposition: attachment; filename="t-registro_plame_' . $periodo . '.txt"');
            echo "\xEF\xBB\xBF" . $salida;
            exit;

        default:
            setMensaje('warning', 'Acción no reconocida.');
            redireccionar('index.php?vista=dashboard');
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