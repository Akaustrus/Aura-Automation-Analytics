<?php
/**
 * Sistema ERP para medianas empresas — Aura Automation & Analytics
 * Router principal (independiente del ERP de incidencias).
 *
 * Entrar desde: http://localhost:8005/index.php  (requiere login)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario = $_SESSION['med_usuario'] ?? null;
if (!$usuario) {
    redireccionar('login.php');
}

$db = MedDB::conexion();

/* ------------------------------------------------------------------ */
/*  Helpers locales                                                    */
/* ------------------------------------------------------------------ */

/** Devuelve el stock de un producto en un almacén. */
function stockEn(PDO $db, int $prod, int $alm): float
{
    $stmt = $db->prepare('SELECT cantidad FROM stock WHERE producto_id=? AND almacen_id=?');
    $stmt->execute([$prod, $alm]);
    $v = $stmt->fetchColumn();
    return $v === false ? 0.0 : (float) $v;
}

/**
 * Aplica un movimiento al kardex (cantidad firmada: + entrada/− salida).
 * Devuelve false si el stock quedaría negativo.
 */
function aplicarMovimiento(PDO $db, string $fecha, int $prod, int $alm, string $tipo, float $delta, string $motivo, string $ref): bool
{
    $actual = stockEn($db, $prod, $alm);
    $nuevo  = $actual + $delta;
    if ($nuevo < -0.0005) return false;

    $db->prepare('INSERT INTO stock (producto_id, almacen_id, cantidad) VALUES (?,?,0)
                  ON DUPLICATE KEY UPDATE producto_id = VALUES(producto_id)')
       ->execute([$prod, $alm]);

    $db->prepare('UPDATE stock SET cantidad = ? WHERE producto_id = ? AND almacen_id = ?')
       ->execute([$nuevo, $prod, $alm]);
    $db->prepare('INSERT INTO movimientos (fecha, producto_id, almacen_id, tipo, cantidad, motivo, referencia, usuario)
                  VALUES (?,?,?,?,?,?,?,?)')
       ->execute([$fecha, $prod, $alm, $tipo, $delta, $motivo, $ref, $_SESSION['med_usuario']['usuario'] ?? 'sistema']);
    return true;
}

/** Parsea ítems del formulario (producto/desc/cant/precio). Puede cortar el guardado. */
function leerItems(array $tamanos = []): array
{
    $desc = $_POST['item_desc'] ?? [];
    $cant = $_POST['item_cant'] ?? [];
    $prec = $_POST['item_precio'] ?? [];
    $prod = $_POST['item_prod_id'] ?? [];
    $items = [];
    for ($i = 0; $i < count($desc); $i++) {
        $d = trim((string) ($desc[$i] ?? ''));
        $c = (float) ($cant[$i] ?? 0);
        $p = (float) ($prec[$i] ?? 0);
        if ($d === '' || $c <= 0) continue;
        $items[] = [
            'producto_id' => (int) ($prod[$i] ?? 0),
            'descripcion' => $d,
            'cantidad'    => $c,
            'precio'      => $p,
            'subtotal'    => round($c * $p, 2),
        ];
    }
    return $items;
}

/* ------------------------------------------------------------------ */
/*  Acciones POST / GET (terminan antes de generar HTML)                */
/* ------------------------------------------------------------------ */

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action !== '') {
    if (!verificarCsrf() && $action !== 'exportar') { /* verificarCsrf ya muere si falla */ }

    switch ($action) {

        /* ================== CLIENTES ================== */
        case 'guardar_cliente':
            if (!puede('clientes', $usuario)) { setMensaje('danger', 'No tiene permisos para esta operación.'); break; }
            verificarCsrf();
            $id          = (int) ($_POST['id'] ?? 0);
            $tipoDoc     = in_array($_POST['tipo_doc'] ?? '', ['RUC', 'DNI'], true) ? $_POST['tipo_doc'] : 'RUC';
            $rucDni      = trim($_POST['ruc_dni'] ?? '');
            $razonSocial = trim($_POST['razon_social'] ?? '');
            $direccion   = trim($_POST['direccion'] ?? '');
            $correo      = trim($_POST['correo'] ?? '');
            $telefono    = trim($_POST['telefono'] ?? '');
            $limite      = (float) ($_POST['limite_credito'] ?? 0);
            $activo      = isset($_POST['activo']) ? 1 : 0;

            if ($rucDni === '' || $razonSocial === '') {
                setMensaje('danger', 'El documento y la razón social son obligatorios.');
                break;
            }
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM clientes WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('UPDATE clientes SET tipo_doc=:td,ruc_dni=:r,razon_social=:rs,direccion=:d,correo=:c,telefono=:t,limite_credito=:lc,activo=:a WHERE id=:id')
                   ->execute([':td' => $tipoDoc, ':r' => $rucDni, ':rs' => $razonSocial, ':d' => $direccion, ':c' => $correo, ':t' => $telefono, ':lc' => $limite, ':a' => $activo, ':id' => $id]);
                if ($ant) registrarAuditoria($db, 'clientes', $id, 'editar', "Cliente {$razonSocial}", $ant, ['ruc_dni' => $rucDni, 'limite_credito' => $limite]);
                setMensaje('success', 'Cliente actualizado.');
            } else {
                try {
                    $db->prepare('INSERT INTO clientes (tipo_doc,ruc_dni,razon_social,direccion,correo,telefono,limite_credito,activo)
                                  VALUES (:td,:r,:rs,:d,:c,:t,:lc,:a)')
                       ->execute([':td' => $tipoDoc, ':r' => $rucDni, ':rs' => $razonSocial, ':d' => $direccion, ':c' => $correo, ':t' => $telefono, ':lc' => $limite, ':a' => $activo]);
                    $nuevoId = (int) $db->lastInsertId();
                    registrarAuditoria($db, 'clientes', $nuevoId, 'crear', "Cliente {$razonSocial}", [], ['ruc_dni' => $rucDni]);
                    setMensaje('success', 'Cliente registrado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'Ya existe un cliente con ese documento.');
                }
            }
            redireccionar('index.php?vista=clientes');

        case 'eliminar_cliente':
            if (!puede('clientes', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM clientes WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                try {
                    $db->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
                    if ($ant) registrarAuditoria($db, 'clientes', $id, 'eliminar', "Cliente {$ant['razon_social']}", $ant);
                    setMensaje('success', 'Cliente eliminado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'El cliente tiene documentos asociados; no se puede eliminar.');
                }
            }
            redireccionar('index.php?vista=clientes');

        /* ================== PROVEEDORES ================== */
        case 'guardar_proveedor':
            if (!puede('proveedores', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id          = (int) ($_POST['id'] ?? 0);
            $tipoDoc     = in_array($_POST['tipo_doc'] ?? '', ['RUC', 'DNI'], true) ? $_POST['tipo_doc'] : 'RUC';
            $rucDni      = trim($_POST['ruc_dni'] ?? '');
            $razonSocial = trim($_POST['razon_social'] ?? '');
            $direccion   = trim($_POST['direccion'] ?? '');
            $correo      = trim($_POST['correo'] ?? '');
            $telefono    = trim($_POST['telefono'] ?? '');
            $plazo       = (int) ($_POST['plazo_credito'] ?? 0);
            $activo      = isset($_POST['activo']) ? 1 : 0;

            if ($rucDni === '' || $razonSocial === '') {
                setMensaje('danger', 'El documento y la razón social son obligatorios.');
                break;
            }
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM proveedores WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('UPDATE proveedores SET tipo_doc=:td,ruc_dni=:r,razon_social=:rs,direccion=:d,correo=:c,telefono=:t,plazo_credito=:pc,activo=:a WHERE id=:id')
                   ->execute([':td' => $tipoDoc, ':r' => $rucDni, ':rs' => $razonSocial, ':d' => $direccion, ':c' => $correo, ':t' => $telefono, ':pc' => $plazo, ':a' => $activo, ':id' => $id]);
                if ($ant) registrarAuditoria($db, 'proveedores', $id, 'editar', "Proveedor {$razonSocial}", $ant, ['ruc_dni' => $rucDni]);
                setMensaje('success', 'Proveedor actualizado.');
            } else {
                try {
                    $db->prepare('INSERT INTO proveedores (tipo_doc,ruc_dni,razon_social,direccion,correo,telefono,plazo_credito,activo)
                                  VALUES (:td,:r,:rs,:d,:c,:t,:pc,:a)')
                       ->execute([':td' => $tipoDoc, ':r' => $rucDni, ':rs' => $razonSocial, ':d' => $direccion, ':c' => $correo, ':t' => $telefono, ':pc' => $plazo, ':a' => $activo]);
                    $nuevoId = (int) $db->lastInsertId();
                    registrarAuditoria($db, 'proveedores', $nuevoId, 'crear', "Proveedor {$razonSocial}", [], ['ruc_dni' => $rucDni]);
                    setMensaje('success', 'Proveedor registrado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'Ya existe un proveedor con ese documento.');
                }
            }
            redireccionar('index.php?vista=proveedores');

        case 'eliminar_proveedor':
            if (!puede('proveedores', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
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

        /* ================== CATEGORÍAS ================== */
        case 'guardar_categoria':
            if (!puede('categorias', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre !== '') {
                try {
                    $db->prepare('INSERT INTO categorias (nombre) VALUES (?)')->execute([$nombre]);
                    $nuevoId = (int) $db->lastInsertId();
                    registrarAuditoria($db, 'categorias', $nuevoId, 'crear', "Categoría {$nombre}");
                    setMensaje('success', 'Categoría registrada.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'Ya existe una categoría con ese nombre.');
                }
            }
            redireccionar('index.php?vista=categorias');

        case 'eliminar_categoria':
            if (!puede('categorias', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $db->prepare('DELETE FROM categorias WHERE id=?')->execute([$id]);
                    setMensaje('success', 'Categoría eliminada.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'La categoría tiene productos asociados.');
                }
            }
            redireccionar('index.php?vista=categorias');

        /* ================== PRODUCTOS ================== */
        case 'guardar_producto':
            if (!puede('productos', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id          = (int) ($_POST['id'] ?? 0);
            $codigo      = trim($_POST['codigo'] ?? '');
            $nombre      = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $categoriaId = (int) ($_POST['categoria_id'] ?? 0) ?: null;
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
                $db->prepare('UPDATE productos SET codigo=:c,nombre=:n,descripcion=:d,categoria_id=:ca,unidad=:u,
                              controla_stock=:cs,stock_minimo=:min,precio_compra=:pc,precio_venta=:pv,activo=:a WHERE id=:id')
                   ->execute([':c' => $codigo, ':n' => $nombre, ':d' => $descripcion, ':ca' => $categoriaId, ':u' => $unidad,
                              ':cs' => $controla, ':min' => $minimo, ':pc' => $pCompra, ':pv' => $pVenta, ':a' => $activo, ':id' => $id]);
                if ($ant) registrarAuditoria($db, 'productos', $id, 'editar', "Producto {$codigo} - {$nombre}", $ant, ['precio_venta' => $pVenta, 'stock_minimo' => $minimo]);
                setMensaje('success', 'Producto actualizado.');
            } else {
                try {
                    $db->prepare('INSERT INTO productos (codigo,nombre,descripcion,categoria_id,unidad,controla_stock,stock_minimo,precio_compra,precio_venta,activo)
                                  VALUES (:c,:n,:d,:ca,:u,:cs,:min,:pc,:pv,:a)')
                       ->execute([':c' => $codigo, ':n' => $nombre, ':d' => $descripcion, ':ca' => $categoriaId, ':u' => $unidad,
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
            if (!puede('productos', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
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

        /* ================== ALMACENES ================== */
        case 'guardar_almacen':
            if (!puede('almacenes', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id        = (int) ($_POST['id'] ?? 0);
            $nombre    = trim($_POST['nombre'] ?? '');
            $ubicacion = trim($_POST['ubicacion'] ?? '');
            $estado    = isset($_POST['estado']) ? 1 : 0;
            if ($nombre === '') {
                setMensaje('danger', 'El nombre del almacén es obligatorio.');
                break;
            }
            if ($id > 0) {
                $db->prepare('UPDATE almacenes SET nombre=?, ubicacion=?, estado=? WHERE id=?')
                   ->execute([$nombre, $ubicacion, $estado, $id]);
                registrarAuditoria($db, 'almacenes', $id, 'editar', "Almacén {$nombre}");
                setMensaje('success', 'Almacén actualizado.');
            } else {
                $db->prepare('INSERT INTO almacenes (nombre, ubicacion, estado) VALUES (?,?,?)')
                   ->execute([$nombre, $ubicacion, $estado]);
                $nuevoId = (int) $db->lastInsertId();
                registrarAuditoria($db, 'almacenes', $nuevoId, 'crear', "Almacén {$nombre}");
                setMensaje('success', 'Almacén registrado.');
            }
            redireccionar('index.php?vista=almacenes');

        case 'eliminar_almacen':
            if (!puede('almacenes', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $db->prepare('DELETE FROM almacenes WHERE id=?')->execute([$id]);
                    setMensaje('success', 'Almacén eliminado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'El almacén tiene stock o movimientos asociados; no se puede eliminar.');
                }
            }
            redireccionar('index.php?vista=almacenes');

        /* ================== INVENTARIO ================== */
        case 'registrar_movimiento':
            if (!puede('inventario', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $productoId = (int) ($_POST['producto_id'] ?? 0);
            $almacenId  = (int) ($_POST['almacen_id'] ?? 0);
            $tipo       = in_array($_POST['tipo'] ?? '', ['entrada', 'salida', 'ajuste'], true) ? $_POST['tipo'] : 'entrada';
            $cantidad   = (float) ($_POST['cantidad'] ?? 0);
            $motivo     = trim($_POST['motivo'] ?? '');
            $fecha      = $_POST['fecha'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');

            if ($productoId <= 0 || $almacenId <= 0 || $motivo === '') {
                setMensaje('danger', 'Seleccione producto, almacén, motivo y cantidad.');
                break;
            }
            $stmt = $db->prepare('SELECT * FROM productos WHERE id=?'); $stmt->execute([$productoId]);
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
            $actual = stockEn($db, $productoId, $almacenId);
            if (aplicarMovimiento($db, $fecha, $productoId, $almacenId, $tipo, $delta, $motivo, '')) {
                registrarAuditoria($db, 'movimientos', $productoId, 'crear',
                    "$tipo: {$p['codigo']} {$p['nombre']} ({formatoCantidad($delta)}) — {$motivo}",
                    ['stock' => $actual], ['stock' => $actual + $delta]);
                setMensaje('success', 'Movimiento registrado. Nuevo stock: ' . formatoCantidad($actual + $delta));
            } else {
                setMensaje('danger', 'El stock no puede quedar en negativo (actual: ' . formatoCantidad($actual) . ').');
            }
            redireccionar('index.php?vista=inventario');

        case 'traspaso':
            if (!puede('inventario', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $productoId = (int) ($_POST['producto_id'] ?? 0);
            $origen     = (int) ($_POST['almacen_origen'] ?? 0);
            $destino    = (int) ($_POST['almacen_destino'] ?? 0);
            $cantidad   = (float) ($_POST['cantidad'] ?? 0);
            $fecha      = $_POST['fecha'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');

            if ($productoId <= 0 || $origen <= 0 || $destino <= 0 || $origen === $destino || $cantidad <= 0) {
                setMensaje('danger', 'Datos inválidos para el traspaso.');
                break;
            }
            $disponible = stockEn($db, $productoId, $origen);
            if ($disponible + 0.0005 < $cantidad) {
                setMensaje('danger', 'Stock insuficiente en el almacén de origen (disponible: ' . formatoCantidad($disponible) . ').');
                redireccionar('index.php?vista=inventario');
            }
            $db->beginTransaction();
            try {
                $ok1 = aplicarMovimiento($db, $fecha, $productoId, $origen, 'traslado', -$cantidad,
                    'Traspaso hacia ' . $destino, 'TRA');
                $ok2 = aplicarMovimiento($db, $fecha, $productoId, $destino, 'traslado', $cantidad,
                    'Traspaso desde ' . $origen, 'TRA');
                if (!$ok1 || !$ok2) {
                    $db->rollBack();
                    setMensaje('danger', 'No se pudo completar el traspaso (stock insuficiente).');
                } else {
                    $db->commit();
                    registrarAuditoria($db, 'movimientos', $productoId, 'crear',
                        "Traspaso {$productoId}: {$origen} → {$destino} ({formatoCantidad($cantidad)})");
                    setMensaje('success', 'Traspaso realizado (' . formatoCantidad($cantidad) . ' unidades).');
                }
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                setMensaje('danger', 'Error en el traspaso: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=inventario');

        /* ================== COTIZACIONES ================== */
        case 'guardar_cotizacion':
            if (!puede('cotizaciones', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $fecha       = $_POST['fecha'] ?? date('Y-m-d');
            $validez     = (int) ($_POST['validez'] ?? 7);
            $clienteId   = (int) ($_POST['cliente_id'] ?? 0);
            $condicion   = in_array($_POST['condicion'] ?? '', ['contado', 'credito'], true) ? $_POST['condicion'] : 'contado';
            $observ      = trim($_POST['observaciones'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $clienteId <= 0) {
                setMensaje('danger', 'Fecha o cliente no válidos.');
                break;
            }
            $items = leerItems();
            if (!$items) { setMensaje('danger', 'La cotización debe tener al menos un ítem.'); break; }

            $base = array_sum(array_column($items, 'subtotal'));
            [$subtotal, $igv, $total] = importesConIgv($db, $base);

            $db->beginTransaction();
            try {
                $numero = siguienteNumero($db, 'cotizaciones');
                $db->prepare('INSERT INTO cotizaciones (numero,fecha,validez,cliente_id,condicion,subtotal,igv,total,observaciones,estado)
                              VALUES (?,?,?,?,?,?,?,?,?,"vigente")')
                   ->execute([$numero, $fecha, $validez, $clienteId, $condicion, $subtotal, $igv, $total, $observ]);
                $cotId = (int) $db->lastInsertId();
                $stmtD = $db->prepare('INSERT INTO cotizacion_detalle (cotizacion_id,producto_id,descripcion,cantidad,precio_unitario,subtotal)
                                       VALUES (?,?,?,?,?,?)');
                foreach ($items as $it) {
                    $stmtD->execute([$cotId, $it['producto_id'] ?: null, $it['descripcion'], $it['cantidad'], $it['precio'], $it['subtotal']]);
                }
                registrarAuditoria($db, 'cotizaciones', $cotId, 'crear',
                    'Cotización ' . numeroCotizacion($numero) . " por " . formatoMoneda($total), [], ['cliente_id' => $clienteId]);
                $db->commit();
                setMensaje('success', 'Cotización ' . numeroCotizacion($numero) . ' registrada.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al registrar la cotización: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=cotizaciones');

        case 'anular_cotizacion':
            if (!puede('cotizaciones', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $db->prepare('UPDATE cotizaciones SET estado="anulada" WHERE id=? AND estado != "aceptada"')->execute([$id]);
            $c = $db->prepare('SELECT * FROM cotizaciones WHERE id=?'); $c->execute([$id]); $c = $c->fetch();
            if ($c) {
                registrarAuditoria($db, 'cotizaciones', $id, 'editar', 'Cotización ' . numeroCotizacion((int) $c['numero']) . ' anulada.', $c, ['estado' => 'anulada']);
                setMensaje('success', 'Cotización anulada.');
            } else {
                setMensaje('warning', 'La cotización no se pudo anular (ya fue aceptada o no existe).');
            }
            redireccionar('index.php?vista=cotizaciones');

        case 'aceptar_cotizacion':
            if (!puede('ventas', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $cot = $db->prepare('SELECT * FROM cotizaciones WHERE id=? AND estado="vigente"'); $cot->execute([$id]);
            $cot = $cot->fetch();
            if (!$cot) { setMensaje('danger', 'Cotización no vigente.'); break; }

            $detalles = $db->prepare('SELECT * FROM cotizacion_detalle WHERE cotizacion_id=? ORDER BY id'); $detalles->execute([$id]);
            $detalles = $detalles->fetchAll();
            if (!$detalles) { setMensaje('danger', 'La cotización no tiene ítems.'); break; }

            $almacenId = (int) ($_POST['almacen_id'] ?? 1);
            $db->beginTransaction();
            try {
                foreach ($detalles as $d) {
                    if ($d['producto_id']) {
                        $pr = $db->prepare('SELECT * FROM productos WHERE id=?'); $pr->execute([$d['producto_id']]); $pr = $pr->fetch();
                        if ($pr && $pr['controla_stock'] && stockEn($db, (int) $d['producto_id'], $almacenId) + 0.0005 < (float) $d['cantidad']) {
                            $db->rollBack();
                            setMensaje('danger', 'Stock insuficiente de ' . $pr['nombre'] . ' en el almacén seleccionado.');
                            redireccionar('index.php?vista=cotizaciones');
                        }
                    }
                }
                $numero = siguienteNumero($db, 'ventas');
                $db->prepare('INSERT INTO ventas (numero,fecha,cliente_id,tipo_doc,condicion,almacen_id,usuario_id,subtotal,igv,total,observaciones,estado)
                              VALUES (?,?,?,"factura",?,?,?,?,?,?,?,"emitida")')
                   ->execute([$numero, date('Y-m-d'), $cot['cliente_id'], $cot['condicion'], $almacenId, $usuario['id'],
                              $cot['subtotal'], $cot['igv'], $cot['total'], 'Generada desde ' . numeroCotizacion((int) $cot['numero'])]);
                $ventaId = (int) $db->lastInsertId();

                $stmtD = $db->prepare('INSERT INTO venta_detalle (venta_id,producto_id,descripcion,cantidad,precio_unitario,subtotal)
                                       VALUES (?,?,?,?,?,?)');
                foreach ($detalles as $d) {
                    $stmtD->execute([$ventaId, $d['producto_id'] ?: null, $d['descripcion'], $d['cantidad'], $d['precio_unitario'], $d['subtotal']]);
                    if ($d['producto_id']) {
                        $pr = $db->prepare('SELECT controla_stock FROM productos WHERE id=?'); $pr->execute([$d['producto_id']]);
                        if ($pr->fetchColumn() == 1) {
                            if (!aplicarMovimiento($db, date('Y-m-d'), (int) $d['producto_id'], $almacenId,
                                    'salida', -$d['cantidad'], 'Salida por venta', numeroVenta($numero))) {
                                $db->rollBack();
                                setMensaje('danger', 'Stock insuficiente al convertir la cotización.');
                                redireccionar('index.php?vista=cotizaciones');
                            }
                        }
                    }
                }
                $db->prepare('UPDATE cotizaciones SET estado="aceptada", venta_id=? WHERE id=?')->execute([$ventaId, $id]);
                registrarAuditoria($db, 'cotizaciones', $id, 'editar',
                    'Cotización ' . numeroCotizacion((int) $cot['numero']) . ' aceptada → venta ' . numeroVenta($numero), $cot, ['estado' => 'aceptada']);
                $db->commit();
                setMensaje('success', 'Cotización aceptada. Venta ' . numeroVenta($numero) . ' generada.');
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                setMensaje('danger', 'Error al aceptar: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=cotizaciones');

        /* ================== VENTAS ================== */
        case 'guardar_venta':
            if (!puede('ventas', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $fecha     = $_POST['fecha'] ?? date('Y-m-d');
            $clienteId = (int) ($_POST['cliente_id'] ?? 0);
            $tipoDoc   = in_array($_POST['tipo_doc'] ?? '', ['factura', 'boleta'], true) ? $_POST['tipo_doc'] : 'factura';
            $condicion = in_array($_POST['condicion'] ?? '', ['contado', 'credito'], true) ? $_POST['condicion'] : 'contado';
            $almacenId = (int) ($_POST['almacen_id'] ?? 0);
            $observ    = trim($_POST['observaciones'] ?? '');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $clienteId <= 0 || $almacenId <= 0) {
                setMensaje('danger', 'Fecha, cliente o almacén no válidos.');
                break;
            }
            $items = leerItems();
            if (!$items) { setMensaje('danger', 'La venta debe tener al menos un ítem válido.'); break; }

            $base = array_sum(array_column($items, 'subtotal'));
            [$subtotal, $igv, $total] = importesConIgv($db, $base);

            $db->beginTransaction();
            try {
                foreach ($items as $it) {
                    if ($it['producto_id']) {
                        $pr = $db->prepare('SELECT controla_stock FROM productos WHERE id=?'); $pr->execute([$it['producto_id']]);
                        if ($pr->fetchColumn() == 1 && stockEn($db, $it['producto_id'], $almacenId) + 0.0005 < $it['cantidad']) {
                            $db->rollBack();
                            setMensaje('danger', 'Stock insuficiente en el almacén (producto ajustado en la venta).');
                            redireccionar('index.php?vista=ventas');
                        }
                    }
                }

                $numero = siguienteNumero($db, 'ventas');
                $db->prepare('INSERT INTO ventas (numero,fecha,cliente_id,tipo_doc,condicion,almacen_id,usuario_id,subtotal,igv,total,observaciones,estado)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?,"emitida")')
                   ->execute([$numero, $fecha, $clienteId, $tipoDoc, $condicion, $almacenId, $usuario['id'], $subtotal, $igv, $total, $observ]);
                $ventaId = (int) $db->lastInsertId();

                $stmtD = $db->prepare('INSERT INTO venta_detalle (venta_id,producto_id,descripcion,cantidad,precio_unitario,subtotal)
                                       VALUES (?,?,?,?,?,?)');
                foreach ($items as $it) {
                    $stmtD->execute([$ventaId, $it['producto_id'] ?: null, $it['descripcion'], $it['cantidad'], $it['precio'], $it['subtotal']]);
                    if ($it['producto_id']) {
                        $pr = $db->prepare('SELECT controla_stock FROM productos WHERE id=?'); $pr->execute([$it['producto_id']]);
                        if ($pr->fetchColumn() == 1) {
                            aplicarMovimiento($db, $fecha, $it['producto_id'], $almacenId,
                                'salida', -$it['cantidad'], 'Salida por venta', numeroVenta($numero));
                        }
                    }
                }
                registrarAuditoria($db, 'ventas', $ventaId, 'crear',
                    'Venta ' . numeroVenta($numero) . " por " . formatoMoneda($total) . " ($tipoDoc / $condicion)", [], ['total' => $total, 'almacen_id' => $almacenId]);
                $db->commit();
                setMensaje('success', 'Venta ' . numeroVenta($numero) . ' registrada. Stock actualizado.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al registrar la venta: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=ventas');

        case 'anular_venta':
            if (!puede('ventas', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $v = $db->prepare('SELECT * FROM ventas WHERE id=?'); $v->execute([$id]); $v = $v->fetch();
            if (!$v) { setMensaje('danger', 'Venta no encontrada.'); break; }
            if ($v['estado'] === 'anulada') { setMensaje('warning', 'La venta ya está anulada.'); break; }

            $db->beginTransaction();
            try {
                $stmtD = $db->prepare('SELECT d.*, p.controla_stock FROM venta_detalle d LEFT JOIN productos p ON p.id=d.producto_id WHERE d.venta_id=?');
                $stmtD->execute([$id]);
                foreach ($stmtD->fetchAll() as $d) {
                    if ($d['producto_id'] && $d['controla_stock']) {
                        aplicarMovimiento($db, $v['fecha'], (int) $d['producto_id'], (int) $v['almacen_id'],
                            'entrada', $d['cantidad'], 'Anulación de venta', numeroVenta((int) $v['numero']));
                    }
                }
                $db->prepare('UPDATE ventas SET estado="anulada" WHERE id=?')->execute([$id]);
                registrarAuditoria($db, 'ventas', $id, 'editar',
                    'Venta ' . numeroVenta((int) $v['numero']) . ' anulada. Stock restaurado.', $v, ['estado' => 'anulada']);
                $db->commit();
                setMensaje('success', 'Venta anulada. Stock restaurado.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al anular: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=ventas');

        /* ================== CxC (cobros) ================== */
        case 'guardar_cobro':
            if (!puede('cxc', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $fecha    = $_POST['fecha'] ?? date('Y-m-d');
            $ventaId  = (int) ($_POST['venta_id'] ?? 0);
            $monto    = (float) ($_POST['monto'] ?? 0);
            $medio    = in_array($_POST['medio'] ?? '', mediosPago(), true) ? $_POST['medio'] : 'efectivo';
            $refer    = trim($_POST['referencia'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');

            if ($ventaId <= 0 || $monto <= 0) { setMensaje('danger', 'Seleccione la venta y un monto mayor a cero.'); break; }
            $v = $db->prepare('SELECT * FROM ventas WHERE id=? AND condicion="credito" AND estado="emitida"'); $v->execute([$ventaId]);
            $v = $v->fetch();
            if (!$v) { setMensaje('danger', 'La venta no es un crédito emitido.'); break; }

            $stmtPagado = $db->prepare('SELECT COALESCE(SUM(monto),0) FROM venta_pagos WHERE venta_id=?');
            $stmtPagado->execute([$ventaId]);
            $saldo = (float) $v['total'] - (float) $stmtPagado->fetchColumn();
            if ($monto > $saldo + 0.005) {
                setMensaje('danger', 'El cobro excede el saldo pendiente (' . formatoMoneda($saldo) . ').');
                break;
            }
            $db->prepare('INSERT INTO venta_pagos (fecha,venta_id,cliente_id,monto,medio,referencia,usuario)
                          VALUES (?,?,?,?,?,?,?)')
               ->execute([$fecha, $ventaId, $v['cliente_id'], $monto, $medio, $refer, $usuario['usuario']]);
            $cobroId = (int) $db->lastInsertId();
            registrarAuditoria($db, 'venta_pagos', $cobroId, 'crear',
                'Cobro ' . formatoMoneda($monto) . ' a ' . numeroVenta((int) $v['numero']), [], ['monto' => $monto, 'medio' => $medio]);
            setMensaje('success', 'Cobro registrado (' . formatoMoneda($monto) . '). Saldo: ' . formatoMoneda($saldo - $monto));
            redireccionar('index.php?vista=cxc');

        case 'eliminar_cobro':
            if (!puede('cxc', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $cp = $db->prepare('SELECT * FROM venta_pagos WHERE id=?'); $cp->execute([$id]);
            if ($cp = $cp->fetch()) {
                $db->prepare('DELETE FROM venta_pagos WHERE id=?')->execute([$id]);
                registrarAuditoria($db, 'venta_pagos', $id, 'eliminar',
                    'Eliminación de cobro ' . formatoMoneda((float) $cp['monto']), $cp);
                setMensaje('success', 'Cobro eliminado.');
            }
            redireccionar('index.php?vista=cxc');

        /* ================== ÓRDENES DE COMPRA ================== */
        case 'guardar_orden_compra':
            if (!puede('ordenes_compra', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $fecha       = $_POST['fecha'] ?? date('Y-m-d');
            $proveedorId = (int) ($_POST['proveedor_id'] ?? 0);
            $almacenId   = (int) ($_POST['almacen_id'] ?? 0);
            $observ      = trim($_POST['observaciones'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $proveedorId <= 0 || $almacenId <= 0) {
                setMensaje('danger', 'Fecha, proveedor o almacén no válidos.');
                break;
            }
            $items = leerItems();
            if (!$items) { setMensaje('danger', 'La orden debe tener al menos un ítem con producto.'); break; }
            if (array_filter($items, fn($i) => $i['producto_id'] <= 0)) {
                setMensaje('danger', 'Todos los ítems de una orden deben ser productos del catálogo.');
                break;
            }

            $db->beginTransaction();
            try {
                $numero = siguienteNumero($db, 'ordenes_compra');
                $db->prepare('INSERT INTO ordenes_compra (numero,fecha,proveedor_id,almacen_id,observaciones,estado)
                              VALUES (?,?,?,?,?,"pendiente")')
                   ->execute([$numero, $fecha, $proveedorId, $almacenId, $observ]);
                $ocId = (int) $db->lastInsertId();
                $stmtD = $db->prepare('INSERT INTO orden_compra_detalle (orden_id,producto_id,descripcion,cantidad,precio_unitario,subtotal)
                                       VALUES (?,?,?,?,?,?)');
                foreach ($items as $it) {
                    $stmtD->execute([$ocId, $it['producto_id'], $it['descripcion'], $it['cantidad'], $it['precio'], $it['subtotal']]);
                }
                registrarAuditoria($db, 'ordenes_compra', $ocId, 'crear',
                    'Orden ' . numeroOrden($numero) . ' a ' . $proveedorId, [], ['items' => count($items), 'almacen_id' => $almacenId]);
                $db->commit();
                setMensaje('success', 'Orden de compra ' . numeroOrden($numero) . ' registrada.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al registrar la orden: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=ordenes_compra');

        case 'anular_orden_compra':
            if (!puede('ordenes_compra', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $db->prepare('UPDATE ordenes_compra SET estado="anulada" WHERE id=? AND estado="pendiente"')->execute([$id]);
            $oc = $db->prepare('SELECT * FROM ordenes_compra WHERE id=?'); $oc->execute([$id]); $oc = $oc->fetch();
            if ($oc) {
                registrarAuditoria($db, 'ordenes_compra', $id, 'editar', 'Orden ' . numeroOrden((int) $oc['numero']) . ' anulada.', $oc, ['estado' => 'anulada']);
                setMensaje('success', 'Orden de compra anulada.');
            } else {
                setMensaje('warning', 'Solo se pueden anular órdenes pendientes.');
            }
            redireccionar('index.php?vista=ordenes_compra');

        case 'recibir_orden_compra':
            if (!puede('compras', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $oc = $db->prepare('SELECT * FROM ordenes_compra WHERE id=? AND estado="pendiente"'); $oc->execute([$id]);
            $oc = $oc->fetch();
            if (!$oc) { setMensaje('danger', 'Orden no encontrada o no pendiente.'); break; }

            $detalles = $db->prepare('SELECT * FROM orden_compra_detalle WHERE orden_id=? ORDER BY id'); $detalles->execute([$id]);
            $detalles = $detalles->fetchAll();
            if (!$detalles) { setMensaje('danger', 'La orden no tiene ítems.'); break; }

            $base = array_sum(array_map(fn($d) => (float) $d['subtotal'], $detalles));
            [$subtotal, $igv, $total] = importesConIgv($db, $base);

            $db->beginTransaction();
            try {
                $numero = siguienteNumero($db, 'compras');
                $db->prepare('INSERT INTO compras (numero,fecha,proveedor_id,orden_compra_id,tipo_doc,condicion,almacen_id,usuario_id,subtotal,igv,total,observaciones,estado)
                              VALUES (?,?,?,?,"factura","credito",?,?,?,?,?,?,"emitida")')
                   ->execute([$numero, date('Y-m-d'), $oc['proveedor_id'], $id, $oc['almacen_id'], $usuario['id'], $subtotal, $igv, $total,
                              'Recibida desde ' . numeroOrden((int) $oc['numero'])]);
                $compraId = (int) $db->lastInsertId();

                $stmtD = $db->prepare('INSERT INTO compra_detalle (compra_id,producto_id,descripcion,cantidad,precio_unitario,subtotal)
                                       VALUES (?,?,?,?,?,?)');
                foreach ($detalles as $d) {
                    $stmtD->execute([$compraId, $d['producto_id'], $d['descripcion'], $d['cantidad'], $d['precio_unitario'], $d['subtotal']]);
                    $pr = $db->prepare('SELECT controla_stock FROM productos WHERE id=?'); $pr->execute([$d['producto_id']]);
                    if ($pr->fetchColumn() == 1) {
                        aplicarMovimiento($db, date('Y-m-d'), (int) $d['producto_id'], (int) $oc['almacen_id'],
                            'entrada', $d['cantidad'], 'Ingreso por compra', numeroCompra($numero));
                    }
                }
                $db->prepare('UPDATE ordenes_compra SET estado="recibida", compra_id=? WHERE id=?')->execute([$compraId, $id]);
                registrarAuditoria($db, 'compras', $compraId, 'crear',
                    'Compra ' . numeroCompra($numero) . " por " . formatoMoneda($total) . ' (desde ' . numeroOrden((int) $oc['numero']) . ')',
                    [], ['oc_id' => $id]);
                $db->commit();
                setMensaje('success', 'Orden recibida. Compra ' . numeroCompra($numero) . ' registrada y stock ingresado.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al recibir: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=ordenes_compra');

        /* ================== COMPRAS ================== */
        case 'guardar_compra':
            if (!puede('compras', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $fecha       = $_POST['fecha'] ?? date('Y-m-d');
            $proveedorId = (int) ($_POST['proveedor_id'] ?? 0);
            $tipoDoc     = in_array($_POST['tipo_doc'] ?? '', ['factura', 'boleta'], true) ? $_POST['tipo_doc'] : 'factura';
            $condicion   = in_array($_POST['condicion'] ?? '', ['contado', 'credito'], true) ? $_POST['condicion'] : 'contado';
            $almacenId   = (int) ($_POST['almacen_id'] ?? 0);
            $ordenId     = (int) ($_POST['orden_id'] ?? 0) ?: null;
            $observ      = trim($_POST['observaciones'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $proveedorId <= 0 || $almacenId <= 0) {
                setMensaje('danger', 'Fecha, proveedor o almacén no válidos.');
                break;
            }
            $items = leerItems();
            if (!$items || array_filter($items, fn($i) => $i['producto_id'] <= 0)) {
                setMensaje('danger', 'La compra debe tener ítems con producto del catálogo.');
                break;
            }

            $base = array_sum(array_column($items, 'subtotal'));
            [$subtotal, $igv, $total] = importesConIgv($db, $base);

            $db->beginTransaction();
            try {
                $numero = siguienteNumero($db, 'compras');
                $db->prepare('INSERT INTO compras (numero,fecha,proveedor_id,orden_compra_id,tipo_doc,condicion,almacen_id,usuario_id,subtotal,igv,total,observaciones,estado)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,"emitida")')
                   ->execute([$numero, $fecha, $proveedorId, $ordenId, $tipoDoc, $condicion, $almacenId, $usuario['id'], $subtotal, $igv, $total, $observ]);
                $compraId = (int) $db->lastInsertId();

                $stmtD = $db->prepare('INSERT INTO compra_detalle (compra_id,producto_id,descripcion,cantidad,precio_unitario,subtotal)
                                       VALUES (?,?,?,?,?,?)');
                foreach ($items as $it) {
                    $stmtD->execute([$compraId, $it['producto_id'], $it['descripcion'], $it['cantidad'], $it['precio'], $it['subtotal']]);
                    $pr = $db->prepare('SELECT controla_stock FROM productos WHERE id=?'); $pr->execute([$it['producto_id']]);
                    if ($pr->fetchColumn() == 1) {
                        aplicarMovimiento($db, $fecha, $it['producto_id'], $almacenId,
                            'entrada', $it['cantidad'], 'Ingreso por compra', numeroCompra($numero));
                    }
                }
                if ($ordenId) {
                    $db->prepare('UPDATE ordenes_compra SET estado="recibida", compra_id=? WHERE id=? AND estado="pendiente"')
                       ->execute([$compraId, $ordenId]);
                }
                registrarAuditoria($db, 'compras', $compraId, 'crear',
                    'Compra ' . numeroCompra($numero) . " por " . formatoMoneda($total), [], ['condicion' => $condicion, 'almacen_id' => $almacenId]);
                $db->commit();
                setMensaje('success', 'Compra ' . numeroCompra($numero) . ' registrada. Stock ingresado.');
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al registrar la compra: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=compras');

        case 'anular_compra':
            if (!puede('compras', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $c0 = $db->prepare('SELECT * FROM compras WHERE id=?'); $c0->execute([$id]); $c0 = $c0->fetch();
            if (!$c0) { setMensaje('danger', 'Compra no encontrada.'); break; }
            if ($c0['estado'] === 'anulada') { setMensaje('warning', 'La compra ya está anulada.'); break; }

            $db->beginTransaction();
            try {
                $stmtD = $db->prepare('SELECT d.*, p.controla_stock FROM compra_detalle d JOIN productos p ON p.id=d.producto_id WHERE d.compra_id=?');
                $stmtD->execute([$id]);
                foreach ($stmtD->fetchAll() as $d) {
                    if ($d['controla_stock']) {
                        aplicarMovimiento($db, $c0['fecha'], (int) $d['producto_id'], (int) $c0['almacen_id'],
                            'salida', -$d['cantidad'], 'Anulación de compra', numeroCompra((int) $c0['numero']));
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

        /* ================== CxP (pagos) ================== */
        case 'guardar_pago':
            if (!puede('cxp', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $fecha    = $_POST['fecha'] ?? date('Y-m-d');
            $compraId = (int) ($_POST['compra_id'] ?? 0);
            $monto    = (float) ($_POST['monto'] ?? 0);
            $medio    = in_array($_POST['medio'] ?? '', mediosPago(), true) ? $_POST['medio'] : 'efectivo';
            $refer    = trim($_POST['referencia'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');

            if ($compraId <= 0 || $monto <= 0) { setMensaje('danger', 'Seleccione la compra y un monto mayor a cero.'); break; }
            $c0 = $db->prepare('SELECT * FROM compras WHERE id=? AND condicion="credito" AND estado="emitida"'); $c0->execute([$compraId]);
            $c0 = $c0->fetch();
            if (!$c0) { setMensaje('danger', 'La compra no es un crédito emitido.'); break; }

            $pagado = $db->prepare('SELECT COALESCE(SUM(monto),0) FROM compra_pagos WHERE compra_id=?'); $pagado->execute([$compraId]);
            $saldo = (float) $c0['total'] - (float) $pagado->fetchColumn();
            if ($monto > $saldo + 0.005) {
                setMensaje('danger', 'El pago excede el saldo pendiente (' . formatoMoneda($saldo) . ').');
                break;
            }
            $db->prepare('INSERT INTO compra_pagos (fecha,compra_id,proveedor_id,monto,medio,referencia,usuario)
                          VALUES (?,?,?,?,?,?,?)')
               ->execute([$fecha, $compraId, $c0['proveedor_id'], $monto, $medio, $refer, $usuario['usuario']]);
            $pagoId = (int) $db->lastInsertId();
            registrarAuditoria($db, 'compra_pagos', $pagoId, 'crear',
                'Pago ' . formatoMoneda($monto) . ' a ' . numeroCompra((int) $c0['numero']), [], ['monto' => $monto, 'medio' => $medio]);
            setMensaje('success', 'Pago registrado (' . formatoMoneda($monto) . '). Saldo: ' . formatoMoneda($saldo - $monto));
            redireccionar('index.php?vista=cxp');

        case 'eliminar_pago':
            if (!puede('cxp', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $cp = $db->prepare('SELECT * FROM compra_pagos WHERE id=?'); $cp->execute([$id]);
            if ($cp = $cp->fetch()) {
                $db->prepare('DELETE FROM compra_pagos WHERE id=?')->execute([$id]);
                registrarAuditoria($db, 'compra_pagos', $id, 'eliminar',
                    'Eliminación de pago ' . formatoMoneda((float) $cp['monto']), $cp);
                setMensaje('success', 'Pago eliminado.');
            }
            redireccionar('index.php?vista=cxp');

        /* ================== CAJA ================== */
        case 'guardar_caja':
            if (!puede('caja', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
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
            $db->prepare('INSERT INTO caja (fecha,tipo_oper,concepto,medio,monto,referencia,usuario) VALUES (?,?,?,?,?,?,?)')
               ->execute([$fecha, $tipo, $concepto, $medio, $monto, $referencia, $usuario['usuario']]);
            $cajaId = (int) $db->lastInsertId();
            registrarAuditoria($db, 'caja', $cajaId, 'crear',
                ucfirst($tipo) . ' de caja: ' . $concepto . ' (' . formatoMoneda($monto) . ')', [], ['tipo' => $tipo, 'monto' => $monto]);
            setMensaje('success', ucfirst($tipo) . ' registrado en caja.');
            redireccionar('index.php?vista=caja');

        case 'eliminar_caja':
            if (!puede('caja', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM caja WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                if ($ant) {
                    $db->prepare('DELETE FROM caja WHERE id=?')->execute([$id]);
                    registrarAuditoria($db, 'caja', $id, 'eliminar',
                        'Eliminación ' . $ant['tipo_oper'] . ': ' . $ant['concepto'] . ' (' . formatoMoneda((float) $ant['monto']) . ')', $ant);
                    setMensaje('success', 'Movimiento de caja eliminado.');
                }
            }
            redireccionar('index.php?vista=caja');

        /* ================== USUARIOS ================== */
        case 'guardar_usuario':
            if (!puede('usuarios', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id      = (int) ($_POST['id'] ?? 0);
            $nameU   = trim($_POST['usuario'] ?? '');
            $nombre  = trim($_POST['nombre'] ?? '');
            $rol     = in_array($_POST['rol'] ?? '', rolesDisponibles(), true) ? $_POST['rol'] : 'ventas';
            $clave   = (string) ($_POST['clave'] ?? '');
            $estado  = isset($_POST['estado']) ? 1 : 0;
            if ($nameU === '' || $nombre === '') { setMensaje('danger', 'Usuario y nombre son obligatorios.'); break; }

            if ($id > 0) {
                if ($id === (int) $usuario['id'] && ($clave === '' || !password_needs_rehash($clave, PASSWORD_DEFAULT))) {
                    /* edición propia sin cambio de clave */ ;
                }
                $ant = $db->prepare('SELECT * FROM usuarios WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                if ($clave !== '') {
                    $db->prepare('UPDATE usuarios SET usuario=?,clave=?,nombre=?,rol=?,estado=? WHERE id=?')
                       ->execute([$nameU, password_hash($clave, PASSWORD_DEFAULT), $nombre, $rol, $estado, $id]);
                } else {
                    $db->prepare('UPDATE usuarios SET usuario=?,nombre=?,rol=?,estado=? WHERE id=?')
                       ->execute([$nameU, $nombre, $rol, $estado, $id]);
                }
                registrarAuditoria($db, 'usuarios', $id, 'editar', "Usuario {$nameU} ({$rol})", $ant, ['rol' => $rol]);
                setMensaje('success', 'Usuario actualizado.');
            } else {
                if ($clave === '') { setMensaje('danger', 'La contraseña es obligatoria al crear.'); break; }
                try {
                    $db->prepare('INSERT INTO usuarios (usuario,clave,nombre,rol,estado) VALUES (?,?,?,?,?)')
                       ->execute([$nameU, password_hash($clave, PASSWORD_DEFAULT), $nombre, $rol, $estado]);
                    $nuevoId = (int) $db->lastInsertId();
                    registrarAuditoria($db, 'usuarios', $nuevoId, 'crear', "Usuario {$nameU} ({$rol})");
                    setMensaje('success', 'Usuario creado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'Ya existe un usuario con ese nombre.');
                }
            }
            redireccionar('index.php?vista=usuarios');

        case 'eliminar_usuario':
            if (!puede('usuarios', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0 && $id !== (int) $usuario['id']) {
                $ant = $db->prepare('SELECT * FROM usuarios WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                if ($ant) {
                    $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
                    registrarAuditoria($db, 'usuarios', $id, 'eliminar', "Usuario {$ant['usuario']} eliminado.", $ant);
                    setMensaje('success', 'Usuario eliminado.');
                }
            } else {
                setMensaje('danger', 'No se puede eliminar al usuario conectado.');
            }
            redireccionar('index.php?vista=usuarios');

        /* ================== CONFIGURACIÓN ================== */
        case 'guardar_parametros':
            if (!puede('configuracion', $usuario)) { setMensaje('danger', 'No tiene permisos.'); break; }
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

        /* ================== EXPORTAR REPORTES ================== */
        case 'exportar':
            if (!puede('reportes', $usuario)) { die('Sin permisos'); }
            require_once __DIR__ . '/includes/Exportar.php';
            $tipoR = $_GET['tipo'] ?? $_POST['tipo'] ?? 'ventas';
            [$desde, $hasta] = rangoFechas();

            if ($tipoR === 'ventas') {
                $sql = 'SELECT v.*, cl.razon_social, a.nombre almacen FROM ventas v
                        JOIN clientes cl ON cl.id=v.cliente_id JOIN almacenes a ON a.id=v.almacen_id
                        WHERE v.fecha BETWEEN ? AND ?';
                $args = [$desde, $hasta];
                if (!empty($_GET['estado_f'])) { $sql .= ' AND v.estado=?'; $args[] = $_GET['estado_f']; }
                $filas = []; $tTotal = 0.0;
                $stmt = $db->prepare($sql . ' ORDER BY v.fecha, v.numero'); $stmt->execute($args);
                foreach ($stmt as $r) {
                    $filas[] = [numeroVenta((int) $r['numero']), formatoFecha($r['fecha']), $r['razon_social'],
                                strtoupper($r['tipo_doc']), ucfirst($r['condicion']), $r['almacen'],
                                number_format((float) $r['subtotal'], 2), number_format((float) $r['igv'], 2),
                                number_format((float) $r['total'], 2), $r['estado']];
                    if ($r['estado'] === 'emitida') $tTotal += (float) $r['total'];
                }
                Exportar::generar('Reporte de ventas ' . $desde . ' al ' . $hasta,
                    ['Número', 'Fecha', 'Cliente', 'Tipo', 'Condición', 'Almacén', 'Subtotal', 'IGV', 'Total', 'Estado'],
                    $filas, "ventas_{$desde}_{$hasta}",
                    [['TOTAL', '', '', '', '', '', '', '', number_format($tTotal, 2), '']]);
            }

            if ($tipoR === 'compras') {
                $sql = 'SELECT c.*, p.razon_social, a.nombre almacen FROM compras c
                        JOIN proveedores p ON p.id=c.proveedor_id JOIN almacenes a ON a.id=c.almacen_id
                        WHERE c.fecha BETWEEN ? AND ?';
                $args = [$desde, $hasta];
                if (!empty($_GET['estado_f'])) { $sql .= ' AND c.estado=?'; $args[] = $_GET['estado_f']; }
                $stmt = $db->prepare($sql . ' ORDER BY c.fecha, c.numero'); $stmt->execute($args);
                $filas = []; $tTotal = 0.0;
                foreach ($stmt as $r) {
                    $filas[] = [numeroCompra((int) $r['numero']), formatoFecha($r['fecha']), $r['razon_social'],
                                ucfirst($r['condicion']), $r['almacen'],
                                number_format((float) $r['subtotal'], 2), number_format((float) $r['igv'], 2),
                                number_format((float) $r['total'], 2), $r['estado']];
                    if ($r['estado'] === 'emitida') $tTotal += (float) $r['total'];
                }
                Exportar::generar('Reporte de compras ' . $desde . ' al ' . $hasta,
                    ['Número', 'Fecha', 'Proveedor', 'Condición', 'Almacén', 'Subtotal', 'IGV', 'Total', 'Estado'],
                    $filas, "compras_{$desde}_{$hasta}",
                    [['TOTAL', '', '', '', '', '', '', number_format($tTotal, 2), '']]);
            }

            if ($tipoR === 'cotizaciones') {
                $sql = 'SELECT c.*, cl.razon_social FROM cotizaciones c JOIN clientes cl ON cl.id=c.cliente_id
                        WHERE c.fecha BETWEEN ? AND ?';
                $args = [$desde, $hasta];
                if (!empty($_GET['estado_f'])) { $sql .= ' AND c.estado=?'; $args[] = $_GET['estado_f']; }
                $stmt = $db->prepare($sql . ' ORDER BY c.fecha, c.numero'); $stmt->execute($args);
                $filas = []; $tTotal = 0.0;
                foreach ($stmt as $r) {
                    $filas[] = [numeroCotizacion((int) $r['numero']), formatoFecha($r['fecha']), $r['razon_social'],
                                ucfirst($r['condicion']), number_format((float) $r['subtotal'], 2),
                                number_format((float) $r['igv'], 2), number_format((float) $r['total'], 2), $r['estado']];
                    if ($r['estado'] !== 'anulada') $tTotal += (float) $r['total'];
                }
                Exportar::generar('Reporte de cotizaciones ' . $desde . ' al ' . $hasta,
                    ['Número', 'Fecha', 'Cliente', 'Condición', 'Subtotal', 'IGV', 'Total', 'Estado'],
                    $filas, "cotizaciones_{$desde}_{$hasta}",
                    [['TOTAL', '', '', '', '', '', number_format($tTotal, 2), '']]);
            }

            if ($tipoR === 'inventario') {
                $cab = ['Código', 'Producto', 'Categoría', 'Unidad', 'Stock', 'Mínimo', 'P. Compra', 'P. Venta',
                        'Valor. compra', 'Valor. venta', 'Estado'];
                $filas = []; $vC = $vV = 0.0;
                foreach ($db->query('SELECT p.*, c.nombre categoria, COALESCE(s.stock_total,0) stock_total
                                     FROM productos p LEFT JOIN categorias c ON c.id=p.categoria_id
                                     LEFT JOIN (SELECT producto_id, SUM(cantidad) stock_total FROM stock GROUP BY producto_id) s ON s.producto_id=p.id
                                     ORDER BY p.nombre') as $p) {
                    $valC = (float) $p['stock_total'] * (float) $p['precio_compra'];
                    $valV = (float) $p['stock_total'] * (float) $p['precio_venta'];
                    $vC += $valC; $vV += $valV;
                    $filas[] = [$p['codigo'], $p['nombre'], $p['categoria'] ?? '', $p['unidad'],
                                formatoCantidad((float) $p['stock_total']), formatoCantidad((float) $p['stock_minimo']),
                                number_format((float) $p['precio_compra'], 2), number_format((float) $p['precio_venta'], 2),
                                number_format($valC, 2), number_format($valV, 2), estadoProducto($p)];
                }
                Exportar::generar('Inventario y valorización', $cab, $filas, 'inventario',
                    [['TOTAL', '', '', '', '', '', '', '', number_format($vC, 2), number_format($vV, 2), '']],
                    ['orientacion' => 'L']);
            }

            if ($tipoR === 'kardex') {
                $sql = 'SELECT m.*, p.codigo, p.nombre, a.nombre almacen FROM movimientos m
                        JOIN productos p ON p.id=m.producto_id JOIN almacenes a ON a.id=m.almacen_id
                        WHERE m.fecha BETWEEN ? AND ?';
                $args = [$desde, $hasta];
                if (!empty($_GET['producto_f'])) { $sql .= ' AND m.producto_id=?'; $args[] = (int) $_GET['producto_f']; }
                if (!empty($_GET['almacen_f']))  { $sql .= ' AND m.almacen_id=?';  $args[] = (int) $_GET['almacen_f']; }
                $stmt = $db->prepare($sql . ' ORDER BY m.fecha, m.id'); $stmt->execute($args);
                $filas = array_map(fn($m) => [formatoFecha($m['fecha']), $m['tipo'], $m['almacen'],
                            "{$m['codigo']} — {$m['nombre']}",
                            (($m['cantidad'] < 0 ? '' : '+') . formatoCantidad((float) $m['cantidad'])),
                            $m['motivo'], $m['referencia'], $m['usuario']], $stmt->fetchAll());
                Exportar::generar('Kardex de inventario ' . $desde . ' al ' . $hasta,
                    ['Fecha', 'Tipo', 'Almacén', 'Producto', 'Cantidad', 'Motivo', 'Referencia', 'Usuario'],
                    $filas, "kardex_{$desde}_{$hasta}");
            }

            if ($tipoR === 'cxc') {
                $filas = []; $tTotal = 0.0;
                foreach ($db->query('SELECT v.*, cl.razon_social FROM ventas v JOIN clientes cl ON cl.id=v.cliente_id
                                     WHERE v.condicion="credito" AND v.estado="emitida" ORDER BY v.fecha') as $v) {
                    $stmtPag = $db->prepare('SELECT COALESCE(SUM(monto),0) FROM venta_pagos WHERE venta_id=?'); $stmtPag->execute([$v['id']]);
                    $pag = (float) $stmtPag->fetchColumn();
                    $saldo = max(0, (float) $v['total'] - $pag);
                    $tTotal += $saldo;
                    if ($saldo > 0) $filas[] = [numeroVenta((int) $v['numero']), formatoFecha($v['fecha']),
                                $v['razon_social'], number_format((float) $v['total'], 2), number_format($saldo, 2), 'pendiente'];
                }
                Exportar::generar('Cuentas por cobrar', ['Documento', 'Fecha', 'Cliente', 'Total', 'Saldo', 'Estado'],
                    $filas, 'cxc', [['TOTAL', '', '', '', number_format($tTotal, 2), '']]);
            }

            if ($tipoR === 'cxp') {
                $filas = []; $tTotal = 0.0;
                foreach ($db->query('SELECT c.*, p.razon_social FROM compras c JOIN proveedores p ON p.id=c.proveedor_id
                                     WHERE c.condicion="credito" AND c.estado="emitida" ORDER BY c.fecha') as $c0) {
                    $pag = $db->prepare('SELECT COALESCE(SUM(monto),0) FROM compra_pagos WHERE compra_id=?'); $pag->execute([$c0['id']]);
                    $saldo = max(0, (float) $c0['total'] - (float) $pag->fetchColumn());
                    $tTotal += $saldo;
                    if ($saldo > 0) $filas[] = [numeroCompra((int) $c0['numero']), formatoFecha($c0['fecha']),
                                $c0['razon_social'], number_format((float) $c0['total'], 2), number_format($saldo, 2), 'pendiente'];
                }
                Exportar::generar('Cuentas por pagar', ['Documento', 'Fecha', 'Proveedor', 'Total', 'Saldo', 'Estado'],
                    $filas, 'cxp', [['TOTAL', '', '', '', number_format($tTotal, 2), '']]);
            }

            if ($tipoR === 'caja') {
                $stmt = $db->prepare('SELECT * FROM caja WHERE fecha BETWEEN ? AND ? ORDER BY fecha, id');
                $stmt->execute([$desde, $hasta]);
                $filas = []; $tIn = $tEg = 0.0;
                foreach ($stmt as $r) {
                    $signo = $r['tipo_oper'] === 'ingreso' ? '+' : '-';
                    $filas[] = [formatoFecha($r['fecha']), $r['tipo_oper'], $r['medio'], $r['concepto'],
                                $signo . number_format((float) $r['monto'], 2), $r['referencia'], $r['usuario']];
                    if ($r['tipo_oper'] === 'ingreso') $tIn += (float) $r['monto']; else $tEg += (float) $r['monto'];
                }
                Exportar::generar('Movimiento de caja ' . $desde . ' al ' . $hasta,
                    ['Fecha', 'Tipo', 'Medio', 'Concepto', 'Monto', 'Referencia', 'Usuario'], $filas, "caja_{$desde}_{$hasta}",
                    [['INGRESOS', '', '', '', number_format($tIn, 2), '', ''],
                     ['EGRESOS',  '', '', '', number_format($tEg, 2), '', ''],
                     ['SALDO',    '', '', '', number_format($tIn - $tEg, 2), '', '']]);
            }

            if ($tipoR === 'estado_resultados') {
                $stmt = $db->prepare('SELECT * FROM ventas WHERE fecha BETWEEN ? AND ? AND estado="emitida"');
                $stmt->execute([$desde, $hasta]);
                $ingresos = 0.0;
                foreach ($stmt as $v) $ingresos += (float) $v['total'];

                $stmtC = $db->prepare('SELECT d.cantidad, p.precio_compra FROM venta_detalle d
                                       LEFT JOIN productos p ON p.id=d.producto_id WHERE d.venta_id IN
                                       (SELECT id FROM ventas WHERE fecha BETWEEN ? AND ? AND estado="emitida")');
                $stmtC->execute([$desde, $hasta]);
                $costoVentas = 0.0;
                foreach ($stmtC as $c) $costoVentas += (float) $c['cantidad'] * (float) $c['precio_compra'];

                $gastosStmt = $db->prepare('SELECT COALESCE(SUM(monto),0) FROM caja WHERE fecha BETWEEN ? AND ? AND tipo_oper="egreso"');
                $gastosStmt->execute([$desde, $hasta]);
                $gastos = (float) $gastosStmt->fetchColumn();

                $utilidadBruta = $ingresos - $costoVentas;
                $utilidadNeta  = $utilidadBruta - $gastos;
                Exportar::generar('Estado de resultados ' . $desde . ' al ' . $hasta,
                    ['Concepto', 'Monto'], [
                        ['Ingresos por ventas', number_format($ingresos, 2)],
                        ['( - ) Costo de ventas', number_format($costoVentas, 2)],
                        ['Utilidad bruta', number_format($utilidadBruta, 2)],
                        ['( - ) Gastos operativos', number_format($gastos, 2)],
                    ], "estado_resultados_{$desde}_{$hasta}",
                    [['UTILIDAD NETA', number_format($utilidadNeta, 2)]]);
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

if (!puede($vistaActual, $usuario)) {
    echo '<div class="alert alert-danger">
        <i class="fa-solid fa-lock me-2"></i>No tiene permisos para acceder a esta sección (<strong>' . limpiar($vistaActual) . '</strong>).</div>';
} else {
    $vistaRuta = __DIR__ . '/vistas/' . $vistaActual . '.php';
    if (is_file($vistaRuta)) {
        require_once $vistaRuta;
    } else {
        echo '<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>Vista no encontrada.</div>';
    }
}

require_once __DIR__ . '/includes/pie.php';