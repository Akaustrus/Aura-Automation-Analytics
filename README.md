# Aura Automation & Analytics — Sistema de Gestión de Incidencias

Somos una empresa que vende **soluciones de software y analytics a empresas**. Nuestros clientes
son **empresas** (ej. "Chamitos S.A.C."). Las atendemos por teléfono: el cliente nos da el
**RUC** y la **clave** de su empresa, y nosotros agendamos sus incidencias en tickets,
los priorizamos, asignamos un **encargado de la solución**, notificamos el culmen del
trabajo y medimos la **satisfacción** del cliente.

## Flujo de atención

1. El soporte (usuario interno) recibe la llamada de la empresa.
2. El cliente da su **RUC** y su **clave**; el soporte los usa para validar la empresa
   (o la registra como nueva si aún no existe).
3. El soporte registra la incidencia respondiendo el **cuestionario de urgencia**:
   - Impacto del problema (ninguno / parcial / total)
   - ¿Detuvo las operaciones? (sí / no)
   - ¿Problema recurrente? (sí / no)
4. El sistema calcula **urgencia** y **prioridad** en tiempo real.
5. Se crea el ticket con estado "nuevo" y se asigna un **encargado de la solución**
   (ej. *Juanito*).
6. Cuando el encargado cumple su trabajo, marca el ticket como **Culminado** y el
   sistema genera una **notificación interna** para administración/encargado.
7. Al validarse/cerrarse, la empresa puede entrar al **portal del cliente** y calificar
   su **nivel de satisfacción**.

## Flujo de gestión de los 5 sistemas por empresa (ERP)

El panel interno dejó de ser solo "tickets" y ahora es un **ERP que administra las
soluciones que vendemos**: a cada empresa se le asigna cuáles de los **5 sistemas**
usa, con su suscripción (estado, licencias, precio, vigencia) y sus incidencias.

| # | Paso | Dónde | Qué pasa |
|---|---|---|---|
| 1 | **Nueva empresa** | Menú → Nueva empresa (`empresa-nueva`) | Se registra empresa + RUC y se marcan los **sistemas** que quiere contratar (quedan `pendiente` hasta activarlos). |
| 2 | **Asignar sistemas** | Empresa → Gestionar planes (`gestionar-planes`) | Se asigna un sistema, estado (`activo · pendiente · vencido · cancelado`), usuarios/licencias, precio mensual, fechas y notas. |
| 3 | **Editar/eliminar** | Gestionar planes | Se actualiza la suscripción (sistema, estado, licencias, fechas) o se retira el sistema. No se permite duplicar un sistema en la misma empresa. |
| 4 | **Atender llamada** | Menú → Atender llamada (`atender`) | El soporte valida la empresa y elige el **sistema afectado** en el ticket. |
| 5 | **Ticket por sistema** | Panel → Tickets (`tickets`) | Listado/filtro por **sistema**; el detalle muestra el sistema afectado y permite cambiarlo. |
| 6 | **Incidencias del cliente** | Portal → Nueva incidencia | El cliente solo ve los **sistemas que tiene contratados** y reporta sobre uno de ellos (validado contra su suscripción). |
| 7 | **Mis sistemas (portal)** | Portal → Mis sistemas (`portal-planes`) | El cliente ve cada sistema con su estado, licencias, precio, vigencia y las incidencias de ese sistema. |
| 8 | **Seguimiento en panel** | Panel (`dashboard`) | KPIs nuevos: suscripciones activas/pendientes/vencidas y empresas sin sistemas, con acceso a Sistemas y Empresas. |

### Los 5 sistemas (catálogo)

| ID | Sistema | Carpeta/BD | Módulos |
|---|---|---|---|
| 1 | Contabilidad | `sistemacontable` / `contabilidad` | Plan de cuentas, asientos, documentos con IGV, libro mayor, balance, estados financieros, presupuestos |
| 2 | Facturación | `sistemafacturacion` / `facturacion` | Clientes, productos/servicios, comprobantes con IGV, correlativos y PDF |
| 3 | Planilla | `sistemaplanilla` / `planilla` | RRHH, asistencia, adelantos, planillas, gratificaciones, CTS, boletas |
| 4 | ERP Básico | `sistemaerp` / `erp` | Compras, inventario, ventas y caja para micro empresas |
| 5 | ERP Mediana Empresa | `sistemaermedio` / `medio` | ERP con login y roles: ventas, cobranzas, compras, multi-almacén, reportes |

## Instalación

1. Crea la base de datos ejecutando el script:
   ```
   mysql -u root -p < soft.sql
   ```
   (Crea la base **`aura`** con tablas y datos de ejemplo.)

   > **BD ya existente:** no reuses `soft.sql` (borra todo). Ejecuta solo la migración
   > que agrega la **gestión de sistemas (planes)** por empresa:
   > ```
   > mysql -u root -p1234 softtech < migracion_planes.sql
   > ```

