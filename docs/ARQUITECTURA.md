# Arquitectura de Aura Automation & Analytics

## Visión general

El repositorio contiene un **núcleo de gestión** y cinco **módulos de negocio**. El núcleo administra usuarios, empresas, suscripciones, tickets, agenda, notificaciones y métricas SLA. Cada módulo mantiene su propia base de datos y puede ejecutarse de forma independiente.

```text
Aura-Automation-Analytics/
├── config/                 Configuración de infraestructura y reglas
│   ├── database.php        Conexión del núcleo
│   └── sla.php             Objetivos de atención por prioridad
├── controladores/          Casos de uso HTTP del núcleo
├── modelo/                 Consultas y persistencia del núcleo
├── servicios/              Reglas de negocio reutilizables
├── views/                  Presentación del panel y portal
├── public/                 Entrada HTTP, JavaScript y sistema visual compartido
├── docs/                   Decisiones y documentación técnica
├── sistemacontable/        Contabilidad
├── sistemafacturacion/     Facturación
├── sistemaplanilla/        Recursos humanos y planillas
├── sistemaerp/             ERP para microempresa
├── sistemaermedio/         ERP para mediana empresa
└── sistemasap/             Sistema de Análisis Predictivo (modelo beta)
```

> **Estado:** `sistemasap/` es el prototipo (beta) del futuro ERP integrado. Los 6 módulos
> existentes se conservan sin cambios; el nuevo ERP se construirá siguiendo la especificación
> completa guardada en `docs/ERP_REQUERIMIENTOS.md`.

## Responsabilidades por capa

| Capa | Responsabilidad | No debe contener |
|---|---|---|
| `config/` | Valores ajustables por ambiente o negocio | HTML y consultas de presentación |
| `modelo/` | Acceso a base de datos | Marcado HTML y decisiones visuales |
| `servicios/` | Reglas de negocio y cálculos | Dependencias de sesión o vistas |
| `controladores/` | Autorización, coordinación y navegación | SQL extenso o estilos |
| `views/` | HTML accesible y componentes de presentación | Consultas directas a la base |
| `public/` | Activos públicos y punto de entrada | Credenciales y reglas sensibles |

El KPI de SLA ilustra el flujo recomendado: `Ticket::datosParaSla()` obtiene datos mínimos, `SlaService` aplica objetivos configurables y `DashboardController` entrega el resultado a la vista.

## Convención de cada módulo independiente

Todos los módulos conservan el mismo contrato de carpetas:

```text
modulo/
├── config/db.php           Conexión exclusiva del módulo
├── includes/               Layout, helpers y exportadores
├── vistas/                 Pantallas por dominio
├── css/estilos.css         Ajustes exclusivos del módulo
├── lib/                    Dependencias locales (por ejemplo, FPDF)
├── index.php               Router y acciones del módulo
├── instalador.php          Instalación local de su esquema
└── modulo.sql              Esquema y datos iniciales
```

La apariencia común vive en `public/css/design-system.css` y se carga después del CSS local. Así, colores, foco, tablas, botones, tarjetas, navegación y responsive se mantienen consistentes sin eliminar la capacidad de personalización de cada producto.

## SLA

Los objetivos predeterminados usan horas corridas:

| Prioridad | Objetivo |
|---|---:|
| Urgente | 4 h |
| Alta | 8 h |
| Media | 24 h |
| Baja | 48 h |

La tasa de cumplimiento considera tickets finalizados. “Fuera de SLA” incluye finalizados tarde y tickets abiertos que ya superaron su límite. “En riesgo” identifica abiertos que consumieron al menos 75% del objetivo. Los valores se modifican únicamente en `config/sla.php`.

## Codificación

- Código, plantillas y SQL: UTF-8 sin doble codificación.
- HTML: encabezado HTTP y `<meta charset="UTF-8">`.
- MySQL: `utf8mb4` en todas las conexiones PDO.
- Los exportadores deben declarar el formato que corresponda; CSV conserva BOM UTF-8 para Excel.

## Ejecución local integrada

Desde la raíz del repositorio:

```bash
php -S localhost:8000 -t .
```

- Panel principal: `http://localhost:8000/public/index.php?ruta=login`
- Contabilidad: `http://localhost:8000/sistemacontable/index.php`
- Facturación: `http://localhost:8000/sistemafacturacion/index.php`
- Planilla: `http://localhost:8000/sistemaplanilla/index.php`
- ERP MYPE: `http://localhost:8000/sistemaerp/index.php`
- ERP mediana empresa: `http://localhost:8000/sistemaermedio/index.php`
- SAP (beta): `http://localhost:8000/sistemasap/index.php`

