<?php
require_once __DIR__ . '/../modelo/Plan.php';
require_once __DIR__ . '/../modelo/Cliente.php';
require_once __DIR__ . '/../modelo/Ticket.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

class PlanController
{
    private Plan $plan;
    private Cliente $cliente;

    public function __construct()
    {
        $this->plan = new Plan();
        $this->cliente = new Cliente();
    }

    /* ===================================================
     * PANEL INTERNO — Catálogo de los 5 sistemas
     * =================================================== */

    public function planes(): void
    {
        exigirLogin();
        $sistemas = $this->plan->listarTodos();
        $conteo = [];
        foreach ($this->plan->conteoPorPlan() as $f) {
            $conteo[(int) $f['id']] = $f;
        }
        $porEstado = $this->plan->conteoPorEstado();
        require __DIR__ . '/../views/planes.php';
    }

    /* ===================================================
     * PANEL INTERNO — Empresas (CRUD)
     * =================================================== */

    public function empresaNueva(): void
    {
        exigirLogin();
        $planes = $this->plan->listar();
        require __DIR__ . '/../views/empresa-nueva.php';
    }

    public function empresaGuardar(): void
    {
        exigirLogin();
        verificarCsrf();

        $empresa = limpiar($_POST['empresa'] ?? '');
        $ruc = limpiar($_POST['ruc'] ?? '');
        $clave = ($_POST['clave'] ?? '') !== '' ? $_POST['clave'] : '123456';
        $contacto = limpiar($_POST['contacto'] ?? '');
        $email = limpiar($_POST['email'] ?? '');
        $telefono = limpiar($_POST['telefono'] ?? '');

        if ($empresa === '' || $ruc === '') {
            $this->redirigirError('empresa-nueva', 'faltan_datos');
        }
        if ($this->cliente->existeRuc($ruc)) {
            $this->redirigirError('empresa-nueva', 'ruc_existe');
        }

        $id = $this->cliente->crear([
            'empresa' => $empresa,
            'ruc' => $ruc,
            'clave' => password_hash($clave, PASSWORD_DEFAULT),
            'email' => $email,
            'telefono' => $telefono,
            'contacto' => $contacto,
        ]);

        // Asigna los planes marcados en el formulario (pendientes por defecto).
        $planesSeleccionados = $_POST['planes'] ?? [];
        foreach ((array) $planesSeleccionados as $planId) {
            $planId = (int) $planId;
            if ($this->plan->obtener($planId)) {
                $this->plan->asignar([
                    'cliente_id' => $id,
                    'plan_id' => $planId,
                    'estado' => 'pendiente',
                    'usuarios' => 1,
                    'precio_mensual' => 0,
                    'fecha_inicio' => date('Y-m-d'),
                    'fecha_vencimiento' => date('Y-12-31'),
                    'notas' => 'Contratado al registrar la empresa.',
                ]);
            }
        }

        irA("empresa-detalle&id=$id&creada=1");
    }

    public function empresaEditar(): void
    {
        exigirLogin();
        $id = (int) ($_GET['id'] ?? 0);
        $empresa = $this->cliente->obtener($id);
        if (!$empresa) {
            http_response_code(404);
            echo 'Empresa no encontrada.';
            return;
        }
        require __DIR__ . '/../views/empresa-editar.php';
    }

    public function empresaActualizar(): void
    {
        exigirLogin();
        verificarCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->cliente->obtener($id)) {
            die('Empresa no encontrada.');
        }

        $empresa = limpiar($_POST['empresa'] ?? '');
        $ruc = limpiar($_POST['ruc'] ?? '');
        $contacto = limpiar($_POST['contacto'] ?? '');
        $email = limpiar($_POST['email'] ?? '');
        $telefono = limpiar($_POST['telefono'] ?? '');

        if ($empresa === '' || $ruc === '') {
            irA("empresa-editar&id=$id&error=faltan_datos");
        }

        // Si cambia el RUC, verifica que no choque con otra empresa.
        $actual = $this->cliente->obtener($id);
        if ($ruc !== $actual['ruc'] && $this->cliente->existeRuc($ruc)) {
            irA("empresa-editar&id=$id&error=ruc_existe");
        }

        // Nueva clave opcional (solo si se escribe una).
        if (isset($_POST['clave']) && trim($_POST['clave']) !== '') {
            $clave = limpiar($_POST['clave']);
            if (strlen($clave) < 4) {
                irA("empresa-editar&id=$id&error=clave_corta");
            }
            $this->cliente->actualizarClave($id, password_hash($clave, PASSWORD_DEFAULT));
        }

