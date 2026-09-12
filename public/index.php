<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../controladores/AuthController.php';
require_once __DIR__ . '/../controladores/TicketController.php';
require_once __DIR__ . '/../controladores/DashboardController.php';
require_once __DIR__ . '/../controladores/ClienteController.php';
require_once __DIR__ . '/../controladores/CitaController.php';
require_once __DIR__ . '/../controladores/PlanController.php';
require_once __DIR__ . '/../modelo/Notificacion.php';
require_once __DIR__ . '/../includes/functions.php';

$ruta = $_GET['ruta'] ?? 'dashboard';
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($ruta) {
    case 'login':
        $auth = new AuthController();
        $metodo === 'POST' ? $auth->procesarLogin() : $auth->mostrarLogin();
        break;

    case 'logout':
        (new AuthController())->logout();
        break;

    case 'dashboard':
        (new DashboardController())->mostrar();
        break;

    case 'tickets':
        (new TicketController())->listar();
        break;

    case 'atender':
        (new TicketController())->atender();
        break;

    case 'atender-crear':
        (new TicketController())->atenderCrear();
        break;

    case 'ticket-detalle':
        (new TicketController())->detalle();
        break;

    case 'ticket-asignar':
        (new TicketController())->asignar();
        break;

    case 'ticket-comentar':
        (new TicketController())->comentar();
        break;

    case 'ticket-estado':
        (new TicketController())->cambiarEstado();
        break;

    // ---- Empresas clientes (panel interno: ver/regenerar clave) ----
    case 'empresas':
        (new ClienteController())->empresas();
        break;

    case 'empresa-clave':
        (new ClienteController())->regenerarClave();
        break;

    // ---- Sistemas (planes) y gestión de suscripciones por empresa ----
    case 'planes':
        (new PlanController())->planes();
        break;

    case 'empresa-nueva':
        (new PlanController())->empresaNueva();
        break;

    case 'empresa-guardar':
        (new PlanController())->empresaGuardar();
        break;

    case 'empresa-editar':
        (new PlanController())->empresaEditar();
        break;

    case 'empresa-actualizar':
        (new PlanController())->empresaActualizar();
        break;

    case 'empresa-eliminar':
        (new PlanController())->empresaEliminar();
        break;

    case 'empresa-detalle':
        (new PlanController())->empresaDetalle();
        break;

    case 'gestionar-planes':
        (new PlanController())->gestionarPlanes();
        break;

    case 'plan-asignar':
        (new PlanController())->planAsignar();
        break;

    case 'plan-actualizar':
        (new PlanController())->planActualizar();
        break;

    case 'plan-eliminar':
        (new PlanController())->planEliminar();
        break;

    // ---- Citas de atención ----
    case 'agenda':
        (new CitaController())->agenda();
        break;

    case 'horarios':
        (new CitaController())->horarios();
        break;

    case 'horario-guardar':
        (new CitaController())->guardarHorario();
        break;

    case 'horario-alternar':
        (new CitaController())->alternarHorario();
        break;

    case 'cita-estado':
        (new CitaController())->cambiarEstadoCita();
        break;

    // ---- Citas del portal del cliente ----
    case 'portal-agendar':
        (new CitaController())->agendar();
        break;

    case 'cita-reservar':
        (new CitaController())->reservar();
        break;

    case 'portal-citas':
        (new CitaController())->misCitas();
        break;

    // ---- Portal del cliente (empresa) ----
    // Login único: cualquier intento al portal de login antiguo redirige al login general.
    case 'portal-login':
        header('Location: index.php?ruta=login');
        exit;

    case 'portal-logout':
        (new ClienteController())->portalLogout();
        break;

    case 'portal-mis-tickets':
        (new ClienteController())->misTickets();
        break;

    case 'portal-nueva-incidencia':
        (new ClienteController())->nuevaIncidencia();
        break;

    case 'portal-crear-incidencia':
        (new ClienteController())->portalCrearIncidencia();
        break;

    case 'portal-ticket':
        if ($metodo === 'POST') {
            (new ClienteController())->calificar();
        } else {
            (new ClienteController())->portalTicket();
        }
        break;

    case 'portal-ticket-comentar':
        (new ClienteController())->comentar();
        break;

    // ---- Planes del portal del cliente (sistemas que usa su empresa) ----
    case 'portal-planes':
        (new PlanController())->misPlanes();
        break;

    case 'portal-plan':
        (new PlanController())->portalPlan();
        break;

    // ---- Notificaciones internas ----
    case 'notificaciones':
        require __DIR__ . '/../views/notificaciones/listar.php';
        break;

    case 'notificacion-leida':
        verificarCsrf();
        (new Notificacion())->marcarLeida((int) ($_POST['id'] ?? 0), (int) $_SESSION['usuario_id']);
        irA('notificaciones');
        break;

    case 'notificaciones-leidas':
        verificarCsrf();
        (new Notificacion())->marcarTodasLeidas((int) $_SESSION['usuario_id']);
        irA('notificaciones');
        break;

    default:
        http_response_code(404);
        echo 'Pagina no encontrada.';
}
