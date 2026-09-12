<?php
require_once __DIR__ . '/../config/database.php';

class Horario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    /** Todos los slots de la grilla, ordenados por día y hora. */
    public function listar(): array
    {
        return $this->db->query(
            "SELECT * FROM horarios_slot ORDER BY dia_semana, hora_inicio"
        )->fetchAll();
    }

    /** Slots activos de un día de la semana. */
    public function porDia(int $diaSemana): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM horarios_slot WHERE dia_semana = :dia ORDER BY hora_inicio"
        );
        $stmt->execute(['dia' => $diaSemana]);
        return $stmt->fetchAll();
    }

    /** Crea o actualiza un slot de la grilla. */
    public function guardar(int $diaSemana, string $horaInicio, string $horaFin, int $cupos, int $activo, ?int $id = null): void
    {
        if ($id) {
            $this->db->prepare(
                "UPDATE horarios_slot SET dia_semana = :dia, hora_inicio = :ini, hora_fin = :fin, cupos = :cupos, activo = :activo WHERE id = :id"
            )->execute(['dia' => $diaSemana, 'ini' => $horaInicio, 'fin' => $horaFin, 'cupos' => $cupos, 'activo' => $activo, 'id' => $id]);
        } else {
            $this->db->prepare(
                "INSERT INTO horarios_slot (dia_semana, hora_inicio, hora_fin, cupos, activo)
                 VALUES (:dia, :ini, :fin, :cupos, :activo)"
            )->execute(['dia' => $diaSemana, 'ini' => $horaInicio, 'fin' => $horaFin, 'cupos' => $cupos, 'activo' => $activo]);
        }
    }

    public function eliminar(int $id): void
    {
        $this->db->prepare("DELETE FROM horarios_slot WHERE id = :id")->execute(['id' => $id]);
    }
}
