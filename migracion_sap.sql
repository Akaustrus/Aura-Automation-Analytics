-- =====================================================
-- Migración: Sistema SAP (Análisis Predictivo)
-- Añade el sistema SAP al catálogo de planes del portal.
--
-- Ejecutar una sola vez:
--   mysql -u root -p1234 softtech < migracion_sap.sql
-- =====================================================

INSERT INTO planes (nombre, slug, modulo, descripcion, icono, modulos, activo, orden)
SELECT 'SAP Predictivo', 'sap', 'sistemasap',
       'Análisis predictivo integral: RRHH, inventario, planilla, facturación, contabilidad y recomendaciones IA (qué comprar, cuándo y a quién contratar).',
       'fa-brain', 9, 1, 6
WHERE NOT EXISTS (SELECT 1 FROM planes WHERE slug = 'sap');

-- Suscripción de prueba para el cliente 1 (empresa demo)
INSERT IGNORE INTO cliente_planes (cliente_id, plan_id, estado, usuarios, precio_mensual, fecha_inicio, fecha_vencimiento, notas)
SELECT 1, id, 'activo', 5, 900.00, '2026-09-01', '2026-12-31', 'Módulo SAP predictivo en fase piloto.'
FROM planes WHERE slug = 'sap';