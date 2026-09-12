<?php
require_once __DIR__ . '/../config/database.php';

class Cita
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    /** Cuántas citas activas ya ocupan un slot en una fecha dada. */
    private function ocupadas(int $slotId, string $fecha): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS t FROM citas WHERE slot_id = :slot AND fecha = :fecha AND estado IN ('reservada','confirmada')"
        );
        $stmt->execute(['slot' => $slotId, 'fecha' => $fecha]);
        return (int) $stmt->fetch()['t'];
    }

    /**
     * Slots de un día de la semana con su disponibilidad para una fecha concreta.
     * Devuelve cupos, ocupados y disponibles (>=1 = libre).
     */
    public function slotsDisponibles(int $diaSemana, string $fecha): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM horarios_slot WHERE dia_semana = :dia AND activo = 1 ORDER BY hora_inicio"
        );
        $stmt->execute(['dia' => $diaSemana]);
        $slots = $stmt->fetchAll();

        foreach ($slots as &$s) {
            $ocupados = $this->ocupadas((int) $s['id'], $fecha);
            $s['ocupados'] = $ocupados;
            $s['disponibles'] = max(0, (int) $s['cupos'] - $ocupados);
            $s['libre'] = $s['disponibles'] > 0;
        }
        return $slots;
    }

    /** Reserva una cita si el slot aún tiene cupo (evita sobre-reserva). */
    public function reservar(int $clienteId, int $slotId, string $fecha, string $modalidad, ?string $asunto): ?int
    {
        $slot = $this->db->prepare("SELECT * FROM horarios_slot WHERE id = :id AND activo = 1");
        $slot->execute(['id' => $slotId]);
        $s = $slot->fetch();
        if (!$s) {
            return null;
        }

        $this->db->beginTransaction();
        try {
            if ($this->ocupadas($slotId, $fecha) >= (int) $s['cupos']) {
                $this->db->rollBack();
                return null;
            }
            $ins = $this->db->prepare(
                "INSERT INTO citas (cliente_id, slot_id, fecha, modalidad, asunto)
                 VALUES (:cliente_id, :slot_id, :fecha, :modalidad, :asunto)"
            );
            $ins->execute([
                'cliente_id' => $clienteId,
                'slot_id' => $slotId,
                'fecha' => $fecha,
                'modalidad' => $modalidad,
                'asunto' => $asunto,
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (Exception $e) {
            $this->db->rollBack();
            // Duplicado (cliente ya reservó ese mismo horario) se trata como "ya ocupado".
            return null;
        }
    }

    /** Citas de un cliente. */
    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, h.hora_inicio, h.hora_fin, h.cupos
             FROM citas c
             JOIN horarios_slot h ON h.id = c.slot_id
             WHERE c.cliente_id = :cliente
             ORDER BY c.fecha DESC, h.hora_inicio DESC"
        );
        $stmt->execute(['cliente' => $clienteId]);
        return $stmt->fetchAll();
    }

    /** Agenda de todas las citas para una fecha (para el panel interno). */
    public function porFecha(string $fecha): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, h.hora_inicio, h.hora_fin, cl.empresa AS cliente_nombre, cl.ruc
             FROM citas c
             JOIN horarios_slot h ON h.id = c.slot_id
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.fecha = :fecha AND c.estado <> 'cancelada'
             ORDER BY h.hora_inicio"
        );
        $stmt->execute(['fecha' => $fecha]);
        return $stmt->fetchAll();
    }

    /** Próximas citas (para el panel interno). */
    public function proximas(int $limite = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, h.hora_inicio, h.hora_fin, cl.empresa AS cliente_nombre, cl.ruc
             FROM citas c
             JOIN horarios_slot h ON h.id = c.slot_id
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.fecha >= CURDATE() AND c.estado <> 'cancelada'
             ORDER BY c.fecha, h.hora_inicio
             LIMIT :limite"
        );
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Cambia el estado de una cita (confirmada/realizada/cancelada). */
    public function cambiarEstado(int $id, string $estado): void
    {
        $this->db->prepare("UPDATE citas SET estado = :estado WHERE id = :id")
            ->execute(['estado' => $estado, 'id' => $id]);
    }
}