2. Ajusta credenciales en `config/database.php` o variables de entorno
   `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`.
   Los valores locales de XAMPP son `localhost:3307`, usuario `root`, sin contraseña,
   y base **`aura`**. Cada módulo admite además variables con su prefijo
   (`CONTABILIDAD_DB_*`, `FACTURACION_DB_*`, `PLANILLA_DB_*`, `ERP_DB_*`, `MEDIO_DB_*`).

3. Levanta el servidor integrado desde la raíz:
   ```
   php -S localhost:8000 -t .
   ```

4. Abre `http://localhost:8000/public/index.php?ruta=login`

## Estructura (MVC)

- `config/` — conexión PDO.
- `modelo/` — acceso a datos (Agente, Cliente, Ticket, Notificacion, Satisfaccion, **Plan**).
- `controladores/` — lógica (AuthController, TicketController, DashboardController, ClienteController, **PlanController**).
- `views/` — plantillas (login, dashboard, tickets/, portal/, notificaciones/, **planes, empresas**).
- `includes/` — autenticación y helpers (incl. cálculo de urgencia/prioridad).
- `public/` — punto de entrada (`index.php`), CSS y JS.
- `servicios/` — reglas de negocio desacopladas (incluye cálculo de SLA).
- `docs/ARQUITECTURA.md` — mapa de capas, contrato de módulos y ejecución integrada.
- `migracion_planes.sql` — migración para BD existente (agrega sistemas/suscripciones).

## Funcionalidades

- Login de usuarios internos (roles: admin, encargado, soporte).
- **Atender llamada**: validar empresa por RUC + clave o **registrar empresa nueva**,
  y crear el ticket con evaluación de urgencia/prioridad en tiempo real.
- Categorías orientadas a ERP: **Rendimiento, Nueva función, Actualización, Error, Consulta**.
- Listado y filtrado de tickets por estado y por **sistema**.
- Detalle con historial (trazabilidad) y **sistema afectado**.
- **Gestión de sistemas (planes):** el ERP administra los **5 sistemas** que vendemos
  (**Contabilidad, Facturación, Planilla, ERP Básico, ERP Mediana Empresa**) y qué empresa
  usa cada uno.
- **Empresas (CRUD):** catálogo de `planes`, alta/edición/eliminación de empresas,
  detalle con sus suscripciones y **gestión de planes** por empresa (asignar, editar,
  cambiar estado, eliminar; estado `activo · pendiente · vencido · cancelado`).
- **Panel:** además de los KPIs de tickets, muestra **suscripciones activas/pendientes/
  vencidas** y empresas sin sistemas.
- **Portal del cliente:** sección **"Mis sistemas"** con los sistemas contratados, estado,
  licencias, vigencia y las incidencias de cada uno.
- Asignar **soporte** y **encargado de la solución**.
- **Notificaciones internas** al culminar el trabajo del encargado.
- **Portal del cliente** (empresa): entra con RUC + clave, ve sus incidencias y
  califica su **satisfacción**.
- Panel de administración con estadísticas, promedio de satisfacción y KPIs de SLA por prioridad.

## Usuarios internos de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| Admin | `direccion@auraautomation.com` | `admin123` |
| Encargado | `rosa@auraautomation.com` | `enc123` |
| Encargado (solución) | `juanito@auraautomation.com` | `enc123` |
| Soporte | `luis@auraautomation.com` | `sop123` |
| Soporte | `elena@auraautomation.com` | `sop123` |

## Empresas cliente de prueba (portal: RUC + clave `clave123`)

| Empresa | RUC | Clave |
|---|---|---|
| Textil Rojas S.A.C. | `20123456789` | `clave123` |
| Vargas Logística | `20598765432` | `clave123` |
| Reyes & Asociados | `20111122333` | `clave123` |
| Prada Constructora | `20544455666` | `clave123` |
| Chamitos S.A.C. | `20612345678` | `clave123` |

## Acceso

Existe **un único login** en `http://localhost:8000/index.php?ruta=login`:

- **Equipo interno** (soporte/encargado/admin) → ingresa con su **correo** y contraseña.
- **Cliente (empresa)** → ingresa con su **RUC** y la **clave** de su empresa.

El sistema detecta el tipo automáticamente según lo que ingreses (correo = interno, RUC numérico = empresa).

## Estados de ticket

`nuevo` · `en_curso` · `pausado` · `culminado` · `resuelto` · `cerrado`

## Niveles de satisfacción

`muy_insatisfecho` · `insatisfecho` · `neutral` · `satisfecho` · `muy_satisfecho`

---

## 📊 Módulo Contable independiente (`sistemacontable/`)

Módulo contable **completamente independiente** del ERP. Tiene su propia base de datos
MySQL (`contabilidad`), su propio router, sin login (acceso abierto) y sin conexión
con el módulo principal. Usa la misma apariencia visual (CSS replicado + Bootstrap + FontAwesome).

### Instalación del módulo contable

```bash
# 1) Crear la BD "contabilidad" (una sola vez)
php -r "require 'sistemacontable/instalador.php';"
# o abre en el navegador:
# http://localhost:8001/instalador.php

# 2) Levantar el servidor desde la carpeta sistemacontable/
cd sistemacontable
php -S localhost:8001
```

