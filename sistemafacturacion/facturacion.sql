-- ============================================================
-- SISTEMA DE FACTURACIÓN — Aura Automation & Analytics
-- Base de datos: facturacion
-- Ejecutar vía el navegador: instalador.php (recomendado)
--   o manualmente:  mysql -u root -p < facturacion.sql
-- ============================================================

DROP DATABASE IF EXISTS facturacion;
CREATE DATABASE facturacion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE facturacion;

-- ------------------------------------------------------------
-- Parámetros del sistema (empresa emisora y configuraciones,
-- editables desde Configuración)
-- ------------------------------------------------------------
CREATE TABLE parametros (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    clave       VARCHAR(40) NOT NULL UNIQUE,
    valor       TEXT NOT NULL,
    descripcion VARCHAR(150) NOT NULL DEFAULT ''
);

INSERT INTO parametros (clave, valor, descripcion) VALUES
('RAZON_SOCIAL', 'Aura Automation & Analytics S.A.C.', 'Razón social de la empresa emisora'),
('RUC',          '20612345678',                         'RUC de la empresa emisora'),
('DIRECCION',    'Av. Primavera 1234, Surco, Lima',     'Dirección fiscal'),
('TELEFONO',     '(01) 555-1234',                       'Teléfono de contacto'),
('CORREO',       'facturacion@auraautomation.com',      'Correo de contacto'),
('IGV_PORC',     '18',                                  'Porcentaje de IGV (Perú)'),
('SERIE_FACTURA','F001',                                'Serie de facturas'),
('SERIE_BOLETA', 'B001',                                'Serie de boletas'),
('SERIE_NOTA_CREDITO', 'NC01',                          'Serie de notas de crédito'),
('SERIE_NOTA_DEBITO',   'ND01',                         'Serie de notas de débito');

-- ------------------------------------------------------------
-- Clientes
-- ------------------------------------------------------------
CREATE TABLE clientes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tipo_doc      ENUM('RUC','DNI') NOT NULL DEFAULT 'RUC',
    ruc_dni       VARCHAR(11) NOT NULL UNIQUE,
    razon_social  VARCHAR(150) NOT NULL,
    direccion     VARCHAR(200) NOT NULL DEFAULT '',
    correo        VARCHAR(120) NOT NULL DEFAULT '',
    telefono      VARCHAR(20) NOT NULL DEFAULT '',
    activo        TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Productos / servicios del catálogo
-- ------------------------------------------------------------
CREATE TABLE productos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    codigo      VARCHAR(20) NOT NULL UNIQUE,
    descripcion VARCHAR(150) NOT NULL,
    tipo        ENUM('producto','servicio') NOT NULL DEFAULT 'producto',
    precio      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    afecto_igv  TINYINT(1) NOT NULL DEFAULT 1,
    activo      TINYINT(1) NOT NULL DEFAULT 1
);

