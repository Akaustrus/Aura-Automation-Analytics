-- =====================================================
-- Aura Automation & Analytics - Sistema SAP
-- Sistema de Analisis Predictivo para el Negocio
-- Base de datos: sistemasap
-- Ejecutar via el navegador: instalador.php
-- =====================================================

DROP DATABASE IF EXISTS sistemasap;
CREATE DATABASE sistemasap
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistemasap;

CREATE TABLE parametros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) NOT NULL UNIQUE,
    valor VARCHAR(255) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL
);

INSERT INTO parametros (clave, valor, descripcion) VALUES
('nombre_empresa', 'Aura Automation & Analytics', 'Nombre comercial'),
('igv', '0.18', 'Tasa IGV 18%'),
('dias_agotamiento', '30', 'Dias umbral para alerta de stock'),
('horas_jornada', '8', 'Horas por jornada laboral'),
('dias_laborables', '26', 'Dias laborables por mes'),
('moneda', 'S/', 'Simbolo monetario');

CREATE TABLE departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    departamento_id INT NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    salario_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    requeridos INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_cargo_depto FOREIGN KEY (departamento_id) REFERENCES departamentos(id)
);

CREATE TABLE empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    apellidos VARCHAR(120) NOT NULL,
    dni VARCHAR(12) NOT NULL UNIQUE,
    email VARCHAR(150) DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    direccion VARCHAR(255) DEFAULT NULL,
    cargo_id INT NOT NULL,
    fecha_contratacion DATE NOT NULL,
    tipo_contrato ENUM('indefinido','temporal','practicas','terceros') NOT NULL DEFAULT 'indefinido',
    salario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estado ENUM('activo','inactivo','vacaciones') NOT NULL DEFAULT 'activo',
    horas_semanales INT NOT NULL DEFAULT 40,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_emp_cargo FOREIGN KEY (cargo_id) REFERENCES cargos(id)
);

CREATE TABLE asistencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    fecha DATE NOT NULL,
    horas INT NOT NULL DEFAULT 8,
    tipo ENUM('normal','permiso','vacaciones','feriado','falta') NOT NULL DEFAULT 'normal',
    observaciones VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uq_asist (empleado_id, fecha),
    CONSTRAINT fk_asist_emp FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
);

CREATE TABLE vacantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cargo_id INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    motivo VARCHAR(255) DEFAULT NULL,
    estado ENUM('abierta','en_proceso','cubierta','cerrada') NOT NULL DEFAULT 'abierta',
    fecha_apertura DATE NOT NULL,
    fecha_cierre DATE DEFAULT NULL,
    CONSTRAINT fk_vac_cargo FOREIGN KEY (cargo_id) REFERENCES cargos(id)
);

CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL
);

CREATE TABLE proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    ruc VARCHAR(20) NOT NULL,
    contacto VARCHAR(120) DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    direccion VARCHAR(255) DEFAULT NULL,
    tiempo_entrega_dias INT NOT NULL DEFAULT 5,
    tipo ENUM('productos','servicios','ambos') NOT NULL DEFAULT 'productos'
);

CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    categoria_id INT NOT NULL,
    proveedor_id INT NOT NULL,
    stock_actual INT NOT NULL DEFAULT 0,
    stock_minimo INT NOT NULL DEFAULT 0,
    stock_maximo INT NOT NULL DEFAULT 0,
    punto_pedido INT NOT NULL DEFAULT 0,
    costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    precio_venta DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    unidad VARCHAR(20) NOT NULL DEFAULT 'unidades',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_prod_cat FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    CONSTRAINT fk_prod_prov FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
);

CREATE TABLE movimientos_inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    tipo ENUM('entrada','salida','ajuste') NOT NULL,
    cantidad INT NOT NULL,
    costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    fecha DATE NOT NULL,
    concepto VARCHAR(255) DEFAULT NULL,
    usuario VARCHAR(100) DEFAULT 'sistema',
    CONSTRAINT fk_mov_prod FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
);

CREATE TABLE ordenes_compra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    proveedor_id INT NOT NULL,
    fecha DATE NOT NULL,
    fecha_estimada_entrega DATE DEFAULT NULL,
    estado ENUM('pendiente','aprobada','recibida','cancelada') NOT NULL DEFAULT 'pendiente',
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_oc_prov FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
);

CREATE TABLE oc_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_ocd_orden FOREIGN KEY (orden_id) REFERENCES ordenes_compra(id) ON DELETE CASCADE,
    CONSTRAINT fk_ocd_prod FOREIGN KEY (producto_id) REFERENCES productos(id)
);

CREATE TABLE periodos_planilla (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periodo VARCHAR(7) NOT NULL UNIQUE,
    inicio DATE NOT NULL,
    fin DATE NOT NULL,
    estado ENUM('abierto','cerrado') NOT NULL DEFAULT 'abierto',
    total_bruto DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_descuentos DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_neto DECIMAL(14,2) NOT NULL DEFAULT 0.00
);

CREATE TABLE boletas_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periodo_id INT NOT NULL,
    empleado_id INT NOT NULL,
    dias_laborados INT NOT NULL DEFAULT 26,
    horas_extras INT NOT NULL DEFAULT 0,
    sueldo_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    bono DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gratificacion DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cts DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    essalud DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    onp DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    otros_descuentos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_bruto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_descuentos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    neto_pagar DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estado ENUM('generado','pagado','anulado') NOT NULL DEFAULT 'generado',
    UNIQUE KEY uq_boleta (periodo_id, empleado_id),
    CONSTRAINT fk_bp_periodo FOREIGN KEY (periodo_id) REFERENCES periodos_planilla(id) ON DELETE CASCADE,
    CONSTRAINT fk_bp_emp FOREIGN KEY (empleado_id) REFERENCES empleados(id)
);

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    ruc_dni VARCHAR(20) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    direccion VARCHAR(255) DEFAULT NULL
);