- Acceso: `http://localhost:8001/index.php`
- **No requiere login** (módulo abierto para uso interno/desarrollo).
- Para reiniciar los datos de ejemplo: vuelve a abrir `instalador.php`.

### Estructura del módulo

| Carpeta | Función |
|---|---|
| `config/` | Conexión PDO a la BD `contabilidad` |
| `includes/` | Helpers, exportador, encabezado/pie HTML |
| `lib/` | Librería FPDF (generación PDF) |
| `vistas/` | Panel, cuentas, asientos, documentos, libro mayor, balance, estados, presupuestos, importar, reportes, auditoría |
| `archivos/` | Boletas/comprobantes subidos por el usuario |
| `css/` | Copia del CSS del ERP (independiente) |
| `contabilidad.sql` | Schema completo + datos semilla |
| `instalador.php` | Crea BD y tablas desde el navegador |

### Base de datos `contabilidad` — tablas

| Tabla | Descripción |
|---|---|
| `cuentas` | Plan de cuentas (activo/pasivo/patrimonio/ingreso/egreso) — 27 registros de ejemplo |
| `asientos` | Cabecera de asientos contables (número, fecha, glosa, estado) |
| `asiento_detalle` | Movimientos debe/haber por cuenta (FK a cuentas) |
| `documentos` | Boletas/facturas/notas con base, **IGV (18%)**, total y upload de archivos |
| `presupuestos` | Presupuesto anual por cuenta de ingreso/egreso |
| `audit_log` | Log de auditoría de todas las acciones (crear/editar/eliminar/importar) |

### Funcionalidades

- **Panel (Dashboard):** 4 KPIs (documentos, base, IGV, asientos) + **gráficas Chart.js**
  (documentos por tipo y montos mensuales) + listados recientes.
- **Plan de cuentas:** CRUD completo. Crear, editar, eliminar cuentas por código/nombre/tipo.
- **Asientos contables:** CRUD con detalle dinámico (mínimo 2 movimientos), validación de cuadre
  y **filtro por rango de fechas**.
- **Documentos / Boletas:** CRUD con **cálculo automático de IGV (18%)** sobre la base imponible,
  filtros por fechas y tipo, **anular/reabrir**, y subida/descarga de archivos.
- **Libro mayor:** movimientos y saldos acumulados por cuenta, filtrable por fecha y cuenta.
- **Balance de comprobación:** debe/haber y saldos deudores/acreedores por cuenta.
- **Estados financieros:** Estado de resultados (P&G) y Balance general, con verificación
  de cuadre automática.
- **Presupuestos vs. Real:** definición de presupuesto anual por cuenta y % de ejecución
  con barras de progreso + tendencia mensual.
- **Importación CSV:** carga masiva de **documentos** (reporte en `importar.php`) o
  **asientos**, desde `importar.php` (formato en pantalla).
- **Auditoría:** log de cambios por tabla (cuentas/asientos/documentos/importaciones).
- **Reportes / Exportación:** genera archivos descargables en 4 formatos:
  - **Excel (.xlsx)** — Archivo real con encabezados en teal, formato nativo de Office.
  - **PDF** — Documento listo para impresión/envío (generado con FPDF).
  - **Word (.doc)** — Tabla formateada compatible con Microsoft Word.
  - **CSV** — Hoja de cálculo de texto plano (sep. punto y coma, UTF-8 con BOM).
- Reportes disponibles: documentos (con IGV), libro diario, plan de cuentas, **libro mayor**,
  **balance de comprobación**, **estados financieros** y **aging de cuentas por pagar/cobrar**
  (por vencer, 0-30, 31-60, 61-90, +90 días).

### Credenciales de la BD (por defecto)

| Parámetro | Valor |
|---|---|
| Host | `localhost` |
| Usuario | `root` |
| Contraseña | `1234` |
| Base de datos | `contabilidad` |

Cambiables vía variables de entorno: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.

---

## 📄 Sistema de Planilla profesional e independiente (`sistemaplanilla/`)

Módulo de **planillas profesionales** completamente independiente del ERP y del módulo
contable. Tiene su propia base de datos MySQL (`planilla`), su propio router, sin login
(uso interno) y la misma apariencia visual (CSS replicado + Bootstrap + Chart.js).
Cubre la operación completa de RR.HH.: **asistencia, adelantos, planillas mensuales,
gratificaciones y CTS**, con los descuentos y aportes de ley del Perú (ONP 13% / AFP 12.5%,
ESSALUD 9% como aporte del empleador, renta de 5ta categoría progresiva sobre 7 UIT,
asignación familiar de 10% RMV y bonificación extraordinaria de gratificación 9%).

### Instalación del módulo de planilla

```bash
# 1) Crear la BD "planilla" (una sola vez)
# abre en el navegador:
# http://localhost:8002/instalador.php

# 2) Levantar el servidor desde la carpeta sistemaplanilla/
cd sistemaplanilla
php -S localhost:8002
```

