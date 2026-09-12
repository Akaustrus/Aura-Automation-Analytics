<?php
require_once __DIR__ . '/../config/database.php';

class Satisfaccion
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    public function registrar(int $ticketId, int $clienteId, string $nivel, ?string $comentario): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO satisfacciones (ticket_id, cliente_id, nivel, comentario)
             VALUES (:ticket_id, :cliente_id, :nivel, :comentario)"
        );
        $stmt->execute([
            'ticket_id' => $ticketId,
            'cliente_id' => $clienteId,
            'nivel' => $nivel,
            'comentario' => $comentario,
        ]);
    }

    public function porTicket(int $ticketId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM satisfacciones WHERE ticket_id = :ticket_id ORDER BY fecha DESC LIMIT 1");
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetch() ?: null;
    }

    public function promedio(): ?float
    {
        $pesos = [
            'muy_insatisfecho' => 1,
            'insatisfecho' => 2,
            'neutral' => 3,
            'satisfecho' => 4,
            'muy_satisfecho' => 5,
        ];
        $filas = $this->db->query("SELECT nivel FROM satisfacciones")->fetchAll();
        if (empty($filas)) {
            return null;
        }
        $suma = 0;
        foreach ($filas as $f) {
            $suma += $pesos[$f['nivel']] ?? 3;
        }
        return round($suma / count($filas), 1);
    }

    public function conteoPorNivel(): array
    {
        return $this->db->query("SELECT nivel, COUNT(*) AS total FROM satisfacciones GROUP BY nivel")->fetchAll();
    }
}
