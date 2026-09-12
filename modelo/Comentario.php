<?php
require_once __DIR__ . '/../config/database.php';

class Comentario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    public function listar(int $ticketId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, COALESCE(u.nombre, cl.empresa) AS autor_nombre,
                    COALESCE(u.rol, 'cliente') AS autor_rol
             FROM comentarios_ticket c
             LEFT JOIN usuarios u ON u.id = c.usuario_id
             LEFT JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.ticket_id = :ticket_id
             ORDER BY c.fecha ASC, c.id ASC"
        );
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll();
    }

    public function crear(int $ticketId, ?int $usuarioId, ?int $clienteId, string $mensaje): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO comentarios_ticket (ticket_id, usuario_id, cliente_id, mensaje)
             VALUES (:ticket_id, :usuario_id, :cliente_id, :mensaje)"
        );
        $stmt->execute([
            'ticket_id' => $ticketId,
            'usuario_id' => $usuarioId,
            'cliente_id' => $clienteId,
            'mensaje' => $mensaje,
        ]);
        return (int) $this->db->lastInsertId();
    }
}