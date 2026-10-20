# Requerimientos — ERP Empresarial Modular (Perú)

> **Estado del documento:** Aprobado — referencia oficial para el desarrollo por fases.
> **Nota de arquitectura vigente:** el módulo `sistemasap/` (Sistema de Análisis Predictivo,
> BD `sistemasap`) se conserva como **modelo beta** y no debe reemplazarse ni eliminarse
> mientras el nuevo ERP no esté en producción.

---

Objetivo: implementar un sistema ERP empresarial modular, inspirado en la estructura
funcional de sistemas ERP como SAP, pero desarrollado de forma propia y orientado
principalmente a pequeñas y medianas empresas de Perú.

Reglas transversales de implementación:

- No reemplazar ni eliminar código existente sin analizarlo primero.
- Antes de modificar archivos, revisar la estructura actual del proyecto.
- Mantener las funcionalidades existentes.
- Implementar los módulos de forma modular para poder activar/desactivar según el cliente.
- No hacer una implementación gigante de una sola vez: trabajar por fases y dejar cada fase
  funcional antes de continuar.
- Utilizar buenas prácticas de arquitectura, seguridad, validaciones, separación de
  responsabilidades y mantenibilidad.
- Toda operación importante debe quedar registrada en auditoría.
- Evitar duplicar lógica entre módulos.

---

## 1. NÚCLEO DEL ERP

Crear un núcleo central que sea utilizado por todos los módulos.

### Empresas

- Empresas
- RUC
- Razón social
- Nombre comercial
- Dirección
- Teléfono
- Correo
- Estado
- Logo
- Configuración tributaria

### Sucursales

- Crear sucursales
- Dirección
- Teléfono
- Estado
- Empresa asociada

### Usuarios

- Crear usuarios
- Editar usuarios
- Activar/desactivar usuarios
- Cambiar contraseña
- Recuperación de contraseña
- Control de sesiones

### Roles y permisos

Implementar RBAC.

Ejemplo:

- **Administrador:** acceso completo
- **Gerente:** Ventas, Compras, Inventario, Finanzas, Reportes
- **Vendedor:** Clientes, Productos, Cotizaciones, Ventas
- **Almacenero:** Inventario, Almacenes, Entradas, Salidas, Transferencias
- **RRHH:** Empleados, Asistencia, Vacaciones, Planilla

Permisos configurables por:

- Módulo
- Submódulo
- Acción

Acciones: Ver, Crear, Editar, Eliminar, Exportar, Aprobar, Anular.

### Auditoría

Registrar:

- Usuario
- Fecha/hora
- IP
- Módulo
- Acción
- Registro afectado
- Valores anteriores
- Valores nuevos

Ejemplo: "Favio modificó el precio del producto X de S/100 a S/120".

### Notificaciones

Sistema centralizado para:

- Alertas
- Vencimientos
- Stock mínimo
- Facturas vencidas
- Contratos próximos a vencer
- Tickets pendientes
- Aprobaciones

---

## 2. MÓDULO COMERCIAL

### Clientes

Campos: tipo de documento, número de documento, RUC/DNI, razón social, nombre comercial,
dirección, teléfono, correo, contactos, estado.

Funciones: crear, editar, buscar, ver historial, ver ventas, ver cuentas por cobrar.

### Proveedores

Funcionalidades equivalentes a clientes.

### Productos

Campos: código, SKU, código de barras, nombre, descripción, categoría, marca, unidad de
medida, precio de compra, precio de venta, impuesto, stock mínimo, estado.

Precios múltiples: público, mayorista, distribuidor, especial.

### Cotizaciones

Flujo: Cliente → Cotización → Aprobación → Pedido/Venta.

Funciones: crear, editar, duplicar, aprobar, rechazar, convertir a venta, PDF, enviar por correo.

### Ventas

Pedido → Venta → Facturación → Despacho.

Flujo: Cotización → Pedido → Reserva de stock → Despacho → Factura → Cuenta por cobrar.

---

## 3. MÓDULO DE COMPRAS

- Solicitud de compra
- Cotizaciones de proveedores
- Comparación de cotizaciones
- Orden de compra
- Recepción
- Factura de proveedor

Flujo: Solicitud → Cotizaciones → Orden de compra → Recepción → Inventario → Cuenta por pagar.

Historial completo de cada compra.

---

## 4. MÓDULO DE INVENTARIO

### Almacenes

Crear almacenes, sucursales, ubicaciones.

### Movimientos

Entrada, salida, transferencia, ajuste.

### Kardex

Registrar: fecha, documento, tipo de movimiento, entrada, salida, stock resultante, costo.

### Control de stock

Stock actual, stock mínimo, stock máximo, productos agotados, productos próximos a agotarse.

### Lotes

Número de lote, fecha de fabricación, fecha de vencimiento.

### Series

Control individual de productos serializados.

> IMPORTANTE: ventas, compras, devoluciones y transferencias deben actualizar automáticamente
> el inventario.

---

## 5. MÓDULO FINANCIERO

### Caja