CREATE TABLE comprobantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('BOLETA','FACTURA','NOTA_CREDITO','NOTA_DEBITO') NOT NULL DEFAULT 'BOLETA',
    serie VARCHAR(8) NOT NULL DEFAULT 'B001',
    numero INT NOT NULL,
    cliente_id INT NOT NULL,
    fecha DATE NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    igv DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    estado ENUM('emitido','pagado','anulado') NOT NULL DEFAULT 'emitido',
    UNIQUE KEY uq_comp (serie, numero),
    CONSTRAINT fk_comp_cli FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE TABLE comprobante_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comprobante_id INT NOT NULL,
    producto_id INT NULL,
    descripcion VARCHAR(255) NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_cd_comp FOREIGN KEY (comprobante_id) REFERENCES comprobantes(id) ON DELETE CASCADE,
    CONSTRAINT fk_cd_prod FOREIGN KEY (producto_id) REFERENCES productos(id)
);

CREATE TABLE cuentas_sap (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(10) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    tipo ENUM('activo','pasivo','patrimonio','ingreso','egreso') NOT NULL,
    saldo DECIMAL(14,2) NOT NULL DEFAULT 0.00
);

CREATE TABLE transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    cuenta_id INT NOT NULL,
    tipo ENUM('ingreso','egreso') NOT NULL,
    monto DECIMAL(14,2) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    referencia VARCHAR(50) DEFAULT NULL,
    CONSTRAINT fk_trans_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas_sap(id)
);

CREATE TABLE historico_diario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL UNIQUE,
    ventas DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    unidades_vendidas INT NOT NULL DEFAULT 0,
    clientes_atendidos INT NOT NULL DEFAULT 0,
    gastos DECIMAL(14,2) NOT NULL DEFAULT 0.00
);

CREATE TABLE predicciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_generacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo ENUM('ventas','stock','personal','flujo_caja','compra') NOT NULL,
    horizonte VARCHAR(10) NOT NULL DEFAULT '30d',
    datos JSON DEFAULT NULL,
    mensaje TEXT DEFAULT NULL,
    nivel ENUM('bajo','medio','alto') NOT NULL DEFAULT 'medio'
);

CREATE TABLE recomendaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_generacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    area ENUM('rrhh','inventario','finanzas','ventas','compras') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    urgencia ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    accion_sugerida VARCHAR(255) DEFAULT NULL,
    estado ENUM('pendiente','en_proceso','completada','ignorada') NOT NULL DEFAULT 'pendiente'
);

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

INSERT INTO departamentos (nombre, descripcion) VALUES
('Administracion', 'Gerencia y administracion general'),
('Ventas', 'Fuerza de ventas y atencion al cliente'),
('Operaciones', 'Operaciones y produccion'),
('Logistica', 'Almacen, compras y distribucion'),
('TI y Analytics', 'Tecnologia, datos y automatizacion'),
('Recursos Humanos', 'Gestion del talento humano');

INSERT INTO cargos (departamento_id, nombre, salario_base, requeridos) VALUES
(1, 'Gerente General', 12000.00, 1),
(1, 'Contador', 4500.00, 1),
(2, 'Vendedor', 1500.00, 6),
(2, 'Ejecutivo de Cuentas', 2500.00, 3),
(3, 'Operador de Produccion', 1400.00, 10),
(3, 'Supervisor de Planta', 3200.00, 2),
(4, 'Almacenero', 1300.00, 4),
(4, 'Jefe de Compras', 3500.00, 1),
(4, 'Repartidor / Chofer', 1500.00, 3),
(5, 'Analista de Datos', 4000.00, 2),
(5, 'Desarrollador de Automatizacion', 5000.00, 2),
(6, 'Analista de RRHH', 2800.00, 1);

INSERT INTO empleados (codigo, nombre, apellidos, dni, email, telefono, cargo_id, fecha_contratacion, tipo_contrato, salario, estado, horas_semanales) VALUES
('EMP-001', 'Carlos', 'Ramirez Torres', '40123456', 'cramirez@aura.pe', '999111222', 1, '2022-01-10', 'indefinido', 12000.00, 'activo', 40),
('EMP-002', 'Maria', 'Fernandez Gomez', '41234567', 'mfernandez@aura.pe', '999222333', 2, '2022-03-15', 'indefinido', 4500.00, 'activo', 40),
('EMP-003', 'Jorge', 'Quispe Mamani', '42345678', 'jquispe@aura.pe', '999333444', 3, '2023-01-05', 'temporal', 1500.00, 'activo', 40),
('EMP-004', 'Lucia', 'Vargas Rojas', '43456789', 'lvargas@aura.pe', '999444555', 4, '2022-06-20', 'indefinido', 2500.00, 'activo', 40),
('EMP-005', 'Pedro', 'Soto Castillo', '44567890', 'psoto@aura.pe', '999555666', 5, '2023-02-12', 'temporal', 1400.00, 'activo', 40),
('EMP-006', 'Ana', 'Torres Paredes', '45678901', 'atorres@aura.pe', '999666777', 6, '2021-09-01', 'indefinido', 3200.00, 'activo', 40),
('EMP-007', 'Luis', 'Huaman Flores', '46789012', 'lhuaman@aura.pe', '999777888', 7, '2023-03-18', 'temporal', 1300.00, 'activo', 40),
('EMP-008', 'Rosa', 'Chambilla Vega', '47890123', 'rchambilla@aura.pe', '999888999', 3, '2024-01-15', 'temporal', 1500.00, 'activo', 40),
('EMP-009', 'Miguel', 'Salazar Puma', '48901234', 'msalazar@aura.pe', '999999000', 8, '2022-05-10', 'indefinido', 3500.00, 'activo', 40),
('EMP-010', 'Carmen', 'Rojas Nina', '49012345', 'crojas@aura.pe', '998111222', 9, '2023-07-01', 'temporal', 1500.00, 'activo', 40),
('EMP-011', 'Diego', 'Paredes Soto', '50123456', 'dparedes@aura.pe', '998222333', 10, '2023-11-01', 'indefinido', 4000.00, 'activo', 40),
('EMP-012', 'Elena', 'Mendoza Rios', '51234567', 'emendoza@aura.pe', '998333444', 11, '2024-02-10', 'indefinido', 5000.00, 'activo', 40),
('EMP-013', 'Felipe', 'Gutierrez Luna', '52345678', 'fgutierrez@aura.pe', '998444555', 12, '2024-05-20', 'indefinido', 2800.00, 'activo', 40);

