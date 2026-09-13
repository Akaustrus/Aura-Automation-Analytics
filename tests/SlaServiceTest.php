<?php

require_once __DIR__ . '/../servicios/SlaService.php';

$servicio = new SlaService([
    'objetivo_cumplimiento' => 90,
    'umbral_riesgo' => 0.75,
    'horas_por_prioridad' => ['urgente' => 4, 'alta' => 8, 'media' => 24, 'baja' => 48],
]);

$ahora = new DateTimeImmutable('2026-09-13 12:00:00');
$resultado = $servicio->calcular([
    ['prioridad' => 'urgente', 'fecha_creacion' => '2026-09-13 08:00:00', 'fecha_fin' => '2026-09-13 10:00:00'],
    ['prioridad' => 'alta', 'fecha_creacion' => '2026-09-13 00:00:00', 'fecha_fin' => null],
    ['prioridad' => 'media', 'fecha_creacion' => '2026-09-12 14:00:00', 'fecha_fin' => null],
    ['prioridad' => 'baja', 'fecha_creacion' => '2026-09-10 08:00:00', 'fecha_fin' => '2026-09-13 08:00:00'],
], $ahora);

assert($resultado['evaluados'] === 2);
assert($resultado['cumplidos'] === 1);
assert($resultado['cumplimiento'] === 50.0);
assert($resultado['vencidos'] === 2);
assert($resultado['en_riesgo'] === 1);
assert($resultado['tiempo_medio_horas'] === 37.0);

echo "SlaServiceTest OK\n";
