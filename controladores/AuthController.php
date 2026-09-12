<?php
require_once __DIR__ . '/../modelo/Agente.php';
require_once __DIR__ . '/../modelo/Cliente.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

class AuthController
{
    public function mostrarLogin(): void
    {
        require __DIR__ . '/../views/login.php';
    }

    public function procesarLogin(): void
    {
        verificarCsrf();
        $usuario = limpiar($_POST['usuario'] ?? ''); // correo o RUC
        $password = $_POST['password'] ?? '';

        // Si el "usuario" es solo dígitos, se trata de una empresa (cliente) por su RUC.
        if (preg_match('/^\d+$/', $usuario) && strlen($usuario) >= 6) {
            $cliente = (new Cliente())->validarPortal($usuario, $password);
            if ($cliente) {
                session_regenerate_id(true);
                $_SESSION['cliente_id'] = (int) $cliente['id'];
                $_SESSION['cliente_empresa'] = $cliente['empresa'];
                unset($_SESSION['usuario_id']);
                irA('portal-mis-tickets');
            }
            $error = 'RUC o clave incorrectos. Verifica tus datos.';
            require __DIR__ . '/../views/login.php';
            return;
        }

        // De lo contrario se asume correo de usuario interno (soporte/encargado/admin).
        $persona = (new Agente())->porEmail($usuario);
        if ($persona && password_verify($password, $persona['password'])) {
            iniciarSesion($persona);
            irA('dashboard');
        }

        $error = 'Correo o contraseña incorrectos.';
        require __DIR__ . '/../views/login.php';
    }

    public function logout(): void
    {
        cerrarSesion();
        irA('login');
    }
}
