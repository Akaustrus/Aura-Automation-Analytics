-- =====================================================
-- Migración: gestión de los 5 SISTEMAS (planes) por empresa
-- Convierte el sistema en un ERP que gestiona las soluciones
-- que vendemos (Contabilidad, Planilla, Facturación, ERP Básico,
-- ERP Mediana Empresa) para cada empresa cliente.
--
-- Ejecutar una sola vez sobre la BD existente (
--   mysql -u root -p1234 softtech < migracion_planes.sql
-- )
-- =====================================================

-- -----------------------------------------------------
-- Tabla: planes  (catálogo de los 5 sistemas que vendemos)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(60) NOT NULL UNIQUE,
    modulo VARCHAR(80) NOT NULL,
    descripcion VARCHAR(300) NOT NULL,
    icono VARCHAR(60) NOT NULL DEFAULT 'fa-box',
    modulos TINYINT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden INT NOT NULL DEFAULT 0
);

-- -----------------------------------------------------
-- Tabla: cliente_planes  (suscripción de una empresa a un sistema)
-- estado: activo | pendiente | vencido | cancelado
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS cliente_planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    plan_id INT NOT NULL,
    estado ENUM('activo', 'pendiente', 'vencido', 'cancelado') NOT NULL DEFAULT 'activo',
    usuarios INT NOT NULL DEFAULT 1,
    precio_mensual DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fecha_inicio DATE DEFAULT NULL,
    fecha_vencimiento DATE DEFAULT NULL,
    notas VARCHAR(500) DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cliente_plan (cliente_id, plan_id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES planes(id) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Columna plan_id en tickets: a qué sistema pertenece la incidencia
-- -----------------------------------------------------
ALTER TABLE tickets ADD COLUMN plan_id INT DEFAULT NULL AFTER cliente_id;

ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_plan FOREIGN KEY (plan_id) REFERENCES planes(id) ON DELETE SET NULL;

-- -----------------------------------------------------
-- Datos semilla - Catálogo de los 5 sistemas
-- -----------------------------------------------------
INSERT INTO planes (nombre, slug, modulo, descripcion, icono, modulos, activo, orden) VALUES
('Contabilidad',             'contabilidad',    'sistemacontable',    'Plan de cuentas, asientos, documentos con IGV, libro mayor, balance, estados financieros, presupuestos y reportes.', 'fa-calculator',           11, 1, 1),
('Facturación',              'facturacion',     'sistemafacturacion', 'Clientes, productos/servicios y comprobantes de venta con IGV configurable, correlativos automáticos y PDF.',      'fa-file-invoice-dollar',   8, 1, 2),
('Planilla',                 'planilla',        'sistemaplanilla',    'RRHH completo: empleados, asistencia, adelantos, planillas mensuales, gratificaciones, CTS y boletas.',          'fa-users-gear',           10, 1, 3),
('ERP Básico',               'erp-basico',      'sistemaerp',         'Operación comercial: compras, inventario, ventas y caja para micro empresas.',                                     'fa-industry',             11, 1, 4),
('ERP Mediana Empresa',      'erp-mediana',     'sistemaermedio',     'ERP completo con login y roles: cotización, ventas, cobranzas, compras, multi-almacén y reportes.',                'fa-building',             19, 1, 5);

-- -----------------------------------------------------
-- Datos semilla - Suscripciones de las empresas de prueba
-- -----------------------------------------------------
INSERT INTO cliente_planes (cliente_id, plan_id, estado, usuarios, precio_mensual, fecha_inicio, fecha_vencimiento, notas) VALUES
(1, 3, 'activo',    5, 250.00, '2026-01-15', '2026-12-31', 'ERP Básico: contado + inventario en producción.'),
(1, 2, 'activo',    3, 150.00, '2026-03-01', '2026-12-31', 'Comprobantes de boleta/factura.'),
(2, 5, 'activo',   10, 800.00, '2026-02-01', '2026-12-31', 'ERP Mediana: todos los módulos con roles.'),
(2, 1, 'activo',    4, 300.00, '2026-02-01', '2026-12-31', 'Plan de cuentas + asientos.'),
(3, 1, 'activo',    6, 300.00, '2026-01-10', '2026-12-31', 'Estado de resultados mensual.'),
(3, 4, 'activo',    8, 550.00, '2026-01-10', '2026-12-31', 'Planilla mensual + CTS.'),
(4, 3, 'pendiente', 3, 250.00, '2026-09-15', '2026-12-31', 'En implementación, capacitación pendiente.'),
(4, 2, 'pendiente', 2, 150.00, '2026-09-15', '2026-12-31', 'Esperando RUC de SUNAT para activar.'),
(5, 5, 'activo',   12, 800.00, '2026-05-01', '2026-12-31', 'Multi-almacén habilitado.'),
(5, 4, 'pendiente', 4, 550.00, '2026-10-01', '2026-12-31', 'Se contrata planilla a partir de octubre.'),
(6, 5, 'activo',    9, 800.00, '2026-04-01', '2026-12-31', 'ERP Mediana en despachos.'),
(6, 1, 'activo',    3, 300.00, '2026-04-01', '2026-12-31', ''),
(7, 3, 'activo',    4, 250.00, '2026-06-01', '2026-12-31', ''),
(8, 5, 'activo',   11, 800.00, '2026-03-15', '2026-12-31', 'Reservas y recepción.'),
(8, 2, 'activo',    5, 150.00, '2026-03-15', '2026-12-31', ''),
(9, 4, 'activo',    6, 550.00, '2026-01-20', '2026-12-31', 'Planilla de vendedores.'),
(9, 1, 'vencido',   2, 300.00, '2026-01-20', '2026-08-31', 'Contabilidad vencida, pendiente de renovar.');

-- -----------------------------------------------------
-- Asocia los tickets existentes al sistema que corresponden
-- -----------------------------------------------------
UPDATE tickets SET plan_id = 3 WHERE id = 1;
UPDATE tickets SET plan_id = 5 WHERE id = 2;
UPDATE tickets SET plan_id = 1 WHERE id = 3;
UPDATE tickets SET plan_id = 4 WHERE id = 4;
UPDATE tickets SET plan_id = 5 WHERE id = 6;
UPDATE tickets SET plan_id = 3 WHERE id = 7;
UPDATE tickets SET plan_id = 2 WHERE id = 8;
UPDATE tickets SET plan_id = 4 WHERE id = 9;