- Acceso: `http://localhost:8002/index.php` · **No requiere login** (módulo abierto).
- Para reiniciar los datos de ejemplo: vuelve a abrir `instalador.php`.

### Estructura del módulo

| Carpeta | Función |
|---|---|
| `config/` | Conexión PDO a la BD `planilla` |
| `includes/` | Helpers + motor de cálculo, exportador, encabezado/pie HTML |
| `lib/` | Librería FPDF (generación PDF) |
| `vistas/` | 10 vistas: panel, empleados, asistencia, adelantos, configuración, planillas, boleta, reportes, portal, auditoría |
| `css/` | Copia del CSS del ERP (independiente) |
| `planilla.sql` | Schema profesional completo + datos semilla |
| `instalador.php` | Crea BD y tablas desde el navegador |

### Base de datos `planilla` — tablas

| Tabla | Descripción |
|---|---|
| `parametros` | RMV, UIT, tasas ESSALUD/ONP/AFP, jornada, bono de gratificación (editables) |
| `conceptos` | Bonos y descuentos configurables (monto fijo o %; por tipo de planilla) |
| `empleados` | DNI único, contrato, jornada, CUSPP, banco/cuenta, ASF, token del portal |
| `asistencia` | Asistencia diaria: asistió/falta/permiso/vacaciones, entradas, horas extra |
| `adelantos` | Adelantos y préstamos (pendiente → cobrado al generar la planilla) |
| `planillas` | Cabecera por período+tipo (mensual/gratificación/CTS) y estado borrador/emitida/anulada |
| `detalle_planilla` | Boleta por empleado con desglose completo (base, ASF, HE, bonos, ESSALUD, descuento, renta, adelantos, neto) |
| `audit_log` | Log de auditoría de todas las acciones |

### Funcionalidades

- **Panel:** KPIs (activos, neto a pagar, ESSALUD del mes, adelantos pendientes), tendencia
  mensual y gráfica de neto por área con **Chart.js**.
- **Empleados:** CRUD completo con datos personales, laborales, tributarios (CUSPP, régimen)
  y bancarios; genera **token personal** para el portal del empleado.
- **Asistencia:** registro diario por empleado, resumen mensual (asistencias/faltas/HE),
  horas extra y **importación masiva por CSV**.
- **Adelantos:** control de adelantos y préstamos; los pendientes se descuentan solos en la
  siguiente planilla mensual.
- **Configuración:** parámetros legales editables (RMV, UIT, tasas) y conceptos configurables.
- **Planillas:** genera en **borrador** el cálculo completo por tipo y período: días
  laborados (30 base), asignación familiar, horas extra (+25%), bonos%, ESSALUD 9%, ONP/AFP,
  **renta de 5ta progresiva**, adelantos; luego **emitir** para habilitar boletas y
  exportaciones; **anular/ reabrir** permite regenerar.
- **Gratificación (julio/dic):** remuneración × meses/6 + bonificación extraordinaria 9%
  (exonerada), con aporte ONP/AFP sobre la remuneración habitual.
- **CTS (mayo/nov):** (remuneración + ASF + 1/6 gratif) × meses/12 × 50% de depósito.
- **Boleta de pago:** desglose profesional (ingresos vs descuentos, aportes del empleador,
  días laborados), **código QR** de verificación, vista imprimible (`sin_shell=1`) y
  descarga PDF/Excel.
- **Reportes:** **T-Registro / PLAME** (archivo plano SOL 2012 con bloques 01 y 02),
  resumen por área (Excel/PDF/CSV) y **tendencia mensual**.
- **Portal del empleado:** el trabajador ingresa con DNI + token y descarga sus boletas
  sin acceso al panel de administración.
- **Auditoría:** historial filtrable de creaciones, ediciones y eliminaciones.

---

## 🧾 Sistema de Facturación independiente (`sistemafacturacion/`)

Módulo de **facturación básica** completamente independiente del ERP y de
los módulos contable y de planilla. Tiene su propia base de datos MySQL (`facturacion`),
su propio router, sin login (uso interno) y la misma apariencia visual (CSS replicado +
Bootstrap + Chart.js + FPDF). Gestiona **clientes, productos/servicios y comprobantes de
venta** (factura, boleta, nota de crédito, nota de débito) con **IGV configurable (18%)**,
**correlativos automáticos por serie**, generación de **PDF** del comprobante, exportación a
4 formatos, configuración de la empresa y log de auditoría.

### Instalación del módulo de facturación

```bash
# 1) Crear la BD "facturacion" (una sola vez)
# abre en el navegador:
# http://localhost:8003/instalador.php

# 2) Levantar el servidor desde la carpeta sistemafacturacion/
cd sistemafacturacion
php -S localhost:8003
```

- Acceso: `http://localhost:8003/index.php` · **No requiere login** (módulo abierto).
- Para reiniciar los datos de ejemplo: vuelve a abrir `instalador.php`.

### Estructura del módulo

