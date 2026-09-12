-- =====================================================
-- Migración: hilo de comentarios soporte <-> cliente
-- Ejecutar una sola vez sobre la BD existente:
--   mysql -u root -p1234 aura < migracion_comentarios.sql
-- =====================================================
CREATE TABLE IF NOT EXISTS comentarios_ticket (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    usuario_id INT DEFAULT NULL,
    cliente_id INT DEFAULT NULL,
    mensaje VARCHAR(2000) NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);