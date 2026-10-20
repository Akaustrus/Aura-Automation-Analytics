<?php

function irA(string $ruta): void
{
    header("Location: index.php?ruta=$ruta");
    exit;
}

function tokenCsrf(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . tokenCsrf() . '">';
}

function verificarCsrf(): void
{
    if (!hash_equals($_SESSION['_csrf'] ?? '', $_POST['_csrf'] ?? '')) {
        http_response_code(419);
        die('Token de seguridad invalido. Recarga la pagina.');
    }
}

function limpiar(string $dato): string
{
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

function formatoFecha(?string $fecha): string
{
    return $fecha ? date('d/m/Y H:i', strtotime($fecha)) : '—';
}

function formatoFechaCorta(?string $fecha): string
{
    return $fecha ? date('d/m/Y', strtotime($fecha)) : '—';
}

function formatoMoneda(?string $monto): string
{
    return 'S/ ' . number_format((float) ($monto ?: 0), 2, '.', ',');
}

function badgeEstado(string $estado): string
{
    $colores = [
        'nuevo' => 'secondary',
        'en_curso' => 'info',
        'pausado' => 'warning',
        'culminado' => 'primary',
        'resuelto' => 'success',
        'cerrado' => 'dark',
    ];
    $color = $colores[$estado] ?? 'secondary';
    $texto = ucfirst(str_replace('_', ' ', $estado));
    return "<span class=\"badge bg-$color\">$texto</span>";
}

function badgePrioridad(string $prioridad): string
{
    $colores = [
        'baja' => 'success',
        'media' => 'primary',
        'alta' => 'warning',
        'urgente' => 'danger',
    ];
    $color = $colores[$prioridad] ?? 'secondary';
    return "<span class=\"badge bg-$color\">" . ucfirst($prioridad) . "</span>";
}

const ESTADOS = ['nuevo', 'en_curso', 'pausado', 'culminado', 'resuelto', 'cerrado'];
const CATEGORIAS = ['rendimiento', 'funcion_nueva', 'actualizacion', 'error', 'consulta'];
const PRIORIDADES = ['baja', 'media', 'alta', 'urgente'];
const ESTADOS_PLAN = ['activo', 'pendiente', 'vencido', 'cancelado'];

function badgePlanEstado(string $estado): string
{
    $colores = [
        'activo' => 'success',
        'pendiente' => 'warning',
        'vencido' => 'danger',
        'cancelado' => 'secondary',
    ];
    $color = $colores[$estado] ?? 'secondary';
    $texto = ucfirst($estado);
    return "<span class=\"badge bg-$color\">$texto</span>";
}

function etiquetaPlanEstado(string $estado): string
{
    return match ($estado) {
        'activo' => 'Activo',
        'pendiente' => 'Pendiente',
        'vencido' => 'Vencido',
        'cancelado' => 'Cancelado',
        default => ucfirst($estado),
    };
}

function urlSistemaPortal(string $slug): ?string
{
    $rutas = [
        'contabilidad' => '../sistemacontable/index.php',
        'facturacion' => '../sistemafacturacion/index.php',
        'planilla' => '../sistemaplanilla/index.php',
        'erp-basico' => '../sistemaerp/index.php',
        'erp-mediana' => '../sistemaermedio/index.php',
        'sap' => '../sistemasap/index.php',
    ];

    return $rutas[$slug] ?? null;
}

function etiquetaCategoria(string $categoria): string
{
    return match ($categoria) {
        'rendimiento' => 'Rendimiento',
        'funcion_nueva' => 'Nueva función',
        'actualizacion' => 'Actualización',
        'error' => 'Error',
        'consulta' => 'Consulta',
        default => ucfirst($categoria),
    };
}

function badgeSatisfaccion(string $nivel): string
{
    $colores = [
        'muy_insatisfecho' => 'danger',
        'insatisfecho' => 'warning',
        'neutral' => 'secondary',
        'satisfecho' => 'info',
        'muy_satisfecho' => 'success',
    ];
    $color = $colores[$nivel] ?? 'secondary';
    $texto = match ($nivel) {
        'muy_insatisfecho' => 'Muy insatisfecho',
        'insatisfecho' => 'Insatisfecho',
        'neutral' => 'Neutral',
        'satisfecho' => 'Satisfecho',
        'muy_satisfecho' => 'Muy satisfecho',
        default => ucfirst($nivel),
    };
    return "<span class=\"badge bg-$color\">$texto</span>";
}

function esValido(string $valor, array $permitidos): bool
{
    return in_array($valor, $permitidos, true);
}

const DIAS_SEMANA = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

function diaSemanaNombre(int $d): string
{
    return DIAS_SEMANA[$d % 7];
}

function formatoFechaLarga(string $fecha): string
{
    $ts = strtotime($fecha);
    return diaSemanaNombre((int) date('w', $ts)) . ' ' . date('d/m/Y', $ts);
}

function badgeModalidad(string $m): string
{
    return $m === 'virtual'
        ? '<span class="badge bg-info text-dark"><i class="fa-solid fa-video me-1"></i>Virtual</span>'
        : '<span class="badge bg-primary"><i class="fa-solid fa-building me-1"></i>Presencial</span>';
}

function badgeCita(string $estado): string
{
    $colores = [
        'reservada' => 'secondary',
        'confirmada' => 'primary',
        'realizada' => 'success',
        'cancelada' => 'danger',
    ];
    $color = $colores[$estado] ?? 'secondary';
    return "<span class=\"badge bg-$color\">" . ucfirst($estado) . "</span>";
}

/**
 * Cuestionario de urgencia: evalúa en tiempo real, durante la llamada,
 * el nivel de urgencia y la prioridad del ticket.
 */

// Valor numérico de cada nivel (para subir/bajar prioridad).
function pesoPrioridad(string $p): int
{
    return array_search($p, PRIORIDADES, true);
}

/**
 * Nivel de urgencia según el impacto del fallo.
 * impacto: ninguno | parcial | total
 */
function sugiereUrgencia(string $impacto): string
{
    return match ($impacto) {
        'total' => 'urgente',
        'parcial' => 'alta',
        default => 'baja',
    };
}

/**
 * Prioridad: parte de la urgencia y sube un nivel si el fallo
 * detuvo operaciones o si es un problema recurrente.
 */
function sugierePrioridad(string $impacto, string $detenerOperaciones, string $reapertura): string
{
    $prioridad = sugiereUrgencia($impacto);

    if ($detenerOperaciones === 'si' || $reapertura === 'si') {
        $prioridad = PRIORIDADES[min(pesoPrioridad($prioridad) + 1, count(PRIORIDADES) - 1)];
    }

    // Si detuvo operaciones se considera siempre urgencia máxima.
    if ($detenerOperaciones === 'si') {
        return 'urgente';
    }

    return $prioridad;
}