| Carpeta | Función |
|---|---|
| `config/` | Conexión PDO a la BD `facturacion` |
| `includes/` | Helpers (correlativos, IGV, número en letras), exportador, `ComprobantePdf` (FPDF), encabezado/pie HTML |
| `lib/` | Librería FPDF (generación PDF) |
| `vistas/` | Panel, clientes, productos, comprobantes, ver (imprimible), configuración, reportes, auditoría |
| `css/` | Copia del CSS del ERP (independiente) |
| `facturacion.sql` | Schema completo + datos semilla |
| `instalador.php` | Crea BD y tablas desde el navegador |

### Base de datos `facturacion` — tablas

| Tabla | Descripción |
|---|---|
| `parametros` | Datos de la empresa emisora, series por tipo de comprobante e **IGV_PORC** (18%) |
| `clientes` | Clientes (RUC/DNI único), datos de contacto y estado |
| `productos` | Productos y servicios con precio y afectación al IGV |
| `comprobantes` | Cabecera: tipo, serie+numero únicos, fechas, cliente, subtotal/IGV/total/letras, estado emitido/anulado |
| `comprobante_detalle` | Ítems del comprobante (descripción, cantidad, precio, importe, IGV) con FN `ON DELETE CASCADE` |
| `audit_log` | Log de auditoría de todas las acciones (crear/editar/eliminar/anular) |

### Funcionalidades

- **Panel (Dashboard):** 4 KPIs (comprobantes del mes, clientes, productos, ventas del mes) +
  gráficas Chart.js (comprobantes por tipo y ventas mensuales) + últimos comprobantes.
- **Clientes:** CRUD con tipos de documento (RUC/DNI) y búsqueda; el documento es único.
- **Productos / Servicios:** CRUD con precio y **afectación al IGV** (los exonerados no
  suman IGV en el comprobante).
- **Comprobantes:** alta con **detalle dinámico en modal** (agrega/quita filas y el
  navegador recalcula importes e IGV en tiempo real; el servidor valida y recalcula),
  captura como **Emitido** con cálculos, y puede **anularse** (se bloquea la edición y se
  marca ANULADO en lista/PDF) o **eliminarse**.
- **Correlativos automáticos:** cada tipo toma su **serie configurada** (ej. `F001`,
  `B001`, `NC01`, `ND01`) y numera `MAX(número)+1` por serie; la serie se edita en
  Configuración.
- **PDF del comprobante:** comprobante tipo factura con datos de la empresa emisora,
  cliente, detalle, totales e **importe en letras**, con marca de agua ANULADO.
- **Vista imprimible** (`ver.php?sin_shell=1`) para imprimir/guardar del navegador.
- **Reportes / Exportación:** comprobantes por rango de fechas (con totales), catálogo de
  productos, clientes y **resumen mensual**; en Excel (.xlsx real), PDF (FPDF), Word (.doc)
  y CSV (sep. punto y coma, UTF-8 con BOM).
- **Configuración:** razón social, RUC, dirección, teléfono, correo, **IGV %** y **serie
  por cada tipo de comprobante** (editable; afecta a los siguientes correlativos).
- **Auditoría:** historial filtrable de creaciones, ediciones, anulaciones y eliminaciones.

### Credenciales de la BD (por defecto)

| Parámetro | Valor |
|---|---|
| Host | `localhost` |
| Usuario | `root` |
| Contraseña | `1234` |
| Base de datos | `facturacion` |

Cambiables vía variables de entorno: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.

---

## 🏭 ERP básico para micro empresas (`sistemaerp/`)

Módulo **ERP básico** para la operación comercial cotidiana de una micro empresa,
completamente independiente de los demás módulos. Tiene su propia base de datos MySQL
(`erp`), su propio router, sin login (uso interno) y la misma apariencia visual (CSS
replicado + Bootstrap + Chart.js). Cubre el ciclo **compras → inventario → ventas → caja**:
maestros de clientes/proveedores, catálogo con **control de stock y stock mínimo de
alerta**, **movimientos de inventario automáticos** (cada venta/compra descuenta o
acumula stock y queda trazado), ventas y compras con detalle dinámico y **anulación que
restituye/descuenta el stock**, caja con saldo automático y reportes exportables a Excel,
PDF, Word y CSV.

### Instalación del módulo ERP

```bash
# 1) Crear la BD "erp" (una sola vez)
# abre en el navegador:
# http://localhost:8004/instalador.php

# 2) Levantar el servidor desde la carpeta sistemaerp/
cd sistemaerp
php -S localhost:8004
```

- Acceso: `http://localhost:8004/index.php` · **No requiere login** (módulo abierto).
- Para reiniciar los datos de ejemplo: vuelve a abrir `instalador.php`.

### Estructura del módulo

| Carpeta | Función |
|---|---|
| `config/` | Conexión PDO a la BD `erp` |
| `includes/` | Helpers (correlativos, estado de stock), exportador, encabezado/pie HTML |
| `lib/` | Librería FPDF (generación PDF) |
| `vistas/` | Panel, ventas, compras, productos, inventario, clientes, proveedores, caja, reportes, configuración, auditoría |
| `css/` | Copia del CSS del ERP (independiente) |
| `erp.sql` | Schema completo + datos semilla consistentes (stocks y movimientos cuadran) |
| `instalador.php` | Crea BD y tablas desde el navegador |

