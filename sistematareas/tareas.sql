-- =====================================================
-- Aura Automation & Analytics - Sistema de Gestión de Tareas
-- Sistema de tareas independiente (AJAX)
-- Base de datos: tareas
-- IMPORTANTE: Este archivo ya NO es necesario. El sistema se autoinstala al
-- abrirse por primera vez (config/db.php crea la BD, tablas y datos de ejemplo).
-- Solo úsalo si quieres reinstalar manualmente:
--   mysql -u root -p < tareas.sql
-- =====================================================

DROP DATABASE IF EXISTS tareas;
CREATE DATABASE tareas
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tareas;

-- -----------------------------------------------------
-- Personal (miembros del equipo que reciben tareas)
-- -----------------------------------------------------
CREATE TABLE personal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    cargo VARCHAR(80) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- Tareas
-- -----------------------------------------------------
CREATE TABLE tareas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    prioridad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    estado ENUM('pendiente','en_proceso','completada') NOT NULL DEFAULT 'pendiente',
    fecha_vencimiento DATE DEFAULT NULL,
    responsable_id INT DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tareas_personal FOREIGN KEY (responsable_id) REFERENCES personal(id) ON DELETE SET NULL
);

-- -----------------------------------------------------
-- Auditoría (quién creó / editó / eliminó)
-- -----------------------------------------------------
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabla VARCHAR(50) NOT NULL,
    registro_id INT NOT NULL,
    accion VARCHAR(20) NOT NULL,
    resumen VARCHAR(255) DEFAULT NULL,
    datos_anteriores JSON DEFAULT NULL,
    datos_nuevos JSON DEFAULT NULL,
    usuario VARCHAR(100) DEFAULT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- Datos de ejemplo
-- -----------------------------------------------------
INSERT INTO tareas (titulo, descripcion, prioridad, estado, fecha_vencimiento) VALUES
('Configurar servidor de producción', 'Revisar Apache, PHP 8.2 y variables de entorno del servidor.', 'alta', 'en_proceso', CURDATE() + INTERVAL 3 DAY),
('Diseñar plantilla de reporte mensual', 'Incluir gráficos de ingresos y gastos por sucursal.', 'media', 'pendiente', CURDATE() + INTERVAL 7 DAY),
('Capacitar al equipo de ventas', 'Sesión de 2 horas sobre el nuevo módulo de facturación.', 'baja', 'pendiente', CURDATE() + INTERVAL 14 DAY),
('Corregir error en importación CSV', 'El separador de miles rompe la importación de documentos.', 'critica', 'en_proceso', CURDATE()),
('Documentar API de pagos', 'Generar la documentación en formato OpenAPI.', 'media', 'completada', CURDATE() - INTERVAL 2 DAY),
('Respaldado semanal de bases de datos', 'Verificar integridad de los respaldos automáticos.', 'alta', 'completada', CURDATE() - INTERVAL 1 DAY);
