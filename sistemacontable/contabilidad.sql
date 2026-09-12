-- =====================================================
-- Aura Automation & Analytics - Módulo Contable
-- Sistema contable independiente
-- Base de datos: contabilidad
-- Ejecutar via el navegador: instalador.php  (recomendado)
--   o manualmente:  mysql -u root -p < contabilidad.sql
-- =====================================================

DROP DATABASE IF EXISTS contabilidad;
CREATE DATABASE contabilidad
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE contabilidad;

-- -----------------------------------------------------
-- Plan de cuentas
-- -----------------------------------------------------
CREATE TABLE cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(10) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    tipo ENUM('activo','pasivo','patrimonio','ingreso','egreso') NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- Asientos contables (cabecera)
-- -----------------------------------------------------
CREATE TABLE asientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero INT NOT NULL UNIQUE,
    fecha DATE NOT NULL,
    glosa VARCHAR(255) NOT NULL,
    estado ENUM('borrador','registrado') NOT NULL DEFAULT 'borrador',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- Detalle de asientos (debe / haber)
-- -----------------------------------------------------
CREATE TABLE asiento_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asiento_id INT NOT NULL,
    cuenta_id INT NOT NULL,
    debe DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    haber DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_asc_asiento FOREIGN KEY (asiento_id) REFERENCES asientos(id) ON DELETE CASCADE,
    CONSTRAINT fk_asc_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id)
);

-- -----------------------------------------------------
-- Documentos / boletas (comprobantes registrados)
-- importe = base imponible  |  igv = 18% de importe  |  total = importe + igv
-- -----------------------------------------------------
CREATE TABLE documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(20) NOT NULL DEFAULT 'BOLETA',
    numero VARCHAR(30) NOT NULL,
    fecha DATE NOT NULL,
    emisor VARCHAR(150) NOT NULL,
    ruc_dni VARCHAR(20) NOT NULL,
    concepto VARCHAR(255) NOT NULL,
    importe DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igv DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    archivo_guardado VARCHAR(255) DEFAULT NULL,
    archivo_original VARCHAR(255) DEFAULT NULL,
    estado ENUM('registrado','anulado') NOT NULL DEFAULT 'registrado',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- Presupuesto por cuenta-año (presupuesto vs real)
-- -----------------------------------------------------
CREATE TABLE presupuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_id INT NOT NULL,
    anio INT NOT NULL DEFAULT 2026,
    monto DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    UNIQUE KEY uq_cuenta_anio (cuenta_id, anio),
    CONSTRAINT fk_pres_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Auditoria: log de cambios (quién editó qué y cuándo)
