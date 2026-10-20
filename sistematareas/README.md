# Sistema de Gestión de Tareas — Aura Automation & Analytics

Sistema independiente (como `sistemasap`, `sistemacontable`, etc.) para agregar,
visualizar y eliminar tareas **sin recargar la página** (AJAX + PHP + MySQL/MariaDB).

## Requisitos

- PHP 8.0+ con extensión `pdo_mysql`
- MySQL o MariaDB (Raspberry Pi OS trae **MariaDB**)
- Apache / Nginx / `php -S`

## Instalación

**No hay instalación manual.** Al abrir `index.php` por primera vez el sistema:

1. Detecta tu MySQL/MariaDB (socket `localhost`, luego TCP `127.0.0.1:3306/3307`).
2. Crea la base de datos `tareas` y sus tablas (`tareas`, `personal`, `audit_log`).
3. Inserta datos de ejemplo la primera vez.
4. Guarda la conexión que funcionó en `config/conexion_cache.php` (los siguientes
   cargas son instantáneas).

## Si aparece "No se pudo conectar a MySQL/MariaDB"

### 1. Verifica que el servicio corra (Raspberry Pi / Linux)

```bash
sudo systemctl status mariadb    # o: mysql
sudo systemctl start mariadb     # si está detenido
# Si no está instalado:
sudo apt install mariadb-server
```

### 2. Crea un usuario dedicado (recomendado, no uses root)

```bash
sudo mysql
```

```sql
CREATE USER 'aura'@'localhost' IDENTIFIED BY 'tu_clave';
GRANT ALL PRIVILEGES ON tareas.* TO 'aura'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Configúralo en `config/entorno.php`

```php
return [
    'host'    => 'localhost',   // o la IP de la máquina con MySQL en la red local
    'puerto'  => null,          // null = socket con 'localhost'; 3306/3307 con IP
    'usuario' => 'aura',
    'clave'   => 'tu_clave',
    'base'    => 'tareas',
];
```

Con `host` fijado, el sistema **no** intenta nada más: usa esos datos directamente.

### 4. Si MySQL está en otra máquina de la red local

- Pon su IP en `'host'` (p. ej. `'192.168.1.20'`).
- Permite conexiones remotas en el servidor MySQL y en su `my.cnf`
  (`bind-address = 0.0.0.0`).

> Nota: si editaste `config/entorno.php`, borra `config/conexion_cache.php`
> para descartar parámetros viejos (o simplemente vuelve a abrir el sistema:
> la caché se ignora si el host manual falla y se regenera al conectar).

## Estructura

```
sistematareas/
├── index.php        → Router de vistas (tareas, personal, calendario, historial, panel)
├── api.php          → Endpoint JSON: listar, guardar, eliminar, cambiar_estado,
│                      listar_personal, guardar_personal, eliminar_personal
├── tareas.sql       → Esquema manual (opcional; el sistema se autoinstala)
├── config/
│   ├── db.php           → Conexión + autoinstalación del esquema
│   └── entorno.php      → Configuración de conexión (editar si hace falta)
├── css/estilos.css  → Estilos propios (paleta teal + Poppins, igual que los demás sistemas)
├── js/tareas.js     → Motor AJAX (alta, listado, edición, filtros, eliminación)
├── includes/        → encabezado.php, pie.php, helpers.php
└── vistas/          → tareas.php, personal.php, calendario.php, historial.php, panel.php
```

## Funciones

- **Agregar tareas** — botón "Nueva tarea" → modal → `api.php?action=guardar` (AJAX).
- **Mostrar tareas** — se listan automáticamente al cargar y tras cada operación.
- **Eliminar tareas** — botón 🗑 junto a cada tarea, con confirmación (AJAX).
- **Asignar** — campo "Asignar a" por tarea + vista "Personal" para gestionar al equipo.
- **Filtros** — estado, prioridad, responsable y búsqueda (todo sin recargar).
- **Estadísticas** — panel con gráficos Chart.js responsivos.
