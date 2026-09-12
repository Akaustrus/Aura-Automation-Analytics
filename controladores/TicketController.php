<?php
require_once __DIR__ . '/../modelo/Ticket.php';
require_once __DIR__ . '/../modelo/Cliente.php';
require_once __DIR__ . '/../modelo/Agente.php';
require_once __DIR__ . '/../modelo/Satisfaccion.php';
require_once __DIR__ . '/../modelo/Comentario.php';
require_once __DIR__ . '/../modelo/Plan.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

class TicketController
{
    private Ticket $ticket;

    public function __construct()
    {
        $this->ticket = new Ticket();
    }

    public function listar(): void
    {
        exigirLogin();
        $estado = $_GET['estado'] ?? null;
        $planId = !empty($_GET['plan']) ? (int) $_GET['plan'] : null;
        $tickets = $this->ticket->listar($estado, $planId);
        $planes = (new Plan())->listarTodos();
        require __DIR__ . '/../views/tickets/listar.php';
    }

    /**
     * Pantalla de atención telefónica.
     * Paso 1: validar al cliente (empresa) por RUC + clave, o registrar una empresa nueva.
     * Paso 2: una vez validado, se muestra el formulario para crear el ticket
     *         con el cuestionario de urgencia que sugiere la prioridad.
     */
    public function atender(): void
    {
        exigirLogin();

        $accion = $_POST['accion'] ?? 'validar';
        $cliente = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'validar') {
            verificarCsrf();
            $ruc = limpiar($_POST['ruc'] ?? '');
            $clave = $_POST['clave'] ?? '';
            $cliente = (new Cliente())->validarClave($ruc, $clave);
            if (!$cliente) {
                $error = 'RUC o clave incorrectos. Verifica los datos del cliente o regístralo como nueva empresa.';
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'registrar') {
            verificarCsrf();
            $resultado = $this->registrarEmpresa();
            $cliente = $resultado['cliente'];
            $error = $resultado['error'];
        }

        $planesCliente = $cliente ? (new Plan())->porCliente((int) $cliente['id']) : [];

        require __DIR__ . '/../views/tickets/atender.php';
    }

    private function registrarEmpresa(): array
    {
        $empresa = limpiar($_POST['empresa'] ?? '');
        $ruc = limpiar($_POST['ruc'] ?? '');
        // Clave predefinida: si no se ingresa, se usa la clave por defecto "123456".
        $clave = ($_POST['clave'] ?? '') !== '' ? $_POST['clave'] : '123456';
        $contacto = limpiar($_POST['contacto'] ?? '');
        $email = limpiar($_POST['email'] ?? '');
        $telefono = limpiar($_POST['telefono'] ?? '');

        if ($empresa === '' || $ruc === '') {
            return ['cliente' => null, 'error' => 'Empresa y RUC son obligatorios.'];
        }

        $cli = new Cliente();
        if ($cli->existeRuc($ruc)) {
            return ['cliente' => null, 'error' => 'Ya existe una empresa con ese RUC.'];
        }

        $id = $cli->crear([
            'empresa' => $empresa,
            'ruc' => $ruc,
            'clave' => password_hash($clave, PASSWORD_DEFAULT),
            'email' => $email,
            'telefono' => $telefono,
            'contacto' => $contacto,
        ]);
        return ['cliente' => $cli->porRuc($ruc), 'error' => null];
    }

    public function atenderCrear(): void
    {
        exigirLogin();
        verificarCsrf();

        $cliente = (new Cliente())->validarClave(
            limpiar($_POST['ruc'] ?? ''),
            $_POST['clave'] ?? ''
        );

        if (!$cliente) {
            $error = 'El cliente ya no es válido. Vuelve a verificarlo.';
            require __DIR__ . '/../views/tickets/atender.php';
            return;
        }

        $categoria = limpiar($_POST['categoria'] ?? '');
        $canal = limpiar($_POST['canal'] ?? 'telefono');
        $planId = !empty($_POST['plan_id']) ? (int) $_POST['plan_id'] : null;

        if (!esValido($categoria, CATEGORIAS) || !esValido($canal, ['telefono', 'correo', 'portal'])) {
            die('Datos inválidos.');
        }
        if ($planId && !(new Plan())->planDeCliente($planId, (int) $cliente['id'])) {
            die('El sistema seleccionado no pertenece al cliente.');
        }

        // El cuestionario de urgencia genera urgencia y prioridad en tiempo real.
        $urgencia = sugiereUrgencia($_POST['impacto'] ?? '');
        $prioridad = sugierePrioridad(
            $_POST['impacto'] ?? '',
            $_POST['detener_operaciones'] ?? '',
            $_POST['reapertura'] ?? ''
        );

        $id = $this->ticket->crear([
            'cliente_id' => (int) $cliente['id'],
            'plan_id' => $planId,
            'usuario_id' => $_SESSION['usuario_id'],
            'categoria' => $categoria,
            'urgencia' => $urgencia,
            'prioridad' => $prioridad,
            'canal' => $canal,
            'asunto' => limpiar($_POST['asunto']),
            'descripcion' => limpiar($_POST['descripcion']),
        ]);
        irA("ticket-detalle&id=$id");
    }

    public function detalle(): void
    {
        exigirLogin();
        $id = (int) ($_GET['id'] ?? 0);
        $ticket = $this->ticket->obtener($id);

        if (!$ticket) {
            http_response_code(404);
            echo 'Ticket no encontrado.';
            return;
        }

        $historial = $this->ticket->historial($id);
        $agentes = (new Agente())->listar();
        $satisfaccion = (new Satisfaccion())->porTicket($id);
        $comentarios = (new Comentario())->listar($id);
        require __DIR__ . '/../views/tickets/detalle.php';
    }

    public function asignar(): void
    {
        exigirRol(['soporte', 'encargado', 'admin']);
        verificarCsrf();
        $encargadoId = !empty($_POST['encargado_id']) ? (int) $_POST['encargado_id'] : null;
        $this->ticket->asignar((int) $_POST['ticket_id'], (int) $_POST['usuario_id'], $encargadoId);
        irA("ticket-detalle&id=" . (int) $_POST['ticket_id']);
    }

    public function cambiarEstado(): void
    {
        exigirLogin();
        verificarCsrf();
        $estado = limpiar($_POST['estado'] ?? '');
        if (!esValido($estado, ESTADOS)) {
            die('Estado inválido.');
        }
        // Si quien mueve a "culminado" es el encargado del ticket, se notifica a administración.
        $esEncargado = $_SESSION['usuario_id'] === (int) ($_POST['encargado_id'] ?? 0);
        $encargadoId = !empty($_POST['encargado_id']) ? (int) $_POST['encargado_id'] : null;
        $this->ticket->cambiarEstado(
            (int) $_POST['ticket_id'],
            $estado,
            limpiar($_POST['comentario'] ?? ''),
            $esEncargado && $estado === 'culminado',
            $encargadoId
        );
        irA("ticket-detalle&id=" . (int) $_POST['ticket_id']);
    }

    public function comentar(): void
    {
        exigirLogin();
        verificarCsrf();
        $id = (int) ($_POST['ticket_id'] ?? 0);
        $ticket = $this->ticket->obtener($id);
        if (!$ticket) {
            die('Ticket no encontrado.');
        }
        $mensaje = limpiar($_POST['mensaje'] ?? '');
        if ($mensaje !== '') {
            (new Comentario())->crear($id, (int) $_SESSION['usuario_id'], null, $mensaje);
        }
        irA("ticket-detalle&id=$id");
    }
}
