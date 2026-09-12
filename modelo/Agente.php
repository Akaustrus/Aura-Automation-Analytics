<?php
require_once __DIR__ . '/../config/database.php';

class Agente
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    public function listar(): array
    {
        return $this->db
            ->query("SELECT id, nombre, email, rol FROM usuarios WHERE activo = 1 ORDER BY nombre")
            ->fetchAll();
    }

    public function porEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE email = :email AND activo = 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }
}