        $this->cliente->actualizar($id, [
            'empresa' => $empresa,
            'ruc' => $ruc,
            'email' => $email,
            'telefono' => $telefono,
            'contacto' => $contacto,
        ]);
        irA("empresa-detalle&id=$id&actualizada=1");
    }

    public function empresaEliminar(): void
    {
        exigirLogin();
        verificarCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->cliente->obtener($id)) {
            die('Empresa no encontrada.');
        }
        $this->cliente->eliminar($id);
        irA('empresas&eliminada=1');
    }

    public function empresaDetalle(): void
    {
        exigirLogin();
        $id = (int) ($_GET['id'] ?? 0);
        $empresa = $this->cliente->obtener($id);
        if (!$empresa) {
            http_response_code(404);
            echo 'Empresa no encontrada.';
            return;
        }
        $planes = $this->plan->porCliente($id);
        $tickets = (new Ticket())->porCliente($id);
        require __DIR__ . '/../views/empresa-detalle.php';
    }

    /* ===================================================
     * PANEL INTERNO — Planes de una empresa (suscripciones)
     * =================================================== */

    public function gestionarPlanes(): void
    {
        exigirLogin();
        $id = (int) ($_GET['id'] ?? 0);
        $empresa = $this->cliente->obtener($id);
        if (!$empresa) {
            http_response_code(404);
            echo 'Empresa no encontrada.';
            return;
        }
        $suscritos = $this->plan->porCliente($id);
        $disponibles = $this->plan->disponiblesPara($id);
        $todos = $this->plan->listar();
        require __DIR__ . '/../views/gestionar-planes.php';
    }

    public function planAsignar(): void
    {
        exigirLogin();
        verificarCsrf();

        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $empresa = $this->cliente->obtener($clienteId);
        if (!$empresa || !$this->plan->obtener($planId)) {
            die('Datos inválidos.');
        }
        if ($this->plan->tiene($clienteId, $planId)) {
            irA("gestionar-planes&id=$clienteId&error=ya_existe");
        }

        $datos = $this->datosSuscripcion();
        $datos['cliente_id'] = $clienteId;
        $datos['plan_id'] = $planId;
        $this->plan->asignar($datos);
        irA("empresa-detalle&id=$clienteId&plan_asignado=1");
    }

    public function planActualizar(): void
    {
        exigirLogin();
        verificarCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $suscripcion = $this->plan->suscripcion($id);
        if (!$suscripcion) {
            die('Suscripción no encontrada.');
        }

        $datos = $this->datosSuscripcion();
        $datos['plan_id'] = (int) $_POST['plan_id'];
        if (!$this->plan->obtener($datos['plan_id'])) {
            die('Plan inválido.');
        }
        if ($datos['plan_id'] !== (int) $suscripcion['plan_id']
            && $this->plan->tiene((int) $suscripcion['cliente_id'], $datos['plan_id'])) {
            irA("empresa-detalle&id=" . (int) $suscripcion['cliente_id'] . "&error=plan_duplicado");
        }
        $this->plan->actualizar($id, $datos);
        irA("empresa-detalle&id=" . (int) $suscripcion['cliente_id'] . "&plan_actualizado=1");
    }

    public function planEliminar(): void
    {
        exigirLogin();
        verificarCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $suscripcion = $this->plan->suscripcion($id);
        if (!$suscripcion) {
            die('Suscripción no encontrada.');
        }
        $clienteId = (int) $suscripcion['cliente_id'];
        $this->plan->eliminar($id);
        irA("empresa-detalle&id=$clienteId&plan_eliminado=1");
    }

    /* ===================================================
     * PORTAL DEL CLIENTE — Mis planes
     * =================================================== */

    public function misPlanes(): void
    {
        $this->exigirCliente();
        $misPlanes = $this->plan->porCliente((int) $_SESSION['cliente_id']);
        $porEstado = $this->conteoCliente();
        require __DIR__ . '/../views/portal/mis-planes.php';
    }

    public function portalPlan(): void
    {
        $this->exigirCliente();
        $id = (int) ($_GET['id'] ?? 0);
        $suscripcion = $this->plan->suscripcion($id);
        if (!$suscripcion || (int) $suscripcion['cliente_id'] !== (int) $_SESSION['cliente_id']) {
            http_response_code(404);
            echo 'Plan no encontrado.';
            return;
        }
        $tickets = (new Ticket())->porClienteYPlan((int) $suscripcion['cliente_id'], (int) $suscripcion['plan_id']);
        require __DIR__ . '/../views/portal/plan.php';
    }

    /* ===================================================
     * Helpers
     * =================================================== */

    private function exigirCliente(): void
    {
        if (empty($_SESSION['cliente_id'])) {
            header('Location: index.php?ruta=login');
            exit;
        }
    }

    private function datosSuscripcion(): array
    {
        $estado = limpiar($_POST['estado'] ?? 'activo');
        if (!$this->plan->tieneEstado($estado)) {
            die('Estado de plan inválido.');
        }
        $precio = str_replace(',', '.', trim($_POST['precio_mensual'] ?? '0'));
        if (!is_numeric($precio)) {
            $precio = 0;
        }
        return [
            'estado' => $estado,
            'usuarios' => max(1, (int) ($_POST['usuarios'] ?? 1)),
            'precio_mensual' => (float) $precio,
            'fecha_inicio' => limpiar($_POST['fecha_inicio'] ?? '') ?: null,
            'fecha_vencimiento' => limpiar($_POST['fecha_vencimiento'] ?? '') ?: null,
            'notas' => limpiar($_POST['notas'] ?? ''),
        ];
    }

    private function conteoCliente(): array
    {
        $conteo = ['activo' => 0, 'pendiente' => 0, 'vencido' => 0, 'cancelado' => 0];
        foreach ($this->plan->porCliente((int) $_SESSION['cliente_id']) as $p) {
            if (isset($conteo[$p['estado']])) {
                $conteo[$p['estado']]++;
            }
        }
        return $conteo;
    }

    private function redirigirError(string $ruta, string $error): void
    {
        irA("$ruta&error=$error");
    }
}