<?php
/**
 * Login del Sistema ERP para medianas empresas (Aura Automation & Analytics).
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['med_usuario'])) {
    redireccionar('index.php');
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $uso  = trim($_POST['usuario'] ?? '');
    $clav = (string) ($_POST['clave'] ?? '');

    if ($uso === '' || $clav === '') {
        $error = 'Ingrese usuario y contraseña.';
    } else {
        try {
            $db = MedDB::conexion();
            $stmt = $db->prepare('SELECT * FROM usuarios WHERE usuario = ? AND estado = 1');
            $stmt->execute([$uso]);
            $user = $stmt->fetch();
            if ($user && password_verify($clav, $user['clave'])) {
                session_regenerate_id(true);
                $_SESSION['med_usuario'] = [
                    'id'     => (int) $user['id'],
                    'usuario'=> $user['usuario'],
                    'nombre' => $user['nombre'],
                    'rol'    => $user['rol'],
                ];
                redireccionar('index.php');
            }
            $error = 'Usuario o contraseña incorrectos.';
        } catch (PDOException $e) {
            $error = 'No se pudo conectar a la base de datos "medio". Ejecuta primero <a href="instalador.php">el instalador</a>.';
        }
    }
}
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — ERP Medio</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="../public/css/design-system.css" rel="stylesheet">
    <style>
        body { background: #f0fdfa; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Poppins', sans-serif; }
        .login-card { width: 100%; max-width: 400px; border-radius: 18px; border: 0; box-shadow: 0 12px 40px rgba(13,148,136,.15); }
        .login-logo { width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg,#0d9488,#14b8a6); color:#fff; display:flex; align-items:center; justify-content:center; font-size: 26px; margin: 0 auto; }
        .app-brand-lbl { color: #0f766e; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card login-card">
        <div class="card-body p-4 p-md-5">
            <div class="login-logo mb-3"><i class="fa-solid fa-building"></i></div>
            <h1 class="h4 text-center app-brand-lbl mb-1">ERP Mediana Empresa</h1>
            <p class="text-center text-muted small mb-4">Aura Automation &amp; Analytics</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= limpiar($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small">Usuario</label>
                    <input type="text" name="usuario" class="form-control" required autofocus autocomplete="username">
                </div>
                <div class="mb-4">
                    <label class="form-label small">Contraseña</label>
                    <input type="password" name="clave" class="form-control" required autocomplete="current-password">
                </div>
                <button class="btn btn-primary w-100"><i class="fa-solid fa-right-to-bracket me-1"></i>Iniciar sesión</button>
            </form>
            <div class="text-center mt-4 small text-muted">
                Usuarios demo (todos con clave <code>1234</code>): <code>admin</code>, <code>ventas1</code>, <code>compras1</code>, <code>store1</code>, <code>caja1</code>.
            </div>
        </div>
    </div>
</body>
</html>
