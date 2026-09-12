# 📘 Manual de usuario — Aura Automation & Analytics

Sistema de gestión de incidencias y atención al cliente (empresas), con tickets,
priorización, satisfacción del cliente y agendamiento de citas.

---

## 1. Cómo levantar el sistema

### Requisitos
- **PHP 8.0+** instalado.
- **MySQL 8.0** corriendo (el servicio `MySQL80` debe estar **Running**).
- Credenciales de BD por defecto: usuario `root`, contraseña `1234`.

### Pasos
1. **Crear/actualizar la base de datos** (solo la primera vez o para reiniciar datos de ejemplo):
   ```
   mysql -u root -p1234 < soft.sql
   ```
   → Crea la BD `aura` con todas las tablas y datos de ejemplo.

   > **BD ya existente** (no reuses `soft.sql` porque borra todo): ejecuta solo la
   > migración que agrega los **sistemas (planes)** y suscripciones:
   > ```
   > mysql -u root -p1234 softtech < migracion_planes.sql
   > ```
   > La aplicación usa por defecto la base **`softtech`** (configurable con la variable
   > de entorno `DB_NAME`, p. ej. `DB_NAME=aura` si instalaste desde cero).

2. Ajusta las credenciales si no son las predeterminadas en `config/database.php`
   (o usa variables de entorno `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).

3. **Levantar el servidor** desde la carpeta `public`:
   ```
   cd public
   php -S localhost:8000
   ```

4. Abre en el navegador:
   ```
   http://localhost:8000/index.php?ruta=login
   ```

> Si cambiaste algo en el código, no hace falta reiniciar PHP salvo que cambies
> `public/index.php`. Los cambios en modelo/controladores se aplican al recargar.

---

## 2. Acceso — Login único

Hay **una sola pantalla de login** con dos pestañas. El sistema detecta el tipo
según lo que escribas en el campo "usuario":
- **Correo** → te identificas como **equipo interno** (soporte/encargado/admin).
- **RUC (solo números)** → te identificas como **empresa cliente**.

URL de acceso: `http://localhost:8000/index.php?ruta=login`

---

## 3. Credenciales (claves y contraseñas)

### 3.1. Usuarios internos (equipo Aura Automation)

| Pestaña | Rol | Correo | Contraseña |
|---|---|---|---|
| Equipo interno | **Admin** | `direccion@auraautomation.com` | `admin123` |
| Equipo interno | Encargado | `rosa@auraautomation.com` | `enc123` |
| Equipo interno | Encargado (solución) | `juanito@auraautomation.com` | `enc123` |
| Equipo interno | Soporte | `luis@auraautomation.com` | `sop123` |
| Equipo interno | Soporte | `elena@auraautomation.com` | `sop123` |

### 3.2. Empresas cliente (portal)

Todas usan la misma clave **`clave123`** (datos de ejemplo):

| Empresa | RUC | Clave |
|---|---|---|
| Textil Rojas S.A.C. | `20123456789` | `clave123` |
| Vargas Logística | `20598765432` | `clave123` |
| Reyes & Asociados | `20111122333` | `clave123` |
| Prada Constructora | `20544455666` | `clave123` |
| Chamitos S.A.C. | `20612345678` | `clave123` |
| AgroExport del Norte S.A.C. | `20678901234` | `clave123` |
| Construcciones Beta E.I.R.L. | `20556789012` | `clave123` |
| Hotel Mirador del Valle S.R.L. | `20434567890` | `clave123` |
| Distribuidora Omega S.A.C. | `20312345678` | `clave123` |

> **Empresas nuevas** que registres por teléfono sin escribir clave quedan con la
> clave predefinida **`123456`**.

---

## 4. Menú interno (después de loguearte como equipo interno)

| Opción | Ruta | Descripción |
|---|---|---|
| Panel | `dashboard` | KPIs: total de incidencias, culminados, en atención, **satisfacción promedio** y **gestión de sistemas** por empresa (suscripciones activas/pendientes/vencidas, empresas sin sistemas). |
| Tickets | `tickets` | Listado y filtrado de incidencias por estado y por **sistema**. |
| Atender llamada | `atender` | Validar empresa y crear un ticket (flujo principal). |
| Notificaciones | `notificaciones` | Avisos internos: tickets nuevos, asignaciones, cambios de estado y culminados por validar/cerrar. |
| Sistemas | `planes` | Catálogo de los **5 sistemas** que vendemos y cuántas empresas usan cada uno. |
| Empresas | `empresas` | Listado de clientes con sus sistemas contratados; ver/editar empresa, fijar clave y eliminar. |
| Nueva empresa | `empresa-nueva` | Registrar una empresa y marcar qué sistemas quiere contratar. |
| Agenda | `agenda` | Agenda del día de citas de atención. |
| Grilla de horarios | `horarios` | Definir horarios y cupos por día de la semana. |

---

## 5. Guía rápida por módulo

### 5.1. Atender una llamada (crear ticket) — paso a paso
1. Menú → **Atender llamada**.
2. Pide al cliente su **RUC** y su **clave**.
   - Si existe → le das **Validar** → se muestra "Cliente verificado".
   - Si no existe → regístralo en el panel "Registrar nueva empresa" (empresa, RUC,
     clave, contacto, teléfono, correo).
3. Rellena la **categoría** (Rendimiento, Error, Nueva función, Actualización, Consulta)
   y el **canal** (Teléfono/Correo/Portal).
4. Responde el **cuestionario de urgencia** (impacto, ¿detuvo operaciones?, ¿recurrente?).
   La **prioridad** (baja/media/alta/urgente) se calcula sola en tiempo real.
5. Escribe **asunto** y **descripción**.
6. **Registrar y abrir ticket** → te lleva al detalle del ticket creado.

### 5.2. Gestionar tickets
- **Listado** (`tickets`): filtra por estado con los botones (Todos/Nuevos/En curso/...).
- **Detalle**: ves la información completa, el historial (trazabilidad) y puedes:
  - **Asignar encargado/soporte** (rol encargado/admin). Está disponible **en cualquier estado**
    del ticket (no solo cuando está "nuevo"), y no altera el estado si el ticket ya está
    culminado/resuelto/cerrado.
  - **Cambiar estado** (nuevo → en_curso → pausado → culminado → resuelto → cerrado)
    con un comentario.
- Cuando el **encargado marca como culminado**, se genera una **notificación** a
  administración/encargados, además de una **confirmación propia** para quien lo marcó.
  Los cambios de estado (nuevo, asignación, etc.) también generan avisos al resto del equipo.

### 5.4. Gestión de los 5 sistemas por empresa
- **Sistemas** (`planes`): catálogo de los sistemas que vendemos — **Contabilidad,
  Facturación, Planilla, ERP Básico y ERP Mediana Empresa** — con las empresas que usan
  cada uno y sus cuentas (activas/pendientes/vencidas).
- **Detalle de empresa** (`empresa-detalle`): tarjetas con cada sistema contratado
  (estado, usuarios/licencias, precio mensual, vigencia, incidencias) y botón
  **Gestionar planes** para asignar/editar/eliminar suscripciones.
- **Gestionar planes** (`gestionar-planes`): asigna un sistema nuevo (eligiendo estado,
  usuarios, precio, fechas y notas) y edita/elimina las suscripciones existentes.
  > No se puede asignar el mismo sistema dos veces a una empresa.

### 5.5. Tickets según el sistema
- Al **atender una llamada** (`atender`) o al crear un ticket, aparece el campo
  **"Sistema"**: elige a cuál de los sistemas de la empresa pertenece la incidencia
  (en el portal solo aparecen los sistemas que esa empresa tiene contratados).
- En **Tickets** puedes filtrar por **sistema** y en el **detalle** se muestra el
  sistema afectado.

### 5.6. Agenda de citas
- **Grilla de horarios** (`horarios`): define para cada día de la semana los horarios
  (hora inicio/fin), cuántos **cupos** tiene y si está **activo**.
  Ejemplo con datos cargados: L–V 09:00, 11:00 y 15:00; sábado solo mañana.
- **Agenda** (`agenda`): elige una fecha y ves el día organizado por horario, con cada
  empresa citada (RUC, modalidad, motivo). Botones para **Confirmar / Marcar realizada /
  Cancelar** (cancelar/realizar libera el cupo).

### 5.7. Satisfacción del cliente (KPIs)
- El cliente califica su satisfacción en el **portal** cuando su ticket está
  `culminado`/`resuelto`/`cerrado` (nivel + comentario opcional).
- En el **Panel** se muestra el **promedio de satisfacción** (de 1 a 5) y los
  conteos por estado. Es tu métrica principal de calidad de soporte.

---

## 6. Portal del cliente (empresa)

Entra con **RUC + clave** en el login único. Después de loguearte ves:

| Opción | Descripción |
|---|---|
| **Mis sistemas** | Sistemas que tu empresa tiene contratados con su estado (activo/pendiente/vencido), licencias, vigencia e incidencias de cada uno. |
| **Nueva incidencia** | Reportar un ticket directamente desde el portal (sin llamar): elige el **sistema**, motivo, impacto, asunto y descripción. |
| Mis incidencias | Seguimiento de tickets, ver detalle y **calificar satisfacción**. |
| Mis citas | Citas agendadas (fecha, hora, modalidad, motivo, estado). |
| Agendar | Elegir día + modalidad (**presencial/virtual**) + horario disponible y reservar. |

> **Nueva incidencia desde el portal:** el cliente elige el **sistema** (solo los que su
> empresa tiene contratado), el **motivo** (página carga lento, error/no carga, consulta,
> nueva función, actualización), responde el **cuestionario de urgencia** (impacto, si
> detuvo operaciones, si es recurrente) y escribe asunto/descripción. La prioridad se
> calcula sola y el ticket entra como **"nuevo"** con canal **Portal** para que el equipo
> interno lo atienda.

> Si un horario está **lleno**, el cliente no puede reservarlo. Si dos clientes
> reservan a la vez el mismo hueco, solo llenan según el cupo definido.

---

## 7. Estados de ticket

`nuevo` · `en_curso` · `pausado` · `culminado` · `resuelto` · `cerrado`

## 8. Niveles de satisfacción

`muy_insatisfecho` · `insatisfecho` · `neutral` · `satisfecho` · `muy_satisfecho`

## 9. Estados de cita

`reservada` · `confirmada` · `realizada` · `cancelada`

## 10. Solución de problemas comunes

| Problema | Solución |
|---|---|
| "No se pudo conectar a la base de datos" | Verifica que MySQL esté corriendo y las credenciales en `config/database.php`. |
| "Usuario o clave incorrectos" en el login | Asegúrate de estar en la pestaña correcta (correo para interno, RUC para empresa) y de usar las credenciales de la sección 3. |
| No veo los cambios al recargar | Haz **Ctrl+F5** (forzar recarga) para limpiar la caché del navegador. |
| Quiero reiniciar los datos de ejemplo | Ejecuta de nuevo `mysql -u root -p1234 < soft.sql` (regenera la BD; recuerda el nombre según `DB_NAME`). |