Ingresos, egresos, apertura, cierre, arqueo.

### Bancos

Cuentas bancarias, movimientos, transferencias, conciliación.

### Cuentas por cobrar

Facturas pendientes, vencimientos, pagos parciales, pagos completos, estado de cuenta del cliente.

### Cuentas por pagar

Facturas de proveedores, vencimientos, pagos, estado de cuenta.

### Contabilidad

Plan contable, asientos, libro diario, libro mayor, registro de compras, registro de ventas,
estados financieros.

Las operaciones comerciales deben poder generar automáticamente los movimientos contables.

---

## 6. MÓDULO DE CENTROS DE COSTO

- Crear centros de costo
- Asignar gastos
- Asignar ingresos
- Presupuestos
- Comparación presupuesto vs real
- Rentabilidad

Ejemplo: centro "Proyecto Lima Norte" — ingresos S/150,000, costos S/90,000, utilidad S/60,000.

---

## 7. MÓDULO DE RRHH

### Empleados

Datos personales, documento, cargo, área, sucursal, fecha de ingreso, tipo de contrato,
sueldo, estado.

### Contratos

Tipo, fecha inicio, fecha fin, renovaciones, documentos.

### Asistencia

Entrada, salida, tardanza, falta, horas extras, turnos, horarios.

### Permisos

Solicitud, aprobación, rechazo, historial.

### Vacaciones

Saldo, solicitudes, aprobaciones, historial.

---

## 8. MÓDULO DE PLANILLA PERÚ

Preparar arquitectura para: sueldo básico, horas extras, bonificaciones, descuentos, AFP, ONP,
EsSalud, CTS, gratificaciones, vacaciones, quinta categoría, boletas de pago, T-Registro, PLAME.

- El cálculo debe ser **configurable** y sin porcentajes tributarios "quemados" en el código.
- Crear sección de **configuración de parámetros legales** para actualizar valores sin modificar
  el código.

---

## 9. MÓDULO DE ACTIVOS FIJOS

- Registro de activos
- Código interno
- Categoría
- Fecha de adquisición
- Costo
- Ubicación
- Responsable
- Depreciación
- Transferencia
- Baja
- Historial

---

## 10. MÓDULO DE SERVICIO / HELPDESK

Tickets: cliente, contacto, categoría, subcategoría, prioridad, descripción, técnico, estado,
fecha creación, fecha resolución.

Estados: Nuevo → Asignado → En proceso → Esperando cliente → Resuelto → Cerrado.

Prioridades: Crítica, Alta, Media, Baja.

SLA configurable (ej.: crítica 4h, alta 8h, media 24h, baja 48h).

Registrar: tiempo de respuesta, tiempo de resolución, incumplimiento de SLA, historial,
comentarios, adjuntos, satisfacción del cliente.

---

## 11. MÓDULO CRM

- Leads
- Contactos
- Empresas
- Oportunidades
- Seguimientos
- Actividades
- Llamadas
- Reuniones
- Notas
- Pipeline comercial

Flujo: Lead → Contactado → Calificado → Propuesta → Negociación → Ganado/Perdido.

---

## 12. MÓDULO DE PROYECTOS

Proyectos, clientes, presupuesto, tareas, responsables, fechas, avance, costos, horas
trabajadas, rentabilidad.

Dashboard ejemplo: presupuesto S/100,000 · costo actual S/62,000 · avance 72%.

---

## 13. MÓDULO DE PRODUCCIÓN

Preparar arquitectura para: productos terminados, materias primas, BOM/lista de materiales,
órdenes de producción, consumo de materiales, producción terminada, merma, costos de producción.

Flujo: Orden de producción → Reserva materiales → Consumo → Producción → Producto terminado →
Inventario.

---

## 14. MÓDULO DE MANTENIMIENTO

Equipos, activos, mantenimiento preventivo, mantenimiento correctivo, órdenes de mantenimiento,
técnicos, repuestos, historial, costos.

---

## 15. FACTURACIÓN ELECTRÓNICA PERÚ

Preparar arquitectura para integración con SUNAT/PSE/OSE.

Soportar: facturas, boletas, notas de crédito, notas de débito, guías de remisión, series,
correlativos, XML, CDR, estados, bajas.

Separar la integración del núcleo del ERP para poder cambiar de proveedor/API posteriormente.

---

## 16. DASHBOARD EMPRESARIAL

Dashboard configurable según el rol.

KPIs: ventas del día, ventas del mes, compras, utilidad, cuentas por cobrar, cuentas por
pagar, stock, productos agotados, tickets abiertos, SLA, empleados presentes, ausencias,
proyectos activos.

Agregar gráficos, filtros por fecha/empresa/sucursal y exportación.

---

## 17. REPORTES

Todos los módulos deben poder generar reportes: PDF, Excel, CSV, impresión, filtros, rangos de
fechas, exportación según permisos.

---

## 18. ARQUITECTURA MODULAR

Diseñar para que un cliente contrate solo determinados módulos. Estructura de módulos:

```text
/modules
    /ventas
    /compras
    /inventario
    /finanzas
    /rrhh
    /planilla
    /crm
    /helpdesk
    /proyectos
    /produccion
    /mantenimiento
```

El sistema debe verificar los módulos contratados antes de permitir el acceso.

---

## 19. MULTIEMPRESA Y MULTISUCURSAL

Un mismo usuario puede trabajar con varias empresas (cada una con sus sucursales). Los datos
deben estar correctamente aislados según empresa y permisos.

---

## 20. SEGURIDAD

- Hash seguro de contraseñas
- Protección contra SQL Injection
- Protección XSS
- Protección CSRF
- Validación de entradas
- Control de sesiones
- Expiración de sesión
- Rate limiting para autenticación
- Control de permisos en backend (no solo en frontend)
- Auditoría
- Validación de archivos subidos
- Protección de información sensible

Nunca confiar únicamente en las restricciones de la interfaz.

---

## 21. BASE DE DATOS

BD relacional normalizada. Antes de crear tablas:

1. Analizar relaciones entre módulos.
2. Evitar duplicación de información.
3. Definir claves primarias y foráneas.
4. Definir índices.
5. Definir restricciones.
6. Definir estados.
7. Definir auditoría.
8. Preparar migraciones/versionado.

Tablas principales deben tener: `id`, `created_at`, `updated_at`, `estado` cuando corresponda.
Usar soft delete donde corresponda (no eliminar físicamente información crítica).

---

## 22. INTEGRACIÓN ENTRE MÓDULOS

Diseño integrado:

- **VENTA:** Cliente → Pedido → Inventario → Despacho → Factura → Cuenta por cobrar → Pago →
  Contabilidad → Dashboard.
- **COMPRA:** Proveedor → Orden de compra → Recepción → Inventario → Factura proveedor →
  Cuenta por pagar → Pago → Contabilidad.
- **RRHH:** Empleado → Horario → Asistencia → Incidencias → Planilla → Boleta → Contabilidad.

---

## 23. REGLAS DE NEGOCIO

No permitir operaciones incoherentes: no vender sin stock cuando haya control de stock, no
eliminar facturas emitidas, no modificar operaciones cerradas sin autorización, no permitir
acceso a empresa no autorizada, no ejecutar acciones sin permiso del rol, no cerrar ticket sin
resolución obligatoria, no aprobar documentos sin permisos.

---

## 24. INTERFAZ

Interfaz empresarial moderna y responsive (PC, laptop, tablet, celular):

```text
┌──────────────────────────────────────────────┐
│ LOGO     Buscar...          🔔 Usuario       │
├──────────────┬───────────────────────────────┤
│ Dashboard    │                               │
│ Ventas       │                               │
│ Compras      │          CONTENIDO            │
│ Inventario   │                               │
│ Finanzas     │                               │
│ RRHH         │                               │
│ Planilla     │                               │
│ CRM          │                               │
│ Helpdesk     │                               │
│ Proyectos    │                               │
│ Reportes     │                               │
│ Configuración│                               │
└──────────────┴───────────────────────────────┘
```

---

## 25. DESARROLLO POR FASES

No implementar todo de una vez. Orden:

- **FASE 1:** Arquitectura · Base de datos · Login · Usuarios · Roles · Permisos · Empresas ·
  Sucursales · Auditoría · Dashboard base.
- **FASE 2:** Clientes · Proveedores · Productos · Ventas · Compras.
- **FASE 3:** Inventario · Almacenes · Kardex · Transferencias · Lotes · Series.
- **FASE 4:** Caja · Bancos · Cuentas por cobrar · Cuentas por pagar · Contabilidad.
- **FASE 5:** RRHH · Asistencia · Vacaciones · Permisos.
- **FASE 6:** Planilla Perú.
- **FASE 7:** Facturación electrónica.
- **FASE 8:** CRM · Helpdesk.
- **FASE 9:** Proyectos · Activos · Mantenimiento · Producción.
- **FASE 10:** BI · Dashboard avanzado · Reportes · Optimización · Seguridad · Pruebas.

---

## 26. METODOLOGÍA DE IMPLEMENTACIÓN

Antes de programar cada fase:

1. Analizar el código existente.
2. Identificar arquitectura actual.
3. Identificar tecnologías utilizadas.
4. Identificar tablas existentes.
5. Identificar posibles conflictos.
6. Proponer los cambios necesarios.
7. Implementar.
8. Ejecutar pruebas.
9. Corregir errores.
10. Documentar lo implementado.

No avanzar a la siguiente fase hasta que la fase actual sea funcional.

Cuando se cree/modifique una tabla, explicar primero: nombre, campos, relaciones, índices,
motivo.

Cuando se modifique código existente, conservar las funcionalidades actuales.

El resultado final debe ser un ERP empresarial modular, escalable, seguro y preparado para
comercializarse a diferentes empresas, permitiendo activar módulos según necesidades y plan
contratado por cada cliente.

---

*Documento generado desde la especificación del solicitante. Guardado como referencia oficial
para el desarrollo del ERP por fases.*