INSERT INTO categorias (nombre, descripcion) VALUES
('Hardware', 'Equipos y componentes de computo'),
('Software', 'Licencias y soluciones digitales'),
('Networking', 'Redes y conectividad'),
('Servicios', 'Servicios profesionales y soporte'),
('Consumibles', 'Insumos y materiales de oficina');

INSERT INTO proveedores (nombre, ruc, contacto, telefono, email, tiempo_entrega_dias, tipo) VALUES
('Infosistemas SAC', '20123456789', 'Juan Morales', '988111222', 'ventas@infosistemas.pe', 5, 'productos'),
('TecnoPeru Import', '20598765432', 'Karina Diaz', '988222333', 'compras@tecnoperu.pe', 12, 'productos'),
('CloudBox SRL', '20612345678', 'Renato Quiroz', '988333444', 'soporte@cloudbox.pe', 3, 'servicios'),
('DataCenter Lima', '20345678901', 'Valeria Chu', '988444555', 'ventas@datacenterlima.pe', 7, 'servicios'),
('Suministros Rapidos', '20765432109', 'Oscar Roca', '988555666', 'pedidos@suministrosrapidos.pe', 2, 'productos');

INSERT INTO productos (codigo, nombre, categoria_id, proveedor_id, stock_actual, stock_minimo, stock_maximo, punto_pedido, costo_unitario, precio_venta, unidad) VALUES
('PROD-001', 'Laptop Empresarial 14"', 1, 2, 25, 10, 80, 20, 2200.00, 3200.00, 'unidades'),
('PROD-002', 'Disco SSD 512GB', 1, 2, 60, 20, 200, 40, 180.00, 259.00, 'unidades'),
('PROD-003', 'Licencia Office 365 Anual', 2, 3, 35, 15, 100, 25, 350.00, 499.00, 'unidades'),
('PROD-004', 'Switch 24 Puertos', 3, 1, 12, 5, 40, 10, 850.00, 1299.00, 'unidades'),
('PROD-005', 'Router WiFi 6', 3, 1, 28, 10, 60, 15, 320.00, 499.00, 'unidades'),
('PROD-006', 'Servicio de Consultoria (hora)', 4, 4, 200, 40, 500, 50, 45.00, 120.00, 'horas'),
('PROD-007', 'Servicio de Automatizacion (hora)', 4, 4, 160, 30, 400, 40, 60.00, 150.00, 'horas'),
('PROD-008', 'Papel Bond A4 (millar)', 5, 5, 45, 20, 100, 30, 18.00, 29.00, 'millares'),
('PROD-009', 'Toner Impresora', 5, 5, 22, 8, 50, 12, 120.00, 189.00, 'unidades'),
('PROD-010', 'Monitor 27" FullHD', 1, 1, 18, 8, 60, 12, 520.00, 799.00, 'unidades'),
('PROD-011', 'Teclado + Mouse Inalambrico', 1, 1, 55, 20, 150, 35, 45.00, 79.00, 'unidades'),
('PROD-012', 'Cable UTP Cat6 (rollo 305m)', 3, 5, 14, 6, 30, 8, 180.00, 289.00, 'rollos');

