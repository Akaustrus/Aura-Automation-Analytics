-- =====================================================
-- Aura Automation & Analytics - Sistema de Gestión de Incidencias
-- Base de datos: aura
-- Somos una empresa que vende soluciones de software y analytics a empresas
-- (clientes = empresas). Atendemos sus incidencias por teléfono,
-- las agendamos en tickets con prioridad, las asignamos a un
-- encargado y medimos la satisfacción del cliente.
-- =====================================================

DROP DATABASE IF EXISTS aura;
CREATE DATABASE aura
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aura;

-- -----------------------------------------------------
-- Tabla: usuarios (soporte, encargado, admin)
-- -----------------------------------------------------
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('soporte', 'encargado', 'admin') NOT NULL DEFAULT 'soporte',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- Tabla: clientes (EMPRESAS a las que vendemos el ERP)
-- El cliente es una empresa. Da su RUC y su clave por
-- teléfono y el soporte los usa para identificarla.
-- La empresa también puede entrar al portal web con
-- su RUC + clave para ver sus tickets y calificar.
-- -----------------------------------------------------
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa VARCHAR(150) NOT NULL,
    ruc VARCHAR(20) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL,
    email VARCHAR(150),
    telefono VARCHAR(30),
    contacto VARCHAR(150),
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------
-- Tabla: planes (catálogo de los 5 SISTEMAS que vendemos:
-- Contabilidad, Facturación, Planilla, ERP Básico, ERP Mediana Empresa)
-- Cada sistema tiene su propia base de datos y carpeta de módulo.
-- -----------------------------------------------------
CREATE TABLE planes (
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
-- Tabla: cliente_planes (suscripción de una empresa a un sistema)
-- Es el "plan que quiere" de los 5 sistemas.
-- estado: activo | pendiente | vencido | cancelado
-- -----------------------------------------------------
CREATE TABLE cliente_planes (
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
-- Tabla: tickets (incidencias de los sistemas que vendemos)
-- Categorías orientadas a problemas de software / ERP:
-- rendimiento, funcion_nueva, actualizacion, error, consulta
-- -----------------------------------------------------
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    plan_id INT DEFAULT NULL,
    usuario_id INT DEFAULT NULL,
    categoria ENUM('rendimiento', 'funcion_nueva', 'actualizacion', 'error', 'consulta') NOT NULL,
    urgencia ENUM('baja', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
    prioridad ENUM('baja', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
    canal ENUM('telefono', 'correo', 'portal') NOT NULL DEFAULT 'telefono',
    asunto VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    estado ENUM('nuevo', 'en_curso', 'pausado', 'culminado', 'resuelto', 'cerrado') NOT NULL DEFAULT 'nuevo',
    encargado_id INT DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_culminacion DATETIME DEFAULT NULL,
    fecha_resolucion DATETIME DEFAULT NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (plan_id) REFERENCES planes(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (encargado_id) REFERENCES usuarios(id)
);

-- -----------------------------------------------------
-- Tabla: historial (movimientos del ticket)
-- -----------------------------------------------------
CREATE TABLE historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    usuario_id INT DEFAULT NULL,
    estado_anterior VARCHAR(30),
    estado_nuevo VARCHAR(30) NOT NULL,
    comentario VARCHAR(255),
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- -----------------------------------------------------
-- Tabla: notificaciones (internas, para encargado/admin)
-- Se crean cuando un encargado culmina su trabajo para
-- avisar que el ticket está culminado y pendiente de
-- validación / cierre.
-- -----------------------------------------------------
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    ticket_id INT NOT NULL,
    mensaje VARCHAR(255) NOT NULL,
    leida TINYINT(1) NOT NULL DEFAULT 0,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Tabla: horarios_slot (grilla de atención por día de la semana)
-- Permite definir cuántos cupos hay por día y horario.
-- dia_semana: 0=Domingo ... 6=Sábado
-- -----------------------------------------------------
CREATE TABLE horarios_slot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dia_semana TINYINT NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    cupos INT NOT NULL DEFAULT 1,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_slot (dia_semana, hora_inicio)
);

-- -----------------------------------------------------
-- Tabla: citas (agendamiento de atención del cliente)
-- El cliente empresa agenda una cita en un slot libre,
-- en modalidad presencial o virtual.
-- estado: reservada | confirmada | realizada | cancelada
-- modalidad: presencial | virtual
-- -----------------------------------------------------
CREATE TABLE citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    slot_id INT NOT NULL,
    fecha DATE NOT NULL,
    modalidad ENUM('presencial', 'virtual') NOT NULL DEFAULT 'presencial',
    asunto VARCHAR(200),
    estado ENUM('reservada', 'confirmada', 'realizada', 'cancelada') NOT NULL DEFAULT 'reservada',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cliente_slot (cliente_id, slot_id, fecha),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (slot_id) REFERENCES horarios_slot(id)
);

-- -----------------------------------------------------
-- Tabla: comentarios_ticket (hilo de comunicación soporte <-> cliente)
-- El equipo interno y el cliente conversan dentro del ticket.
-- usuario_id indica que el autor es del equipo interno;
-- cliente_id indica que el autor es la empresa cliente.
-- -----------------------------------------------------
CREATE TABLE comentarios_ticket (
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

-- -----------------------------------------------------
-- Tabla: satisfacciones (nivel de satisfacción del cliente)
-- El cliente empresa califica su nivel de satisfacción
-- desde el portal, cuando su ticket está culminado/resuelto.
-- -----------------------------------------------------
CREATE TABLE satisfacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    cliente_id INT NOT NULL,
    nivel ENUM('muy_insatisfecho', 'insatisfecho', 'neutral', 'satisfecho', 'muy_satisfecho') NOT NULL,
    comentario VARCHAR(500),
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

-- -----------------------------------------------------
-- Datos semilla - Usuarios (equipo Aura)
-- Contraseñas: admin123, enc123, sop123
-- -----------------------------------------------------
INSERT INTO usuarios (nombre, email, password, rol) VALUES
('Director',        'direccion@auraautomation.com',  '$2y$10$IQv2Jj.6hoy6CL33IC3j..G4YMeKc.iI7wojys1CenSNs/WSNo4mC', 'admin'),
('Rosa Encargada',  'rosa@auraautomation.com',       '$2y$10$Zobbpa0iMuqHZXFyooChwubvWDBTEHtCq3NiY29//abNUiwKAocea', 'encargado'),
('Luis Soporte',    'luis@auraautomation.com',       '$2y$10$A5mlYl51z9QO/wemBJhPKuF/q89qC89zW.XPBt0E4wy6zWJcl2yB.', 'soporte'),
('Elena Soporte',   'elena@auraautomation.com',      '$2y$10$A5mlYl51z9QO/wemBJhPKuF/q89qC89zW.XPBt0E4wy6zWJcl2yB.', 'soporte'),
('Juanito Solución', 'juanito@auraautomation.com',   '$2y$10$Zobbpa0iMuqHZXFyooChwubvWDBTEHtCq3NiY29//abNUiwKAocea', 'encargado');

-- -----------------------------------------------------
-- Datos semilla - Clientes (EMPRESAS)
-- Clave de acceso (por teléfono): clave123 (todas iguales)
-- El cliente entra al portal con su RUC + clave123
-- -----------------------------------------------------
INSERT INTO clientes (empresa, ruc, clave, email, telefono, contacto) VALUES
('Textil Rojas S.A.C.', '20123456789', '$2y$10$6Cnr9wlnAQZRH.PQ0NPAVevbRveZu16dSp.pByx96sYxDF8zZjP2K', 'carmen@textilrojas.com', '900111222', 'Carmen Rojas'),
('Vargas Logística',    '20598765432', '$2y$10$6Cnr9wlnAQZRH.PQ0NPAVevbRveZu16dSp.pByx96sYxDF8zZjP2K', 'diego@vargaslog.com',    '900333444', 'Diego Vargas'),
('Reyes & Asociados',   '20111122333', '$2y$10$6Cnr9wlnAQZRH.PQ0NPAVevbRveZu16dSp.pByx96sYxDF8zZjP2K', 'sofia@reyes.com',        '900555666', 'Sofía Reyes'),
('Prada Constructora',  '20544455666', '$2y$10$6Cnr9wlnAQZRH.PQ0NPAVevbRveZu16dSp.pByx96sYxDF8zZjP2K', 'miguel@prada.com',      '900777888', 'Miguel Prada'),
('Chamitos S.A.C.',     '20612345678', '$2y$10$6Cnr9wlnAQZRH.PQ0NPAVevbRveZu16dSp.pByx96sYxDF8zZjP2K', 'info@chamitos.com',     '900999000', 'Pedro Chamito'),
('AgroExport del Norte S.A.C.', '20678901234', '$2y$10$6Cnr9wlnAQZRH.PQ0NPAVevbRveZu16dSp.pByx96sYxDF8zZjP2K', 'ventas@agroexport.com', '911222333', 'Rosa Aguirre'),
('Construcciones Beta E.I.R.L.', '20556789012', '$2y$10$6Cnr9wlnAQZRH.PQ0NPAVevbRveZu16dSp.pByx96sYxDF8zZjP2K', 'admin@constbeta.com',   '912333444', 'Jorge Vargas'),
('Hotel Mirador del Valle S.R.L.', '20434567890', '$2y$10$6Cnr9wlnAQZRH.PQ0NPAVevbRveZu16dSp.pByx96sYxDF8zZjP2K', 'recepcion@mirador.com', '913444555', 'Lucía Mendoza'),
('Distribuidora Omega S.A.C.', '20312345678', '$2y$10$6Cnr9wlnAQZRH.PQ0NPAVevbRveZu16dSp.pByx96sYxDF8zZjP2K', 'gerencia@omega.com',   '914555666', 'Carlos Ruiz');

-- -----------------------------------------------------
-- Datos semilla - Catálogo de los 5 SISTEMAS que vendemos
-- (cada uno tiene su propia BD y carpeta de módulo)
-- -----------------------------------------------------
INSERT INTO planes (nombre, slug, modulo, descripcion, icono, modulos, activo, orden) VALUES
('Contabilidad',        'contabilidad', 'sistemacontable',    'Plan de cuentas, asientos, documentos con IGV, libro mayor, balance, estados financieros, presupuestos y reportes.', 'fa-calculator',         11, 1, 1),
('Facturación',         'facturacion',  'sistemafacturacion', 'Clientes, productos/servicios y comprobantes de venta con IGV configurable, correlativos automáticos y PDF.',      'fa-file-invoice-dollar', 8, 1, 2),
('Planilla',            'planilla',     'sistemaplanilla',    'RRHH completo: empleados, asistencia, adelantos, planillas mensuales, gratificaciones, CTS y boletas.',            'fa-users-gear',         10, 1, 3),
('ERP Básico',          'erp-basico',   'sistemaerp',         'Operación comercial: compras, inventario, ventas y caja para micro empresas.',                                       'fa-industry',           11, 1, 4),
('ERP Mediana Empresa', 'erp-mediana',  'sistemaermedio',     'ERP completo con login y roles: cotización, ventas, cobranzas, compras, multi-almacén y reportes.',                 'fa-building',           19, 1, 5);

-- -----------------------------------------------------
-- Datos semilla - Suscripciones (plan por empresa)
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
-- Datos semilla - Tickets (incidencias de los sistemas)
-- -----------------------------------------------------
INSERT INTO tickets (cliente_id, plan_id, usuario_id, categoria, urgencia, prioridad, canal, asunto, descripcion, estado, encargado_id, fecha_creacion, fecha_culminacion, fecha_resolucion) VALUES
-- Ticket culminado por Juanito (encargado) pendiente de validación
(1, 3, 1, 'rendimiento', 'alta', 'alta', 'telefono', 'Dashboard lento',
 'La empresa reporta que su dashboard tarda más de 30 segundos en cargar. Impacta la operación diaria.',
 'culminado', 5, '2026-09-01 09:00:00', '2026-09-02 10:00:00', NULL),

-- Ticket nuevo sin encargado asignado
(2, 5, NULL, 'error', 'urgente', 'urgente', 'telefono', 'No cargan los usuarios',
 'Al ingresar al módulo de usuarios se queda en blanco. No pueden registrarse nuevas cuentas. Detuvo operaciones.',
 'nuevo', NULL, '2026-09-02 08:20:00', NULL, NULL),

-- Ticket con solicitud de nueva función
(3, 1, 1, 'funcion_nueva', 'baja', 'baja', 'telefono', 'Agregar reporte de ventas por sucursal',
 'La empresa solicita una nueva funcionalidad: reporte de ventas desglosado por sucursal.',
 'en_curso', 5, '2026-08-30 10:00:00', NULL, NULL),

-- Ticket de actualización
(4, 4, 4, 'actualizacion', 'media', 'media', 'correo', 'Actualizar versión del ERP',
 'Solicitan actualizar el ERP a la última versión disponible para corregir bugs conocidos.',
 'resuelto', 5, '2026-09-02 11:45:00', NULL, '2026-09-03 09:00:00'),

-- AgroExport: rendimiento del módulo de inventario lento (en_curso)
(6, 5, 4, 'rendimiento', 'alta', 'alta', 'telefono', 'Módulo de inventario muy lento',
 'El módulo de inventario tarda hasta 40 segundos en listar los productos. Afecta los despachos del día y genera colas en el almacén.',
 'en_curso', 5, '2026-09-03 09:00:00', NULL, NULL),

-- Construcciones Beta: error al emitir factura (nuevo, urgente)
(7, 3, NULL, 'error', 'urgente', 'urgente', 'telefono', 'No se pueden emitir facturas',
 'Al intentar generar una factura electrónica el sistema lanza error de validación y no emite el comprobante. Detuvo el cierre diario de ventas.',
 'nuevo', NULL, '2026-09-04 08:00:00', NULL, NULL),

-- Hotel Mirador: consulta sobre configuración de permisos (resuelto)
(8, 2, 3, 'consulta', 'baja', 'baja', 'correo', 'Configurar permisos de recepción',
 'Requieren asesoría para limitar lo que ve el personal de recepción en el módulo de reservas y no ver datos financieros.',
 'resuelto', 5, '2026-09-02 14:00:00', NULL, '2026-09-03 16:00:00'),

-- Distribuidora Omega: nueva función reporte de comisiones (pausado)
(9, 4, 1, 'funcion_nueva', 'media', 'media', 'portal', 'Reporte de comisiones de vendedores',
 'Solicitan una nueva funcionalidad para calcular y exportar las comisiones de los vendedores por periodo y por línea de producto.',
 'pausado', 5, '2026-09-01 10:30:00', NULL, NULL);

-- -----------------------------------------------------
-- Datos semilla - Historial
-- -----------------------------------------------------
INSERT INTO historial (ticket_id, usuario_id, estado_anterior, estado_nuevo, comentario, fecha) VALUES
(1, 1, 'nuevo', 'en_curso', 'Asignado a Juanito para revisar consulta del dashboard', '2026-09-01 09:15:00'),
(1, 5, 'en_curso', 'culminado', 'Optimizada la consulta SQL y el renderizado. Dashboard responde en <2s.', '2026-09-02 10:00:00'),
(3, 1, 'nuevo', 'en_curso', 'Asignado a Juanito para diseñar el nuevo reporte', '2026-08-30 10:30:00'),
(3, 5, 'en_curso', 'culminado', 'Reporte desarrollado y en pruebas.', '2026-09-03 11:00:00'),
(4, 1, 'nuevo', 'en_curso', 'Asignado para coordinar la migración', '2026-09-02 12:00:00'),
(4, 5, 'en_curso', 'resuelto', 'Actualización completada en producción.', '2026-09-03 09:00:00'),
(5, 1, 'nuevo', 'en_curso', 'Asignado a Elena y Juanito para revisar rendimiento del inventario', '2026-09-03 09:15:00'),
(5, 5, 'en_curso', 'en_curso', 'Optimizando consultas del módulo de inventario', '2026-09-03 15:00:00'),
(7, 1, 'nuevo', 'en_curso', 'Coordinando asesoría con el hotel', '2026-09-02 14:30:00'),
(7, 5, 'en_curso', 'resuelto', 'Permisos configurados según requerimiento', '2026-09-03 16:00:00'),
(8, 1, 'nuevo', 'en_curso', 'Asignado para cotizar el desarrollo del reporte', '2026-09-01 11:00:00'),
(8, 5, 'en_curso', 'pausado', 'Pendiente de aprobación de alcance por el cliente', '2026-09-02 12:00:00');

-- -----------------------------------------------------
-- Datos semilla - Notificaciones (avisos de "culminado")
-- -----------------------------------------------------
INSERT INTO notificaciones (usuario_id, ticket_id, mensaje, leida, fecha) VALUES
(1, 1, 'Juanito culminó el ticket #1: Dashboard lento. Revisa y cierra.', 0, '2026-09-02 10:00:00'),
(1, 3, 'Juanito culminó el ticket #3: Agregar reporte de ventas por sucursal. Revisa.', 0, '2026-09-03 11:00:00');

-- -----------------------------------------------------
-- Datos semilla - Satisfacciones
-- -----------------------------------------------------
INSERT INTO satisfacciones (ticket_id, cliente_id, nivel, comentario, fecha) VALUES
(4, 3, 'muy_satisfecho', 'La actualización fue rápida y sin cortes.', '2026-09-03 10:00:00');

-- -----------------------------------------------------
-- Datos semilla - Horarios de atención por día de la semana
-- (Lunes..Viernes: 09:00, 11:00, 15:00; cupo de 2 por slot;
--  Sábado solo mañana con cupo de 1)
-- -----------------------------------------------------
INSERT INTO horarios_slot (dia_semana, hora_inicio, hora_fin, cupos, activo) VALUES
(1, '09:00:00', '09:45:00', 2, 1), (1, '11:00:00', '11:45:00', 2, 1), (1, '15:00:00', '15:45:00', 2, 1),
(2, '09:00:00', '09:45:00', 2, 1), (2, '11:00:00', '11:45:00', 2, 1), (2, '15:00:00', '15:45:00', 2, 1),
(3, '09:00:00', '09:45:00', 2, 1), (3, '11:00:00', '11:45:00', 2, 1), (3, '15:00:00', '15:45:00', 2, 1),
(4, '09:00:00', '09:45:00', 2, 1), (4, '11:00:00', '11:45:00', 2, 1), (4, '15:00:00', '15:45:00', 2, 1),
(5, '09:00:00', '09:45:00', 2, 1), (5, '11:00:00', '11:45:00', 2, 1), (5, '15:00:00', '15:45:00', 2, 1),
(6, '09:00:00', '10:00:00', 1, 1), (6, '11:00:00', '12:00:00', 1, 1);

-- -----------------------------------------------------
-- Datos semilla - Cita de ejemplo (Chamitos, próx. miércoles 11:00)
-- -----------------------------------------------------
INSERT INTO citas (cliente_id, slot_id, fecha, modalidad, asunto, estado) VALUES
(5, 9, '2026-09-09', 'virtual', 'Revisión de rendimiento del dashboard', 'reservada');