-- -----------------------------------------------------
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabla VARCHAR(50) NOT NULL,
    registro_id INT NOT NULL,
    accion ENUM('crear','editar','eliminar') NOT NULL,
    resumen VARCHAR(255) DEFAULT NULL,
    datos_anteriores TEXT DEFAULT NULL,
    datos_nuevos TEXT DEFAULT NULL,
    usuario VARCHAR(100) DEFAULT 'sistema',
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- Datos semilla - Plan de cuentas (referencia Perú)
-- -----------------------------------------------------
INSERT INTO cuentas (codigo, nombre, tipo, descripcion) VALUES
('10', 'Caja y bancos', 'activo', 'Efectivo y cuentas bancarias'),
('101', 'Caja', 'activo', 'Efectivo en caja'),
('104', 'Cuentas corrientes', 'activo', 'Bancos: cuentas corrientes'),
('12', 'Cuentas por cobrar comerciales', 'activo', 'Clientes y facturas por cobrar'),
('121', 'Facturas por cobrar', 'activo', 'Clientes terceros'),
('141', 'Cuentas por cobrar diversas', 'activo', 'Otras cuentas por cobrar'),
('20', 'Mercaderías', 'activo', 'Existencias para la venta'),
('33', 'Inmuebles, maquinaria y equipo', 'activo', 'Activo fijo'),
('40', 'Tributos por pagar', 'pasivo', 'IGV, renta y otros tributos'),
('401', 'IGV por pagar', 'pasivo', 'IGV 18%'),
('403', 'Renta por pagar', 'pasivo', 'Renta de quinta categoría'),
('42', 'Cuentas por pagar comerciales', 'pasivo', 'Proveedores'),
('421', 'Facturas por pagar', 'pasivo', 'Proveedores terceros'),
('45', 'Obligaciones financieras', 'pasivo', 'Préstamos y financiamiento'),
('46', 'Cuentas por pagar diversas', 'pasivo', 'Obligaciones varias'),
('50', 'Capital', 'patrimonio', 'Capital social'),
('59', 'Resultados acumulados', 'patrimonio', 'Resultados no distribuidos'),
('60', 'Compras', 'egreso', 'Compras de mercaderías'),
('601', 'Mercaderías', 'egreso', 'Compras de mercaderías'),
('62', 'Gastos de personal', 'egreso', 'Sueldos y salarios'),
('63', 'Servicios prestados por terceros', 'egreso', 'Servicios contratados'),
('64', 'Tributos', 'egreso', 'Impuestos y contribuciones'),
('70', 'Ventas', 'ingreso', 'Ventas de mercaderías'),
('701', 'Mercaderías', 'ingreso', 'Ventas de mercaderías'),
('707', 'Prestación de servicios', 'ingreso', 'Servicios brindados'),
('75', 'Otros ingresos', 'ingreso', 'Ingresos diversos'),
('94', 'Gastos administrativos', 'egreso', 'Gastos de administración');

-- -----------------------------------------------------
-- Datos semilla - Asientos de ejemplo equilibrados
-- -----------------------------------------------------
INSERT INTO asientos (numero, fecha, glosa, estado) VALUES
(1, '2026-01-15', 'Apertura: aporte de capital en efectivo', 'registrado'),
(2, '2026-01-16', 'Apertura: capital pendiente de depósito en banco', 'registrado'),
(3, '2026-01-31', 'Venta de mercadería al contado (venta 01)', 'registrado'),
(4, '2026-01-31', 'Compra de mercadería a crédito (compra 01)', 'registrado'),
(5, '2026-02-28', 'Pago de sueldos del personal', 'registrado'),
(6, '2026-02-28', 'Servicio de transporte contratado', 'registrado'),
(7, '2026-03-15', 'Venta de mercadería a crédito (venta 02)', 'registrado'),
(8, '2026-03-31', 'Cobro de venta 02 y pago de deuda a proveedor', 'registrado');

