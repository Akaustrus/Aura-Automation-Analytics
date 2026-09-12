<?php
require_once __DIR__ . '/../config/database.php';

class Cliente
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    public function listar(): array
    {
        return $this->db->query("SELECT * FROM clientes ORDER BY empresa")->fetchAll();
    }

    /** Lista las empresas con sus suscripciones activas (para el panel interno). */
    public function listarConPlanes(): array
    {
        return $this->db->query(
            "SELECT c.*,
                    COUNT(cp.id) AS total_planes,
                    SUM(cp.estado = 'activo') AS planes_activos,
                    (SELECT COUNT(*) FROM tickets t WHERE t.cliente_id = c.id) AS total_tickets
             FROM clientes c
             LEFT JOIN cliente_planes cp ON cp.cliente_id = c.id
             GROUP BY c.id
             ORDER BY c.empresa"
        )->fetchAll();
    }

    /** Obtiene una empresa por su id. */
    public function obtener(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM clientes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Actualiza los datos de una empresa. */
    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            "UPDATE clientes SET
                empresa = :empresa,
                ruc = :ruc,
                email = :email,
                telefono = :telefono,
                contacto = :contacto
             WHERE id = :id"
        );
        $stmt->execute([
            'empresa' => $datos['empresa'],
            'ruc' => $datos['ruc'],
            'email' => $datos['email'],
            'telefono' => $datos['telefono'],
            'contacto' => $datos['contacto'],
            'id' => $id,
        ]);
    }

    /** Elimina una empresa (y por FK sus suscripciones). */
    public function eliminar(int $id): void
    {
        $this->db->prepare("DELETE FROM clientes WHERE id = :id")->execute(['id' => $id]);
    }

    /** Actualiza la clave de acceso de la empresa (para regenerarla/verla). */
    public function actualizarClave(int $id, string $clave): void
    {
        $this->db->prepare("UPDATE clientes SET clave = :clave WHERE id = :id")
            ->execute(['clave' => $clave, 'id' => $id]);
    }

    /** Busca un cliente por su RUC (dato que da por teléfono). */
    public function porRuc(string $ruc): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM clientes WHERE ruc = :ruc LIMIT 1");
        $stmt->execute(['ruc' => $ruc]);
        return $stmt->fetch() ?: null;
    }

    /** Valida la clave que el cliente da por teléfono. */
    public function validarClave(string $ruc, string $clave): ?array
    {
        $cliente = $this->porRuc($ruc);
        if ($cliente && password_verify($clave, $cliente['clave'])) {
            return $cliente;
        }
        return null;
    }

    /** Crea una empresa cliente (nuevo registro por teléfono). */
    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO clientes (empresa, ruc, clave, email, telefono, contacto)
             VALUES (:empresa, :ruc, :clave, :email, :telefono, :contacto)"
        );
        $stmt->execute($datos);
        return (int) $this->db->lastInsertId();
    }

    /** Verifica si un RUC ya está registrado. */
    public function existeRuc(string $ruc): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM clientes WHERE ruc = :ruc LIMIT 1");
        $stmt->execute(['ruc' => $ruc]);
        return (bool) $stmt->fetch();
    }

    /** Autenticación para el portal del cliente (RUC + clave). */
    public function validarPortal(string $ruc, string $clave): ?array
    {
        return $this->validarClave($ruc, $clave);
    }
}