INSERT INTO movimientos_inventario (producto_id, tipo, cantidad, costo_unitario, fecha, concepto) VALUES
(1, 'entrada', 40, 2200.00, '2026-03-01', 'Compra trimestre'),
(1, 'salida', 8, 3200.00, '2026-03-15', 'Venta'),
(1, 'salida', 5, 3200.00, '2026-04-05', 'Venta'),
(1, 'salida', 6, 3200.00, '2026-05-10', 'Venta'),
(1, 'salida', 7, 3200.00, '2026-06-12', 'Venta'),
(1, 'salida', 8, 3200.00, '2026-07-14', 'Venta'),
(1, 'salida', 9, 3200.00, '2026-08-16', 'Venta'),
(2, 'entrada', 100, 180.00, '2026-03-01', 'Compra trimestre'),
(2, 'salida', 10, 259.00, '2026-03-20', 'Venta'),
(2, 'salida', 12, 259.00, '2026-04-18', 'Venta'),
(2, 'salida', 14, 259.00, '2026-05-15', 'Venta'),
(2, 'salida', 16, 259.00, '2026-06-20', 'Venta'),
(2, 'salida', 18, 259.00, '2026-07-22', 'Venta'),
(2, 'salida', 20, 259.00, '2026-08-25', 'Venta'),
(3, 'entrada', 50, 350.00, '2026-03-01', 'Compra trimestre'),
(3, 'salida', 3, 499.00, '2026-03-10', 'Venta'),
(3, 'salida', 4, 499.00, '2026-04-10', 'Venta'),
(3, 'salida', 5, 499.00, '2026-05-12', 'Venta'),
(3, 'salida', 6, 499.00, '2026-06-14', 'Venta'),
(3, 'salida', 7, 499.00, '2026-07-16', 'Venta'),
(3, 'salida', 8, 499.00, '2026-08-18', 'Venta'),
(4, 'entrada', 20, 850.00, '2026-03-01', 'Compra trimestre'),
(4, 'salida', 3, 1299.00, '2026-03-25', 'Venta'),
(4, 'salida', 2, 1299.00, '2026-04-28', 'Venta'),
(4, 'salida', 4, 1299.00, '2026-05-20', 'Venta'),
(4, 'salida', 3, 1299.00, '2026-06-25', 'Venta'),
(4, 'salida', 4, 1299.00, '2026-07-28', 'Venta'),
(4, 'salida', 5, 1299.00, '2026-08-30', 'Venta'),
(5, 'entrada', 40, 320.00, '2026-03-01', 'Compra trimestre'),
(5, 'salida', 4, 499.00, '2026-03-18', 'Venta'),
(5, 'salida', 5, 499.00, '2026-04-22', 'Venta'),
(5, 'salida', 6, 499.00, '2026-05-25', 'Venta'),
(5, 'salida', 7, 499.00, '2026-06-27', 'Venta'),
(5, 'salida', 8, 499.00, '2026-07-30', 'Venta'),
(5, 'salida', 9, 499.00, '2026-08-28', 'Venta'),
(6, 'entrada', 250, 45.00, '2026-03-01', 'Capacidad'),
(6, 'salida', 20, 120.00, '2026-03-15', 'Consultoria'),
(6, 'salida', 15, 120.00, '2026-04-15', 'Consultoria'),
(6, 'salida', 22, 120.00, '2026-05-15', 'Consultoria'),
(6, 'salida', 18, 120.00, '2026-06-15', 'Consultoria'),
(6, 'salida', 25, 120.00, '2026-07-15', 'Consultoria'),
(6, 'salida', 30, 120.00, '2026-08-15', 'Consultoria'),
(7, 'entrada', 200, 60.00, '2026-03-01', 'Capacidad'),
(7, 'salida', 12, 150.00, '2026-03-15', 'Automatizacion'),
(7, 'salida', 16, 150.00, '2026-04-15', 'Automatizacion'),
(7, 'salida', 14, 150.00, '2026-05-15', 'Automatizacion'),
(7, 'salida', 18, 150.00, '2026-06-15', 'Automatizacion'),
(7, 'salida', 22, 150.00, '2026-07-15', 'Automatizacion'),
(7, 'salida', 26, 150.00, '2026-08-15', 'Automatizacion'),
(8, 'entrada', 80, 18.00, '2026-03-01', 'Compra trimestre'),
(8, 'salida', 8, 29.00, '2026-03-10', 'Consumo'),
(8, 'salida', 5, 29.00, '2026-04-10', 'Consumo'),
(8, 'salida', 6, 29.00, '2026-05-10', 'Consumo'),
(8, 'salida', 7, 29.00, '2026-06-10', 'Consumo'),
(8, 'salida', 9, 29.00, '2026-07-10', 'Consumo'),
(8, 'salida', 11, 29.00, '2026-08-10', 'Consumo'),
(9, 'entrada', 40, 120.00, '2026-03-01', 'Compra trimestre'),
(9, 'salida', 4, 189.00, '2026-03-20', 'Venta'),
(9, 'salida', 3, 189.00, '2026-04-22', 'Venta'),
(9, 'salida', 5, 189.00, '2026-05-18', 'Venta'),
(9, 'salida', 3, 189.00, '2026-06-20', 'Venta'),
(9, 'salida', 4, 189.00, '2026-07-22', 'Venta'),
(9, 'salida', 5, 189.00, '2026-08-24', 'Venta'),
(10, 'entrada', 30, 520.00, '2026-03-01', 'Compra trimestre'),
(10, 'salida', 2, 799.00, '2026-03-15', 'Venta'),
(10, 'salida', 3, 799.00, '2026-04-18', 'Venta'),
(10, 'salida', 2, 799.00, '2026-05-20', 'Venta'),
(10, 'salida', 4, 799.00, '2026-06-22', 'Venta'),
(10, 'salida', 3, 799.00, '2026-07-25', 'Venta'),
(10, 'salida', 5, 799.00, '2026-08-28', 'Venta'),
(11, 'entrada', 100, 45.00, '2026-03-01', 'Compra trimestre'),
(11, 'salida', 8, 79.00, '2026-03-12', 'Venta'),
(11, 'salida', 10, 79.00, '2026-04-14', 'Venta'),
(11, 'salida', 12, 79.00, '2026-05-16', 'Venta'),
(11, 'salida', 10, 79.00, '2026-06-18', 'Venta'),
(11, 'salida', 12, 79.00, '2026-07-20', 'Venta'),
(11, 'salida', 14, 79.00, '2026-08-22', 'Venta'),
(12, 'entrada', 20, 180.00, '2026-03-01', 'Compra trimestre'),
(12, 'salida', 2, 289.00, '2026-03-15', 'Venta'),
(12, 'salida', 1, 289.00, '2026-04-15', 'Venta'),
(12, 'salida', 2, 289.00, '2026-05-15', 'Venta'),
(12, 'salida', 1, 289.00, '2026-06-15', 'Venta'),
(12, 'salida', 2, 289.00, '2026-07-15', 'Venta'),
(12, 'salida', 1, 289.00, '2026-08-15', 'Venta');