### Base de datos `erp` — tablas

| Tabla | Descripción |
|---|---|
| `parametros` | Razón social, RUC, dirección, contacto y moneda (editables) |
| `clientes` / `proveedores` | Maestros con RUC/DNI únicos y estado activo |
| `productos` | Código único, categoría, unidad, **controla_stock** (servicios sin stock), stock, stock mínimo, precios de compra/venta |
| `movimientos` | Trazabilidad de inventario: **cantidad firmada** (+entra/−sale), tipo (entrada/salida/ajuste), motivo y referencia |
| `ventas` / `venta_detalle` | Cabecera correlativa (V-0001…) con cliente, condición contado/crédito y estado emitida/anulada + ítems |
| `compras` / `compra_detalle` | Cabecera correlativa (C-0001…) con proveedor y estado + ítems |
| `caja` | Ingresos/egresos con medio de pago; el saldo se calcula sumando ingresos − egresos |
| `audit_log` | Log de auditoría de todas las acciones |

### Funcionalidades

- **Panel:** KPIs (ventas del mes, compras del mes, saldo de caja, productos por reponer),
  gráficas Chart.js (**ventas vs compras** de los últimos 6 meses y **ventas por cliente**),
  últimas ventas, alerta de stock bajo y actividad reciente.
- **Clientes y proveedores:** CRUD completo con RUC/DNI único, búsqueda y activo/inactivo.
- **Productos:** catálogo con categoría, unidad, **precios de compra/venta**, marca de
  *servicio* (sin control de stock) y **stock mínimo de alerta**.
- **Inventario:** valorización al costo y a precio de venta, estado por producto
  (OK / Bajo / Agotado / Servicio) y **movimientos manuales** (entradas, salidas y
  ajustes firmados) además de los automáticos.
- **Ventas:** registro con **detalle dinámico** (producto → autocompleta precio; valida
  stock disponible en servidor y no permite quedar negativo), numera V-0001…, **descuenta
  stock** y deja movimiento de salida; anular **restituye el stock**. Vista de detalle por
  documento. Los documentos no se editan: se anulan (estándar en un ERP).
- **Compras:** registro con detalle dinámico (autocompleta **precio de compra**), numera
  C-0001…, **acumula stock**; anular **descuenta el stock**.
- **Caja:** registro de ingresos/egresos con medio de pago (efectivo, transferencia,
  tarjeta, cheque), saldo automático, resumen del período y filtros.
- **Reportes / Exportación:** **ventas y compras por rango**, **inventario y
  valorización**, **movimientos de stock** y **movimiento de caja** (con totales) en
  **Excel (.xlsx) real, PDF (FPDF), Word (.doc) y CSV** (sep. punto y coma, UTF-8 BOM);
  además de un **resumen mensual** ventas/compras/margen.
- **Configuración:** datos de la empresa (razón social, RUC, dirección, contacto, moneda).
- **Auditoría:** historial filtrable de creaciones, ediciones, anulaciones y movimientos.

### Credenciales de la BD (por defecto)

| Parámetro | Valor |
|---|---|
| Host | `localhost` |
| Usuario | `root` |
| Contraseña | `1234` |
| Base de datos | `erp` |

Cambiables vía variables de entorno: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.

---

## 🏢 ERP para mediana empresa (`sistemaermedio/`)

Módulo **ERP con login y roles** para la operación completa de una mediana empresa,
completamente independiente de los demás módulos. Tiene su propia base de datos MySQL
(`medio`), su propio router y **autenticación propia** con **roles y permisos**
(admin, ventas, compras, almacén, caja, reportes, usuarios, auditoría, configuración),
además de la misma apariencia visual (CSS + Bootstrap + Chart.js + FPDF). Cubre el ciclo
**cotización → venta → cobranza (CxC)** y **orden de compra → compra → pago (CxP)** con
**multi-almacén**, control de stock automático, caja, reportes exportables y auditoría.

### Instalación del módulo ERP medio

```bash
# 1) Crear la BD "medio" (una sola vez)
# abre en el navegador:
# http://localhost:8005/instalador.php

# 2) Levantar el servidor desde la carpeta sistemaermedio/
cd sistemaermedio
php -S localhost:8005
```

- Acceso: `http://localhost:8005/index.php` → login.
- **Usuarios de prueba (clave `1234`):** `admin` (todos los permisos), `ventas1`,
  `compras1`, `store1` (almacén), `caja1`.
- Para reiniciar los datos de ejemplo: vuelve a abrir `instalador.php`.

### Estructura del módulo

