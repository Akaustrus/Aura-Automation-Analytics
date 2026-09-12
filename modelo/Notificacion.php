<?php
require_once __DIR__ . '/../config/database.php';

class Notificacion
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    public function crear(int $usuarioId, int $ticketId, string $mensaje): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO notificaciones (usuario_id, ticket_id, mensaje)
             VALUES (:usuario_id, :ticket_id, :mensaje)"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'ticket_id' => $ticketId, 'mensaje' => $mensaje]);
    }

    /** Notifica que un encargado culminó su trabajo, a los demás admins/encargados. */
    public function notificarCulminado(int $ticketId, string $encargadoNombre, string $asunto): void
    {
        $excepto = (int) ($_SESSION['usuario_id'] ?? 0);
        $stmt = $this->db->prepare(
            "SELECT id FROM usuarios WHERE rol IN ('admin', 'encargado') AND id <> :excepto"
        );
        $stmt->execute(['excepto' => $excepto]);
        $filas = $stmt->fetchAll();
        foreach ($filas as $f) {
            $this->crear((int) $f['id'], $ticketId, "El encargado " . $encargadoNombre . " culminó el ticket #" . $ticketId . ": " . $asunto . ". Revisa y cierra.");
        }
    }

    /** Notifica a todos los admins/encargados (excepto quien genera la acción) que se creó un ticket nuevo. */
    public function notificarNuevo(int $ticketId, string $asunto, string $prioridad): void
    {
        $excepto = (int) ($_SESSION['usuario_id'] ?? 0);
        $stmt = $this->db->prepare(
            "SELECT id FROM usuarios WHERE rol IN ('admin', 'encargado') AND id <> :excepto"
        );
        $stmt->execute(['excepto' => $excepto]);
        $filas = $stmt->fetchAll();
        foreach ($filas as $f) {
            $this->crear((int) $f['id'], $ticketId, "Nuevo ticket #" . $ticketId . " (" . ucfirst($prioridad) . "): " . $asunto . ".");
        }
    }

    /** Notifica a todos los admins/encargados (excepto quien ejecuta) un cambio de estado de un ticket. */
    public function notificarCambio(int $ticketId, string $estado, string $asunto, ?string $comentario = null): void
    {
        $excepto = (int) ($_SESSION['usuario_id'] ?? 0);
        $stmt = $this->db->prepare(
            "SELECT id FROM usuarios WHERE rol IN ('admin', 'encargado') AND id <> :excepto"
        );
        $stmt->execute(['excepto' => $excepto]);
        $filas = $stmt->fetchAll();
        foreach ($filas as $f) {
            $texto = "El ticket #" . $ticketId . " pasó a '" . ucfirst(str_replace('_', ' ', $estado)) . "': " . $asunto . ".";
            if ($comentario) {
                $texto .= " " . $comentario;
            }
            $this->crear((int) $f['id'], $ticketId, $texto);
        }
    }

    /** Crea una notificación de confirmación para el propio usuario que marcó el ticket como culminado. */
    public function notificarCulminacionPropia(int $ticketId, string $asunto): void
    {
        $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($usuarioId <= 0) {
            return;
        }
        $this->crear($usuarioId, $ticketId, "Marcaste el ticket #" . $ticketId . " como culminado: " . $asunto . ".");
    }

    public function listarNoLeidas(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT n.*, t.asunto FROM notificaciones n
             JOIN tickets t ON t.id = n.ticket_id
             WHERE n.usuario_id = :usuario_id ORDER BY n.fecha DESC"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }

    public function contarNoLeidas(int $usuarioId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS t FROM notificaciones WHERE usuario_id = :usuario_id AND leida = 0");
        $stmt->execute(['usuario_id' => $usuarioId]);
        return (int) $stmt->fetch()['t'];
    }

    public function marcarLeida(int $id, int $usuarioId): void
    {
        $this->db->prepare(
            "UPDATE notificaciones SET leida = 1 WHERE id = :id AND usuario_id = :usuario_id"
        )->execute(['id' => $id, 'usuario_id' => $usuarioId]);
    }

    public function marcarTodasLeidas(int $usuarioId): void
    {
        $this->db->prepare(
            "UPDATE notificaciones SET leida = 1 WHERE usuario_id = :usuario_id AND leida = 0"
        )->execute(['usuario_id' => $usuarioId]);
    }
}
