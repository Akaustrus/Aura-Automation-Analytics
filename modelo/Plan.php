<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Modelo de los sistemas que vendemos (planes) y las suscripciones
 * de cada empresa (cliente_planes).
 */
class Plan
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    public const ESTADOS = ['activo', 'pendiente', 'vencido', 'cancelado'];

    public function tieneEstado(string $e): bool
    {
        return in_array($e, self::ESTADOS, true);
    }

    /** Catálogo completo de los 5 sistemas. */
    public function listar(): array
    {
        return $this->db->query("SELECT * FROM planes WHERE activo = 1 ORDER BY orden, nombre")->fetchAll();
    }

    public function listarTodos(): array
    {
        return $this->db->query("SELECT * FROM planes ORDER BY orden, nombre")->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM planes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function porSlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM planes WHERE slug = :slug LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() ?: null;
    }

    /** Cuántas empresas usan cada plan (para el catálogo interno). */
    public function conteoPorPlan(): array
    {
        return $this->db->query(
            "SELECT p.id, p.nombre, COUNT(cp.id) AS empresas, SUM(cp.estado = 'activo') AS activas
             FROM planes p
             LEFT JOIN cliente_planes cp ON cp.plan_id = p.id
             GROUP BY p.id, p.nombre
             ORDER BY p.orden, p.nombre"
        )->fetchAll();
    }

    /** Suscripciones de una empresa, con los datos del plan. */
    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cp.*, p.nombre AS plan_nombre, p.slug AS plan_slug, p.icono,
                    p.modulos AS plan_modulos, p.modulo AS plan_modulo, p.descripcion AS plan_descripcion,
                    (SELECT COUNT(*) FROM tickets t WHERE t.cliente_id = cp.cliente_id AND t.plan_id = cp.plan_id) AS tickets
             FROM cliente_planes cp
             JOIN planes p ON p.id = cp.plan_id
             WHERE cp.cliente_id = :clienteId
             ORDER BY p.orden, p.nombre"
        );
        $stmt->execute(['clienteId' => $clienteId]);
        return $stmt->fetchAll();
    }

    /** Todos los planes que la empresa NO tiene aún (para asignar). */
    public function disponiblesPara(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM planes p
             WHERE p.activo = 1 AND p.id NOT IN (
                 SELECT cp.plan_id FROM cliente_planes cp WHERE cp.cliente_id = :clienteId
             )
             ORDER BY p.orden, p.nombre"
        );
        $stmt->execute(['clienteId' => $clienteId]);
        return $stmt->fetchAll();
    }

    /** Suscripción concreta de una empresa para un plan. */
    public function suscripcion(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT cp.*, p.nombre AS plan_nombre, p.slug AS plan_slug, p.icono, p.modulos AS plan_modulos,
                    p.modulo AS plan_modulo, p.descripcion AS plan_descripcion,
                    c.empresa AS cliente_empresa, c.ruc AS cliente_ruc
             FROM cliente_planes cp
             JOIN planes p ON p.id = cp.plan_id
             JOIN clientes c ON c.id = cp.cliente_id
             WHERE cp.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Asigna un plan a una empresa. Devuelve el id de la suscripción. */
    public function asignar(array $datos): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO cliente_planes (cliente_id, plan_id, estado, usuarios, precio_mensual, fecha_inicio, fecha_vencimiento, notas)
             VALUES (:cliente_id, :plan_id, :estado, :usuarios, :precio, :fecha_inicio, :fecha_vencimiento, :notas)"
        );
        $stmt->execute([
            'cliente_id' => $datos['cliente_id'],
            'plan_id' => $datos['plan_id'],
            'estado' => $datos['estado'],
            'usuarios' => $datos['usuarios'],
            'precio' => $datos['precio_mensual'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_vencimiento' => $datos['fecha_vencimiento'],
            'notas' => $datos['notas'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Actualiza una suscripción (plan de una empresa). */
    public function actualizar(int $id, array $datos): void
    {
        $this->db->prepare(
            "UPDATE cliente_planes SET
                plan_id = :plan_id,
                estado = :estado,
                usuarios = :usuarios,
                precio_mensual = :precio,
                fecha_inicio = :fecha_inicio,
                fecha_vencimiento = :fecha_vencimiento,
                notas = :notas
             WHERE id = :id"
        )->execute([
            'plan_id' => $datos['plan_id'],
            'estado' => $datos['estado'],
            'usuarios' => $datos['usuarios'],
            'precio' => $datos['precio_mensual'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_vencimiento' => $datos['fecha_vencimiento'],
            'notas' => $datos['notas'],
            'id' => $id,
        ]);
    }

    /** Quita una suscripción (ya no usa ese sistema). */
    public function eliminar(int $id): void
    {
        $this->db->prepare("DELETE FROM cliente_planes WHERE id = :id")->execute(['id' => $id]);
    }

    /** ¿La empresa ya tiene asignado ese plan? */
    public function tiene(int $clienteId, int $planId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM cliente_planes WHERE cliente_id = :c AND plan_id = :p LIMIT 1"
        );
        $stmt->execute(['c' => $clienteId, 'p' => $planId]);
        return (bool) $stmt->fetch();
    }

    /** ¿Esa empresa usa ese sistema? (para las incidencias de cada plan). */
    public function planDeCliente(int $planId, int $clienteId): bool
    {
        return $this->tiene($clienteId, $planId);
    }

    /** Conteos para el panel interno (empresas por estado de suscripción). */
    public function conteoPorEstado(): array
    {
        $filas = $this->db->query(
            "SELECT estado, COUNT(*) AS total FROM cliente_planes GROUP BY estado"
        )->fetchAll();
        $resultado = ['activo' => 0, 'pendiente' => 0, 'vencido' => 0, 'cancelado' => 0];
        foreach ($filas as $f) {
            if (isset($resultado[$f['estado']])) {
                $resultado[$f['estado']] = (int) $f['total'];
            }
        }
        return $resultado;
    }
}