INSERT INTO clientes (nombre, ruc_dni, email, telefono, direccion) VALUES
('TechStart SAC', '20612345678', 'ventas@techstart.pe', '988111000', 'Av. Arequipa 1234, Lima'),
('Inversiones del Sur', '20598765432', 'compras@delsur.pe', '988222111', 'Jr. de la Union 567, Lima'),
('Distribuciones Norte', '20456789012', 'admin@distnorte.pe', '988333222', 'Av. Brasil 890, Lima'),
('Consultora Global', '20345678901', 'contacto@cglobal.pe', '988444333', 'Av. Javier Prado 456, Lima'),
('Minera San Juan', '20234567890', 'compras@minasanjuan.pe', '988555444', 'Calle Los Olivos 321, Arequipa'),
('Agro Export Perú', '20123456789', 'ventas@agroexport.pe', '988666555', 'Av. La Marina 789, Trujillo'),
('Hospital Regional', '20987654321', 'logistica@hregional.pe', '988777666', 'Av. Salaverry 147, Chimbote'),
('Constructora Horizonte', '20876543210', 'compras@horizonte.pe', '988888777', 'Calle San Martin 258, Cusco');

INSERT INTO comprobantes (tipo, serie, numero, cliente_id, fecha, subtotal, igv, total, estado) VALUES
('BOLETA', 'B001', 1, 1, '2026-01-15', 3200.00, 576.00, 3776.00, 'pagado'),
('FACTURA', 'F001', 1, 2, '2026-01-20', 8000.00, 1440.00, 9440.00, 'pagado'),
('BOLETA', 'B001', 2, 3, '2026-02-05', 1600.00, 288.00, 1888.00, 'pagado'),
('FACTURA', 'F001', 2, 4, '2026-02-18', 12000.00, 2160.00, 14160.00, 'pagado'),
('BOLETA', 'B001', 3, 5, '2026-03-10', 4800.00, 864.00, 5664.00, 'pagado'),
('FACTURA', 'F001', 3, 6, '2026-03-25', 6400.00, 1152.00, 7552.00, 'pagado'),
('FACTURA', 'F001', 4, 7, '2026-04-08', 16000.00, 2880.00, 18880.00, 'pagado'),
('BOLETA', 'B001', 4, 8, '2026-04-22', 2400.00, 432.00, 2832.00, 'pagado'),
('FACTURA', 'F001', 5, 1, '2026-05-05', 9600.00, 1728.00, 11328.00, 'pagado'),
('BOLETA', 'B001', 5, 2, '2026-05-18', 4000.00, 720.00, 4720.00, 'pagado'),
('FACTURA', 'F001', 6, 3, '2026-06-02', 14400.00, 2592.00, 16992.00, 'pagado'),
('BOLETA', 'B001', 6, 4, '2026-06-15', 3200.00, 576.00, 3776.00, 'pagado'),
('FACTURA', 'F001', 7, 5, '2026-07-01', 19200.00, 3456.00, 22656.00, 'pagado'),
('BOLETA', 'B001', 7, 6, '2026-07-14', 5600.00, 1008.00, 6608.00, 'pagado'),
('FACTURA', 'F001', 8, 7, '2026-08-01', 24000.00, 4320.00, 28320.00, 'pagado'),
('BOLETA', 'B001', 8, 8, '2026-08-18', 4800.00, 864.00, 5664.00, 'pagado');

INSERT INTO comprobante_detalle (comprobante_id, producto_id, descripcion, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 'Laptop Empresarial 14"', 1, 3200.00, 3200.00),
(2, 1, 'Laptop Empresarial 14"', 2, 3200.00, 6400.00),
(2, 11, 'Teclado + Mouse Inalambrico', 20, 80.00, 1600.00),
(3, 1, 'Laptop Empresarial 14"', 1, 3200.00, 3200.00),
(4, 7, 'Servicio de Automatizacion (hora)', 80, 150.00, 12000.00),
(5, 10, 'Monitor 27 FullHD', 6, 800.00, 4800.00),
(6, 1, 'Laptop Empresarial 14"', 2, 3200.00, 6400.00),
(7, 7, 'Servicio de Automatizacion (hora)', 100, 150.00, 15000.00),
(7, 6, 'Servicio de Consultoria (hora)', 8, 125.00, 1000.00),
(8, 3, 'Licencia Office 365 Anual', 6, 400.00, 2400.00),
(9, 1, 'Laptop Empresarial 14"', 3, 3200.00, 9600.00),
(10, 2, 'Disco SSD 512GB', 20, 200.00, 4000.00),
(11, 7, 'Servicio de Automatizacion (hora)', 90, 160.00, 14400.00),
(12, 4, 'Switch 24 Puertos', 2, 1300.00, 2600.00),
(12, 11, 'Teclado + Mouse Inalambrico', 10, 60.00, 600.00),
(13, 7, 'Servicio de Automatizacion (hora)', 120, 160.00, 19200.00),
(14, 1, 'Laptop Empresarial 14"', 1, 3200.00, 3200.00),
(14, 3, 'Licencia Office 365 Anual', 6, 400.00, 2400.00),
(15, 7, 'Servicio de Automatizacion (hora)', 150, 160.00, 24000.00),
(16, 10, 'Monitor 27 FullHD', 6, 800.00, 4800.00);

INSERT INTO cuentas_sap (codigo, nombre, tipo, saldo) VALUES
('101', 'Caja', 'activo', 45000.00),
('104', 'Cuentas corrientes', 'activo', 180000.00),
('121', 'Facturas por cobrar', 'activo', 35000.00),
('201', 'Inventario mercaderias', 'activo', 95000.00),
('401', 'IGV por pagar', 'pasivo', 28000.00),
('421', 'Facturas por pagar', 'pasivo', 42000.00),
('50', 'Capital', 'patrimonio', 200000.00),
('59', 'Resultados acumulados', 'patrimonio', 85000.00),
('701', 'Ventas mercaderias', 'ingreso', 245000.00),
('707', 'Servicios prestados', 'ingreso', 180000.00),
('601', 'Compras mercaderias', 'egreso', 130000.00),
('62', 'Gastos de personal', 'egreso', 156000.00),
('63', 'Servicios terceros', 'egreso', 24000.00),
('64', 'Tributos', 'egreso', 28000.00),
('94', 'Gastos administrativos', 'egreso', 18000.00);

