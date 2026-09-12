<?php
/**
 * Sistema de Facturación — Aura Automation & Analytics
 * Router principal independiente del ERP.
 *
 * Entrar desde: http://localhost:8003/index.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['facturacion_usuario'])) {
    $_SESSION['facturacion_usuario'] = 'usuario-local';
}

$db = FacturaDB::conexion();

/* ------------------------------------------------------------------ */
/*  Acciones POST / GET (terminan antes de generar HTML)                */
/* ------------------------------------------------------------------ */

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action !== '') {
    switch ($action) {

        /* -------- CLIENTES -------- */
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
                if ($ant) registrarAuditoria($db, 'clientes', $id, 'editar', "Cliente {$razonSocial}", $ant, ['ruc_dni' => $rucDni, 'razon_social' => $razonSocial]);
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
                    setMensaje('danger', 'El cliente tiene comprobantes asociados; no se puede eliminar.');
                }
            }
            redireccionar('index.php?vista=clientes');

        /* -------- PRODUCTOS / SERVICIOS -------- */
        case 'guardar_producto':
            verificarCsrf();
            $id        = (int) ($_POST['id'] ?? 0);
            $codigo    = trim($_POST['codigo'] ?? '');
            $tipo      = in_array($_POST['tipo'] ?? '', ['producto', 'servicio'], true) ? $_POST['tipo'] : 'producto';
            $descrip   = trim($_POST['descripcion'] ?? '');
            $precio    = (float) ($_POST['precio'] ?? 0);
            $afectoIgv = isset($_POST['afecto_igv']) ? 1 : 0;
            $activo    = isset($_POST['activo']) ? 1 : 0;

            if ($codigo === '' || $descrip === '' || $precio < 0) {
                setMensaje('danger', 'Faltan datos obligatorios (código, descripción y precio válido).');
                break;
            }

            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM productos WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('UPDATE productos SET codigo=:c,tipo=:t,descripcion=:d,precio=:p,afecto_igv=:ai,activo=:a WHERE id=:id')
                   ->execute([':c' => $codigo, ':t' => $tipo, ':d' => $descrip, ':p' => $precio, ':ai' => $afectoIgv, ':a' => $activo, ':id' => $id]);
                if ($ant) registrarAuditoria($db, 'productos', $id, 'editar', "Producto {$codigo} - {$descrip}", $ant, ['precio' => $precio, 'tipo' => $tipo]);
                setMensaje('success', 'Producto actualizado.');
            } else {
                try {
                    $db->prepare('INSERT INTO productos (codigo,tipo,descripcion,precio,afecto_igv,activo)
                                  VALUES (:c,:t,:d,:p,:ai,:a)')
                       ->execute([':c' => $codigo, ':t' => $tipo, ':d' => $descrip, ':p' => $precio, ':ai' => $afectoIgv, ':a' => $activo]);
                    $nuevoId = (int) $db->lastInsertId();
                    registrarAuditoria($db, 'productos', $nuevoId, 'crear', "Producto {$codigo} - {$descrip}", [], ['precio' => $precio]);
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
                    if ($ant) registrarAuditoria($db, 'productos', $id, 'eliminar', "Producto {$ant['codigo']} - {$ant['descripcion']}", $ant);
                    setMensaje('success', 'Producto eliminado.');
                } catch (PDOException $e) {
                    setMensaje('danger', 'El producto forma parte de comprobantes; no se puede eliminar.');
                }
            }
            redireccionar('index.php?vista=productos');

        /* -------- COMPROBANTES -------- */
        case 'guardar_comprobante':
            verificarCsrf();
            $id         = (int) ($_POST['id'] ?? 0);
            $tipoPost   = in_array($_POST['tipo'] ?? '', tiposComprobante(), true) ? $_POST['tipo'] : 'BOLETA';
            $fechaE     = $_POST['fecha_emision'] ?? date('Y-m-d');
            $fechaV     = $_POST['fecha_vencimiento'] ?? null;
            $clienteId  = (int) ($_POST['cliente_id'] ?? 0);
            $condicion  = $_POST['condicion_pago'] ?? 'Contado';
            $observ     = trim($_POST['observaciones'] ?? '');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaE)) {
                setMensaje('danger', 'Fecha de emisión no válida.');
                break;
            }
            if ($clienteId <= 0) {
                setMensaje('danger', 'Seleccione un cliente.');
                break;
            }
            if ($fechaV && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaV)) $fechaV = null;

            $items = [];
            $desc  = $_POST['item_desc'] ?? [];
            $cant  = $_POST['item_cant'] ?? [];
            $prec  = $_POST['item_precio'] ?? [];
            $afect = $_POST['item_afecto'] ?? [];
            $prod  = $_POST['item_prod_id'] ?? [];

            $totalItems = count($desc);
            $tasa = tasaIgv($db);
            $subtotal = $igv = $total = 0.0;

            for ($i = 0; $i < $totalItems; $i++) {
                $d = trim((string) ($desc[$i] ?? ''));
                $c = (float) ($cant[$i] ?? 0);
                $p = (float) ($prec[$i] ?? 0);
                $a = ($afect[$i] ?? '1') === '1' ? 1 : 0;
                if ($d === '' || $c <= 0) continue;
                $importe = round($c * $p, 2);
                $lIgv    = $a ? round($importe * $tasa, 2) : 0.00;
                $items[] = ['producto_id' => (int) ($prod[$i] ?? 0), 'descripcion' => $d,
                            'cantidad' => $c, 'precio_unitario' => $p, 'importe' => $importe,
                            'igv' => $lIgv, 'total' => round($importe + $lIgv, 2)];
                $subtotal += $importe;
                $igv += $lIgv;
                $total += $importe + $lIgv;
            }

            if (!$items) {
                setMensaje('danger', 'El comprobante debe tener al menos un ítem válido.');
                break;
            }
            $subtotal = round($subtotal, 2);
            $igv      = round($igv, 2);
            $total    = round($total, 2);
            $letras   = letrasTotales($db, $total);

            $db->beginTransaction();
            try {
                if ($id > 0) {
                    $ant = $db->prepare('SELECT * FROM comprobantes WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                    if ($ant && $ant['estado'] === 'anulado') {
                        $db->rollBack();
                        setMensaje('danger', 'No se puede editar un comprobante anulado.');
                        break;
                    }
                    $serie  = $ant['serie'];
                    $numero = (int) $ant['numero'];
                    $tipo   = $ant['tipo'];
                    $db->prepare('UPDATE comprobantes SET fecha_emision=:f,fecha_vencimiento=:fv,cliente_id=:cl,
                                  condicion_pago=:cp,subtotal=:s,igv=:i,total=:t,letras=:l,observaciones=:o
                                  WHERE id=:id')
                       ->execute([':f' => $fechaE, ':fv' => $fechaV, ':cl' => $clienteId, ':cp' => $condicion,
                                  ':s' => $subtotal, ':i' => $igv, ':t' => $total, ':l' => $letras, ':o' => $observ, ':id' => $id]);
                    $db->prepare('DELETE FROM comprobante_detalle WHERE comprobante_id=?')->execute([$id]);
                    $compId = $id;
                    registrarAuditoria($db, 'comprobantes', $id, 'editar',
                        "Comprobante " . numeroComprobante($serie, $numero) . " actualizado", $ant,
                        ['total' => $total, 'items' => count($items)]);
                    setMensaje('success', 'Comprobante actualizado.');
                } else {
                    $tipo   = $tipoPost;
                    $serie  = serieActual($db, $tipo);
                    $numero = siguienteNumero($db, $tipo, $serie);
                    $db->prepare('INSERT INTO comprobantes (tipo,serie,numero,fecha_emision,fecha_vencimiento,cliente_id,
                                  condicion_pago,subtotal,igv,total,letras,observaciones,estado)
                                  VALUES (:tipo,:serie,:num,:f,:fv,:cl,:cp,:s,:i,:t,:l,:o, "emitido")')
                       ->execute([':tipo' => $tipo, ':serie' => $serie, ':num' => $numero, ':f' => $fechaE, ':fv' => $fechaV,
                                  ':cl' => $clienteId, ':cp' => $condicion, ':s' => $subtotal, ':i' => $igv,
                                  ':t' => $total, ':l' => $letras, ':o' => $observ]);
                    $compId = (int) $db->lastInsertId();
                    registrarAuditoria($db, 'comprobantes', $compId, 'crear',
                        "Comprobante " . numeroComprobante($serie, $numero), [],
                        ['tipo' => $tipo, 'total' => $total, 'items' => count($items)]);
                    setMensaje('success', 'Comprobante ' . numeroComprobante($serie, $numero) . ' registrado.');
                }

                $stmt = $db->prepare('INSERT INTO comprobante_detalle
                    (comprobante_id,producto_id,descripcion,cantidad,precio_unitario,importe,igv,total)
                    VALUES (:c,:p,:d,:q,:pu,:im,:ig,:tt)');
                foreach ($items as $it) {
                    $stmt->execute([
                        ':c' => $compId, ':p' => ($it['producto_id'] ?: null), ':d' => $it['descripcion'],
                        ':q' => $it['cantidad'], ':pu' => $it['precio_unitario'], ':im' => $it['importe'],
                        ':ig' => $it['igv'], ':tt' => $it['total'],
                    ]);
                }
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                setMensaje('danger', 'Error al guardar el comprobante: ' . $e->getMessage());
            }
            redireccionar('index.php?vista=comprobantes');

        case 'anular_comprobante':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM comprobantes WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                if ($ant) {
                    $db->prepare('UPDATE comprobantes SET estado="anulado" WHERE id=?')->execute([$id]);
                    registrarAuditoria($db, 'comprobantes', $id, 'editar',
                        "Comprobante " . numeroComprobante($ant['serie'], (int) $ant['numero']) . " anulado", $ant, ['estado' => 'anulado']);
                    setMensaje('success', 'Comprobante anulado.');
                }
            }
            redireccionar('index.php?vista=comprobantes');

        case 'eliminar_comprobante':
            verificarCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $ant = $db->prepare('SELECT * FROM comprobantes WHERE id=?'); $ant->execute([$id]); $ant = $ant->fetch();
                $db->prepare('DELETE FROM comprobantes WHERE id = ?')->execute([$id]);
                if ($ant) registrarAuditoria($db, 'comprobantes', $id, 'eliminar',
                    "Comprobante " . numeroComprobante($ant['serie'], (int) $ant['numero']) . " eliminado", $ant);
                setMensaje('success', 'Comprobante eliminado.');
            }
            redireccionar('index.php?vista=comprobantes');

        /* -------- CONFIGURACIÓN (parámetros) -------- */
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
            setMensaje('success', 'Parámetros actualizados.');
            redireccionar('index.php?vista=configuracion');

        /* -------- PDF de comprobante -------- */
        case 'pdf':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) redireccionar('index.php?vista=comprobantes');
            require_once __DIR__ . '/includes/ComprobantePdf.php';
            $stmtC = $db->prepare('SELECT c.*, cl.razon_social, cl.tipo_doc, cl.ruc_dni, cl.direccion, cl.telefono
                                   FROM comprobantes c JOIN clientes cl ON cl.id=c.cliente_id WHERE c.id=?');
            $stmtC->execute([$id]);
            $comp = $stmtC->fetch();
            if (!$comp) {
                setMensaje('warning', 'Comprobante no encontrado.');
                redireccionar('index.php?vista=comprobantes');
            }
            $stmtD = $db->prepare('SELECT * FROM comprobante_detalle WHERE comprobante_id=? ORDER BY id');
            $stmtD->execute([$id]);
            ComprobantePdf::generar($db, $comp, $stmtD->fetchAll(), datosEmpresa($db));

        /* -------- EXPORTAR / REPORTES -------- */
        case 'exportar':
            require_once __DIR__ . '/includes/Exportar.php';
            $tipoR   = $_GET['tipo'] ?? $_POST['tipo'] ?? 'comprobantes';
            $formato = $_GET['formato'] ?? $_POST['formato'] ?? 'xlsx';

            if ($tipoR === 'comprobantes') {
                [$desde, $hasta] = rangoFechas();
                $tipoF = $_GET['tipo_f'] ?? '';
                $sql = 'SELECT c.tipo,c.serie,c.numero,c.fecha_emision,c.condicion_pago,c.subtotal,c.igv,c.total,c.estado,
                               cl.razon_social, cl.tipo_doc, cl.ruc_dni
                        FROM comprobantes c JOIN clientes cl ON cl.id=c.cliente_id
                        WHERE c.fecha_emision BETWEEN ? AND ?';
                $args = [$desde, $hasta];
                if ($tipoF !== '') { $sql .= ' AND c.tipo = ?'; $args[] = $tipoF; }
                $sql .= ' ORDER BY c.fecha_emision, c.id';
                $stmt = $db->prepare($sql);
                $stmt->execute($args);
                $filas = [];
                $tS = $tI = $tT = 0.0;
                foreach ($stmt as $r) {
                    $filas[] = [$r['tipo'], numeroComprobante($r['serie'], (int) $r['numero']), formatoFecha($r['fecha_emision']),
                                $r['razon_social'], $r['tipo_doc'] . ' ' . $r['ruc_dni'], $r['condicion_pago'],
                                number_format((float) $r['subtotal'], 2), number_format((float) $r['igv'], 2),
                                number_format((float) $r['total'], 2), $r['estado']];
                    if ($r['estado'] === 'emitido') {
                        $tS += (float) $r['subtotal']; $tI += (float) $r['igv']; $tT += (float) $r['total'];
                    }
                }
                $cab = ['Tipo', 'Número', 'Fecha', 'Cliente', 'Documento', 'Condición', 'Subtotal', 'IGV', 'Total', 'Estado'];
                Exportar::generar('Comprobantes de venta ' . $desde . ' al ' . $hasta, $cab, $filas,
                    "comprobantes_{$desde}_{$hasta}",
                    [['TOTAL', '', '', '', '', '', number_format($tS, 2), number_format($tI, 2), number_format($tT, 2), '']]);
            }

            if ($tipoR === 'productos') {
                $cab = ['Código', 'Descripción', 'Tipo', 'Precio (S/)', 'Afecto IGV', 'Estado'];
                $filas = array_map(fn($p) => [$p['codigo'], $p['descripcion'], $p['tipo'],
                            number_format((float) $p['precio'], 2), $p['afecto_igv'] ? 'Sí' : 'No',
                            $p['activo'] ? 'Activo' : 'Inactivo'],
                    $db->query('SELECT * FROM productos ORDER BY descripcion')->fetchAll());
                Exportar::generar('Catálogo de productos y servicios', $cab, $filas, 'catalogo_productos');
            }

            if ($tipoR === 'clientes') {
                $cab = ['Documento', 'RUC/DNI', 'Razón social', 'Dirección', 'Correo', 'Teléfono', 'Estado'];
                $filas = array_map(fn($c) => [$c['tipo_doc'], $c['ruc_dni'], $c['razon_social'], $c['direccion'],
                            $c['correo'], $c['telefono'], $c['activo'] ? 'Activo' : 'Inactivo'],
                    $db->query('SELECT * FROM clientes ORDER BY razon_social')->fetchAll());
                Exportar::generar('Base de clientes', $cab, $filas, 'clientes');
            }

            if ($tipoR === 'resumen') {
                $cab = ['Mes', 'N° comprobantes', 'Subtotal', 'IGV', 'Total'];
                $filas = [];
                foreach ($db->query("SELECT DATE_FORMAT(fecha_emision,'%Y-%m') mes, COUNT(*) n,
                                     COALESCE(SUM(subtotal),0) s, COALESCE(SUM(igv),0) ig, COALESCE(SUM(total),0) t
                                     FROM comprobantes WHERE estado='emitido' GROUP BY mes ORDER BY mes DESC LIMIT 12") as $r) {
                    [$a, $m] = explode('-', $r['mes']);
                    $filas[] = [nombreMes((int) $m) . ' ' . $a, $r['n'],
                                number_format((float) $r['s'], 2), number_format((float) $r['ig'], 2), number_format((float) $r['t'], 2)];
                }
                Exportar::generar('Resumen mensual de ventas', $cab, $filas, 'resumen_mensual');
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