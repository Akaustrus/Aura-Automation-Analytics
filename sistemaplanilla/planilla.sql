-- ============================================================
-- SISTEMA DE PLANILLA PROFESIONAL — Aura Automation & Analytics
-- Base de datos: "planilla"
-- ============================================================

DROP DATABASE IF EXISTS planilla;
CREATE DATABASE planilla CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE planilla;

-- ------------------------------------------------------------
-- Parámetros del sistema (editables desde Configuración)
-- ------------------------------------------------------------
CREATE TABLE parametros (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    clave       VARCHAR(40) NOT NULL UNIQUE,
    valor       DECIMAL(14,4) NOT NULL DEFAULT 0,
    descripcion VARCHAR(120) NOT NULL DEFAULT ''
);
INSERT INTO parametros (clave, valor, descripcion) VALUES
('RMV',          1130.0000, 'Remuneración Mínima Vital (S/)'),
('UIT',          5350.0000, 'Unidad Impositiva Tributaria (S/) para renta de 5ta'),
('TASA_ESSALUD',  9.0000,  'ESSALUD aporte empleador (%)'),
('TASA_ONP',     13.0000,  'Descuento ONP (%)'),
('TASA_AFP',     12.5000,  'Descuento AFP referencial (%)'),
('JORNADA_HORAS', 8.0000,  'Jornada diaria (horas) para el valor hora'),
('GRATIF_BONO',   9.0000,  'Bonificación extraordinaria de gratificación (%)');

-- ------------------------------------------------------------
-- Conceptos configurables (bonos y descuentos adicionales)
--   tipo        : ingreso / descuento  (hacia el trabajador)
--   ambito      : trabajador / empleador (empleador = aporte patronal)
--   naturaleza  : fijo (monto) o porcentaje (sobre la remuneración)
--   aplica      : en qué tipo de planilla se aplica
-- ------------------------------------------------------------
CREATE TABLE conceptos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(80) NOT NULL,
    tipo        ENUM('ingreso','descuento') NOT NULL DEFAULT 'ingreso',
    ambito      ENUM('trabajador','empleador') NOT NULL DEFAULT 'trabajador',
    naturaleza  ENUM('fijo','porcentaje') NOT NULL DEFAULT 'fijo',
    monto       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    porcentaje  DECIMAL(6,4) NOT NULL DEFAULT 0.0000,
    aplica      ENUM('mensual','gratificacion','cts','todos') NOT NULL DEFAULT 'todos',
    activo      TINYINT(1) NOT NULL DEFAULT 1
);
INSERT INTO conceptos (nombre, tipo, ambito, naturaleza, monto, porcentaje, aplica, activo) VALUES
('Bonificación por movilidad',          'ingreso',   'trabajador', 'fijo',       100.0000, 0.0000, 'todos', 1),
('Comisión por meta',                   'ingreso',   'trabajador', 'porcentaje',   0.0000, 0.0200, 'todos', 1),
('Asignación familiar (10% RMV)',       'ingreso',   'trabajador', 'fijo',         0.0000, 0.0000, 'mensual', 1),
('Sobretiempo/horas extras',            'ingreso',   'trabajador', 'fijo',         0.0000, 0.0000, 'mensual', 1),
('Mención de no afectación',            'ingreso',   'trabajador', 'fijo',         0.0000, 0.0000, 'mensual', 0),
('Aporte ESSALUD',                      'descuento', 'empleador',   'porcentaje',   0.0000, 9.0000, 'todos', 1),
('Renta de 5ta categoría',              'descuento', 'trabajador', 'fijo',         0.0000, 0.0000, 'todos', 1);