-- ------------------------------------------------------------
-- Comprobantes de venta (cabecera)
-- contenido = base imponible | igv = 18% | total = contenido + igv
-- ------------------------------------------------------------
CREATE TABLE comprobantes (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    tipo              ENUM('FACTURA','BOLETA','NOTA_CREDITO','NOTA_DEBITO') NOT NULL DEFAULT 'BOLETA',
    serie             VARCHAR(4) NOT NULL,
    numero            INT NOT NULL,
    fecha_emision     DATE NOT NULL,
    fecha_vencimiento DATE NULL,
    cliente_id        INT NOT NULL,
    condicion_pago    VARCHAR(40) NOT NULL DEFAULT 'Contado',
    subtotal          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igv               DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    letras            VARCHAR(255) NOT NULL DEFAULT '',
    observaciones     VARCHAR(255) NOT NULL DEFAULT '',
    estado            ENUM('emitido','anulado') NOT NULL DEFAULT 'emitido',
    fecha_creacion    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_serie_numero (serie, numero),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

-- ------------------------------------------------------------
-- Detalle de comprobante (ítems)
-- importe = cantidad * precio | igv = 18% del importe | total = importe + igv
-- ------------------------------------------------------------
CREATE TABLE comprobante_detalle (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    comprobante_id INT NOT NULL,
    producto_id    INT NULL,
    descripcion    VARCHAR(255) NOT NULL,
    cantidad       DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    importe        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igv            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (comprobante_id) REFERENCES comprobantes(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- ------------------------------------------------------------
-- Log de auditoría
-- ------------------------------------------------------------
CREATE TABLE audit_log (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    tabla            VARCHAR(40) NOT NULL,
    registro_id      INT NULL,
    accion           VARCHAR(20) NOT NULL,
    resumen          VARCHAR(255) NOT NULL DEFAULT '',
    datos_anteriores TEXT NULL,
    datos_nuevos     TEXT NULL,
    usuario          VARCHAR(80) NOT NULL DEFAULT 'sistema',
    fecha            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- DATOS DE EJEMPLO
-- ============================================================
INSERT INTO clientes (tipo_doc, ruc_dni, razon_social, direccion, correo, telefono) VALUES
('RUC', '20123456789', 'Textil Rojas S.A.C.',   'Av. Los Alisos 500, Ate, Lima',     'ventas@textilrojas.pe',    '999111222'),
('RUC', '20598765432', 'Vargas Logística',       'Jr. Las Palmeras 210, Callao',     'contacto@vargaslogis.com', '988222333'),
('RUC', '20612345678', 'Chamitos S.A.C.',        'Av. Industrial 800, SJL, Lima',    'info@chamitos.pe',         '977333444'),
('RUC', '20544455666', 'Prada Constructora',     'Calle Los Nogales 45, Miraflores', 'prada@constructora.com',   '966444555'),
('DNI', '40123456',    'María Fernanda Quispe',  'Urb. Santa Catalina Mz B Lt 6',    'maria.quispe@gmail.com',   '955555666'),
('RUC', '20678901234', 'AgroExport del Norte',   'Carretera Panamericana Km 756',    'admin@agroexport.com',     '944666777');

INSERT INTO productos (codigo, descripcion, tipo, precio, afecto_igv) VALUES
('SRV001', 'Consultoría en automatización de procesos', 'servicio', 1200.00, 1),
('SRV002', 'Servicio de analytics y reportes',           'servicio',  850.00, 1),
('SRV003', 'Soporte técnico (hora hombre)',              'servicio',  150.00, 1),
('PRD001', 'Licencia de software anual',                 'producto',  2400.00, 1),
('PRD002', 'Equipo de cómputo / workstation',            'producto',  3800.00, 1),
('PRD003', 'Capacitación empresarial (por persona)',     'servicio',  320.00, 1);

-- Factura de ejemplo (enero del año en curso)
INSERT INTO comprobantes
(tipo, serie, numero, fecha_emision, fecha_vencimiento, cliente_id, condicion_pago,
 subtotal, igv, total, letras, observaciones, estado)
VALUES
('FACTURA', 'F001', 1, DATE_FORMAT(CURDATE(), '%Y-01-12'), DATE_FORMAT(CURDATE(), '%Y-02-12'), 1, 'Crédito 30 días',
 5200.00, 936.00, 6136.00,
 'SON CINCO MIL DOSCIENTOS CON 00/100 SOLES', 'Factura de ejemplo: consultoría + licencia', 'emitido');

INSERT INTO comprobante_detalle
(comprobante_id, producto_id, descripcion, cantidad, precio_unitario, importe, igv, total) VALUES
(1, 1, 'Consultoría en automatización de procesos', 2.000, 1200.00, 2400.00, 432.00, 2832.00),
(1, 4, 'Licencia de software anual',                1.000, 2400.00, 2400.00, 432.00, 2832.00),
(1, 2, 'Servicio de analytics y reportes',          1.000,  400.00,  400.00,  72.00,  472.00);

-- Boleta de ejemplo (mes en curso)
INSERT INTO comprobantes
(tipo, serie, numero, fecha_emision, fecha_vencimiento, cliente_id, condicion_pago,
 subtotal, igv, total, letras, observaciones, estado)
VALUES
('BOLETA', 'B001', 1, CURDATE(), NULL, 5, 'Contado',
 300.00, 54.00, 354.00,
 'SON TRESCIENTOS CON 00/100 SOLES', 'Boleta de ejemplo: capacitación', 'emitido');

INSERT INTO comprobante_detalle
(comprobante_id, producto_id, descripcion, cantidad, precio_unitario, importe, igv, total) VALUES
(2, 6, 'Capacitación empresarial (por persona)', 1.000, 300.00, 300.00, 54.00, 354.00);