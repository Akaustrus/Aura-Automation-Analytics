<?php
/**
 * Sistema de Gestión de Tareas (Aura Automation & Analytics)
 *
 * La instalación ya es AUTOMÁTICA: al abrir index.php por primera vez el sistema
 * detecta tu MySQL (puerto 3306/3307, con o sin contraseña), crea la base de datos
 * "tareas", las tablas y los datos de ejemplo. No es necesario ejecutar nada.
 *
 * Esta página queda solo como herramienta opcional para reiniciar los datos.
 */

require_once __DIR__ . '/config/db.php';

$reiniciado = false;
$error = '';

if (($_GET['accion'] ?? '') === 'reiniciar') {
    try {
        $db = TareasDB::conexion();
        $db->exec('DELETE FROM audit_log');
        $db->exec('DELETE FROM tareas');
        $db->exec('
            INSERT INTO tareas (titulo, descripcion, prioridad, estado, fecha_vencimiento) VALUES
            ("Configurar servidor de producción", "Revisar Apache, PHP 8.2 y variables de entorno del servidor.", "alta", "en_proceso", CURDATE() + INTERVAL 3 DAY),
            ("Diseñar plantilla de reporte mensual", "Incluir gráficos de ingresos y gastos por sucursal.", "media", "pendiente", CURDATE() + INTERVAL 7 DAY),
            ("Capacitar al equipo de ventas", "Sesión de 2 horas sobre el nuevo módulo de facturación.", "baja", "pendiente", CURDATE() + INTERVAL 14 DAY),
            ("Corregir error en importación CSV", "El separador de miles rompe la importación de documentos.", "critica", "en_proceso", CURDATE()),
            ("Documentar API de pagos", "Generar la documentación en formato OpenAPI.", "media", "completada", CURDATE() - INTERVAL 2 DAY),
            ("Respaldado semanal de bases de datos", "Verificar integridad de los respaldos automáticos.", "alta", "completada", CURDATE() - INTERVAL 1 DAY)
        ');
        $reiniciado = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Tareas - Estado</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/estilos.css" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .estado-card { width: 100%; max-width: 560px; border-radius: 18px; }
    </style>
</head>
<body>
    <div class="card estado-card shadow-lg">
        <div class="card-header py-3">
            <h1 class="h5 mb-0"><i class="fa-solid fa-list-check me-2 text-success"></i>Sistema de Gestión de Tareas</h1>
        </div>
        <div class="card-body">
            <div class="alert alert-success py-2 mb-3">
                <i class="fa-solid fa-circle-check me-2"></i>La instalación es automática: el sistema crea la base de datos y las tablas al abrirse por primera vez.
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 mb-3">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php elseif ($reiniciado): ?>
                <div class="alert alert-info py-2 mb-3">
                    <i class="fa-solid fa-rotate me-2"></i>Datos de ejemplo restaurados correctamente.
                </div>
            <?php endif; ?>

            <hr>
            <a href="index.php" class="btn btn-primary"><i class="fa-solid fa-arrow-right me-1"></i>Ir al sistema de tareas</a>
            <a href="index.php?vista=panel" class="btn btn-outline-secondary ms-2"><i class="fa-solid fa-chart-pie me-1"></i>Ver estadísticas</a>
            <a href="?accion=reiniciar" class="btn btn-outline-danger ms-2" data-confirm="¿Restaurar los datos de ejemplo? Se borrarán tus tareas."><i class="fa-solid fa-rotate me-1"></i>Restaurar datos de ejemplo</a>
        </div>
    </div>
    <script>
        document.querySelectorAll('[data-confirm]').forEach(function(a){
            a.addEventListener('click', function(e){
                if (!confirm(this.dataset.confirm)) e.preventDefault();
            });
        });
    </script>
</body>
</html>