INSERT INTO transacciones (fecha, cuenta_id, tipo, monto, descripcion, referencia) VALUES
('2026-01-31', 9, 'ingreso', 13216.00, 'Ventas enero', NULL),
('2026-01-31', 14, 'egreso', 12000.00, 'Planilla enero', 'PL001'),
('2026-01-31', 11, 'egreso', 25000.00, 'Compra mercaderia enero', 'OC001'),
('2026-02-28', 9, 'ingreso', 16048.00, 'Ventas febrero', NULL),
('2026-02-28', 14, 'egreso', 12000.00, 'Planilla febrero', 'PL002'),
('2026-02-28', 11, 'egreso', 22000.00, 'Compra mercaderia febrero', 'OC002'),
('2026-03-31', 9, 'ingreso', 24544.00, 'Ventas marzo', NULL),
('2026-03-31', 14, 'egreso', 12000.00, 'Planilla marzo', 'PL003'),
('2026-03-31', 11, 'egreso', 28000.00, 'Compra mercaderia marzo', 'OC003'),
('2026-04-30', 9, 'ingreso', 21712.00, 'Ventas abril', NULL),
('2026-04-30', 14, 'egreso', 12000.00, 'Planilla abril', 'PL004'),
('2026-04-30', 11, 'egreso', 24000.00, 'Compra mercaderia abril', 'OC004'),
('2026-05-31', 9, 'ingreso', 27328.00, 'Ventas mayo', NULL),
('2026-05-31', 14, 'egreso', 12000.00, 'Planilla mayo', 'PL005'),
('2026-05-31', 11, 'egreso', 30000.00, 'Compra mercaderia mayo', 'OC005'),
('2026-06-30', 9, 'ingreso', 20768.00, 'Ventas junio', NULL),
('2026-06-30', 14, 'egreso', 12000.00, 'Planilla junio', 'PL006'),
('2026-06-30', 11, 'egreso', 26000.00, 'Compra mercaderia junio', 'OC006'),
('2026-07-31', 9, 'ingreso', 29264.00, 'Ventas julio', NULL),
('2026-07-31', 14, 'egreso', 12000.00, 'Planilla julio', 'PL007'),
('2026-07-31', 11, 'egreso', 32000.00, 'Compra mercaderia julio', 'OC007'),
('2026-08-31', 9, 'ingreso', 33984.00, 'Ventas agosto', NULL),
('2026-08-31', 14, 'egreso', 12000.00, 'Planilla agosto', 'PL008'),
('2026-08-31', 11, 'egreso', 35000.00, 'Compra mercaderia agosto', 'OC008');

INSERT INTO historico_diario (fecha, ventas, unidades_vendidas, clientes_atendidos, gastos) VALUES
('2026-07-01', 4200.00, 3, 2, 1800.00),
('2026-07-02', 3800.00, 2, 2, 1600.00),
('2026-07-03', 5100.00, 4, 3, 2100.00),
('2026-07-04', 2900.00, 2, 1, 1400.00),
('2026-07-05', 6300.00, 5, 4, 2400.00),
('2026-07-06', 1200.00, 1, 1, 800.00),
('2026-07-07', 4800.00, 3, 3, 1900.00),
('2026-07-08', 5500.00, 4, 3, 2200.00),
('2026-07-09', 3200.00, 2, 2, 1500.00),
('2026-07-10', 7100.00, 6, 5, 2800.00),
('2026-07-11', 4600.00, 3, 3, 1800.00),
('2026-07-12', 2800.00, 2, 1, 1300.00),
('2026-07-13', 5900.00, 4, 4, 2300.00),
('2026-07-14', 4100.00, 3, 2, 1700.00),
('2026-07-15', 6700.00, 5, 4, 2500.00),
('2026-07-16', 3500.00, 2, 2, 1600.00),
('2026-07-17', 5200.00, 4, 3, 2000.00),
('2026-07-18', 8100.00, 6, 5, 3000.00),
('2026-07-19', 4400.00, 3, 2, 1800.00),
('2026-07-20', 3600.00, 2, 2, 1500.00),
('2026-07-21', 5800.00, 4, 3, 2200.00),
('2026-07-22', 6200.00, 5, 4, 2400.00),
('2026-07-23', 4900.00, 3, 3, 1900.00),
('2026-07-24', 3100.00, 2, 1, 1400.00),
('2026-07-25', 7500.00, 6, 5, 2900.00),
('2026-07-26', 2200.00, 1, 1, 1000.00),
('2026-07-27', 5400.00, 4, 3, 2100.00),
('2026-07-28', 4700.00, 3, 3, 1800.00),
('2026-07-29', 6000.00, 5, 4, 2300.00),
('2026-07-30', 3900.00, 2, 2, 1600.00),
('2026-07-31', 5600.00, 4, 3, 2200.00),
('2026-08-01', 4500.00, 3, 2, 1800.00),
('2026-08-02', 3700.00, 2, 2, 1600.00),
('2026-08-03', 5300.00, 4, 3, 2100.00),
('2026-08-04', 3000.00, 2, 1, 1400.00),
('2026-08-05', 6500.00, 5, 4, 2400.00),
('2026-08-06', 1400.00, 1, 1, 800.00),
('2026-08-07', 5000.00, 3, 3, 1900.00),
('2026-08-08', 5700.00, 4, 3, 2200.00),
('2026-08-09', 3400.00, 2, 2, 1500.00),
('2026-08-10', 7300.00, 6, 5, 2800.00),
('2026-08-11', 4800.00, 3, 3, 1800.00),
('2026-08-12', 2900.00, 2, 1, 1300.00),
('2026-08-13', 6100.00, 4, 4, 2300.00),
('2026-08-14', 4300.00, 3, 2, 1700.00),
('2026-08-15', 6900.00, 5, 4, 2500.00),
('2026-08-16', 3700.00, 2, 2, 1600.00),
('2026-08-17', 5400.00, 4, 3, 2000.00),
('2026-08-18', 8300.00, 6, 5, 3000.00),
('2026-08-19', 4600.00, 3, 2, 1800.00),
('2026-08-20', 3800.00, 2, 2, 1500.00),
('2026-08-21', 6000.00, 4, 3, 2200.00),
('2026-08-22', 6400.00, 5, 4, 2400.00),
('2026-08-23', 5100.00, 3, 3, 1900.00),
('2026-08-24', 3300.00, 2, 1, 1400.00),
('2026-08-25', 7700.00, 6, 5, 2900.00),
('2026-08-26', 2400.00, 1, 1, 1000.00),
('2026-08-27', 5600.00, 4, 3, 2100.00),
('2026-08-28', 4900.00, 3, 3, 1800.00),
('2026-08-29', 6200.00, 5, 4, 2300.00),
('2026-08-30', 4100.00, 2, 2, 1600.00),
('2026-08-31', 5800.00, 4, 3, 2200.00);