| Carpeta | Función |
|---|---|
| `config/` | Conexión PDO a la BD `medio` |
| `includes/` | Helpers (correlativos, IGV, permisos/auditoría), exportador, encabezado/pie HTML |
| `lib/` | Librería FPDF (generación PDF) |
| `vistas/` | 19 vistas: dashboard, clientes, proveedores, categorías, productos, almacenes, inventario, kardex, ventas, cotizaciones, CxC, órdenes de compra, compras, CxP, caja, usuarios, reportes, configuración, auditoría |
| `css/` | Copia del CSS del ERP (independiente) |
| `medio.sql` | Schema completo + datos semilla consistentes (stocks, saldos y correlativos cuadran) |
| `instalador.php` | Crea BD y tablas desde el navegador |

### Base de datos `medio` — tablas

| Tabla | Descripción |
|---|---|
| `parametros` | Igv_porc (18%), almacén predeterminado, plantilla de movimiento de caja, etc. |
| `usuarios` | Usuarios con clave (password_hash), **rol** único y estado activo/inactivo |
| `clientes` / `proveedores` | Maestros con RUC/DNI únicos y estado activo |
| `categorias` | Categorías de productos |
| `productos` | Código único, **controla_stock** (servicios), stock y **stock mínimo por almacén** (`stock_por_almacen`), precios de compra/venta |
| `almacenes` | Sucursales/almacenes (default por parámetro) |
| `stock_por_almacen` | Stock por producto + almacén (referencia que usa el stock automático) |
| `movimientos` | Kardex: **cantidad firmada** (+entra/−sale) por producto y almacén, tipo (entrada/salida/traspaso/venta/compra/anulación), motivo y referencia |
| `cotizaciones` / `cotizacion_detalle` | Cotización correlativa (CO-0001…) con estado pendiente/vigente/aceptada/vencida/rechazada; **aceptar genera la venta** |
| `ventas` / `venta_detalle` | Venta correlativa (V-0001…) con tipo factura/boleta y condición contado/crédito; estado emitida/anulada |
| `venta_pagos` | Cobros de CxC (parciales); el **saldo** se valida contra el total |
| `ordenes_compra` / `oc_detalle` | Orden de compra correlativa (OC-0001…) con estado calculado; **recibir genera la compra** |
| `compras` / `compra_detalle` | Compra correlativa (C-0001…) con tipo factura/boleta y condición; estado emitida/anulada |
| `compra_pagos` | Pagos de CxP (parciales); validación contra el saldo |
| `caja` | Ingresos/egresos con **tipo de operación** (venta/cobro/pago/compra/traspaso/otro) y medio de pago; saldo automático |
| `auditoria` | Log de auditoría de todas las acciones (con datos antes/después) |

### Funcionalidades

- **Login y permisos:** autenticación con sesión propia; cada rol (admin, ventas,
  compras, store, caja) solo ve sus módulos; `admin` tiene acceso total. Los enlaces
  de menú y los handlers `index.php` validan permiso.
- **Panel:** KPIs (ventas del mes, compras del mes, saldo de caja, CxC/CxP pendientes,
  productos por reponer), gráficas Chart.js (ventas vs compras por mes, ventas por
  cliente, stock por producto) y actividad reciente.
- **Clientes, proveedores y categorías:** CRUD completo con documento único y búsqueda.
- **Productos:** catálogo con categoría, unidad, precio de compra/venta, **servicios sin
  stock** y **stock mínimo por almacén**.
- **Almacenes:** sucursales activas/inactivas (el almacén predeterminado se configura).
- **Inventario y kardex:** valorización al costo y a precio de venta, estado por producto
  (OK / Bajo / Agotado / Servicio) y **movimientos manuales** (entrada, salida, ajuste,
  **traspaso entre almacenes**) con trazabilidad completa.
- **Ventas:** registro con detalle dinámico y **IGV (18%)** sobre subtotal, tipo
  factura/boleta, condición contado/crédito, **descuenta stock automático por almacén**,
  numera V-0001…; **anular** restituye stock y lo marca `anulada`.
- **Cotizaciones:** presupuesto con vigencia (días), ítems con importe e IGV,
  **estados y vencimiento automático**; **aceptar** genera la venta de contado
  (mismo total) y descuenta stock.
- **Cuentas por cobrar (CxC):** saldos por venta de crédito, cobros parciales con
  **validación de saldo** (no permite sobrepagar) y movimientos de caja vinculados.
- **Órdenes de compra:** solicitud con proveedor/almacén e ítems; estados calculados
  (completada/recibida/parcial/pendiente); **recibir** genera la compra y **acumula
  stock** en el almacén destino.
- **Compras y Cuentas por pagar (CxP):** compras correlativas C-0001… con tipo
  factura/boleta, anulación que descuenta stock, y pagos parciales validados contra saldo.
- **Caja:** ingresos/egresos con tipo de operación y medio de pago, saldo automático,
  resumen por período y la caja se alimenta sola con cobros/pagos.
- **Usuarios:** CRUD con clave encriptada y rol; un usuario no puede eliminarse a sí mismo.
- **Configuración:** parámetros editables (IGV %, almacén predeterminado, plantilla de
  movimiento de caja).
