<?php
require_once __DIR__ . '/../modelo/Cita.php';
require_once __DIR__ . '/../modelo/Horario.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

class CitaController
{
    private Cita $cita;

    public function __construct()
    {
        $this->cita = new Cita();
    }

    // ===================== PORTAL DEL CLIENTE =====================

    private function exigirCliente(): void
    {
        if (empty($_SESSION['cliente_id'])) {
            header('Location: index.php?ruta=login');
            exit;
        }
    }

    /** Pantalla para agendar una cita (cliente elige día + modalidad + slot). */
    public function agendar(): void
    {
        $this->exigirCliente();
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

        $fecha = $_GET['fecha'] ?? '';
        $slots = [];
        $error = null;
        $modalidad = 'presencial';

        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $modalidad = $_GET['modalidad'] ?? 'presencial';
            $diaSemana = (int) date('w', strtotime($fecha));
            $slots = $this->cita->slotsDisponibles($diaSemana, $fecha);
        }

        require __DIR__ . '/../views/portal/agendar.php';
    }

    /** Procesa la reserva de una cita. */
    public function reservar(): void
    {
        $this->exigirCliente();
        verificarCsrf();

        $fecha = limpiar($_POST['fecha'] ?? '');
        $slotId = (int) ($_POST['slot_id'] ?? 0);
        $modalidad = in_array($_POST['modalidad'] ?? '', ['presencial', 'virtual'], true)
            ? $_POST['modalidad']
            : 'presencial';
        $asunto = !empty($_POST['asunto']) ? limpiar($_POST['asunto']) : null;

        // Asegurar que la fecha corresponde al día del slot elegido.
        $slot = (new Horario())->porDia((int) date('w', strtotime($fecha)));
        $slotValido = false;
        foreach ($slot as $s) {
            if ((int) $s['id'] === $slotId) {
                $slotValido = true;
                break;
            }
        }

        if (!$slotValido) {
            die('Horario inválido.');
        }

        $id = $this->cita->reservar(
            (int) $_SESSION['cliente_id'],
            $slotId,
            $fecha,
            $modalidad,
            $asunto
        );

        if ($id) {
            irA('portal-citas&reservada=1');
        } else {
            irA('portal-agendar&fecha=' . $fecha . '&modalidad=' . $modalidad . '&lleno=1');
        }
    }

    /** Mis citas (cliente). */
    public function misCitas(): void
    {
        $this->exigirCliente();
        $citas = $this->cita->porCliente((int) $_SESSION['cliente_id']);
        require __DIR__ . '/../views/portal/mis-citas.php';
    }

    // ===================== PANEL INTERNO =====================

    /** Agenda del día (admin/encargado/soporte). */
    public function agenda(): void
    {
        exigirLogin();
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }
        $diaSemana = (int) date('w', strtotime($fecha));
        $slots = (new Horario())->porDia($diaSemana);
        $citas = $this->cita->porFecha($fecha);
        require __DIR__ . '/../views/agenda.php';
    }

    /** Definir la grilla de horarios por día de la semana (admin). */
    public function horarios(): void
    {
        exigirRol(['admin', 'encargado']);
        $horarios = (new Horario())->listar();
        require __DIR__ . '/../views/horarios.php';
    }

    /** Guardar / editar un slot de la grilla. */
    public function guardarHorario(): void
    {
        exigirRol(['admin', 'encargado']);
        verificarCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $dia = (int) ($_POST['dia_semana'] ?? 0);
        $horario = new Horario();
        $fila = null;
        if ($id) {
            foreach ($horario->listar() as $s) {
                if ((int) $s['id'] === $id) {
                    $fila = $s;
                    break;
                }
            }
        }
        // Si solo se cambia el estado activo a través del botón dedicado.
        $horario->guardar(
            $dia,
            $_POST['hora_inicio'] ?? ($fila['hora_inicio'] ?? '09:00'),
            $_POST['hora_fin'] ?? ($fila['hora_fin'] ?? '09:45'),
            (int) ($_POST['cupos'] ?? 1),
            (int) ($_POST['activo'] ?? 1),
            $id ?: null
        );
        irA('horarios');
    }

    /** Alterna el estado activo de un slot. */
    public function alternarHorario(): void
    {
        exigirRol(['admin', 'encargado']);
        verificarCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $activo = (int) ($_POST['activo'] ?? 0);
        $dia = (int) ($_POST['dia'] ?? 0);
        $hora = $_POST['hora_inicio'] ?? '09:00';
        $horario = new Horario();
        foreach ($horario->listar() as $s) {
            if ((int) $s['id'] === $id) {
                $horario->guardar(
                    (int) $s['dia_semana'], $s['hora_inicio'], $s['hora_fin'],
                    (int) $s['cupos'], $activo, (int) $s['id']
                );
                break;
            }
        }
        irA('horarios');
    }

    /** Cambia el estado de una cita (confirmar/realizar/cancelar) desde la agenda. */
    public function cambiarEstadoCita(): void
    {
        exigirLogin();
        verificarCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        if (!in_array($estado, ['confirmada', 'realizada', 'cancelada'], true)) {
            die('Estado inválido.');
        }
        $this->cita->cambiarEstado($id, $estado);
        $fecha = limpiar($_POST['fecha'] ?? date('Y-m-d'));
        irA('agenda&fecha=' . $fecha);
    }
}