INSERT INTO periodos_planilla (periodo, inicio, fin, estado, total_bruto, total_descuentos, total_neto) VALUES
('2026-01', '2026-01-01', '2026-01-31', 'cerrado', 58200.00, 12540.00, 45660.00),
('2026-02', '2026-02-01', '2026-02-28', 'cerrado', 58200.00, 12540.00, 45660.00),
('2026-03', '2026-03-01', '2026-03-31', 'cerrado', 58200.00, 12540.00, 45660.00),
('2026-04', '2026-04-01', '2026-04-30', 'cerrado', 58200.00, 12540.00, 45660.00),
('2026-05', '2026-05-01', '2026-05-31', 'cerrado', 58200.00, 12540.00, 45660.00),
('2026-06', '2026-06-01', '2026-06-30', 'cerrado', 58200.00, 12540.00, 45660.00),
('2026-07', '2026-07-01', '2026-07-31', 'cerrado', 58200.00, 12540.00, 45660.00),
('2026-08', '2026-08-01', '2026-08-31', 'abierto', 58200.00, 12540.00, 45660.00);

INSERT INTO boletas_pago (periodo_id, empleado_id, dias_laborados, horas_extras, sueldo_base, bono, gratificacion, cts, essalud, onp, otros_descuentos, total_bruto, total_descuentos, neto_pagar, estado) VALUES
(1, 1, 26, 0, 12000.00, 0, 0, 0, 1080.00, 1560.00, 0, 12000.00, 2640.00, 9360.00, 'pagado'),
(1, 2, 26, 0, 4500.00, 0, 0, 0, 405.00, 585.00, 0, 4500.00, 990.00, 3510.00, 'pagado'),
(1, 3, 26, 2, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(1, 4, 26, 0, 2500.00, 0, 0, 0, 225.00, 325.00, 0, 2500.00, 550.00, 1950.00, 'pagado'),
(1, 5, 26, 4, 1400.00, 0, 0, 0, 126.00, 182.00, 0, 1400.00, 308.00, 1092.00, 'pagado'),
(1, 6, 26, 0, 3200.00, 0, 0, 0, 288.00, 416.00, 0, 3200.00, 704.00, 2496.00, 'pagado'),
(1, 7, 26, 0, 1300.00, 0, 0, 0, 117.00, 169.00, 0, 1300.00, 286.00, 1014.00, 'pagado'),
(1, 8, 26, 3, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(1, 9, 26, 0, 3500.00, 0, 0, 0, 315.00, 455.00, 0, 3500.00, 770.00, 2730.00, 'pagado'),
(1, 10, 26, 0, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(1, 11, 26, 0, 4000.00, 0, 0, 0, 360.00, 520.00, 0, 4000.00, 880.00, 3120.00, 'pagado'),
(1, 12, 26, 0, 5000.00, 0, 0, 0, 450.00, 650.00, 0, 5000.00, 1100.00, 3900.00, 'pagado'),
(1, 13, 26, 0, 2800.00, 0, 0, 0, 252.00, 364.00, 0, 2800.00, 616.00, 2184.00, 'pagado'),
(2, 1, 26, 0, 12000.00, 0, 0, 0, 1080.00, 1560.00, 0, 12000.00, 2640.00, 9360.00, 'pagado'),
(2, 2, 26, 0, 4500.00, 0, 0, 0, 405.00, 585.00, 0, 4500.00, 990.00, 3510.00, 'pagado'),
(2, 3, 26, 2, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(2, 4, 26, 0, 2500.00, 0, 0, 0, 225.00, 325.00, 0, 2500.00, 550.00, 1950.00, 'pagado'),
(2, 5, 26, 4, 1400.00, 0, 0, 0, 126.00, 182.00, 0, 1400.00, 308.00, 1092.00, 'pagado'),
(2, 6, 26, 0, 3200.00, 0, 0, 0, 288.00, 416.00, 0, 3200.00, 704.00, 2496.00, 'pagado'),
(2, 7, 26, 0, 1300.00, 0, 0, 0, 117.00, 169.00, 0, 1300.00, 286.00, 1014.00, 'pagado'),
(2, 8, 26, 3, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(2, 9, 26, 0, 3500.00, 0, 0, 0, 315.00, 455.00, 0, 3500.00, 770.00, 2730.00, 'pagado'),
(2, 10, 26, 0, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(2, 11, 26, 0, 4000.00, 0, 0, 0, 360.00, 520.00, 0, 4000.00, 880.00, 3120.00, 'pagado'),
(2, 12, 26, 0, 5000.00, 0, 0, 0, 450.00, 650.00, 0, 5000.00, 1100.00, 3900.00, 'pagado'),
(2, 13, 26, 0, 2800.00, 0, 0, 0, 252.00, 364.00, 0, 2800.00, 616.00, 2184.00, 'pagado'),
(3, 1, 26, 0, 12000.00, 0, 0, 0, 1080.00, 1560.00, 0, 12000.00, 2640.00, 9360.00, 'pagado'),
(3, 2, 26, 0, 4500.00, 0, 0, 0, 405.00, 585.00, 0, 4500.00, 990.00, 3510.00, 'pagado'),
(3, 3, 26, 2, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(3, 4, 26, 0, 2500.00, 0, 0, 0, 225.00, 325.00, 0, 2500.00, 550.00, 1950.00, 'pagado'),
(3, 5, 26, 4, 1400.00, 0, 0, 0, 126.00, 182.00, 0, 1400.00, 308.00, 1092.00, 'pagado'),
(3, 6, 26, 0, 3200.00, 0, 0, 0, 288.00, 416.00, 0, 3200.00, 704.00, 2496.00, 'pagado'),
(3, 7, 26, 0, 1300.00, 0, 0, 0, 117.00, 169.00, 0, 1300.00, 286.00, 1014.00, 'pagado'),
(3, 8, 26, 3, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(3, 9, 26, 0, 3500.00, 0, 0, 0, 315.00, 455.00, 0, 3500.00, 770.00, 2730.00, 'pagado'),
(3, 10, 26, 0, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(3, 11, 26, 0, 4000.00, 0, 0, 0, 360.00, 520.00, 0, 4000.00, 880.00, 3120.00, 'pagado'),
(3, 12, 26, 0, 5000.00, 0, 0, 0, 450.00, 650.00, 0, 5000.00, 1100.00, 3900.00, 'pagado'),
(3, 13, 26, 0, 2800.00, 0, 0, 0, 252.00, 364.00, 0, 2800.00, 616.00, 2184.00, 'pagado'),
(7, 1, 26, 0, 12000.00, 0, 0, 0, 1080.00, 1560.00, 0, 12000.00, 2640.00, 9360.00, 'pagado'),
(7, 2, 26, 0, 4500.00, 0, 0, 0, 405.00, 585.00, 0, 4500.00, 990.00, 3510.00, 'pagado'),
(7, 3, 26, 2, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(7, 4, 26, 0, 2500.00, 0, 0, 0, 225.00, 325.00, 0, 2500.00, 550.00, 1950.00, 'pagado'),
(7, 5, 26, 4, 1400.00, 0, 0, 0, 126.00, 182.00, 0, 1400.00, 308.00, 1092.00, 'pagado'),
(7, 6, 26, 0, 3200.00, 0, 0, 0, 288.00, 416.00, 0, 3200.00, 704.00, 2496.00, 'pagado'),
(7, 7, 26, 0, 1300.00, 0, 0, 0, 117.00, 169.00, 0, 1300.00, 286.00, 1014.00, 'pagado'),
(7, 8, 26, 3, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(7, 9, 26, 0, 3500.00, 0, 0, 0, 315.00, 455.00, 0, 3500.00, 770.00, 2730.00, 'pagado'),
(7, 10, 26, 0, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'pagado'),
(7, 11, 26, 0, 4000.00, 0, 0, 0, 360.00, 520.00, 0, 4000.00, 880.00, 3120.00, 'pagado'),
(7, 12, 26, 0, 5000.00, 0, 0, 0, 450.00, 650.00, 0, 5000.00, 1100.00, 3900.00, 'pagado'),
(7, 13, 26, 0, 2800.00, 0, 0, 0, 252.00, 364.00, 0, 2800.00, 616.00, 2184.00, 'pagado'),
(8, 1, 26, 0, 12000.00, 0, 0, 0, 1080.00, 1560.00, 0, 12000.00, 2640.00, 9360.00, 'generado'),
(8, 2, 26, 0, 4500.00, 0, 0, 0, 405.00, 585.00, 0, 4500.00, 990.00, 3510.00, 'generado'),
(8, 3, 26, 2, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'generado'),
(8, 4, 26, 0, 2500.00, 0, 0, 0, 225.00, 325.00, 0, 2500.00, 550.00, 1950.00, 'generado'),
(8, 5, 26, 4, 1400.00, 0, 0, 0, 126.00, 182.00, 0, 1400.00, 308.00, 1092.00, 'generado'),
(8, 6, 26, 0, 3200.00, 0, 0, 0, 288.00, 416.00, 0, 3200.00, 704.00, 2496.00, 'generado'),
(8, 7, 26, 0, 1300.00, 0, 0, 0, 117.00, 169.00, 0, 1300.00, 286.00, 1014.00, 'generado'),
(8, 8, 26, 3, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'generado'),
(8, 9, 26, 0, 3500.00, 0, 0, 0, 315.00, 455.00, 0, 3500.00, 770.00, 2730.00, 'generado'),
(8, 10, 26, 0, 1500.00, 0, 0, 0, 135.00, 195.00, 0, 1500.00, 330.00, 1170.00, 'generado'),
(8, 11, 26, 0, 4000.00, 0, 0, 0, 360.00, 520.00, 0, 4000.00, 880.00, 3120.00, 'generado'),
(8, 12, 26, 0, 5000.00, 0, 0, 0, 450.00, 650.00, 0, 5000.00, 1100.00, 3900.00, 'generado'),
(8, 13, 26, 0, 2800.00, 0, 0, 0, 252.00, 364.00, 0, 2800.00, 616.00, 2184.00, 'generado');