INSERT INTO asiento_detalle (asiento_id, cuenta_id, debe, haber) VALUES
-- Asiento 1: Aporte en efectivo
(1, (SELECT id FROM cuentas WHERE codigo='101'), 100000.00, 0.00),
(1, (SELECT id FROM cuentas WHERE codigo='50'), 0.00, 100000.00),
-- Asiento 2: Depósito al banco
(2, (SELECT id FROM cuentas WHERE codigo='104'), 90000.00, 0.00),
(2, (SELECT id FROM cuentas WHERE codigo='101'), 0.00, 90000.00),
-- Asiento 3: Venta al contado (base 4000 + IGV 720)
(3, (SELECT id FROM cuentas WHERE codigo='101'), 4720.00, 0.00),
(3, (SELECT id FROM cuentas WHERE codigo='401'), 0.00, 720.00),
(3, (SELECT id FROM cuentas WHERE codigo='701'), 0.00, 4000.00),
-- Asiento 4: Compra a crédito (base 2500 + IGV 450)
(4, (SELECT id FROM cuentas WHERE codigo='601'), 2500.00, 0.00),
(4, (SELECT id FROM cuentas WHERE codigo='401'), 450.00, 0.00),
(4, (SELECT id FROM cuentas WHERE codigo='421'), 0.00, 2950.00),
-- Asiento 5: Sueldos
(5, (SELECT id FROM cuentas WHERE codigo='62'), 5000.00, 0.00),
(5, (SELECT id FROM cuentas WHERE codigo='101'), 0.00, 5000.00),
-- Asiento 6: Servicio de transporte
(6, (SELECT id FROM cuentas WHERE codigo='63'), 1200.00, 0.00),
(6, (SELECT id FROM cuentas WHERE codigo='104'), 0.00, 1200.00),
-- Asiento 7: Venta a crédito (base 8000 + IGV 1440)
(7, (SELECT id FROM cuentas WHERE codigo='121'), 9440.00, 0.00),
(7, (SELECT id FROM cuentas WHERE codigo='401'), 0.00, 1440.00),
(7, (SELECT id FROM cuentas WHERE codigo='701'), 0.00, 8000.00),
-- Asiento 8: Cobro venta 02 y pago proveedor
(8, (SELECT id FROM cuentas WHERE codigo='104'), 9440.00, 0.00),
(8, (SELECT id FROM cuentas WHERE codigo='121'), 0.00, 9440.00),
(8, (SELECT id FROM cuentas WHERE codigo='421'), 2950.00, 0.00),
(8, (SELECT id FROM cuentas WHERE codigo='104'), 0.00, 2950.00);

-- -----------------------------------------------------
-- Datos semilla - Presupuestos 2026 (por cuenta de ingreso/egreso)
-- -----------------------------------------------------
INSERT INTO presupuestos (cuenta_id, anio, monto) VALUES
((SELECT id FROM cuentas WHERE codigo='70'),  2026, 240000.00),
((SELECT id FROM cuentas WHERE codigo='60'),  2026, 130000.00),
((SELECT id FROM cuentas WHERE codigo='62'),  2026, 60000.00),
((SELECT id FROM cuentas WHERE codigo='63'),  2026, 20000.00),
((SELECT id FROM cuentas WHERE codigo='94'),  2026, 15000.00);

-- -----------------------------------------------------
-- Datos semilla - Boletas / documentos de ejemplo
-- -----------------------------------------------------
INSERT INTO documentos (tipo, numero, fecha, emisor, ruc_dni, concepto, importe, igv, total, estado) VALUES
('BOLETA',  'B001-000001', '2026-01-10', 'Textil Rojas S.A.C.',   '20123456789', 'Venta de mercadería - bolsa de telas',          1000.00, 180.00, 1180.00, 'registrado'),
('FACTURA', 'F001-000010', '2026-01-12', 'Vargas Logística',      '20598765432', 'Servicio de transporte de mercadería',         2000.00, 360.00, 2360.00, 'registrado'),
('BOLETA',  'B001-000002', '2026-01-18', 'Chamitos S.A.C.',       '20612345678', 'Venta de equipos de cómputo',                   500.00,  90.00, 590.00,  'registrado'),
('FACTURA', 'F001-000011', '2026-01-20', 'Prada Constructora',    '20544455666', 'Consultoría en automatización de procesos',    3000.00, 540.00, 3540.00, 'registrado'),
('FACTURA', 'F001-000012', '2026-02-05', 'AgroExport del Norte',  '20678901234', 'Servicio de analytics y reportes',             2400.00, 432.00, 2832.00, 'registrado'),
('BOLETA',  'B001-000003', '2026-02-17', 'Reyes & Asociados',     '20111122333', 'Venta de licencias de software',               1500.00, 270.00, 1770.00, 'registrado'),
('FACTURA', 'F001-000013', '2026-03-03', 'Hotel Mirador del Valle','20434567890','Automatización de procesos',                   5000.00, 900.00, 5900.00, 'registrado'),
('FACTURA', 'F001-000014', '2026-03-21', 'Distribuidora Omega',   '20312345678', 'Consultoría en integración de sistemas',       3600.00, 648.00, 4248.00, 'registrado');