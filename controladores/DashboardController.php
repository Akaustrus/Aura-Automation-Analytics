<?php
require_once __DIR__ . '/../modelo/Ticket.php';
require_once __DIR__ . '/../modelo/Satisfaccion.php';
require_once __DIR__ . '/../modelo/Notificacion.php';
require_once __DIR__ . '/../modelo/Cliente.php';
require_once __DIR__ . '/../modelo/Plan.php';
require_once __DIR__ . '/../includes/auth.php';

class DashboardController
{
    public function mostrar(): void
    {
        exigirLogin();
        $model = new Ticket();
        $porEstado = $model->conteoPorEstado();
        $notificaciones = (new Notificacion())->listarNoLeidas((int) $_SESSION['usuario_id']);
        $satisfaccion = new Satisfaccion();
        $promedioSatisfaccion = $satisfaccion->promedio();

        $planes = (new Plan());
        $sistemas = $planes->listarTodos();
        $empresas = (new Cliente())->listarConPlanes();
        $porEstadoPlan = $planes->conteoPorEstado();
        $totalEmpresas = count($empresas);
        $empresasSinPlan = count(array_filter($empresas, fn ($e) => (int) $e['total_planes'] === 0));
        require __DIR__ . '/../views/dashboard.php';
    }
}