-- ------------------------------------------------------------
-- Empleados
-- ------------------------------------------------------------
CREATE TABLE empleados (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    dni                VARCHAR(8)  NOT NULL UNIQUE,
    nombres            VARCHAR(60) NOT NULL,
    apellidos          VARCHAR(80) NOT NULL,
    genero             ENUM('M','F') NOT NULL DEFAULT 'M',
    telefono           VARCHAR(20) NOT NULL DEFAULT '',
    correo             VARCHAR(120) NOT NULL DEFAULT '',
    cargo              VARCHAR(80) NOT NULL,
    area               VARCHAR(60) NOT NULL DEFAULT '',
    fecha_ingreso      DATE NOT NULL,
    tipo_contrato      ENUM('indefinido','plazo_fijo','parcial','practicante') NOT NULL DEFAULT 'indefinido',
    sueldo_base        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    asignacion_familiar TINYINT(1) NOT NULL DEFAULT 0,
    jornada_horas      DECIMAL(4,2) NOT NULL DEFAULT 8.00,
    regimen            ENUM('onp','afp','ninguno') NOT NULL DEFAULT 'onp',
    cuspp              VARCHAR(20) NOT NULL DEFAULT '',
    banco              VARCHAR(40) NOT NULL DEFAULT '',
    cuenta_bancaria    VARCHAR(30) NOT NULL DEFAULT '',
    fecha_cese         DATE NULL,
    motivo_cese        VARCHAR(120) NOT NULL DEFAULT '',
    portal_token       CHAR(20) NOT NULL UNIQUE,
    activo             TINYINT(1) NOT NULL DEFAULT 1,
    creado             TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Asistencia diaria
-- ------------------------------------------------------------
CREATE TABLE asistencia (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    fecha       DATE NOT NULL,
    estado      ENUM('asistio','falta','permiso','vacaciones') NOT NULL DEFAULT 'asistio',
    entrada     TIME NULL,
    salida      TIME NULL,
    horas_extra DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    justificacion VARCHAR(255) NOT NULL DEFAULT '',
    UNIQUE KEY uq_asis (empleado_id, fecha),
    FOREIGN KEY (empleado_id) REFERENCES empleados(id)
);

-- ------------------------------------------------------------
-- Adelantos y prestamos
-- ------------------------------------------------------------
CREATE TABLE adelantos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    fecha       DATE NOT NULL,
    concepto    VARCHAR(120) NOT NULL,
    monto       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estado      ENUM('pendiente','cobrado') NOT NULL DEFAULT 'pendiente',
    FOREIGN KEY (empleado_id) REFERENCES empleados(id)
);

-- ------------------------------------------------------------
-- Planillas (cabecera por período + tipo)
-- ------------------------------------------------------------
CREATE TABLE planillas (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    periodo          VARCHAR(7) NOT NULL,
    tipo_planilla    ENUM('mensual','gratificacion','cts') NOT NULL DEFAULT 'mensual',
    estado           ENUM('borrador','emitida','anulada') NOT NULL DEFAULT 'borrador',
    fecha_generacion DATE NOT NULL,
    total_bruto      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_essalud    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_descuento  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_renta      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_adelantos  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_neto       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    glosa            VARCHAR(255) NOT NULL DEFAULT '',
    UNIQUE KEY uq_periodo (periodo, tipo_planilla)
);

-- ------------------------------------------------------------
-- Detalle de planilla (boleta por empleado)
-- ------------------------------------------------------------
CREATE TABLE detalle_planilla (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    planilla_id         INT NOT NULL,
    empleado_id         INT NOT NULL,
    dias_laborados      INT NOT NULL DEFAULT 30,
    dias_faltas         INT NOT NULL DEFAULT 0,
    horas_extra         DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    sueldo_base         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    asignacion_familiar DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    horas_extra_monto   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    bonos               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sueldo_bruto        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    essalud             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    descuento           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    renta               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    adelantos           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    neto                DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    detalle             TEXT NULL,
    FOREIGN KEY (planilla_id) REFERENCES planillas(id) ON DELETE CASCADE,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id)
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
INSERT INTO empleados
(dni, nombres, apellidos, genero, telefono, correo, cargo, area, fecha_ingreso, tipo_contrato,
 sueldo_base, asignacion_familiar, jornada_horas, regimen, cuspp, banco, cuenta_bancaria, portal_token) VALUES
('40123456', 'Lucía',   'Mendoza Rojas',  'F', '987654321', 'lucia.mendoza@auraautomation.com', 'Asistente Administrativa', 'Administración', '2024-03-01', 'indefinido',
 1800.00, 1, 8.00, 'onp', '101234567890', 'BBVA', '0011-2345-6789',  'aa11bb22cc33dd44ee'),
('40234567', 'Carlos',  'Paredes Vega',   'M', '976543210', 'carlos.paredes@auraautomation.com',  'Analista de Sistemas',     'TI',             '2023-07-15', 'indefinido',
 3200.00, 0, 8.00, 'afp', '400123456789',  'BCP', '191-45678901',     'bb22cc33dd44ee55ff'),
('40345678', 'María',   'Flores Cáceres', 'F', '965432109', 'maria.flores@auraautomation.com',    'Contadora',                'Finanzas',       '2022-01-10', 'indefinido',
 4200.00, 0, 8.00, 'onp', '101345678901',  'BCP', '191-12345678',     'cc33dd44ee55ff66a0'),
