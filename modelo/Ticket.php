<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Notificacion.php';

class Ticket
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO tickets (cliente_id, plan_id, usuario_id, categoria, urgencia, prioridad, canal, estado, asunto, descripcion)
             VALUES (:cliente_id, :plan_id, :usuario_id, :categoria, :urgencia, :prioridad, :canal, 'nuevo', :asunto, :descripcion)"
        );
        $stmt->execute([
            'cliente_id' => $datos['cliente_id'],
            'plan_id' => $datos['plan_id'] ?? null,
            'usuario_id' => $datos['usuario_id'],
            'categoria' => $datos['categoria'],
            'urgencia' => $datos['urgencia'],
            'prioridad' => $datos['prioridad'],
            'canal' => $datos['canal'],
            'asunto' => $datos['asunto'],
            'descripcion' => $datos['descripcion'],
        ]);
        $id = (int) $this->db->lastInsertId();
        $comentario = $datos['canal'] === 'portal'
            ? 'Ticket registrado por el cliente'
            : 'Ticket atendido por teléfono';
        $this->registrar($id, null, 'nuevo', $datos['usuario_id'], $comentario);

        // Avisa a admin/encargados (excepto quien lo creó) que hay un ticket nuevo por atender.
        if (!empty($datos['usuario_id'])) {
            (new Notificacion())->notificarNuevo($id, $datos['asunto'], $datos['prioridad']);
        }
        return $id;
    }

    public function listar(?string $estado = null, ?int $planId = null): array
    {
        $sql = "SELECT t.*, c.empresa AS cliente_nombre, c.contacto AS cliente_contacto,
                       p.nombre AS plan_nombre, p.slug AS plan_slug, p.icono AS plan_icono,
                       u.nombre AS usuario_nombre, enc.nombre AS encargado_nombre
                FROM tickets t
                JOIN clientes c ON c.id = t.cliente_id
                LEFT JOIN planes p ON p.id = t.plan_id
                LEFT JOIN usuarios u ON u.id = t.usuario_id
                LEFT JOIN usuarios enc ON enc.id = t.encargado_id
                WHERE 1=1";
        $params = [];
        if ($estado) {
            $sql .= " AND t.estado = :estado";
            $params['estado'] = $estado;
        }
        if ($planId) {
            $sql .= " AND t.plan_id = :plan_id";
            $params['plan_id'] = $planId;
        }
        $sql .= " ORDER BY "
            . "CASE t.estado WHEN 'nuevo' THEN 0 WHEN 'en_curso' THEN 1 WHEN 'culminado' THEN 2 "
            . "WHEN 'pausado' THEN 3 WHEN 'resuelto' THEN 4 ELSE 5 END, "
            . "t.prioridad DESC, t.fecha_creacion DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, c.empresa AS cliente_nombre, c.ruc, c.email AS cliente_email, c.contacto,
                    p.nombre AS plan_nombre, p.slug AS plan_slug, p.icono AS plan_icono,
                    u.nombre AS usuario_nombre, enc.nombre AS encargado_nombre
             FROM tickets t
             JOIN clientes c ON c.id = t.cliente_id
             LEFT JOIN planes p ON p.id = t.plan_id
             LEFT JOIN usuarios u ON u.id = t.usuario_id
             LEFT JOIN usuarios enc ON enc.id = t.encargado_id
             WHERE t.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function asignar(int $id, int $usuarioId, ?int $encargadoId = null): void
    {
        $actual = $this->obtener($id);
        // Al asignar, el ticket pasa a "en_curso" solo si aún no avanzó a un estado posterior
        // (culminado/resuelto/cerrado). Si ya está culminado o resuelto, solo se actualiza el personal.
        $estado = in_array($actual['estado'], ['culminado', 'resuelto', 'cerrado'], true)
            ? $actual['estado']
            : 'en_curso';

        if ($encargadoId) {
            $this->db->prepare(
                "UPDATE tickets SET usuario_id = :u, encargado_id = :enc, estado = :estado WHERE id = :id"
            )->execute(['u' => $usuarioId, 'enc' => $encargadoId, 'estado' => $estado, 'id' => $id]);
        } else {
            $this->db->prepare("UPDATE tickets SET usuario_id = :u, encargado_id = NULL, estado = :estado WHERE id = :id")
                ->execute(['u' => $usuarioId, 'estado' => $estado, 'id' => $id]);
        }
        $actuales = $this->obtener($id);
        $this->registrar($id, $actual['estado'], $estado, $_SESSION['usuario_id'] ?? null, 'Ticket asignado');

        // Avisa al resto (admin/encargados) que el ticket quedó asignado y en curso.
        (new Notificacion())->notificarCambio($id, $estado, $actuales['asunto'], 'Ticket asignado a ' . ($actuales['encargado_nombre'] ?: ('usuario #' . $usuarioId)) . '.');
    }

    public function cambiarEstado(int $id, string $estado, ?string $comentario = null, bool $notificarCulminado = false, ?int $encargadoId = null): void
    {
        $actual = $this->obtener($id);
        if (!$actual) {
            return;
        }
        if ($encargadoId !== null) {
            $this->db->prepare("UPDATE tickets SET encargado_id = :enc WHERE id = :id")
                ->execute(['enc' => $encargadoId ?: null, 'id' => $id]);
        }
        $this->db->prepare("UPDATE tickets SET estado = :estado WHERE id = :id")
            ->execute(['estado' => $estado, 'id' => $id]);

        if ($estado === 'culminado' && !$actual['fecha_culminacion']) {
            $this->db->prepare("UPDATE tickets SET fecha_culminacion = NOW() WHERE id = :id")
                ->execute(['id' => $id]);
        }

        if ($estado === 'resuelto' && !$actual['fecha_resolucion']) {
            $this->db->prepare(
                "UPDATE tickets SET fecha_resolucion = NOW() WHERE id = :id AND estado = 'resuelto'"
            )->execute(['id' => $id]);
        }

        // Cuando se pasa a "culminado", se notifica a admin/otros encargados para que revisen y cierren,
        // y además se envía una confirmación al propio usuario que realizó el cambio.
        if ($estado === 'culminado' && $actual['estado'] !== 'culminado') {
            if ($notificarCulminado) {
                (new Notificacion())->notificarCulminado($id, $actual['encargado_nombre'] ?: 'Trabajo', $actual['asunto']);
            } else {
                // Cualquier otro que cambie a culminado (sin ser el encargado) avisa al resto.
                (new Notificacion())->notificarCambio($id, $estado, $actual['asunto'], $comentario);
            }
            // Confirmación propia al usuario que marcó "culminado".
            (new Notificacion())->notificarCulminacionPropia($id, $actual['asunto']);
        } else {
            // Cualquier otro cambio de estado avisa al resto de admin/encargados (excepto quien lo hizo).
            (new Notificacion())->notificarCambio($id, $estado, $actual['asunto'], $comentario);
        }
        $this->registrar($id, $actual['estado'], $estado, $_SESSION['usuario_id'] ?? null, $comentario);
    }

    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, enc.nombre AS encargado_nombre,
                    p.nombre AS plan_nombre, p.slug AS plan_slug, p.icono AS plan_icono
             FROM tickets t
             LEFT JOIN usuarios enc ON enc.id = t.encargado_id
             LEFT JOIN planes p ON p.id = t.plan_id
             WHERE t.cliente_id = :clienteId
             ORDER BY t.fecha_creacion DESC"
        );
        $stmt->execute(['clienteId' => $clienteId]);
        return $stmt->fetchAll();
    }

    /** Tickets de un cliente para un sistema concreto (que usa la empresa). */
    public function porClienteYPlan(int $clienteId, int $planId): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, enc.nombre AS encargado_nombre,
                    p.nombre AS plan_nombre
             FROM tickets t
             LEFT JOIN usuarios enc ON enc.id = t.encargado_id
             LEFT JOIN planes p ON p.id = t.plan_id
             WHERE t.cliente_id = :clienteId AND t.plan_id = :planId
             ORDER BY t.fecha_creacion DESC"
        );
        $stmt->execute(['clienteId' => $clienteId, 'planId' => $planId]);
        return $stmt->fetchAll();
    }

    public function historial(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.*, u.nombre AS usuario_nombre
             FROM historial h
             LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.ticket_id = :id ORDER BY h.fecha"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    private function registrar(int $ticketId, ?string $anterior, string $nuevo, ?int $usuarioId, ?string $comentario): void
    {
        $this->db->prepare(
            "INSERT INTO historial (ticket_id, usuario_id, estado_anterior, estado_nuevo, comentario)
             VALUES (:t, :u, :a, :n, :c)"
        )->execute(['t' => $ticketId, 'u' => $usuarioId, 'a' => $anterior, 'n' => $nuevo, 'c' => $comentario]);
    }

    // Resumen para el dashboard (retorna array asociado por estado)
    public function conteoPorEstado(): array
    {
        $filas = $this->db->query("SELECT estado, COUNT(*) AS total FROM tickets GROUP BY estado")->fetchAll();
        $orden = ['nuevo', 'en_curso', 'pausado', 'culminado', 'resuelto', 'cerrado'];
        $resultado = [];
        foreach ($orden as $e) {
            $resultado[$e] = ['estado' => $e, 'total' => 0];
        }
        foreach ($filas as $f) {
            if (isset($resultado[$f['estado']])) {
                $resultado[$f['estado']]['total'] = (int) $f['total'];
            }
        }
        return $resultado;
    }

    public function total(string $campo): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) AS t FROM tickets WHERE estado IN ('resuelto','cerrado')");
        if ($campo === 'resueltos') {
            return (int) $stmt->fetch()['t'];
        }
        return (int) $this->db->query("SELECT COUNT(*) AS t FROM tickets")->fetch()['t'];
    }
}
