<?php
/**
 * API JSON del Sistema de Gestión de Tareas.
 * Todas las operaciones se consumen vía AJAX sin recargar la página.
 *
 * GET  ?accion=listar        (filtros opcionales: estado, prioridad, busqueda, responsable)
 * GET  ?accion=obtener&id=N
 * GET  ?accion=listar_personal
 * POST action=guardar        (id opcional para editar, responsable_id)
 * POST action=eliminar       (id)
 * POST action=cambiar_estado (id, estado)
 * POST action=guardar_personal (id opcional)
 * POST action=eliminar_personal (id)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['tareas_usuario'])) {
    $_SESSION['tareas_usuario'] = 'usuario-local';
}

header('Cache-Control: no-store');

$db   = TareasDB::conexion();
$accion = $_GET['accion'] ?? $_POST['action'] ?? '';

switch ($accion) {

    /* ------------------------------------------------ */
    /*  LISTAR (con filtros)                             */
    /* ------------------------------------------------ */
    case 'listar':
        $sql = 'SELECT t.*, p.nombre AS responsable_nombre, p.cargo AS responsable_cargo
                FROM tareas t
                LEFT JOIN personal p ON p.id = t.responsable_id
                WHERE 1=1';
        $args = [];

        $estado = $_GET['estado'] ?? '';
        if (in_array($estado, estadosTarea(), true)) {
            $sql .= ' AND t.estado = ?';
            $args[] = $estado;
        }

        $prioridad = $_GET['prioridad'] ?? '';
        if (in_array($prioridad, prioridadesTarea(), true)) {
            $sql .= ' AND t.prioridad = ?';
            $args[] = $prioridad;
        }

        $responsable = (int) ($_GET['responsable'] ?? 0);
        if ($responsable > 0) {
            $sql .= ' AND t.responsable_id = ?';
            $args[] = $responsable;
        }

        $busqueda = trim((string) ($_GET['busqueda'] ?? ''));
        if ($busqueda !== '') {
            $sql .= ' AND (t.titulo LIKE ? OR t.descripcion LIKE ?)';
            $args[] = '%' . $busqueda . '%';
            $args[] = '%' . $busqueda . '%';
        }

        $orden = ($_GET['orden'] ?? 'recientes') === 'vencimiento' ? 't.fecha_vencimiento IS NULL, t.fecha_vencimiento ASC' : 't.id DESC';
        $sql .= ' ORDER BY ' . $orden;

        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $tareas = array_map('tareaToArray', $stmt->fetchAll());

        $personal = $db->query(
            'SELECT p.*, COUNT(t.id) AS total_tareas
             FROM personal p LEFT JOIN tareas t ON t.responsable_id = p.id
             GROUP BY p.id ORDER BY p.nombre'
        )->fetchAll();

        responderJson(true, '', [
            'tareas'       => $tareas,
            'personal'     => $personal,
            'estadisticas' => estadisticasTareas($db),
        ]);

    /* ------------------------------------------------ */
    /*  LISTAR PERSONAL                                  */
    /* ------------------------------------------------ */
    case 'listar_personal':
        $personal = $db->query(
            'SELECT p.*, COUNT(t.id) AS total_tareas,
                    SUM(CASE WHEN t.estado <> "completada" THEN 1 ELSE 0 END) AS tareas_abiertas
             FROM personal p LEFT JOIN tareas t ON t.responsable_id = p.id
             GROUP BY p.id ORDER BY p.nombre'
        )->fetchAll();
        responderJson(true, '', ['personal' => $personal]);

    /* ------------------------------------------------ */
    /*  OBTENER UNA SOLA TAREA                           */
    /* ------------------------------------------------ */
    case 'obtener':
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = $db->prepare(
            'SELECT t.*, p.nombre AS responsable_nombre
             FROM tareas t LEFT JOIN personal p ON p.id = t.responsable_id
             WHERE t.id = ?'
        );
        $stmt->execute([$id]);
        $t = $stmt->fetch();
        if (!$t) {
            responderJson(false, 'Tarea no encontrada.', null, 404);
        }
        responderJson(true, '', tareaToArray($t));

    /* ------------------------------------------------ */
    /*  CREAR / EDITAR                                   */
    /* ------------------------------------------------ */
    case 'guardar':
        verificarCsrfAjax();

        $id        = (int) ($_POST['id'] ?? 0);
        $titulo    = trim((string) ($_POST['titulo'] ?? ''));
        $desc      = trim((string) ($_POST['descripcion'] ?? ''));
        $prioridad = $_POST['prioridad'] ?? 'media';
        $estado    = $_POST['estado'] ?? 'pendiente';
        $vence     = trim((string) ($_POST['fecha_vencimiento'] ?? ''));
        $responsable = (int) ($_POST['responsable_id'] ?? 0) ?: null;

        if ($titulo === '') {
            responderJson(false, 'El título es obligatorio.');
        }
        if (!in_array($prioridad, prioridadesTarea(), true)) {
            $prioridad = 'media';
        }
        if (!in_array($estado, estadosTarea(), true)) {
            $estado = 'pendiente';
        }
        if ($vence !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vence)) {
            responderJson(false, 'La fecha de vencimiento no es válida.');
        }
        $vence = $vence === '' ? null : $vence;

        if ($responsable !== null) {
            $existe = $db->prepare('SELECT COUNT(*) FROM personal WHERE id = ?');
            $existe->execute([$responsable]);
            if ((int) $existe->fetchColumn() === 0) {
                responderJson(false, 'El responsable indicado no existe.');
            }
        }

        if ($id > 0) {
            $ant = $db->prepare('SELECT * FROM tareas WHERE id = ?');
            $ant->execute([$id]);
            $ant = $ant->fetch();
            if (!$ant) {
                responderJson(false, 'Tarea no encontrada.', null, 404);
            }
            $db->prepare('UPDATE tareas SET titulo=:t, descripcion=:d, prioridad=:p, estado=:e, fecha_vencimiento=:v, responsable_id=:r WHERE id=:id')
               ->execute([':t'=>$titulo, ':d'=>$desc, ':p'=>$prioridad, ':e'=>$estado, ':v'=>$vence, ':r'=>$responsable, ':id'=>$id]);
            registrarAuditoria($db, 'tareas', $id, 'editar', "Tarea: {$titulo}", $ant, ['titulo'=>$titulo,'estado'=>$estado,'prioridad'=>$prioridad,'responsable_id'=>$responsable]);
            responderJson(true, 'Tarea actualizada.');
        }

        $db->prepare('INSERT INTO tareas (titulo, descripcion, prioridad, estado, fecha_vencimiento, responsable_id) VALUES (:t,:d,:p,:e,:v,:r)')
           ->execute([':t'=>$titulo, ':d'=>$desc, ':p'=>$prioridad, ':e'=>$estado, ':v'=>$vence, ':r'=>$responsable]);
        $nuevoId = (int) $db->lastInsertId();
        registrarAuditoria($db, 'tareas', $nuevoId, 'crear', "Tarea: {$titulo}", [], ['titulo'=>$titulo,'estado'=>$estado,'prioridad'=>$prioridad,'responsable_id'=>$responsable]);

        $stmt = $db->prepare('SELECT t.*, p.nombre AS responsable_nombre, p.cargo AS responsable_cargo
                              FROM tareas t LEFT JOIN personal p ON p.id = t.responsable_id WHERE t.id = ?');
        $stmt->execute([$nuevoId]);
        responderJson(true, 'Tarea creada.', tareaToArray($stmt->fetch()));

    /* ------------------------------------------------ */
    /*  CAMBIAR ESTADO (pendiente / en_proceso / completada) */
    /* ------------------------------------------------ */
    case 'cambiar_estado':
        verificarCsrfAjax();

        $id     = (int) ($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        if ($id <= 0 || !in_array($estado, estadosTarea(), true)) {
            responderJson(false, 'Datos no válidos.');
        }
        $ant = $db->prepare('SELECT * FROM tareas WHERE id = ?');
        $ant->execute([$id]);
        $ant = $ant->fetch();
        if (!$ant) {
            responderJson(false, 'Tarea no encontrada.', null, 404);
        }
        $db->prepare('UPDATE tareas SET estado = ? WHERE id = ?')->execute([$estado, $id]);
        registrarAuditoria($db, 'tareas', $id, 'estado', "Tarea «{$ant['titulo']}» → {$estado}", ['estado'=>$ant['estado']], ['estado'=>$estado]);

        $stmt = $db->prepare('SELECT t.*, p.nombre AS responsable_nombre, p.cargo AS responsable_cargo
                              FROM tareas t LEFT JOIN personal p ON p.id = t.responsable_id WHERE t.id = ?');
        $stmt->execute([$id]);
        responderJson(true, 'Estado actualizado.', tareaToArray($stmt->fetch()));

    /* ------------------------------------------------ */
    /*  ELIMINAR                                         */
    /* ------------------------------------------------ */
    case 'eliminar':
        verificarCsrfAjax();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            responderJson(false, 'Datos no válidos.');
        }
        $ant = $db->prepare('SELECT * FROM tareas WHERE id = ?');
        $ant->execute([$id]);
        $ant = $ant->fetch();
        if (!$ant) {
            responderJson(false, 'Tarea no encontrada.', null, 404);
        }
        $db->prepare('DELETE FROM tareas WHERE id = ?')->execute([$id]);
        registrarAuditoria($db, 'tareas', $id, 'eliminar', "Tarea eliminada: {$ant['titulo']}", $ant);
        responderJson(true, 'Tarea eliminada.');

    /* ------------------------------------------------ */
    /*  PERSONAL: CREAR / EDITAR                         */
    /* ------------------------------------------------ */
    case 'guardar_personal':
        verificarCsrfAjax();

        $id     = (int) ($_POST['id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $cargo  = trim((string) ($_POST['cargo'] ?? ''));
        $email  = trim((string) ($_POST['email'] ?? ''));

        if ($nombre === '') {
            responderJson(false, 'El nombre es obligatorio.');
        }

        if ($id > 0) {
            $ant = $db->prepare('SELECT * FROM personal WHERE id = ?');
            $ant->execute([$id]);
            $ant = $ant->fetch();
            if (!$ant) {
                responderJson(false, 'Miembro no encontrado.', null, 404);
            }
            $db->prepare('UPDATE personal SET nombre=:n, cargo=:c, email=:e WHERE id=:id')
               ->execute([':n'=>$nombre, ':c'=>$cargo, ':e'=>$email, ':id'=>$id]);
            registrarAuditoria($db, 'personal', $id, 'editar', "Personal: {$nombre}", $ant, ['nombre'=>$nombre,'cargo'=>$cargo]);
            responderJson(true, 'Miembro actualizado.');
        }

        $db->prepare('INSERT INTO personal (nombre, cargo, email) VALUES (:n, :c, :e)')
           ->execute([':n'=>$nombre, ':c'=>$cargo, ':e'=>$email]);
        $nuevoId = (int) $db->lastInsertId();
        registrarAuditoria($db, 'personal', $nuevoId, 'crear', "Personal: {$nombre}", [], ['nombre'=>$nombre,'cargo'=>$cargo]);
        responderJson(true, 'Miembro agregado.');

    /* ------------------------------------------------ */
    /*  PERSONAL: ELIMINAR                               */
    /* ------------------------------------------------ */
    case 'eliminar_personal':
        verificarCsrfAjax();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            responderJson(false, 'Datos no válidos.');
        }
        $ant = $db->prepare('SELECT * FROM personal WHERE id = ?');
        $ant->execute([$id]);
        $ant = $ant->fetch();
        if (!$ant) {
            responderJson(false, 'Miembro no encontrado.', null, 404);
        }

        // Las tareas asignadas quedan sin responsable (ON DELETE SET NULL es el respaldo).
        $db->prepare('UPDATE tareas SET responsable_id = NULL WHERE responsable_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM personal WHERE id = ?')->execute([$id]);
        registrarAuditoria($db, 'personal', $id, 'eliminar', "Personal eliminado: {$ant['nombre']}", $ant);
        responderJson(true, 'Miembro eliminado. Sus tareas quedaron sin asignar.');

    default:
        responderJson(false, 'Acción no reconocida.', null, 400);
}