('40456789', 'José',    'Quispe Llanos',  'M', '954321098', 'jose.quispe@auraautomation.com',     'Técnico de Soporte',       'TI',             '2024-06-01', 'plazo_fijo',
 2000.00, 1, 8.00, 'afp', '400234567890',  'BBVA', '0011-8765-4321', 'dd44ee55ff66a0bb1c'),
('40567890', 'Ana',     'Torres Salas',   'F', '943210987', 'ana.torres@auraautomation.com',      'Ejecutiva de Ventas',      'Ventas',         '2023-10-23', 'indefinido',
 2500.00, 0, 8.00, 'onp', '101456789012',  'Interbank', '210-09876543', 'ee55ff66a0bb1c2d3e');

-- Asistencia: primeros 8 días hábiles del mes en curso
INSERT INTO asistencia (empleado_id, fecha, estado, entrada, salida, horas_extra, justificacion)
SELECT e.id,
       DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL t.n DAY),
       'asistio',
       ADDTIME('08:00:00', CAST(CONCAT('0:', t.n, ':00') AS TIME)),
       ADDTIME('17:00:00', CAST(CONCAT('0:', t.n, ':00') AS TIME)),
       IF(e.id = 2 AND t.n IN (1,3), 2.00, IF(e.id = 5 AND t.n = 2, 1.50, 0.00)), ''
FROM empleados e
CROSS JOIN (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) t
WHERE e.activo = 1
  AND DAYOFWEEK(DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL t.n DAY)) NOT IN (1,7);

-- Una falta y un permiso de ejemplo en el mes en curso
INSERT INTO asistencia (empleado_id, fecha, estado, justificacion)
SELECT e.id, DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 9 DAY), 'falta', 'Inasistencia sin justificar'
FROM empleados e WHERE e.id = 4;
INSERT INTO asistencia (empleado_id, fecha, estado, justificacion)
SELECT e.id, DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 10 DAY), 'permiso', 'Permiso personal'
FROM empleados e WHERE e.id = 1;

-- Adelanto pendiente de ejemplo
INSERT INTO adelantos (empleado_id, fecha, concepto, monto, estado) VALUES
(1, DATE_FORMAT(CURDATE(), '%Y-%m-%d'), 'Adelanto de sueldo', 200.00, 'pendiente');

-- ============================================================
-- Planilla MENSUAL de ejemplo del período en curso
-- ============================================================
INSERT INTO planillas (periodo, tipo_planilla, estado, fecha_generacion,
                       total_bruto, total_essalud, total_descuento, total_renta, total_adelantos, total_neto, glosa)
VALUES (DATE_FORMAT(CURDATE(), '%Y-%m'), 'mensual', 'emitida', CURDATE(),
        14714.43, 1304.30, 2182.31, 125.84, 200.00, 12532.12,
        'Planilla mensual de ejemplo (con bonos y descuentos de ley)');

INSERT INTO detalle_planilla
(planilla_id, empleado_id, dias_laborados, dias_faltas, horas_extra, sueldo_base, asignacion_familiar,
 horas_extra_monto, bonos, sueldo_bruto, essalud, descuento, renta, adelantos, neto, detalle)
VALUES
(1, 1, 30, 0, 0.00, 1800.00, 113.00,  0.00, 136.00, 2049.00, 174.24, 251.68,  0.00, 200.00, 1597.32, '{"base":1800,"asf":113,"he":0,"bonos":136}'),
(1, 2, 30, 0, 4.00, 3200.00,   0.00, 66.67, 164.00, 3430.67, 308.76, 428.83, 24.79,   0.00, 2977.05, '{"base":3200,"asf":0,"he":66.67,"bonos":164}'),
(1, 3, 30, 0, 0.00, 4200.00,   0.00,  0.00, 184.00, 4384.00, 394.56, 569.92, 101.05,  0.00, 3713.03, '{"base":4200,"asf":0,"he":0,"bonos":184}'),
(1, 4, 29, 1, 0.00, 1933.33, 109.23,  0.00, 138.67, 2181.23, 186.48, 259.00,  0.00,   0.00, 1922.23, '{"base":1933.33,"asf":109.23,"he":0,"bonos":138.67}'),
(1, 5, 30, 0, 1.50, 2500.00,   0.00, 19.53, 150.00, 2669.53, 240.26, 347.04,  0.00,   0.00, 2322.49, '{"base":2500,"asf":0,"he":19.53,"bonos":150}');