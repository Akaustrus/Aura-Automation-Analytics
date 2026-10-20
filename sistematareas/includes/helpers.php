<?php
/**
 * Funciones auxiliares del Sistema de Gestión de Tareas (independiente).
 */

function limpiar(?string $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatoFecha(?string $fecha): string
{
    if (empty($fecha)) {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt ? $dt->format('d/m/Y') : $fecha;
}

function tokenCsrf(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['tareas_csrf'])) {
        $_SESSION['tareas_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['tareas_csrf'];
}

/** Verifica el token CSRF de peticiones AJAX (cabecera X-CSRF-Token o campo csrf). */
function verificarCsrfAjax(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $dado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['tareas_csrf'] ?? '', (string) $dado)) {
        responderJson(false, 'Solicitud no válida (CSRF).', null, 403);
    }
}

/** Responde JSON y termina la petición. */
function responderJson(bool $ok, string $mensaje = '', $datos = null, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok'      => $ok,
        'mensaje' => $mensaje,
        'datos'   => $datos,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Registra un evento en el log de auditoría. */
function registrarAuditoria(PDO $db, string $tabla, int $registroId, string $accion, string $resumen = '', array $antes = [], array $despues = []): void
{
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
        ':usuario' => $_SESSION['tareas_usuario'] ?? 'sistema',
    ]);
}

function estadosTarea(): array
{
    return ['pendiente', 'en_proceso', 'completada'];
}

function prioridadesTarea(): array
{
    return ['baja', 'media', 'alta', 'critica'];
}

/** Convierte un registro de tarea a la forma que consume el front-end. */
function tareaToArray(array $t): array
{
    return [
        'id'               => (int) $t['id'],
        'titulo'           => $t['titulo'],
        'descripcion'      => (string) ($t['descripcion'] ?? ''),
        'prioridad'        => $t['prioridad'],
        'estado'           => $t['estado'],
        'fecha_vencimiento'=> $t['fecha_vencimiento'],
        'fecha_vencimiento_fmt' => formatoFecha($t['fecha_vencimiento']),
        'responsable_id'   => isset($t['responsable_id']) && $t['responsable_id'] !== null ? (int) $t['responsable_id'] : null,
        'responsable'      => $t['responsable_nombre'] ?? null,
        'responsable_cargo'=> $t['responsable_cargo'] ?? null,
        'fecha_creacion'   => $t['fecha_creacion'],
        'fecha_creacion_fmt'    => formatoFecha(substr((string) $t['fecha_creacion'], 0, 10)),
        'vencida'          => $t['fecha_vencimiento']
            && $t['estado'] !== 'completada'
            && $t['fecha_vencimiento'] < date('Y-m-d'),
    ];
}

/** Contadores para las tarjetas del panel. */
function estadisticasTareas(PDO $db): array
{
    $filas = $db->query('SELECT estado, COUNT(*) AS total FROM tareas GROUP BY estado')->fetchAll();
    $base = ['total' => 0, 'pendiente' => 0, 'en_proceso' => 0, 'completada' => 0, 'criticas' => 0, 'vencidas' => 0];
    foreach ($filas as $f) {
        $base[$f['estado']] = (int) $f['total'];
        $base['total'] += (int) $f['total'];
    }
    $base['criticas'] = (int) $db->query("SELECT COUNT(*) FROM tareas WHERE prioridad='critica' AND estado <> 'completada'")->fetchColumn();
    $base['vencidas'] = (int) $db->query("SELECT COUNT(*) FROM tareas WHERE fecha_vencimiento < CURDATE() AND estado <> 'completada'")->fetchColumn();
    return $base;
}
