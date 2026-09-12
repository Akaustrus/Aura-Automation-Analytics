<?php
/**
 * Sistema ERP básico para micro empresas — Aura Automation & Analytics
 * Router principal independiente del ERP de incidencias.
 *
 * Entrar desde: http://localhost:8004/index.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['erp_usuario'])) {
    $_SESSION['erp_usuario'] = 'usuario-local';
}

$db = ErpDB::conexion();

/* ------------------------------------------------------------------ */
/*  Acciones POST / GET (terminan antes de generar HTML)                */
/* ------------------------------------------------------------------ */

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action !== '') {
    switch ($action) {

        /* ------------------ CLIENTES ------------------ */
        case 'guardar_cliente':
            verificarCsrf();
            $id          = (int) ($_POST['id'] ?? 0);
            $tipoDoc     = in_array($_POST['tipo_doc'] ?? '', ['RUC', 'DNI'], true) ? $_POST['tipo_doc'] : 'RUC';
            $rucDni      = trim($_POST['ruc_dni'] ?? '');
            $razonSocial = trim($_POST['razon_social'] ?? '');
            $direccion   = trim($_POST['direccion'] ?? '');
            $correo      = trim($_POST['correo'] ?? '');
            $telefono    = trim($_POST['telefono'] ?? '');
            $activo      = isset($_POST['activo']) ? 1 : 0;

            if ($rucDni === '' || $razonSocial === '') {
                setMensaje('danger', 'El documento y la razón social son obligatorios.');
                break;
            }

            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM clientes WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('UPDATE clientes SET tipo_doc=:td,ruc_dni=:r,razon_social=:rs,direccion=:d,correo=:c,telefono=:t,activo=:a WHERE id=:id')
                   ->execute([':td' => $tipoDoc, ':r' => $rucDni, ':rs' => $razonSocial, ':d' => $direccion, ':c' => $correo, ':t' => $telefono, ':a' => $activo, ':id' => $id]);
                if ($ant) registrarAuditoria($db, 'clientes', $id, 'editar', "Cliente {$razonSocial}", $ant, ['razon_social' => $razonSocial, 'ruc_dni' => $rucDni]);
                setMensaje('success', 'Cliente actualizado.');
            } else {
                try {
                    $db->prepare('INSERT INTO clientes (tipo_doc,ruc_dni,razon_social,direccion,correo,telefono,activo)
                                  VALUES (:td,:r,:rs,:d,:c,:t,:a)')
                       ->execute([':td' => $tipoDoc, ':r' => $rucDni, ':rs' => $razonSocial, ':d' => $direccion, ':c' => $correo, ':t' => $telefono, ':a' => $activo]);
                    $nuevoId = (int) $db->lastInsertId();
                    registrarAuditoria($db, 'clientes', $nuevoId, 'crear', "Cliente {$razonSocial}", [], ['ruc_dni' => $rucDni]);
                    setMensaje('success', 'Cliente registrado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'Ya existe un cliente con ese documento.');
                }
            }
            redireccionar('index.php?vista=clientes');

        case 'eliminar_cliente':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM clientes WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                try {
                    $db->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
                    if ($ant) registrarAuditoria($db, 'clientes', $id, 'eliminar', "Cliente {$ant['razon_social']}", $ant);
                    setMensaje('success', 'Cliente eliminado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'El cliente tiene ventas asociadas; no se puede eliminar.');
                }
            }
            redireccionar('index.php?vista=clientes');

        /* ------------------ PROVEEDORES ------------------ */
        case 'guardar_proveedor':
            verificarCsrf();
            $id          = (int) ($_POST['id'] ?? 0);
            $tipoDoc     = in_array($_POST['tipo_doc'] ?? '', ['RUC', 'DNI'], true) ? $_POST['tipo_doc'] : 'RUC';
            $rucDni      = trim($_POST['ruc_dni'] ?? '');
            $razonSocial = trim($_POST['razon_social'] ?? '');
            $direccion   = trim($_POST['direccion'] ?? '');
            $correo      = trim($_POST['correo'] ?? '');
            $telefono    = trim($_POST['telefono'] ?? '');
            $activo      = isset($_POST['activo']) ? 1 : 0;

            if ($rucDni === '' || $razonSocial === '') {
                setMensaje('danger', 'El documento y la razón social son obligatorios.');
                break;
            }

            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM proveedores WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('UPDATE proveedores SET tipo_doc=:td,ruc_dni=:r,razon_social=:rs,direccion=:d,correo=:c,telefono=:t,activo=:a WHERE id=:id')
                   ->execute([':td' => $tipoDoc, ':r' => $rucDni, ':rs' => $razonSocial, ':d' => $direccion, ':c' => $correo, ':t' => $telefono, ':a' => $activo, ':id' => $id]);
                if ($ant) registrarAuditoria($db, 'proveedores', $id, 'editar', "Proveedor {$razonSocial}", $ant, ['razon_social' => $razonSocial]);
                setMensaje('success', 'Proveedor actualizado.');
            } else {
                try {
                    $db->prepare('INSERT INTO proveedores (tipo_doc,ruc_dni,razon_social,direccion,correo,telefono,activo)
                                  VALUES (:td,:r,:rs,:d,:c,:t,:a)')
                       ->execute([':td' => $tipoDoc, ':r' => $rucDni, ':rs' => $razonSocial, ':d' => $direccion, ':c' => $correo, ':t' => $telefono, ':a' => $activo]);
                    $nuevoId = (int) $db->lastInsertId();
                    registrarAuditoria($db, 'proveedores', $nuevoId, 'crear', "Proveedor {$razonSocial}", [], ['ruc_dni' => $rucDni]);
                    setMensaje('success', 'Proveedor registrado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'Ya existe un proveedor con ese documento.');
                }
            }
            redireccionar('index.php?vista=proveedores');

        case 'eliminar_proveedor':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM proveedores WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                try {
                    $db->prepare('DELETE FROM proveedores WHERE id = ?')->execute([$id]);
                    if ($ant) registrarAuditoria($db, 'proveedores', $id, 'eliminar', "Proveedor {$ant['razon_social']}", $ant);
                    setMensaje('success', 'Proveedor eliminado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'El proveedor tiene compras asociadas; no se puede eliminar.');
                }
            }
            redireccionar('index.php?vista=proveedores');

        /* ------------------ PRODUCTOS ------------------ */
        case 'guardar_producto':
            verificarCsrf();
            $id          = (int) ($_POST['id'] ?? 0);
            $codigo      = trim($_POST['codigo'] ?? '');
            $nombre      = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $categoria   = trim($_POST['categoria'] ?? '');
            $unidad      = trim($_POST['unidad'] ?? 'unidad');
            $controla    = isset($_POST['controla_stock']) ? 1 : 0;
            $minimo      = (float) ($_POST['stock_minimo'] ?? 0);
            $pCompra     = (float) ($_POST['precio_compra'] ?? 0);
            $pVenta      = (float) ($_POST['precio_venta'] ?? 0);
            $activo      = isset($_POST['activo']) ? 1 : 0;

            if ($codigo === '' || $nombre === '' || $pCompra < 0 || $pVenta < 0) {
                setMensaje('danger', 'Código, nombre y precios válidos son obligatorios.');
                break;
            }

            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM productos WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('UPDATE productos SET codigo=:c,nombre=:n,descripcion=:d,categoria=:ca,unidad=:u,
                              controla_stock=:cs,stock_minimo=:min,precio_compra=:pc,precio_venta=:pv,activo=:a WHERE id=:id')
                   ->execute([':c' => $codigo, ':n' => $nombre, ':d' => $descripcion, ':ca' => $categoria, ':u' => $unidad,
                              ':cs' => $controla, ':min' => $minimo, ':pc' => $pCompra, ':pv' => $pVenta, ':a' => $activo, ':id' => $id]);
                if ($ant) registrarAuditoria($db, 'productos', $id, 'editar', "Producto {$codigo} - {$nombre}", $ant, ['precio_venta' => $pVenta, 'precio_compra' => $pCompra]);
                setMensaje('success', 'Producto actualizado.');
            } else {
                try {
                    $db->prepare('INSERT INTO productos (codigo,nombre,descripcion,categoria,unidad,controla_stock,stock,stock_minimo,precio_compra,precio_venta,activo)
                                  VALUES (:c,:n,:d,:ca,:u,:cs,0,:min,:pc,:pv,:a)')
                       ->execute([':c' => $codigo, ':n' => $nombre, ':d' => $descripcion, ':ca' => $categoria, ':u' => $unidad,
                                  ':cs' => $controla, ':min' => $minimo, ':pc' => $pCompra, ':pv' => $pVenta, ':a' => $activo]);
                    $nuevoId = (int) $db->lastInsertId();
                    registrarAuditoria($db, 'productos', $nuevoId, 'crear', "Producto {$codigo} - {$nombre}", [], ['precio_venta' => $pVenta]);
                    setMensaje('success', 'Producto registrado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'Ya existe un producto con ese código.');
                }
            }
            redireccionar('index.php?vista=productos');

        case 'eliminar_producto':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM productos WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                try {
                    $db->prepare('DELETE FROM productos WHERE id = ?')->execute([$id]);
                    if ($ant) registrarAuditoria($db, 'productos', $id, 'eliminar', "Producto {$ant['codigo']} - {$ant['nombre']}", $ant);
                    setMensaje('success', 'Producto eliminado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'El producto tiene movimientos o documentos asociados; no se puede eliminar.');
                }
            }
            redireccionar('index.php?vista=productos');

        /* ------------------ INVENTARIO: movimiento manual ------------------ */
        case 'registrar_movimiento':
            verificarCsrf();
            $productoId = (int) ($_POST['producto_id'] ?? 0);
            $tipo       = in_array($_POST['tipo'] ?? '', ['entrada', 'salida', 'ajuste'], true) ? $_POST['tipo'] : 'entrada';
            $cantidad   = (float) ($_POST['cantidad'] ?? 0);
            $motivo     = trim($_POST['motivo'] ?? '');
            $fecha      = $_POST['fecha'] ?? date('Y-m-d');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');
            if ($productoId <= 0 || $motivo === '') {
                setMensaje('danger', 'Seleccione el producto, el motivo y la cantidad.');
                break;
            }

            $stmt = $db->prepare('SELECT * FROM productos WHERE id=?');
            $stmt->execute([$productoId]);
            $p = $stmt->fetch();
            if (!$p || !$p['controla_stock']) {
                setMensaje('danger', 'El producto no existe o no controla stock.');
                break;
            }

            $delta = match ($tipo) {
                'entrada' => abs($cantidad),
                'salida'  => -abs($cantidad),
                'ajuste'  => $cantidad,
            };
            if ($delta == 0) {
                setMensaje('danger', 'La cantidad no puede ser cero.');
                break;
            }
            $nuevoStock = (float) $p['stock'] + $delta;
            if ($nuevoStock < -0.0005) {
                setMensaje('danger', 'El stock no puede quedar en negativo (actual: ' . formatoCantidad((float) $p['stock']) . ').');
                break;
            }

            $db->prepare('UPDATE productos SET stock = ? WHERE id = ?')->execute([$nuevoStock, $productoId]);
            $db->prepare('INSERT INTO movimientos (fecha, producto_id, tipo, cantidad, motivo, usuario)
                          VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$fecha, $productoId, $tipo, $delta, $motivo, $_SESSION['erp_usuario']]);
            registrarAuditoria($db, 'movimientos', $productoId, 'crear',
                "$tipo: {$p['codigo']} {$p['nombre']} ({formatoCantidad($delta)}) — {$motivo}",
                ['stock' => (float) $p['stock']], ['stock' => $nuevoStock]);
            setMensaje('success', 'Movimiento registrado. Nuevo stock: ' . formatoCantidad($nuevoStock));
            redireccionar('index.php?vista=inventario');

        /* ------------------ VENTAS ------------------ */
        case 'guardar_venta':
            verificarCsrf();
            $fecha     = $_POST['fecha'] ?? date('Y-m-d');
            $clienteId = (int) ($_POST['cliente_id'] ?? 0);
            $condicion = in_array($_POST['condicion'] ?? '', ['contado', 'credito'], true) ? $_POST['condicion'] : 'contado';
            $observ    = trim($_POST['observaciones'] ?? '');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                setMensaje('danger', 'Fecha no válida.');
                break;
            }
            if ($clienteId <= 0) {
                setMensaje('danger', 'Seleccione un cliente.');
                break;
            }

            $desc = $_POST['item_desc'] ?? [];
            $cant = $_POST['item_cant'] ?? [];
            $prec = $_POST['item_precio'] ?? [];
            $prod = $_POST['item_prod_id'] ?? [];
            $subtotalTotal = 0.0;
            $items = [];

            for ($i = 0; $i < count($desc); $i++) {
                $d = trim((string) ($desc[$i] ?? ''));
                $c = (float) ($cant[$i] ?? 0);
                $p = (float) ($prec[$i] ?? 0);
                if ($d === '' || $c <= 0) continue;

                $productoId = (int) ($prod[$i] ?? 0);
                $controla   = false;
                if ($productoId > 0) {
                    $stmt = $db->prepare('SELECT * FROM productos WHERE id=?'); $stmt->execute([$productoId]);
                    $pr = $stmt->fetch();
                    if ($pr && $pr['controla_stock'] && (float) $pr['stock'] + 0.0005 < $c) {
                        setMensaje('danger', "Stock insuficiente de {$pr['nombre']} (disponible: " . formatoCantidad((float) $pr['stock']) . ').');
                        break 2;
                    }
                    $controla = $pr && (bool) $pr['controla_stock'];
                }
                $subtotal = round($c * $p, 2);
                $subtotalTotal += $subtotal;
                $items[] = ['producto_id' => $productoId, 'descripcion' => $d, 'cantidad' => $c,
                            'precio_unitario' => $p, 'subtotal' => $subtotal, 'controla' => $controla];
            }

            if (!$items) {
                setMensaje('danger', 'La venta debe tener al menos un ítem válido.');
                break;
            }
            $total = round($subtotalTotal, 2);

            $db->beginTransaction();
            try {
                $numero = siguienteNumero($db, 'ventas');
                $db->prepare('INSERT INTO ventas (numero, fecha, cliente_id, condicion, total, observaciones, estado)
                              VALUES (?,?,?,?,?,?, "emitida")')
                   ->execute([$numero, $fecha, $clienteId, $condicion, $total, $observ]);
                $ventaId = (int) $db->lastInsertId();

                $stmtD = $db->prepare('INSERT INTO venta_detalle (venta_id, producto_id, descripcion, cantidad, precio_unitario, subtotal)
                                       VALUES (?,?,?,?,?,?)');
                $stmtM = $db->prepare('INSERT INTO movimientos (fecha, producto_id, tipo, cantidad, motivo, referencia, usuario)
                                       VALUES (?,?,"salida",?,"Salida por venta",?,?)');
                $stmtS = $db->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?');
                foreach ($items as $it) {
                    $stmtD->execute([$ventaId, $it['producto_id'] ?: null, $it['descripcion'], $it['cantidad'],
                                     $it['precio_unitario'], $it['subtotal']]);
                    if ($it['producto_id'] && $it['controla']) {
                        $numDoc = numeroVenta($numero);
                        $stmtM->execute([$fecha, $it['producto_id'], -$it['cantidad'], $numDoc, $_SESSION['erp_usuario']]);
                        $stmtS->execute([$it['cantidad'], $it['producto_id']]);
                    }
                }
                registrarAuditoria($db, 'ventas', $ventaId, 'crear',
                    'Venta ' . numeroVenta($numero) . " por " . formatoMoneda($total), [], ['condicion' => $condicion, 'items' => count($items)]);
                $db->commit();
                setMensaje('success', 'Venta ' . numeroVenta($numero) . ' registrada. Stock actualizado.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al registrar la venta: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=ventas');

        case 'anular_venta':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $db->prepare('SELECT * FROM ventas WHERE id=?'); $stmt->execute([$id]);
            $v = $stmt->fetch();
            if (!$v) {
                setMensaje('danger', 'Venta no encontrada.');
                break;
            }
            if ($v['estado'] === 'anulada') {
                setMensaje('warning', 'La venta ya está anulada.');
                break;
            }

            $db->beginTransaction();
            try {
                $stmtD = $db->prepare('SELECT d.*, p.controla_stock FROM venta_detalle d LEFT JOIN productos p ON p.id=d.producto_id WHERE d.venta_id=?');
                $stmtD->execute([$id]);
                $stmtM = $db->prepare('INSERT INTO movimientos (fecha, producto_id, tipo, cantidad, motivo, referencia, usuario)
                                       VALUES (?,?,"entrada",?,"Anulación de venta",?,?)');
                $stmtS = $db->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?');
                foreach ($stmtD->fetchAll() as $d) {
                    if ($d['producto_id'] && $d['controla_stock']) {
                        $stmtM->execute([$v['fecha'], $d['producto_id'], $d['cantidad'], numeroVenta((int) $v['numero']), $_SESSION['erp_usuario']]);
                        $stmtS->execute([$d['cantidad'], $d['producto_id']]);
                    }
                }
                $db->prepare('UPDATE ventas SET estado="anulada" WHERE id=?')->execute([$id]);
                registrarAuditoria($db, 'ventas', $id, 'editar', 'Venta ' . numeroVenta((int) $v['numero']) . ' anulada. Stock restaurado.', $v, ['estado' => 'anulada']);
                $db->commit();
                setMensaje('success', 'Venta anulada. Stock restaurado.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al anular: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=ventas');

        /* ------------------ COMPRAS ------------------ */
        case 'guardar_compra':
            verificarCsrf();
            $fecha       = $_POST['fecha'] ?? date('Y-m-d');
            $proveedorId = (int) ($_POST['proveedor_id'] ?? 0);
            $condicion   = in_array($_POST['condicion'] ?? '', ['contado', 'credito'], true) ? $_POST['condicion'] : 'contado';
            $observ      = trim($_POST['observaciones'] ?? '');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                setMensaje('danger', 'Fecha no válida.');
                break;
            }
            if ($proveedorId <= 0) {
                setMensaje('danger', 'Seleccione un proveedor.');
                break;
            }

            $desc = $_POST['item_desc'] ?? [];
            $cant = $_POST['item_cant'] ?? [];
            $prec = $_POST['item_precio'] ?? [];
            $prod = $_POST['item_prod_id'] ?? [];
            $total = 0.0;
            $items = [];

            for ($i = 0; $i < count($desc); $i++) {
                $d = trim((string) ($desc[$i] ?? ''));
                $c = (float) ($cant[$i] ?? 0);
                $p = (float) ($prec[$i] ?? 0);
                $productoId = (int) ($prod[$i] ?? 0);
                if ($d === '' || $c <= 0 || $productoId <= 0) continue;

                $stmt = $db->prepare('SELECT * FROM productos WHERE id=?'); $stmt->execute([$productoId]);
                $pr = $stmt->fetch();
                if (!$pr) {
                    setMensaje('danger', 'Producto no válido en el ítem: ' . $d);
                    break 2;
                }
                $subtotal = round($c * $p, 2);
                $total += $subtotal;
                $items[] = ['producto_id' => $productoId, 'descripcion' => $d, 'cantidad' => $c,
                            'precio_unitario' => $p, 'subtotal' => $subtotal, 'controla' => (bool) $pr['controla_stock']];
            }

            if (!$items) {
                setMensaje('danger', 'La compra debe tener al menos un ítem con producto válido.');
                break;
            }
            $total = round($total, 2);

            $db->beginTransaction();
            try {
                $numero = siguienteNumero($db, 'compras');
                $db->prepare('INSERT INTO compras (numero, fecha, proveedor_id, condicion, total, observaciones, estado)
                              VALUES (?,?,?,?,?,?, "emitida")')
                   ->execute([$numero, $fecha, $proveedorId, $condicion, $total, $observ]);
                $compraId = (int) $db->lastInsertId();

                $stmtD = $db->prepare('INSERT INTO compra_detalle (compra_id, producto_id, descripcion, cantidad, precio_unitario, subtotal)
                                       VALUES (?,?,?,?,?,?)');
                $stmtM = $db->prepare('INSERT INTO movimientos (fecha, producto_id, tipo, cantidad, motivo, referencia, usuario)
                                       VALUES (?,?,"entrada",?,"Ingreso por compra",?,?)');
                $stmtS = $db->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?');
                foreach ($items as $it) {
                    $stmtD->execute([$compraId, $it['producto_id'], $it['descripcion'], $it['cantidad'],
                                     $it['precio_unitario'], $it['subtotal']]);
                    if ($it['controla']) {
                        $numDoc = numeroCompra($numero);
                        $stmtM->execute([$fecha, $it['producto_id'], $it['cantidad'], $numDoc, $_SESSION['erp_usuario']]);
                        $stmtS->execute([$it['cantidad'], $it['producto_id']]);
                    }
                }
                registrarAuditoria($db, 'compras', $compraId, 'crear',
                    'Compra ' . numeroCompra($numero) . " por " . formatoMoneda($total), [], ['condicion' => $condicion, 'items' => count($items)]);
                $db->commit();
                setMensaje('success', 'Compra ' . numeroCompra($numero) . ' registrada. Stock actualizado.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al registrar la compra: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=compras');

        case 'anular_compra':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $db->prepare('SELECT * FROM compras WHERE id=?'); $stmt->execute([$id]);
            $c0 = $stmt->fetch();
            if (!$c0) {
                setMensaje('danger', 'Compra no encontrada.');
                break;
            }
            if ($c0['estado'] === 'anulada') {
                setMensaje('warning', 'La compra ya está anulada.');
                break;
            }

            $db->beginTransaction();
            try {
                $stmtD = $db->prepare('SELECT d.*, p.controla_stock FROM compra_detalle d JOIN productos p ON p.id=d.producto_id WHERE d.compra_id=?');
                $stmtD->execute([$id]);
                $stmtM = $db->prepare('INSERT INTO movimientos (fecha, producto_id, tipo, cantidad, motivo, referencia, usuario)
                                       VALUES (?,?,"salida",?,"Anulación de compra",?,?)');
                $stmtS = $db->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?');
                foreach ($stmtD->fetchAll() as $d) {
                    if ($d['controla_stock']) {
                        $stmtM->execute([$c0['fecha'], $d['producto_id'], -$d['cantidad'], numeroCompra((int) $c0['numero']), $_SESSION['erp_usuario']]);
                        $stmtS->execute([$d['cantidad'], $d['producto_id']]);
                    }
                }
                $db->prepare('UPDATE compras SET estado="anulada" WHERE id=?')->execute([$id]);
                registrarAuditoria($db, 'compras', $id, 'editar',
                    'Compra ' . numeroCompra((int) $c0['numero']) . ' anulada. Stock descontado.', $c0, ['estado' => 'anulada']);
                $db->commit();
                setMensaje('success', 'Compra anulada. Stock descontado.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al anular: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=compras');

        /* ------------------ CAJA ------------------ */
        case 'guardar_caja':
            verificarCsrf();
            $fecha     = $_POST['fecha'] ?? date('Y-m-d');
            $tipo      = in_array($_POST['tipo'] ?? '', ['ingreso', 'egreso'], true) ? $_POST['tipo'] : 'ingreso';
            $concepto  = trim($_POST['concepto'] ?? '');
            $medio     = in_array($_POST['medio'] ?? '', mediosPago(), true) ? $_POST['medio'] : 'efectivo';
            $monto     = (float) ($_POST['monto'] ?? 0);
            $referencia = trim($_POST['referencia'] ?? '');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');
            if ($concepto === '' || $monto <= 0) {
                setMensaje('danger', 'Ingrese concepto y un monto mayor a cero.');
                break;
            }
            $db->prepare('INSERT INTO caja (fecha, tipo, concepto, medio, monto, referencia) VALUES (?,?,?,?,?,?)')
               ->execute([$fecha, $tipo, $concepto, $medio, $monto, $referencia]);
            $cajaId = (int) $db->lastInsertId();
            registrarAuditoria($db, 'caja', $cajaId, 'crear',
                ucfirst($tipo) . ' de caja: ' . $concepto . ' (' . formatoMoneda($monto) . ')', [], ['tipo' => $tipo, 'monto' => $monto]);
            setMensaje('success', ucfirst($tipo) . ' registrado en caja.');
            redireccionar('index.php?vista=caja');

        case 'eliminar_caja':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM caja WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                if ($ant) {
                    $db->prepare('DELETE FROM caja WHERE id=?')->execute([$id]);
                    registrarAuditoria($db, 'caja', $id, 'eliminar',
                        'Eliminación ' . $ant['tipo'] . ': ' . $ant['concepto'] . ' (' . formatoMoneda((float) $ant['monto']) . ')', $ant);
                    setMensaje('success', 'Movimiento de caja eliminado.');
                }
            }
            redireccionar('index.php?vista=caja');

        /* ------------------ CONFIGURACIÓN ------------------ */
        case 'guardar_parametros':
            verificarCsrf();
            foreach ($_POST as $clave => $valor) {
                if ($clave === 'action' || $clave === 'csrf') continue;
                $clave = preg_replace('/[^A-Z_]/', '', $clave);
                if ($clave === '') continue;
                $db->prepare('INSERT INTO parametros (clave, valor, descripcion) VALUES (?, ?, "Editado por usuario")
                              ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
                   ->execute([$clave, trim((string) $valor)]);
            }
            setMensaje('success', 'Configuración actualizada.');
            redireccionar('index.php?vista=configuracion');

        /* ------------------ EXPORTAR REPORTES ------------------ */
        case 'exportar':
            require_once __DIR__ . '/includes/Exportar.php';
            $tipoR   = $_GET['tipo'] ?? $_POST['tipo'] ?? 'ventas';
            $formato = $_GET['formato'] ?? $_POST['formato'] ?? 'xlsx';

            if ($tipoR === 'ventas') {
                [$desde, $hasta] = rangoFechas();
                $sql = 'SELECT v.*, cl.razon_social FROM ventas v JOIN clientes cl ON cl.id=v.cliente_id
                        WHERE v.fecha BETWEEN ? AND ?';
                $args = [$desde, $hasta];
                if (!empty($_GET['estado_f'])) { $sql .= ' AND v.estado=?'; $args[] = $_GET['estado_f']; }
                $stmt = $db->prepare($sql . ' ORDER BY v.fecha, v.numero');
                $stmt->execute($args);

                $filas = [];
                $tTotal = 0.0;
                foreach ($stmt as $r) {
                    $filas[] = [numeroVenta((int) $r['numero']), formatoFecha($r['fecha']), $r['razon_social'],
                                ucfirst($r['condicion']), number_format((float) $r['total'], 2), $r['estado']];
                    if ($r['estado'] === 'emitida') $tTotal += (float) $r['total'];
                }
                Exportar::generar('Reporte de ventas ' . $desde . ' al ' . $hasta,
                    ['Número', 'Fecha', 'Cliente', 'Condición', 'Total', 'Estado'], $filas, "ventas_{$desde}_{$hasta}",
                    [['TOTAL', '', '', '', number_format($tTotal, 2), '']]);
            }

            if ($tipoR === 'compras') {
                [$desde, $hasta] = rangoFechas();
                $sql = 'SELECT c.*, p.razon_social FROM compras c JOIN proveedores p ON p.id=c.proveedor_id
                        WHERE c.fecha BETWEEN ? AND ?';
                $args = [$desde, $hasta];
                if (!empty($_GET['estado_f'])) { $sql .= ' AND c.estado=?'; $args[] = $_GET['estado_f']; }
                $stmt = $db->prepare($sql . ' ORDER BY c.fecha, c.numero');
                $stmt->execute($args);

                $filas = [];
                $tTotal = 0.0;
                foreach ($stmt as $r) {
                    $filas[] = [numeroCompra((int) $r['numero']), formatoFecha($r['fecha']), $r['razon_social'],
                                ucfirst($r['condicion']), number_format((float) $r['total'], 2), $r['estado']];
                    if ($r['estado'] === 'emitida') $tTotal += (float) $r['total'];
                }
                Exportar::generar('Reporte de compras ' . $desde . ' al ' . $hasta,
                    ['Número', 'Fecha', 'Proveedor', 'Condición', 'Total', 'Estado'], $filas, "compras_{$desde}_{$hasta}",
                    [['TOTAL', '', '', '', number_format($tTotal, 2), '']]);
            }

            if ($tipoR === 'inventario') {
                $cab = ['Código', 'Producto', 'Categoría', 'Unidad', 'Stock', 'Mínimo',
                        'P. Compra', 'P. Venta', 'Valorización compra', 'Valorización venta', 'Estado'];
                $filas = [];
                $vC = $vV = 0.0;
                foreach ($db->query('SELECT * FROM productos ORDER BY nombre') as $p) {
                    $valC = (float) $p['stock'] * (float) $p['precio_compra'];
                    $valV = (float) $p['stock'] * (float) $p['precio_venta'];
                    $vC += $valC; $vV += $valV;
                    $filas[] = [$p['codigo'], $p['nombre'], $p['categoria'], $p['unidad'],
                                formatoCantidad((float) $p['stock']), formatoCantidad((float) $p['stock_minimo']),
                                number_format((float) $p['precio_compra'], 2), number_format((float) $p['precio_venta'], 2),
                                number_format($valC, 2), number_format($valV, 2), estadoStock($p)];
                }
                Exportar::generar('Inventario y valorización', $cab, $filas, 'inventario',
                    [['TOTAL', '', '', '', '', '', '', '', number_format($vC, 2), number_format($vV, 2), '']]);
            }

            if ($tipoR === 'movimientos') {
                [$desde, $hasta] = rangoFechas();
                $stmt = $db->prepare('SELECT m.*, p.codigo, p.nombre FROM movimientos m JOIN productos p ON p.id=m.producto_id
                                      WHERE m.fecha BETWEEN ? AND ? ORDER BY m.fecha, m.id');
                $stmt->execute([$desde, $hasta]);
                $filas = array_map(fn($m) => [formatoFecha($m['fecha']), $m['tipo'], "{$m['codigo']} — {$m['nombre']}",
                            (($m['cantidad'] < 0 ? '' : '+') . formatoCantidad((float) $m['cantidad'])),
                            $m['motivo'], $m['referencia'], $m['usuario']], $stmt->fetchAll());
                Exportar::generar('Movimientos de inventario ' . $desde . ' al ' . $hasta,
                    ['Fecha', 'Tipo', 'Producto', 'Cantidad', 'Motivo', 'Referencia', 'Usuario'], $filas,
                    "movimientos_{$desde}_{$hasta}");
            }

            if ($tipoR === 'caja') {
                [$desde, $hasta] = rangoFechas();
                $stmt = $db->prepare('SELECT * FROM caja WHERE fecha BETWEEN ? AND ? ORDER BY fecha, id');
                $stmt->execute([$desde, $hasta]);
                $filas = [];
                $tIn = $tEg = 0.0;
                foreach ($stmt as $r) {
                    $signo = $r['tipo'] === 'ingreso' ? '+' : '-';
                    $filas[] = [formatoFecha($r['fecha']), $r['tipo'], $r['medio'], $r['concepto'],
                                $signo . number_format((float) $r['monto'], 2), $r['referencia']];
                    if ($r['tipo'] === 'ingreso') $tIn += (float) $r['monto']; else $tEg += (float) $r['monto'];
                }
                Exportar::generar('Movimiento de caja ' . $desde . ' al ' . $hasta,
                    ['Fecha', 'Tipo', 'Medio', 'Concepto', 'Monto', 'Referencia'], $filas, "caja_{$desde}_{$hasta}",
                    [['INGRESOS', '', '', '', number_format($tIn, 2), ''],
                     ['EGRESOS',  '', '', '', number_format($tEg, 2), ''],
                     ['SALDO',    '', '', '', number_format($tIn - $tEg, 2), '']]);
            }

            setMensaje('warning', 'Tipo de reporte no válido.');
            redireccionar('index.php?vista=reportes');
            break;

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