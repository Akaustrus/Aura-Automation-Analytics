<?php
/**
 * Sistema SAP — Aura Automation & Analytics
 * Router principal del módulo de Análisis Predictivo.
 *
 * Entrar desde: http://localhost:8001/sistemasap/index.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['sap_usuario'])) {
    $_SESSION['sap_usuario'] = 'usuario-local';
}

$db = SAPDB::conexion();

/* ------------------------------------------------------------------ */
/*  Acciones POST / GET (terminan antes de generar HTML)                */
/* ------------------------------------------------------------------ */

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action !== '') {
    switch ($action) {

        /* ===================== RRHH ===================== */
        case 'guardar_empleado':
            verificarCsrf();
            $id       = (int) ($_POST['id'] ?? 0);
            $nombre   = trim($_POST['nombre'] ?? '');
            $apellidos = trim($_POST['apellidos'] ?? '');
            $dni      = trim($_POST['dni'] ?? '');
            $cargo    = (int) ($_POST['cargo_id'] ?? 0);
            $salario  = (float) ($_POST['salario'] ?? 0);
            $fecha    = $_POST['fecha_contratacion'] ?? date('Y-m-d');
            $tipo     = $_POST['tipo_contrato'] ?? 'indefinido';
            $estadoVal = $_POST['estado'] ?? 'activo';
            $email    = trim($_POST['email'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $horas    = (int) ($_POST['horas_semanales'] ?? 40);

            if ($nombre === '' || $apellidos === '' || $dni === '' || $cargo <= 0 || $salario <= 0) {
                setMensaje('danger', 'Faltan datos obligatorios.');
                break;
            }

            $codigo = 'EMP-' . str_pad((string) ((int) $db->query('SELECT COALESCE(MAX(CAST(SUBSTRING(codigo,5) AS UNSIGNED)),0) + 1 FROM empleados')->fetchColumn()), 3, '0', STR_PAD_LEFT);

            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM empleados WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('UPDATE empleados SET nombre=:n,apellidos=:a,dni=:d,cargo_id=:c,salario=:s,fecha_contratacion=:f,tipo_contrato=:t,estado=:e,email=:em,telefono=:t2,horas_semanales=:h WHERE id=:id')
                   ->execute([
                       ':n'=>$nombre, ':a'=>$apellidos, ':d'=>$dni, ':c'=>$cargo, ':s'=>$salario,
                       ':f'=>$fecha, ':t'=>$tipo, ':e'=>$estadoVal, ':em'=>$email ?: null, ':t2'=>$telefono ?: null,
                       ':h'=>$horas, ':id'=>$id
                   ]);
                if ($ant) registrarAuditoria($db, 'empleados', $id, 'editar', "Empleado {$nombre} {$apellidos}", $ant, $_POST);
                setMensaje('success', 'Empleado actualizado.');
            } else {
                try {
                    $db->prepare('INSERT INTO empleados (codigo,nombre,apellidos,dni,email,telefono,cargo_id,fecha_contratacion,tipo_contrato,salario,estado,horas_semanales) VALUES (:co,:n,:a,:d,:em,:t2,:c,:f,:t,:s,:e,:h)')
                       ->execute([
                           ':co'=>$codigo, ':n'=>$nombre, ':a'=>$apellidos, ':d'=>$dni,
                           ':em'=>$email ?: null, ':t2'=>$telefono ?: null, ':c'=>$cargo,
                           ':f'=>$fecha, ':t'=>$tipo, ':s'=>$salario, ':e'=>$estadoVal, ':h'=>$horas
                       ]);
                    registrarAuditoria($db, 'empleados', (int)$db->lastInsertId(), 'crear', "Empleado {$nombre} {$apellidos}", [], $_POST);
                    setMensaje('success', "Empleado creado con código {$codigo}.");
                } catch (PDOException $e) {
                    setMensaje('danger', 'El DNI o correo ya está registrado.');
                }
            }
            redireccionar('index.php?vista=rrhh');

        case 'eliminar_empleado':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM empleados WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('DELETE FROM empleados WHERE id = ?')->execute([$id]);
                if ($ant) registrarAuditoria($db, 'empleados', $id, 'eliminar', "Empleado {$ant['nombre']}", $ant);
                setMensaje('success', 'Empleado eliminado.');
            }
            redireccionar('index.php?vista=rrhh');

        case 'guardar_vacante':
            verificarCsrf();
            $cargoId = (int) ($_POST['cargo_id'] ?? 0);
            $cantidad = (int) ($_POST['cantidad'] ?? 1);
            $motivo = trim($_POST['motivo'] ?? '');
            $estado = $_POST['estado'] ?? 'abierta';
            if ($cargoId > 0 && $cantidad > 0) {
                $db->prepare('INSERT INTO vacantes (cargo_id,cantidad,motivo,estado,fecha_apertura) VALUES (?,?,?,?,CURDATE())')
                   ->execute([$cargoId, $cantidad, $motivo ?: null, $estado]);
                registrarAuditoria($db, 'vacantes', (int)$db->lastInsertId(), 'crear', "Vacante cargo #$cargoId x$cantidad");
                setMensaje('success', 'Vacante registrada.');
            }
            redireccionar('index.php?vista=rrhh');

        case 'eliminar_vacante':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare('DELETE FROM vacantes WHERE id = ?')->execute([$id]);
                setMensaje('success', 'Vacante eliminada.');
            }
            redireccionar('index.php?vista=rrhh');

        /* ===================== INVENTARIO ===================== */
        case 'guardar_producto':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $codigo = trim($_POST['codigo'] ?? '');
            $nombre = trim($_POST['nombre'] ?? '');
            $categoria = (int) ($_POST['categoria_id'] ?? 0);
            $proveedor = (int) ($_POST['proveedor_id'] ?? 0);
            $stock = (int) ($_POST['stock_actual'] ?? 0);
            $stockMin = (int) ($_POST['stock_minimo'] ?? 0);
            $stockMax = (int) ($_POST['stock_maximo'] ?? 0);
            $punto = (int) ($_POST['punto_pedido'] ?? 0);
            $costo = (float) ($_POST['costo_unitario'] ?? 0);
            $precio = (float) ($_POST['precio_venta'] ?? 0);
            $unidad = trim($_POST['unidad'] ?? 'unidades');

            if ($codigo === '' || $nombre === '' || $categoria <= 0 || $proveedor <= 0 || $costo <= 0) {
                setMensaje('danger', 'Faltan datos obligatorios.');
                break;
            }

            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM productos WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('UPDATE productos SET codigo=:c,nombre=:n,categoria_id=:ca,proveedor_id=:pr,stock_actual=:s,stock_minimo=:sm,stock_maximo=:sx,punto_pedido=:pp,costo_unitario=:co,precio_venta=:pv,unidad=:u WHERE id=:id')
                   ->execute([':c'=>$codigo, ':n'=>$nombre, ':ca'=>$categoria, ':pr'=>$proveedor, ':s'=>$stock, ':sm'=>$stockMin, ':sx'=>$stockMax, ':pp'=>$punto, ':co'=>$costo, ':pv'=>$precio, ':u'=>$unidad, ':id'=>$id]);
                if ($ant) registrarAuditoria($db, 'productos', $id, 'editar', "Producto {$nombre}", $ant, $_POST);
                setMensaje('success', 'Producto actualizado.');
            } else {
                try {
                    $db->prepare('INSERT INTO productos (codigo,nombre,categoria_id,proveedor_id,stock_actual,stock_minimo,stock_maximo,punto_pedido,costo_unitario,precio_venta,unidad) VALUES (:c,:n,:ca,:pr,:s,:sm,:sx,:pp,:co,:pv,:u)')
                       ->execute([':c'=>$codigo, ':n'=>$nombre, ':ca'=>$categoria, ':pr'=>$proveedor, ':s'=>$stock, ':sm'=>$stockMin, ':sx'=>$stockMax, ':pp'=>$punto, ':co'=>$costo, ':pv'=>$precio, ':u'=>$unidad]);
                    registrarAuditoria($db, 'productos', (int)$db->lastInsertId(), 'crear', "Producto {$nombre}", [], $_POST);
                    setMensaje('success', 'Producto creado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'Ya existe un producto con ese código.');
                }
            }
            redireccionar('index.php?vista=inventario');

        case 'eliminar_producto':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM productos WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('DELETE FROM productos WHERE id = ?')->execute([$id]);
                if ($ant) registrarAuditoria($db, 'productos', $id, 'eliminar', "Producto {$ant['nombre']}", $ant);
                setMensaje('success', 'Producto eliminado.');
            }
            redireccionar('index.php?vista=inventario');

        case 'registrar_movimiento':
            verificarCsrf();
            $producto = (int) ($_POST['producto_id'] ?? 0);
            $tipo = $_POST['tipo'] ?? 'salida';
            $cantidad = (int) ($_POST['cantidad'] ?? 0);
            $concepto = trim($_POST['concepto'] ?? '');
            $fecha = $_POST['fecha'] ?? date('Y-m-d');
            $costoU = (float) ($_POST['costo_unitario'] ?? 0);

            if ($producto <= 0 || $cantidad <= 0) {
                setMensaje('danger', 'Datos de movimiento inválidos.');
                break;
            }

            $db->beginTransaction();
            try {
                $stmt = $db->prepare('SELECT costo_unitario FROM productos WHERE id=?');
                $stmt->execute([$producto]);
                $costoActual = (float) $stmt->fetchColumn();
                $costoU = $costoU > 0 ? $costoU : $costoActual;

                $db->prepare('INSERT INTO movimientos_inventario (producto_id,tipo,cantidad,costo_unitario,fecha,concepto) VALUES (?,?,?,?,?,?)')
                   ->execute([$producto, $tipo, $cantidad, $costoU, $fecha, $concepto ?: null]);

                if ($tipo === 'entrada') {
                    $db->prepare('UPDATE productos SET stock_actual = stock_actual + ? WHERE id = ?')->execute([$cantidad, $producto]);
                } elseif ($tipo === 'salida') {
                    $db->prepare('UPDATE productos SET stock_actual = GREATEST(0, stock_actual - ?) WHERE id = ?')->execute([$cantidad, $producto]);
                } else {
                    $db->prepare('UPDATE productos SET stock_actual = ? WHERE id = ?')->execute([$cantidad, $producto]);
                }
                $db->commit();
                registrarAuditoria($db, 'movimientos_inventario', (int)$db->lastInsertId(), 'crear', "Movimiento $tipo x$cantidad del producto #$producto");
                setMensaje('success', "Movimiento de $tipo registrado.");
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al registrar movimiento: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=inventario');

        /* ===================== PLANILLA ===================== */
        case 'generar_planilla':
            verificarCsrf();
            $periodo = $_POST['periodo'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) $periodo = date('Y-m');

            $inicio = $periodo . '-01';
            $fin = date('Y-m-t', strtotime($inicio));
            $diasMes = (int) date('t', strtotime($inicio));

            // Verificar si el periodo ya existe
            $stmtP = $db->prepare('SELECT id FROM periodos_planilla WHERE periodo = ?');
            $stmtP->execute([$periodo]);
            $periodoId = $stmtP->fetchColumn();

            if (!$periodoId) {
                $db->prepare('INSERT INTO periodos_planilla (periodo,inicio,fin) VALUES (?,?,?)')->execute([$periodo, $inicio, $fin]);
                $periodoId = (int) $db->lastInsertId();
            }

            $db->prepare('DELETE FROM boletas_pago WHERE periodo_id = ?')->execute([$periodoId]);

            $stmt = $db->prepare('SELECT e.id, e.salario, e.estado FROM empleados e WHERE e.estado != "inactivo"');
            $stmt->execute();
            $empList = $stmt->fetchAll();

            $stmtBP = $db->prepare(
                'INSERT INTO boletas_pago (periodo_id,empleado_id,dias_laborados,horas_extras,sueldo_base,bono,gratificacion,cts,essalud,onp,otros_descuentos,total_bruto,total_descuentos,neto_pagar,estado)
                 VALUES (:pid,:eid,:dl,:he,:base,:bono,:graf,:cts,:essalud,:onp,:otros,:bruto,:desc,:neto,"generado")'
            );
            $stmtAsist = $db->prepare('SELECT COUNT(*) FROM asistencias WHERE empleado_id=? AND tipo="falta" AND fecha BETWEEN ? AND ?');

            $totBruto = 0; $totDesc = 0; $totNeto = 0;
            foreach ($empList as $emp) {
                if ($emp['estado'] === 'vacaciones') continue;
                $stmtAsist->execute([$emp['id'], $inicio, $fin]);
                $faltas = (int) $stmtAsist->fetchColumn();
                $diasLaborados = max(1, $diasMes - $faltas);
                $salario = (float) $emp['salario'];
                $base = round($salario * ($diasLaborados / $diasMes), 2);
                $bono = round($base * 0.03, 2);
                $gratif = $periodo === date('Y-07') || $periodo === date('Y-12') ? $base : 0;
                $cts = $periodo === date('Y-05') || $periodo === date('Y-11') ? $base / 12 : 0;
                $essalud = round($base * 0.09, 2);
                $onp = round($base * 0.13, 2);
                $totalBruto = round($base + $bono + $gratif + $cts, 2);
                $totalDesc = round($essalud + $onp, 2);
                $neto = round($totalBruto - $totalDesc, 2);

                $stmtBP->execute([
                    ':pid'=>$periodoId, ':eid'=>$emp['id'], ':dl'=>$diasLaborados, ':he'=>0, ':base'=>$base,
                    ':bono'=>$bono, ':graf'=>$gratif, ':cts'=>$cts, ':essalud'=>$essalud, ':onp'=>$onp,
                    ':otros'=>0, ':bruto'=>$totalBruto, ':desc'=>$totalDesc, ':neto'=>$neto
                ]);
                $totBruto += $totalBruto; $totDesc += $totalDesc; $totNeto += $neto;
            }

            $db->prepare('UPDATE periodos_planilla SET total_bruto=?, total_descuentos=?, total_neto=? WHERE id=?')
               ->execute([$totBruto, $totDesc, $totNeto, $periodoId]);

            registrarAuditoria($db, 'boletas_pago', $periodoId, 'crear', "Planilla generada para $periodo");
            setMensaje('success', "Planilla de $periodo generada para " . count($empList) . ' trabajadores.');
            redireccionar('index.php?vista=planilla');

        case 'marcar_planilla_pagada':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare('UPDATE boletas_pago SET estado="pagado" WHERE id=?')->execute([$id]);
                setMensaje('success', 'Boleta marcada como pagada.');
            }
            redireccionar('index.php?vista=planilla');

        case 'cerrar_periodo':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare('UPDATE periodos_planilla SET estado="cerrado" WHERE id=?')->execute([$id]);
                setMensaje('success', 'Período cerrado.');
            }
            redireccionar('index.php?vista=planilla');

        /* ===================== FACTURACIÓN ===================== */
        case 'guardar_comprobante':
            verificarCsrf();
            $tipo = $_POST['tipo'] ?? 'BOLETA';
            $serie = trim($_POST['serie'] ?? ($tipo === 'BOLETA' ? 'B001' : 'F001'));
            $cliente = (int) ($_POST['cliente_id'] ?? 0);
            $fecha = $_POST['fecha'] ?? date('Y-m-d');
            $detalle = $_POST['det'] ?? [];
            $cantidades = $_POST['cant'] ?? [];
            $precios = $_POST['precio'] ?? [];

            if ($cliente <= 0 || empty($detalle)) {
                setMensaje('danger', 'Selecciona cliente y al menos un ítem.');
                break;
            }

            $ultNum = $db->prepare('SELECT COALESCE(MAX(numero),0)+1 n FROM comprobantes WHERE tipo=? AND serie=?');
            $ultNum->execute([$tipo, $serie]);
            $numero = (int) $ultNum->fetchColumn();

            $subtotal = 0;
            $lineas = [];
            for ($i = 0; $i < count($detalle); $i++) {
                $lineaDesc = trim($detalle[$i] ?? '');
                $lineaCant = (int) ($cantidades[$i] ?? 1);
                $lineaPrecio = (float) ($precios[$i] ?? 0);
                if ($lineaDesc === '' || $lineaCant <= 0 || $lineaPrecio <= 0) continue;
                $lineaSub = round($lineaCant * $lineaPrecio, 2);
                $subtotal += $lineaSub;
                $lineas[] = [$lineaDesc, $lineaCant, $lineaPrecio, $lineaSub];
            }
            if (empty($lineas)) {
                setMensaje('danger', 'Agrega al menos una línea válida.');
                break;
            }

            $igv = round($subtotal * tasaIGV($db), 2);
            $total = round($subtotal + $igv, 2);

            $db->beginTransaction();
            try {
                $db->prepare('INSERT INTO comprobantes (tipo,serie,numero,cliente_id,fecha,subtotal,igv,total,estado) VALUES (?,?,?,?,?,?,?,?,"emitido")')
                   ->execute([$tipo, $serie, $numero, $cliente, $fecha, $subtotal, $igv, $total]);
                $compId = (int) $db->lastInsertId();

                $stmtD = $db->prepare('INSERT INTO comprobante_detalle (comprobante_id,descripcion,cantidad,precio_unitario,subtotal) VALUES (?,?,?,?,?)');
                foreach ($lineas as [$d, $c, $p, $s]) {
                    $stmtD->execute([$compId, $d, $c, $p, $s]);
                }
                $db->commit();
                registrarAuditoria($db, 'comprobantes', $compId, 'crear', "Comprobante $tipo $serie-$numero");
                setMensaje('success', "Comprobante $tipo $serie-$numero creado por S/ " . number_format($total, 2) . '.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al crear comprobante: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=facturacion');

        case 'anular_comprobante':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare('UPDATE comprobantes SET estado="anulado" WHERE id=?')->execute([$id]);
                registrarAuditoria($db, 'comprobantes', $id, 'editar', 'Comprobante anulado');
                setMensaje('success', 'Comprobante anulado.');
            }
            redireccionar('index.php?vista=facturacion');

        case 'guardar_cliente':
            verificarCsrf();
            $nombre = trim($_POST['nombre'] ?? '');
            $ruc = trim($_POST['ruc_dni'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            if ($nombre !== '' && $ruc !== '') {
                $db->prepare('INSERT INTO clientes (nombre,ruc_dni,email,telefono) VALUES (?,?,?,?)')
                   ->execute([$nombre, $ruc, $email ?: null, $telefono ?: null]);
                setMensaje('success', 'Cliente registrado.');
            }
            redireccionar('index.php?vista=facturacion');

        /* ===================== CONTABILIDAD ===================== */
        case 'guardar_transaccion':
            verificarCsrf();
            $fecha = $_POST['fecha'] ?? date('Y-m-d');
            $cuenta = (int) ($_POST['cuenta_id'] ?? 0);
            $tipo = $_POST['tipo'] ?? 'ingreso';
            $monto = (float) ($_POST['monto'] ?? 0);
            $desc = trim($_POST['descripcion'] ?? '');
            if ($cuenta > 0 && $monto > 0) {
                $db->prepare('INSERT INTO transacciones (fecha,cuenta_id,tipo,monto,descripcion) VALUES (?,?,?,?,?)')
                   ->execute([$fecha, $cuenta, $tipo, $monto, $desc ?: null]);
                $signo = $tipo === 'ingreso' ? 1 : -1;
                $db->prepare('UPDATE cuentas_sap SET saldo = saldo + ? WHERE id = ?')->execute([$signo * $monto, $cuenta]);
                registrarAuditoria($db, 'transacciones', (int)$db->lastInsertId(), 'crear', "Transacción $tipo S/ $monto");
                setMensaje('success', 'Transacción registrada.');
            }
            redireccionar('index.php?vista=contabilidad');

        case 'eliminar_transaccion':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM transacciones WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                if ($ant) {
                    $signo = $ant['tipo'] === 'ingreso' ? -1 : 1;
                    $db->prepare('UPDATE cuentas_sap SET saldo = saldo + ? WHERE id = ?')->execute([$signo * (float) $ant['monto'], $ant['cuenta_id']]);
                    $db->prepare('DELETE FROM transacciones WHERE id = ?')->execute([$id]);
                    setMensaje('success', 'Transacción eliminada.');
                }
            }
            redireccionar('index.php?vista=contabilidad');

        /* ===================== PLANIFICACIÓN ===================== */
        case 'generar_predicciones':
            verificarCsrf();
            $horizonte = $_POST['horizonte'] ?? '30d';
            if (!in_array($horizonte, ['7d', '30d', '90d'], true)) $horizonte = '30d';
            $pasos = (int) rtrim($horizonte, 'd');

            try {
                // Predicción de ventas
                $ventas = [];
                $fe = [];
                $daily = $db->query('SELECT fecha, ventas FROM historico_diario ORDER BY fecha')->fetchAll();
                foreach ($daily as $d) {
                    $ventas[] = (float) $d['ventas'];
                    $fe[] = $d['fecha'];
                }
                $proyVentas = proyectarSerie($ventas, $pasos);
                $promVentas = promedioMovil($ventas, 30);
                $db->prepare('INSERT INTO predicciones (tipo,horizonte,datos,mensaje,nivel) VALUES ("ventas",?,?,?,?)')
                   ->execute([$horizonte, json_encode(['diario'=>array_slice($daily, -15), 'proyeccion'=>$proyVentas, 'promedio'=>$promVentas]),
                              'Se proyecta un promedio de S/ ' . number_format($promVentas, 2) . ' por día.', 'medio']);

                // Predicción de stock (productos con riesgo)
                $riesgoStock = [];
                $mapper = [];
                foreach ($db->query('SELECT id FROM productos WHERE activo=1')->fetchAll() as $pp) {
                    $pid = (int) $pp['id'];
                    $dias = diasRestantesStock($db, $pid);
                    $st = $db->prepare('SELECT stock_actual FROM productos WHERE id=?');
                    $st->execute([$pid]);
                    $stock = (int) $st->fetchColumn();
                    if ($dias <= 90) {
                        $riesgoStock[] = ['id'=>$pid, 'dias'=>$dias, 'stock'=>$stock];
                    }
                }
                $db->prepare('INSERT INTO predicciones (tipo,horizonte,datos,mensaje,nivel) VALUES ("stock",?,?,?,?)')
                   ->execute([$horizonte, json_encode($riesgoStock),
                              count($riesgoStock) . ' producto(s) con riesgo de agotarse en menos de 90 días.', count($riesgoStock) ? 'alto' : 'bajo']);

                // Predicción de personal
                $necesidadPersonal = [];
                foreach ($db->query("SELECT c.id, c.nombre, c.requeridos, COUNT(e.id) actuales, c.salario_base
                                     FROM cargos c LEFT JOIN empleados e ON e.cargo_id=c.id AND e.estado='activo'
                                     GROUP BY c.id, c.nombre, c.requeridos, c.salario_base")->fetchAll() as $cg) {
                    $necesidadPersonal[] = [
                        'cargo'=>$cg['nombre'], 'requeridos'=>(int)$cg['requeridos'], 'actuales'=>(int)$cg['actuales'],
                        'faltantes'=>max(0, (int)$cg['requeridos']-(int)$cg['actuales']), 'salario'=>(float)$cg['salario_base']
                    ];
                }
                $totFaltan = array_sum(array_column($necesidadPersonal, 'faltantes'));
                $db->prepare('INSERT INTO predicciones (tipo,horizonte,datos,mensaje,nivel) VALUES ("personal",?,?,?,?)')
                   ->execute([$horizonte, json_encode($necesidadPersonal),
                              $totFaltan ? "Se necesitan $totFaltan persona(s) adicional(es) para cubrir cargos." : 'Plantilla disponible según cargos requeridos.',
                              $totFaltan ? 'alto' : 'bajo']);

                // Predicción de flujo de caja
                $gastos = (float) $db->query("SELECT COALESCE(SUM(gastos),0) FROM historico_diario WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
                $ingresosM = (float) $db->query("SELECT COALESCE(SUM(in.p),0) FROM (SELECT SUM(monto) p FROM transacciones WHERE tipo='ingreso' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) in")->fetchColumn();
                $egresosM = (float) $db->query("SELECT COALESCE(SUM(eg.p),0) FROM (SELECT SUM(monto) p FROM transacciones WHERE tipo='egreso' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) eg")->fetchColumn();
                $flujoMensual = $ingresosM - $egresosM - $gastos;
                $db->prepare('INSERT INTO predicciones (tipo,horizonte,datos,mensaje,nivel) VALUES ("flujo_caja",?,?,?,?)')
                   ->execute([$horizonte, json_encode(['ingresos'=>$ingresosM, 'egresos'=>$egresosM, 'gastos_operativos'=>$gastos, 'flujo'=>$flujoMensual]),
                              $flujoMensual >= 0 ? 'Flujo de caja proyectado positivo: +S/ ' . number_format($flujoMensual, 2) : 'Flujo de caja proyectado negativo: S/ ' . number_format($flujoMensual, 2) . '. Revisa gastos.',
                              $flujoMensual >= 0 ? 'bajo' : 'alto']);

                // Predicción de compras recomendadas
                $compras = [];
                foreach ($db->query("SELECT p.id, p.nombre, p.stock_actual, p.punto_pedido, p.costo_unitario, prov.nombre proveedor, prov.tiempo_entrega_dias
                                     FROM productos p JOIN proveedores prov ON prov.id=p.proveedor_id
                                     WHERE p.activo=1 AND p.stock_actual <= p.punto_pedido")->fetchAll() as $pc) {
                    $compras[] = [
                        'id'=>(int)$pc['id'], 'nombre'=>$pc['nombre'], 'stock'=>(int)$pc['stock_actual'],
                        'punto_pedido'=>(int)$pc['punto_pedido'], 'costo'=>(float)$pc['costo_unitario'],
                        'proveedor'=>$pc['proveedor'], 'dias_entrega'=>(int)$pc['tiempo_entrega_dias'],
                        'cantidad_sugerida'=>max(1, (int)$pc['punto_pedido'] - (int)$pc['stock_actual'])
                    ];
                }
                $db->prepare('INSERT INTO predicciones (tipo,horizonte,datos,mensaje,nivel) VALUES ("compra",?,?,?,?)')
                   ->execute([$horizonte, json_encode($compras),
                              count($compras) ? 'Se recomienda comprar ' . count($compras) . ' producto(s) para no agotar stock.' : 'Sin compras urgentes en el horizonte.',
                              count($compras) ? 'medio' : 'bajo']);

                sincronizarRecomendaciones($db);
                setMensaje('success', 'Predicciones generadas para el horizonte ' . $horizonte . '.');
            } catch (Throwable $e) {
                setMensaje('danger', 'Error generando predicciones: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=planificacion');

        case 'actualizar_historial':
            verificarCsrf();
            $hoy = date('Y-m-d');
            $n = (int) $db->query("SELECT COUNT(*) FROM historico_diario WHERE fecha='$hoy'")->fetchColumn();
            if ($n > 0) {
                setMensaje('warning', 'El historial de hoy ya existe.');
            } else {
                $ventas = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM comprobantes WHERE fecha='$hoy' AND estado!='anulado'")->fetchColumn();
                $unid = (int) $db->query("SELECT COALESCE(SUM(cd.cantidad),0) FROM comprobante_detalle cd JOIN comprobantes c ON c.id=cd.comprobante_id WHERE c.fecha='$hoy' AND c.estado!='anulado'")->fetchColumn();
                $clientes = (int) $db->query("SELECT COUNT(DISTINCT cliente_id) FROM comprobantes WHERE fecha='$hoy' AND estado!='anulado'")->fetchColumn();
                $gastos = (float) $db->query("SELECT COALESCE(SUM(monto),0) FROM transacciones WHERE fecha='$hoy' AND tipo='egreso'")->fetchColumn();
                $db->prepare('INSERT INTO historico_diario (fecha,ventas,unidades_vendidas,clientes_atendidos,gastos) VALUES (?,?,?,?,?)')
                   ->execute([$hoy, $ventas, $unid, $clientes, $gastos]);
                setMensaje('success', 'Historial de hoy registrado.');
            }
            redireccionar('index.php?vista=planificacion');

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