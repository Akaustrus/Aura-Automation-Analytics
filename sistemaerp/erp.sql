-- ============================================================
-- SISTEMA ERP BÁSICO PARA MICRO EMPRESAS — Aura Automation & Analytics
-- Base de datos: erp
-- Ejecutar vía el navegador: instalador.php (recomendado)
--   o manualmente:  mysql -u root -p < erp.sql
-- ============================================================

DROP DATABASE IF EXISTS erp;
CREATE DATABASE erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE erp;

-- ------------------------------------------------------------
-- Parámetros del sistema (empresa y configuraciones)
-- ------------------------------------------------------------
CREATE TABLE parametros (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    clave       VARCHAR(40) NOT NULL UNIQUE,
    valor       TEXT NOT NULL,
    descripcion VARCHAR(150) NOT NULL DEFAULT ''
);

INSERT INTO parametros (clave, valor, descripcion) VALUES
('EMPRESA_NOMBRE', 'Aura Automation & Analytics S.A.C.', 'Razón social de la micro empresa'),
('EMPRESA_RUC',    '20612345678',                         'RUC de la empresa'),
('EMPRESA_DIRECCION', 'Av. Primavera 1234, Surco, Lima',  'Dirección fiscal'),
('EMPRESA_TELEFONO',  '(01) 555-1234',                    'Teléfono de contacto'),
('EMPRESA_CORREO',    'erp@auraautomation.com',           'Correo de contacto'),
('MONEDA',         'SOLES',                               'Moneda de operación (SOLES/DOLARES)');

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
-- Proveedores
-- ------------------------------------------------------------
CREATE TABLE proveedores (
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
-- Productos / servicios con control de stock opcional
-- ------------------------------------------------------------
CREATE TABLE productos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    codigo         VARCHAR(20) NOT NULL UNIQUE,
    nombre         VARCHAR(150) NOT NULL,
    descripcion    VARCHAR(255) NOT NULL DEFAULT '',
    categoria      VARCHAR(60) NOT NULL DEFAULT '',
    unidad         VARCHAR(20) NOT NULL DEFAULT 'unidad',
    controla_stock TINYINT(1) NOT NULL DEFAULT 1,
    stock          DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    stock_minimo   DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    precio_compra  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    precio_venta   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    activo         TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Movimientos de inventario
-- cantidad = variación FIRMADA del stock (+entra / -sale).
-- entrada  → compras y devoluciones (positivo)
-- salida   → ventas y anulación de compras (negativo)
-- ajuste   → corrección manual (signo según corresponda)
-- ------------------------------------------------------------
CREATE TABLE movimientos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    fecha      DATE NOT NULL,
    producto_id INT NOT NULL,
    tipo       ENUM('entrada','salida','ajuste') NOT NULL DEFAULT 'entrada',
    cantidad   DECIMAL(12,3) NOT NULL,
    motivo     VARCHAR(120) NOT NULL DEFAULT '',
    referencia VARCHAR(40) NOT NULL DEFAULT '',
    usuario    VARCHAR(80) NOT NULL DEFAULT 'sistema',
    fecha_reg  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- ------------------------------------------------------------
-- Ventas
-- ------------------------------------------------------------
CREATE TABLE ventas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    numero        INT NOT NULL UNIQUE,
    fecha         DATE NOT NULL,
    cliente_id    INT NOT NULL,
    condicion     ENUM('contado','credito') NOT NULL DEFAULT 'contado',
    total         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    observaciones VARCHAR(255) NOT NULL DEFAULT '',
    estado        ENUM('emitida','anulada') NOT NULL DEFAULT 'emitida',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE TABLE venta_detalle (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    venta_id       INT NOT NULL,
    producto_id    INT NULL,
    descripcion    VARCHAR(255) NOT NULL,
    cantidad       DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- ------------------------------------------------------------
-- Compras
-- ------------------------------------------------------------
CREATE TABLE compras (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    numero        INT NOT NULL UNIQUE,
    fecha         DATE NOT NULL,
    proveedor_id  INT NOT NULL,
    condicion     ENUM('contado','credito') NOT NULL DEFAULT 'contado',
    total         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    observaciones VARCHAR(255) NOT NULL DEFAULT '',
    estado        ENUM('emitida','anulada') NOT NULL DEFAULT 'emitida',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
);

CREATE TABLE compra_detalle (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    compra_id      INT NOT NULL,
    producto_id    INT NOT NULL,
    descripcion    VARCHAR(255) NOT NULL,
    cantidad       DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- ------------------------------------------------------------
-- Caja (ingresos y egresos)
-- ------------------------------------------------------------
CREATE TABLE caja (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    fecha      DATE NOT NULL,
    tipo       ENUM('ingreso','egreso') NOT NULL DEFAULT 'ingreso',
    concepto   VARCHAR(150) NOT NULL,
    medio      ENUM('efectivo','transferencia','tarjeta','cheque') NOT NULL DEFAULT 'efectivo',
    monto      DECIMAL(14,2) NOT NULL,
    referencia VARCHAR(60) NOT NULL DEFAULT '',
    fecha_reg  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
-- DATOS DE EJEMPLO (stocks y movimientos cuadran)
-- ============================================================

INSERT INTO clientes (tipo_doc, ruc_dni, razon_social, direccion, correo, telefono) VALUES
('RUC', '20123456789', 'Textil Rojas S.A.C.',    'Av. Los Alisos 500, Ate, Lima',     'ventas@textilrojas.pe',    '999111222'),
('RUC', '20612345678', 'Chamitos S.A.C.',        'Av. Industrial 800, SJL, Lima',    'info@chamitos.pe',         '977333444'),
('DNI', '40123456',    'María Fernanda Quispe',  'Urb. Santa Catalina Mz B Lt 6',    'maria.quispe@gmail.com',   '955555666'),
('RUC', '20544455666', 'Prada Constructora',     'Calle Los Nogales 45, Miraflores', 'prada@constructora.com',   '966444555'),
('DNI', '41234567',    'Jorge Luis Mendoza',     'Av. Grau 890, Barranco',           'jorge.mendoza@gmail.com',  '944777888');

INSERT INTO proveedores (tipo_doc, ruc_dni, razon_social, direccion, correo, telefono) VALUES
('RUC', '20100995533', 'TecnoImport del Perú',         'Jr. Las Torres 120, La Victoria', 'ventas@tecnoimport.pe',    '985111222'),
('RUC', '20377788899', 'Distribuidora Andina',         'Av. Aviación 3450, Surquillo',    'pedidos@distandina.com',   '986222333'),
('RUC', '20511223344', 'Suministros Rápidos S.A.C.',   'Calle Capón 780, Lima Cercado',   'hola@sumira.com.pe',       '987333444'),
('RUC', '20220998877', 'Emporio Office Perú',          'Jr. Paruro 510, Lima Cercado',    'ventas@emporiooffice.pe',  '988444555');

INSERT INTO productos (codigo, nombre, descripcion, categoria, unidad, controla_stock, stock, stock_minimo, precio_compra, precio_venta) VALUES
('PRD001', 'Laptop Lenovo IdeaPad 3',     'Core i5 / 8GB / 512GB SSD',   'Equipos',     'unidad', 1, 17.000, 3.000, 1800.00, 2200.00),
('PRD002', 'Monitor Samsung 24"',         'Panel IPS Full HD',           'Equipos',     'unidad', 1,  8.000, 2.000,  450.00,  620.00),
('PRD003', 'Teclado inalámbrico Logitech','Bluetooth, español',          'Accesorios',  'unidad', 1, 35.000, 5.000,   45.00,   80.00),
('PRD004', 'Mouse óptico Logitech',       'USB, 1000 DPI',               'Accesorios',  'unidad', 1, 26.000, 5.000,   25.00,   50.00),
('PRD005', 'Impresora Epson L3250',       'Multifuncional tanque',       'Equipos',     'unidad', 1,  4.000, 5.000,  550.00,  750.00),
('PRD006', 'Toner HP 85A',                'Compatible, 1600 páginas',    'Consumibles', 'unidad', 1,  1.000, 3.000,  120.00,  180.00),
('SRV001', 'Instalación y configuración','Servicio técnico a domicilio', 'Servicios',   'servicio', 0,  0.000, 0.000,    0.00,  150.00);

-- Compra inicial C-0001
INSERT INTO compras (numero, fecha, proveedor_id, condicion, total, observaciones, estado) VALUES
(1, '2026-07-08', 1, 'credito', 46820.00, 'Compra inicial de mercadería', 'emitida');

INSERT INTO compra_detalle (compra_id, producto_id, descripcion, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 'Laptop Lenovo IdeaPad 3',  20.000, 1800.00, 36000.00),
(1, 2, 'Monitor Samsung 24"',      12.000,  450.00,  5400.00),
(1, 3, 'Teclado inalámbrico Logitech', 40.000, 45.00, 1800.00),
(1, 4, 'Mouse óptico Logitech',    30.000,   25.00,  750.00),
(1, 5, 'Impresora Epson L3250',     5.000,  550.00,  2750.00),
(1, 6, 'Toner HP 85A',              1.000,  120.00,  120.00);

-- Ventas de ejemplo
INSERT INTO ventas (numero, fecha, cliente_id, condicion, total, observaciones, estado) VALUES
(1, '2026-08-05', 1, 'contado', 6660.00, 'Venta de equipos Mayorista',      'emitida'),
(2, '2026-09-02', 2, 'credito', 1100.00, 'Printer + instalación (crédito)', 'emitida'),
(3, '2026-09-05', 3, 'contado', 2820.00, 'Laptop + monitor (mostrador)',    'emitida');

INSERT INTO venta_detalle (venta_id, producto_id, descripcion, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 'Laptop Lenovo IdeaPad 3',     2.000, 2200.00, 4400.00),
(1, 2, 'Monitor Samsung 24"',         3.000,  620.00, 1860.00),
(1, 3, 'Teclado inalámbrico Logitech',5.000,   80.00,  400.00),
(2, 5, 'Impresora Epson L3250',       1.000,  750.00,  750.00),
(2, 4, 'Mouse óptico Logitech',       4.000,   50.00,  200.00),
(2, 7, 'Instalación y configuración', 1.000,  150.00,  150.00),
(3, 1, 'Laptop Lenovo IdeaPad 3',     1.000, 2200.00, 2200.00),
(3, 2, 'Monitor Samsung 24"',         1.000,  620.00,  620.00);

-- Movimientos (cuadran con el stock de productos)
INSERT INTO movimientos (fecha, producto_id, tipo, cantidad, motivo, referencia) VALUES
('2026-07-08', 1, 'entrada',  20.000, 'Ingreso por compra',   'C-0001'),
('2026-07-08', 2, 'entrada',  12.000, 'Ingreso por compra',   'C-0001'),
('2026-07-08', 3, 'entrada',  40.000, 'Ingreso por compra',   'C-0001'),
('2026-07-08', 4, 'entrada',  30.000, 'Ingreso por compra',   'C-0001'),
('2026-07-08', 5, 'entrada',   5.000, 'Ingreso por compra',   'C-0001'),
('2026-07-08', 6, 'entrada',   1.000, 'Ingreso por compra',   'C-0001'),
('2026-08-05', 1, 'salida',   -2.000, 'Salida por venta',     'V-0001'),
('2026-08-05', 2, 'salida',   -3.000, 'Salida por venta',     'V-0001'),
('2026-08-05', 3, 'salida',   -5.000, 'Salida por venta',     'V-0001'),
('2026-09-02', 4, 'salida',   -4.000, 'Salida por venta',     'V-0002'),
('2026-09-02', 5, 'salida',   -1.000, 'Salida por venta',     'V-0002'),
('2026-09-05', 1, 'salida',   -1.000, 'Salida por venta',     'V-0003'),
('2026-09-05', 2, 'salida',   -1.000, 'Salida por venta',     'V-0003');

-- Caja
INSERT INTO caja (fecha, tipo, concepto, medio, monto, referencia) VALUES
('2026-08-01', 'ingreso', 'Aporte inicial del titular',      'efectivo',      20000.00, ''),
('2026-08-02', 'ingreso', 'Crédito bancario a corto plazo',  'transferencia', 60000.00, ''),
('2026-08-05', 'ingreso', 'Venta contado V-0001',            'efectivo',       6660.00, 'V-0001'),
('2026-08-07', 'egreso',  'Pago parcial compra C-0001',      'transferencia', 12000.00, 'C-0001'),
('2026-08-28', 'egreso',  'Pago saldo compra C-0001',        'transferencia', 34820.00, 'C-0001'),
('2026-09-03', 'egreso',  'Alquiler del local',              'efectivo',       2500.00, ''),
('2026-09-05', 'ingreso', 'Venta contado V-0003',            'efectivo',       2820.00, 'V-0003'),
('2026-09-10', 'ingreso', 'Cobro venta a crédito V-0002',    'efectivo',       1100.00, 'V-0002');