<?php
require_once __DIR__ . '/../modelo/Cliente.php';
require_once __DIR__ . '/../modelo/Ticket.php';
require_once __DIR__ . '/../modelo/Satisfaccion.php';
require_once __DIR__ . '/../modelo/Comentario.php';
require_once __DIR__ . '/../modelo/Plan.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

class ClienteController
{
    /** Panel interno: listado de empresas clientes (admin/soporte). */
    public function empresas(): void
    {
        exigirLogin();
        $empresas = (new Cliente())->listarConPlanes();
        $planes = (new \Plan())->listar();
        require __DIR__ . '/../views/empresas.php';
    }

    /** Panel interno: regenerar la clave de una empresa y mostrarla. */
    public function regenerarClave(): void
    {
        exigirLogin();
        verificarCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        // Clave predefinida fija: la define el soporte/admin, no se genera aleatoriamente.
        $clave = $_POST['clave'] ?? '';
        if ($clave === '') {
            irA('empresas&error=clave_vacia&id=' . $id);
        }
        if (strlen($clave) < 4) {
            irA('empresas&error=clave_corta&id=' . $id);
        }
        (new Cliente())->actualizarClave($id, password_hash($clave, PASSWORD_DEFAULT));
        irA("empresas&edita=$id");
    }

    public function portalLogout(): void
    {
        unset($_SESSION['cliente_id'], $_SESSION['cliente_empresa']);
        cerrarSesion();
        irA('login');
    }

    private function exigirCliente(): void
    {
        if (empty($_SESSION['cliente_id'])) {
            header('Location: index.php?ruta=login');
            exit;
        }
    }

    public function misTickets(): void
    {
        $this->exigirCliente();
        $documentos = (new Ticket())->porCliente((int) $_SESSION['cliente_id']);
        require __DIR__ . '/../views/portal/mis-tickets.php';
    }

    public function portalTicket(): void
    {
        $this->exigirCliente();
        $id = (int) ($_GET['id'] ?? 0);
        $ticket = (new Ticket())->obtener($id);

        if (!$ticket || (int) $ticket['cliente_id'] !== (int) $_SESSION['cliente_id']) {
            http_response_code(404);
            echo 'Ticket no encontrado.';
            return;
        }

        $satisfaccion = (new Satisfaccion())->porTicket($id);
        $comentarios = (new Comentario())->listar($id);
        require __DIR__ . '/../views/portal/ticket.php';
    }

    public function comentar(): void
    {
        $this->exigirCliente();
        verificarCsrf();
        $id = (int) ($_POST['ticket_id'] ?? 0);
        $ticket = (new Ticket())->obtener($id);

        if (!$ticket || (int) $ticket['cliente_id'] !== (int) $_SESSION['cliente_id']) {
            die('Ticket no encontrado.');
        }

        $mensaje = limpiar($_POST['mensaje'] ?? '');
        if ($mensaje !== '') {
            (new Comentario())->crear($id, null, (int) $_SESSION['cliente_id'], $mensaje);
        }
        irA("portal-ticket&id=$id");
    }

    public function nuevaIncidencia(): void
    {
        $this->exigirCliente();
        $misPlanes = (new Plan())->porCliente((int) $_SESSION['cliente_id']);
        require __DIR__ . '/../views/portal/nueva-incidencia.php';
    }

    public function portalCrearIncidencia(): void
    {
        $this->exigirCliente();
        verificarCsrf();

        $categoria = limpiar($_POST['categoria'] ?? '');
        if (!esValido($categoria, CATEGORIAS)) {
            die('Categoría inválida.');
        }

        $planId = !empty($_POST['plan_id']) ? (int) $_POST['plan_id'] : null;
        if ($planId && !(new Plan())->planDeCliente($planId, (int) $_SESSION['cliente_id'])) {
            die('Sistema inválido.');
        }

        $urgencia = sugiereUrgencia($_POST['impacto'] ?? '');
        $prioridad = sugierePrioridad(
            $_POST['impacto'] ?? '',
            $_POST['detener_operaciones'] ?? '',
            $_POST['reapertura'] ?? ''
        );

        $asunto = limpiar($_POST['asunto'] ?? '');
        $descripcion = limpiar($_POST['descripcion'] ?? '');
        if ($asunto === '' || $descripcion === '') {
            die('Asunto y descripción son obligatorios.');
        }

        $id = (new Ticket())->crear([
            'cliente_id' => (int) $_SESSION['cliente_id'],
            'plan_id' => $planId,
            'usuario_id' => null,
            'categoria' => $categoria,
            'urgencia' => $urgencia,
            'prioridad' => $prioridad,
            'canal' => 'portal',
            'asunto' => $asunto,
            'descripcion' => $descripcion,
        ]);
        irA("portal-ticket&id=$id&creada=1");
    }

    public function calificar(): void
    {
        $this->exigirCliente();
        verificarCsrf();
        $id = (int) ($_POST['ticket_id'] ?? 0);
        $ticket = (new Ticket())->obtener($id);

        if (!$ticket || (int) $ticket['cliente_id'] !== (int) $_SESSION['cliente_id']) {
            die('Ticket no encontrado.');
        }

        // Solo se puede calificar un ticket culminado o resuelto.
        if (!in_array($ticket['estado'], ['culminado', 'resuelto', 'cerrado'], true)) {
            die('Este ticket aún no puede calificarse.');
        }

        $nivel = limpiar($_POST['nivel'] ?? '');
        $niveles = ['muy_insatisfecho', 'insatisfecho', 'neutral', 'satisfecho', 'muy_satisfecho'];
        if (!in_array($nivel, $niveles, true)) {
            die('Nivel de satisfacción inválido.');
        }

        $comentario = !empty($_POST['comentario']) ? limpiar($_POST['comentario']) : null;
        (new Satisfaccion())->registrar($id, (int) $ticket['cliente_id'], $nivel, $comentario);
        irA("portal-ticket&id=$id&calificado=1");
    }
}
