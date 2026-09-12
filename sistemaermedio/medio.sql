-- ============================================================
--  Sistema ERP para medianas empresas  (Aura Automation & Analytics)
--  Base de datos: medio
-- ============================================================

CREATE DATABASE IF NOT EXISTS medio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medio;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS compra_pagos;
DROP TABLE IF EXISTS venta_pagos;
DROP TABLE IF EXISTS compra_detalle;
DROP TABLE IF EXISTS compras;
DROP TABLE IF EXISTS orden_compra_detalle;
DROP TABLE IF EXISTS ordenes_compra;
DROP TABLE IF EXISTS venta_detalle;
DROP TABLE IF EXISTS ventas;
DROP TABLE IF EXISTS cotizacion_detalle;
DROP TABLE IF EXISTS cotizaciones;
DROP TABLE IF EXISTS movimientos;
DROP TABLE IF EXISTS stock;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS proveedores;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS almacenes;
DROP TABLE IF EXISTS caja;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS parametros;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Parámetros de la empresa
-- ------------------------------------------------------------
CREATE TABLE parametros (
    clave       VARCHAR(50) PRIMARY KEY,
    valor       VARCHAR(255) NOT NULL DEFAULT '',
    descripcion VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

INSERT INTO parametros (clave, valor, descripcion) VALUES
('EMPRESA_NOMBRE',   'Aura Automation & Analytics E.I.R.L.', 'Razón social'),
('EMPRESA_RUC',      '20604123456', 'RUC'),
('EMPRESA_DIRECCION','Av. Los Ingenieros 1040, Lima', 'Dirección'),
('EMPRESA_TELEFONO', '(01) 440-8877', 'Teléfono'),
('EMPRESA_CORREO',   'ventas@aura-analytics.com', 'Correo'),
('MONEDA',           'SOLES', 'Moneda (SOLES o DOLARES)'),
('IGV',              '18', 'Porcentaje de IGV');

-- ------------------------------------------------------------
-- Usuarios (login con roles)
-- ------------------------------------------------------------
CREATE TABLE usuarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    usuario    VARCHAR(50)  NOT NULL UNIQUE,
    clave      VARCHAR(255) NOT NULL,
    nombre     VARCHAR(120) NOT NULL,
    rol        ENUM('admin','ventas','compras','inventario','caja') NOT NULL DEFAULT 'ventas',
    estado     TINYINT NOT NULL DEFAULT 1,
    creado     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Almacenes
-- ------------------------------------------------------------
CREATE TABLE almacenes (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nombre    VARCHAR(120) NOT NULL,
    ubicacion VARCHAR(180) NOT NULL DEFAULT '',
    estado    TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO almacenes (id, nombre, ubicacion) VALUES
(1, 'Almacén Central',  'Planta Lima'),
(2, 'Depósito Norte',   'Callao');

-- ------------------------------------------------------------
-- Maestros
-- ------------------------------------------------------------
CREATE TABLE categorias (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO categorias (id, nombre) VALUES
(1, 'Hardware'),
(2, 'Equipos'),
(3, 'Consumibles'),
(4, 'Servicios');

CREATE TABLE clientes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tipo_doc    VARCHAR(5)  NOT NULL DEFAULT 'RUC',
    ruc_dni     VARCHAR(15) NOT NULL UNIQUE,
    razon_social VARCHAR(180) NOT NULL,
    direccion   VARCHAR(255) NOT NULL DEFAULT '',
    correo      VARCHAR(120) NOT NULL DEFAULT '',
    telefono    VARCHAR(30)  NOT NULL DEFAULT '',
    limite_credito DECIMAL(12,2) NOT NULL DEFAULT 0,
    activo      TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO clientes (tipo_doc, ruc_dni, razon_social, direccion, correo, telefono, limite_credito) VALUES
('RUC','20600111222','Construmaq Andina S.A.C.', 'Av. Argentina 552, Lima', 'compras@construmaq.pe', '987654321', 50000),
('RUC','20600333444','Distribuciones Los Andes E.I.R.L.', 'Jr. Paruro 210, Cusco', 'ventas@losandes.com', '984000111', 15000),
('DNI','43555666','Carlos Mendoza Rojas', 'Urb. Santa Anita Mz A Lt 3', 'cmendoza@correo.com', '961222333', 2000),
('RUC','20600555666','Pymes Renovables S.A.C.', 'Av. Universitaria 1450, Lima', 'contacto@pymesrenovables.pe', '980111222', 30000),
('RUC','20600777888','Consorcio Norte SAC', 'Alameda del Norte 320, Lima', 'oc@consorcionorte.com', '970333444', 20000);

CREATE TABLE proveedores (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tipo_doc    VARCHAR(5)  NOT NULL DEFAULT 'RUC',
    ruc_dni     VARCHAR(15) NOT NULL UNIQUE,
    razon_social VARCHAR(180) NOT NULL,
    direccion   VARCHAR(255) NOT NULL DEFAULT '',
    correo      VARCHAR(120) NOT NULL DEFAULT '',
    telefono    VARCHAR(30)  NOT NULL DEFAULT '',
    plazo_credito INT NOT NULL DEFAULT 0,
    activo      TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO proveedores (tipo_doc, ruc_dni, razon_social, direccion, correo, telefono, plazo_credito) VALUES
('RUC','20411222333','Distec Tecnología S.A.C.', 'Av. Bolivia 700, Lima', 'ventas@distec.com', '998700111', 30),
('RUC','20511444555','Peru Systems Import', 'Mz E Lt 25, Lurín', 'import@perusystems.pe', '993888222', 15),
('RUC','20611555666','Paper Office Perú S.A.', 'Jr. Huaraz 118, Lima', 'ventas@paperoffice.pe', '981555000', 0),
('RUC','20111666777','CompuParts S.R.L.', 'Av. Bausate y Meza 215, Lima', 'pedidos@compuparts.com', '960222333', 30);

-- ------------------------------------------------------------
-- Productos y stock por almacén
-- ------------------------------------------------------------
CREATE TABLE productos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    codigo          VARCHAR(20) NOT NULL UNIQUE,
    nombre          VARCHAR(180) NOT NULL,
    descripcion     VARCHAR(255) NOT NULL DEFAULT '',
    categoria_id    INT NULL,
    unidad          VARCHAR(20) NOT NULL DEFAULT 'unidad',
    controla_stock  TINYINT NOT NULL DEFAULT 1,
    stock_minimo    DECIMAL(12,3) NOT NULL DEFAULT 0,
    precio_compra   DECIMAL(12,2) NOT NULL DEFAULT 0,
    precio_venta    DECIMAL(12,2) NOT NULL DEFAULT 0,
    activo          TINYINT NOT NULL DEFAULT 1,
    CONSTRAINT fk_prod_cat FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    CONSTRAINT chk_precios CHECK (precio_compra >= 0 AND precio_venta >= 0)
) ENGINE=InnoDB;

INSERT INTO productos (id, codigo, nombre, descripcion, categoria_id, unidad, controla_stock, stock_minimo, precio_compra, precio_venta) VALUES
(1, 'PRD001', 'Laptop Dell Latitude 3420', 'Intel i5, 8GB, 256GB SSD', 1, 'unidad', 1, 3, 2100.00, 2450.00),
(2, 'PRD002', 'Monitor Samsung 24"',        'LED Full HD 1920x1080', 1, 'unidad', 1, 2, 620.00, 700.00),
(3, 'PRD003', 'Impresora Epson L3250',      'Multifuncional tinta continua', 2, 'unidad', 1, 2, 550.00, 750.00),
(4, 'PRD004', 'Tóner HP 85A',               'Cartucho láser CE285A', 3, 'unidad', 1, 8, 180.00, 230.00),
(5, 'PRD005', 'Resma de papel A4 (75g)',    'Caja x 10 resmas', 3, 'caja', 1, 6, 25.00, 35.00),
(6, 'PRD006', 'Laptop HP Pavilion 15',      'Ryzen 5, 16GB, 512GB SSD', 1, 'unidad', 1, 6, 2750.00, 3100.00),
(7, 'SRV001', 'Soporte técnico',            'Servicio por hora', 4, 'hora', 0, 0, 0.00, 85.00);

CREATE TABLE stock (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    almacen_id  INT NOT NULL,
    cantidad    DECIMAL(12,3) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_stock (producto_id, almacen_id),
    CONSTRAINT fk_stock_prd FOREIGN KEY (producto_id) REFERENCES productos(id),
    CONSTRAINT fk_stock_alm FOREIGN KEY (almacen_id) REFERENCES almacenes(id)
) ENGINE=InnoDB;

INSERT INTO stock (producto_id, almacen_id, cantidad) VALUES
(1,1,11),(1,2,0),
(2,1,7),(2,2,1),
(3,1,3),(3,2,5),
(4,1,4),(4,2,3),
(5,1,14),(5,2,20),
(6,1,5),(6,2,0);

-- ------------------------------------------------------------
-- Kardex / movimientos de inventario
-- ------------------------------------------------------------
CREATE TABLE movimientos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    fecha       DATE NOT NULL,
    producto_id INT NOT NULL,
    almacen_id  INT NOT NULL,
    tipo        ENUM('entrada','salida','ajuste','traslado') NOT NULL,
    cantidad    DECIMAL(12,3) NOT NULL,
    motivo      VARCHAR(255) NOT NULL DEFAULT '',
    referencia  VARCHAR(40) NOT NULL DEFAULT '',
    usuario     VARCHAR(50) NOT NULL DEFAULT 'sistema',
    CONSTRAINT fk_mov_prd FOREIGN KEY (producto_id) REFERENCES productos(id),
    CONSTRAINT fk_mov_alm FOREIGN KEY (almacen_id) REFERENCES almacenes(id)
) ENGINE=InnoDB;

-- Entradas iniciales (stock inicial de cada almacén)
INSERT INTO movimientos (fecha, producto_id, almacen_id, tipo, cantidad, motivo, referencia) VALUES
('2026-09-01',1,1,'entrada',2,'Stock inicial','INICIAL'),
('2026-09-01',2,1,'entrada',9,'Stock inicial','INICIAL'),
('2026-09-01',3,1,'entrada',5,'Stock inicial','INICIAL'),
('2026-09-01',4,1,'entrada',3,'Stock inicial','INICIAL'),
('2026-09-01',5,1,'entrada',14,'Stock inicial','INICIAL'),
('2026-09-01',2,2,'entrada',1,'Stock inicial','INICIAL');

-- Compra C-0001 (crédito, Almacén Central)
INSERT INTO movimientos (fecha, producto_id, almacen_id, tipo, cantidad, motivo, referencia) VALUES
('2026-09-02',1,1,'entrada',10,'Ingreso por compra','C-0001'),
('2026-09-02',6,1,'entrada',5,'Ingreso por compra','C-0001');

-- Compra C-0002 (contado, Depósito Norte)
INSERT INTO movimientos (fecha, producto_id, almacen_id, tipo, cantidad, motivo, referencia) VALUES
('2026-09-04',3,2,'entrada',5,'Ingreso por compra','C-0002'),
('2026-09-04',5,2,'entrada',10,'Ingreso por compra','C-0002'),
('2026-09-04',4,2,'entrada',3,'Ingreso por compra','C-0002');

-- Venta V-0001 (contado, Almacén Central)
INSERT INTO movimientos (fecha, producto_id, almacen_id, tipo, cantidad, motivo, referencia) VALUES
('2026-09-06',1,1,'salida',-1,'Salida por venta','V-0001'),
('2026-09-06',2,1,'salida',-2,'Salida por venta','V-0001');

-- Venta V-0002 (crédito, Almacén Central)
INSERT INTO movimientos (fecha, producto_id, almacen_id, tipo, cantidad, motivo, referencia) VALUES
('2026-09-08',3,1,'salida',-2,'Salida por venta','V-0002');

-- Ajuste: devolución de cliente
INSERT INTO movimientos (fecha, producto_id, almacen_id, tipo, cantidad, motivo, referencia) VALUES
('2026-09-09',4,1,'ajuste',1,'Devolución de cliente','AJ-0001');

-- ------------------------------------------------------------
-- Cotizaciones
-- ------------------------------------------------------------
CREATE TABLE cotizaciones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    numero      INT NOT NULL UNIQUE,
    fecha       DATE NOT NULL,
    validez     INT NOT NULL DEFAULT 7,
    cliente_id  INT NOT NULL,
    condicion   ENUM('contado','credito') NOT NULL DEFAULT 'contado',
    subtotal    DECIMAL(12,2) NOT NULL DEFAULT 0,
    igv         DECIMAL(12,2) NOT NULL DEFAULT 0,
    total       DECIMAL(12,2) NOT NULL DEFAULT 0,
    observaciones VARCHAR(255) NOT NULL DEFAULT '',
    estado      ENUM('vigente','aceptada','anulada') NOT NULL DEFAULT 'vigente',
    venta_id    INT NULL,
    CONSTRAINT fk_cot_cl FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB;

CREATE TABLE cotizacion_detalle (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    cotizacion_id  INT NOT NULL,
    producto_id    INT NULL,
    descripcion    VARCHAR(180) NOT NULL,
    cantidad       DECIMAL(12,3) NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_cod_cot FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id)
) ENGINE=InnoDB;

INSERT INTO cotizaciones (numero, fecha, validez, cliente_id, condicion, subtotal, igv, total, observaciones, estado) VALUES
(1, '2026-09-10', 7, 5, 'contado', 3270.00, 588.60, 3858.60, 'Cotización de equipos para oficina', 'vigente');

INSERT INTO cotizacion_detalle (cotizacion_id, producto_id, descripcion, cantidad, precio_unitario, subtotal) VALUES
(1, 6,    'Laptop HP Pavilion 15', 1, 3100.00, 3100.00),
(1, 7,    'Soporte técnico',       2, 85.00,   170.00);

-- ------------------------------------------------------------
-- Ventas (tipo factura/boleta + IGV + almacén)
-- ------------------------------------------------------------
CREATE TABLE ventas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    numero       INT NOT NULL UNIQUE,
    fecha        DATE NOT NULL,
    cliente_id   INT NOT NULL,
    tipo_doc     ENUM('factura','boleta') NOT NULL DEFAULT 'factura',
    condicion    ENUM('contado','credito') NOT NULL DEFAULT 'contado',
    almacen_id   INT NOT NULL,
    usuario_id   INT NULL,
    subtotal     DECIMAL(12,2) NOT NULL DEFAULT 0,
    igv          DECIMAL(12,2) NOT NULL DEFAULT 0,
    total        DECIMAL(12,2) NOT NULL DEFAULT 0,
    observaciones VARCHAR(255) NOT NULL DEFAULT '',
    estado       ENUM('emitida','anulada') NOT NULL DEFAULT 'emitida',
    CONSTRAINT fk_ven_cl FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT fk_ven_alm FOREIGN KEY (almacen_id) REFERENCES almacenes(id),
    CONSTRAINT fk_ven_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE venta_detalle (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    venta_id       INT NOT NULL,
    producto_id    INT NULL,
    descripcion    VARCHAR(180) NOT NULL,
    cantidad       DECIMAL(12,3) NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_vde_ven FOREIGN KEY (venta_id) REFERENCES ventas(id)
) ENGINE=InnoDB;

INSERT INTO ventas (numero, fecha, cliente_id, tipo_doc, condicion, almacen_id, subtotal, igv, total, observaciones, estado) VALUES
(1, '2026-09-06', 4, 'factura', 'contado', 1, 3850.00,  693.00,  4543.00,  'Venta mostrador', 'emitida'),
(2, '2026-09-08', 1, 'factura', 'credito', 1, 1500.00,  270.00,  1770.00,  'Crédito a 30 días', 'emitida');

INSERT INTO venta_detalle (venta_id, producto_id, descripcion, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 'Laptop Dell Latitude 3420',    1, 2450.00, 2450.00),
(1, 2, 'Monitor Samsung 24"',          2, 700.00,  1400.00),
(2, 3, 'Impresora Epson L3250',        2, 750.00,  1500.00);

-- Cobros (cuentas por cobrar)
CREATE TABLE venta_pagos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    fecha        DATE NOT NULL,
    venta_id     INT NOT NULL,
    cliente_id   INT NOT NULL,
    monto        DECIMAL(12,2) NOT NULL,
    medio        ENUM('efectivo','transferencia','tarjeta','cheque') NOT NULL DEFAULT 'efectivo',
    referencia   VARCHAR(80) NOT NULL DEFAULT '',
    usuario      VARCHAR(50) NOT NULL DEFAULT 'sistema',
    CONSTRAINT fk_vp_ven FOREIGN KEY (venta_id) REFERENCES ventas(id),
    CONSTRAINT fk_vp_cl FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB;

INSERT INTO venta_pagos (fecha, venta_id, cliente_id, monto, medio, referencia) VALUES
('2026-09-12', 2, 1, 1000.00, 'transferencia', 'Abono parcial CxC');

-- ------------------------------------------------------------
-- Órdenes de compra
-- ------------------------------------------------------------
CREATE TABLE ordenes_compra (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    numero       INT NOT NULL UNIQUE,
    fecha        DATE NOT NULL,
    proveedor_id INT NOT NULL,
    almacen_id   INT NOT NULL,
    observaciones VARCHAR(255) NOT NULL DEFAULT '',
    estado       ENUM('pendiente','recibida','anulada') NOT NULL DEFAULT 'pendiente',
    compra_id    INT NULL,
    CONSTRAINT fk_oc_pr FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    CONSTRAINT fk_oc_alm FOREIGN KEY (almacen_id) REFERENCES almacenes(id)
) ENGINE=InnoDB;

CREATE TABLE orden_compra_detalle (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    orden_id       INT NOT NULL,
    producto_id    INT NOT NULL,
    descripcion    VARCHAR(180) NOT NULL,
    cantidad       DECIMAL(12,3) NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_ocd_oc FOREIGN KEY (orden_id) REFERENCES ordenes_compra(id)
) ENGINE=InnoDB;

INSERT INTO ordenes_compra (numero, fecha, proveedor_id, almacen_id, observaciones, estado) VALUES
(1, '2026-09-09', 1, 1, 'Reaprovisionamiento mensual', 'pendiente');

INSERT INTO orden_compra_detalle (orden_id, producto_id, descripcion, cantidad, precio_unitario, subtotal) VALUES
(1, 2, 'Monitor Samsung 24"', 4, 680.00, 2720.00),
(1, 4, 'Tóner HP 85A',        6, 185.00, 1110.00);

-- ------------------------------------------------------------
-- Compras (IGV + almacén + localización de OC)
-- ------------------------------------------------------------
CREATE TABLE compras (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    numero         INT NOT NULL UNIQUE,
    fecha          DATE NOT NULL,
    proveedor_id   INT NOT NULL,
    orden_compra_id INT NULL,
    tipo_doc       ENUM('factura','boleta') NOT NULL DEFAULT 'factura',
    condicion      ENUM('contado','credito') NOT NULL DEFAULT 'contado',
    almacen_id     INT NOT NULL,
    usuario_id     INT NULL,
    subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0,
    igv            DECIMAL(12,2) NOT NULL DEFAULT 0,
    total          DECIMAL(12,2) NOT NULL DEFAULT 0,
    observaciones  VARCHAR(255) NOT NULL DEFAULT '',
    estado         ENUM('emitida','anulada') NOT NULL DEFAULT 'emitida',
    CONSTRAINT fk_com_pr FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    CONSTRAINT fk_com_alm FOREIGN KEY (almacen_id) REFERENCES almacenes(id),
    CONSTRAINT fk_com_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE compra_detalle (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    compra_id       INT NOT NULL,
    producto_id     INT NOT NULL,
    descripcion     VARCHAR(180) NOT NULL,
    cantidad        DECIMAL(12,3) NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_cde_com FOREIGN KEY (compra_id) REFERENCES compras(id)
) ENGINE=InnoDB;

INSERT INTO compras (numero, fecha, proveedor_id, tipo_doc, condicion, almacen_id, subtotal, igv, total, observaciones, estado) VALUES
(1, '2026-09-02', 1, 'factura', 'credito', 1, 34750.00, 6255.00, 41005.00, 'Crédito 30 días', 'emitida'),
(2, '2026-09-04', 2, 'factura', 'contado', 2, 3540.00,  637.20,  4177.20, 'Compra directa', 'emitida');

INSERT INTO compra_detalle (compra_id, producto_id, descripcion, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 'Laptop Dell Latitude 3420',    10, 2100.00, 21000.00),
(1, 6, 'Laptop HP Pavilion 15',        5,  2750.00, 13750.00),
(2, 3, 'Impresora Epson L3250',        5,  550.00,  2750.00),
(2, 5, 'Resma de papel A4 (75g)',      10, 25.00,   250.00),
(2, 4, 'Tóner HP 85A',                 3,  180.00,  540.00);

-- Pagos a proveedores (cuentas por pagar)
CREATE TABLE compra_pagos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    fecha        DATE NOT NULL,
    compra_id    INT NOT NULL,
    proveedor_id INT NOT NULL,
    monto        DECIMAL(12,2) NOT NULL,
    medio        ENUM('efectivo','transferencia','tarjeta','cheque') NOT NULL DEFAULT 'efectivo',
    referencia   VARCHAR(80) NOT NULL DEFAULT '',
    usuario      VARCHAR(50) NOT NULL DEFAULT 'sistema',
    CONSTRAINT fk_cp_com FOREIGN KEY (compra_id) REFERENCES compras(id),
    CONSTRAINT fk_cp_pr FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
) ENGINE=InnoDB;

INSERT INTO compra_pagos (fecha, compra_id, proveedor_id, monto, medio, referencia) VALUES
('2026-09-20', 1, 1, 15000.00, 'transferencia', 'Abono parcial CxP');

-- ------------------------------------------------------------
-- Caja / bancos
-- ------------------------------------------------------------
CREATE TABLE caja (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    fecha      DATE NOT NULL,
    tipo_oper  ENUM('ingreso','egreso') NOT NULL,
    concepto   VARCHAR(180) NOT NULL,
    medio      ENUM('efectivo','transferencia','tarjeta','cheque') NOT NULL DEFAULT 'efectivo',
    monto      DECIMAL(12,2) NOT NULL,
    referencia VARCHAR(80) NOT NULL DEFAULT '',
    usuario    VARCHAR(50) NOT NULL DEFAULT 'sistema'
) ENGINE=InnoDB;

INSERT INTO caja (fecha, tipo_oper, concepto, medio, monto, referencia) VALUES
('2026-09-01', 'ingreso', 'Capital inicial',         'efectivo',      30000.00, 'INICIAL'),
('2026-09-06', 'ingreso', 'Venta contado V-0001',    'efectivo',      4543.00,  'V-0001'),
('2026-09-12', 'ingreso', 'Cobro CxC V-0002',        'transferencia', 1000.00,  'V-0002'),
('2026-09-20', 'egreso',  'Pago CxP C-0001',         'transferencia', 15000.00, 'C-0001'),
('2026-09-25', 'egreso',  'Alquiler de local',       'efectivo',      2800.00,  'RENTA');

-- ------------------------------------------------------------
-- Auditoría
-- ------------------------------------------------------------
CREATE TABLE audit_log (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    fecha          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tabla          VARCHAR(50) NOT NULL,
    registro_id    INT NULL,
    accion         VARCHAR(20) NOT NULL,
    resumen        VARCHAR(255) NOT NULL DEFAULT '',
    datos_anteriores TEXT NULL,
    datos_nuevos     TEXT NULL,
    usuario        VARCHAR(50) NOT NULL DEFAULT 'sistema'
) ENGINE=InnoDB;