- **Auditoría:** historial filtrable de todas las acciones con datos anterior/nuevo.
- **Reportes / Exportación (9 reportes):** ventas, compras, cotizaciones, inventario y
  valorización, **kardex**, **CxC**, **CxP**, **movimiento de caja** y **estado de
  resultados** (ingresos − costos − gastos = utilidad) en **Excel (.xlsx) real, PDF
  (FPDF), Word (.doc) y CSV** (sep. punto y coma, UTF-8 con BOM).

### Credenciales de la BD (por defecto)

| Parámetro | Valor |
|---|---|
| Host | `localhost` |
| Usuario | `root` |
| Contraseña | `1234` |
| Base de datos | `medio` |

Cambiables vía variables de entorno: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.

---

## 📜 Bitácora de avances

Resumen de todo lo implementado en el sistema principal (a la fecha). Cada bloque es
funcional y verificado contra la BD real.

### 1. Sistema de gestión de incidencias (base)
- Login único por **correo (interno)** o **RUC (empresa cliente)**; roles
  `admin / encargado / soporte`.
- **Atender llamada** con validación de empresa por RUC + clave, registro de empresa
  nueva y **cuestionario de urgencia/prioridad** en tiempo real
  (impacto + ¿detuvo operaciones? + ¿recurrente?).
- Categorías ERP (`rendimiento · funcion_nueva · actualizacion · error · consulta`),
  canales (`telefono · correo · portal`) y prioridades (`baja · media · alta · urgente`).
- Tickets con 6 estados (`nuevo · en_curso · pausado · culminado · resuelto · cerrado`),
  listado/filtro por estado, **detalle con historial** (trazabilidad), asignación de
  **soporte + encargado de la solución**.
- **Notificaciones internas** (ticket nuevo, asignación, cambios de estado, culminado
  por validar, culminación propia).
- **Satisfacción del cliente** (5 niveles) al cerrar; el **Panel** muestra el promedio.
- **Agenda de citas** de atención: grilla de horarios/cupos por día + agenda por fecha
  (`reservada · confirmada · realizada · cancelada`).
- **Portal del cliente** (RUC + clave): nueva incidencia, mis incidencias, calificar
  satisfacción, citas y agendar.

### 2. ERP: gestión de los 5 sistemas por empresa (última entrega)
- **Base de datos**: nuevas tablas `planes` (catálogo de los 5 sistemas) y
  `cliente_planes` (suscripción empresa→sistema, con UNIQUE por empresa+sistema y
  `ON DELETE CASCADE`), columna `tickets.plan_id` (FK a `planes`, `ON DELETE SET NULL`).
  Archivos: `migracion_planes.sql` (BD existente) y `soft.sql` (instalación completa).
- **Modelos**: `Plan` (catálogo, suscripciones, conteos por plan/estado, validación
  `planDeCliente`), `Cliente` (`obtener/actualizar/eliminar/listarConPlanes`),
  `Ticket` (creación y listados con `plan_id` y JOIN a `planes`).
- **Controlador**: `PlanController` — catálogo de sistemas, **CRUD de empresas**,
  **gestión de planes por empresa** (asignar/editar/eliminar) y portal
  (mis sistemas + detalle por sistema). Rutas nuevas en `public/index.php`
  (`planes`, `empresa-nueva/guardar/editar/actualizar/eliminar/detalle`,
  `gestionar-planes`, `plan-asignar/actualizar/eliminar`, `portal-planes`, `portal-plan`).
- **Vistas internas**: `planes.php` (catálogo con empresas por sistema),
  `empresa-nueva.php` (registro + selección de sistemas), `empresa-editar.php`,
  `empresa-detalle.php` (tarjetas de suscripción + tickets del cliente),
  `gestionar-planes.php` (alta/edición/eliminación de suscripciones) y `empresas.php`
  renovado (listado con totales/activos de sistemas y botones Ver/Editar/Clave/Eliminar).
- **Vistas del portal**: `mis-planes.php` (tarjetas de sistemas contratados con estado,
  licencias, precio y vigencia) y `plan.php` (detalle + incidencias de ese sistema).
- **Integración con tickets**: selector de **Sistema** en `atender.php` (solo sistemas
  de la empresa), filtro por sistema en `tickets/listar.php`, columna e indicador de
  sistema en listados/detalles (interno y portal), y validación en el portal de que la
  incidencia pertenece a un sistema contratado por esa empresa.
- **Panel**: bloque "Gestión de sistemas por empresa" (empresas totales, suscripciones
  activas, pendientes+vencidos, empresas sin sistemas).
- **Navegación**: menú interno con **Sistemas / Empresas / Nueva empresa**; barra del
  portal con **Mis sistemas** destacado; nuevas clases CSS del layout.

### 3. Verificación aplicada
- `php -l` sin errores en todos los archivos nuevos/modificados.
- Prueba real contra la BD (`softtech`): creación/lectura/actualización/eliminación de
  suscripciones, conteos y consultas de Plan/Cliente/Ticket.
- 15 vistas renderizadas sin errores con sesión simulada (admin interno + cliente).
- Login HTTP y guardas de autenticación funcionando.
- Los nombres de sistemas con acentos quedaron grabados en UTF‑8 válido en la BD.
