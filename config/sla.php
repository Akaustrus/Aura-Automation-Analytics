<?php

/**
 * Acuerdos de nivel de servicio para incidencias.
 *
 * Los tiempos se expresan en horas corridas. Mantenerlos en configuración evita
 * duplicar reglas de negocio en controladores, vistas o JavaScript.
 */
return [
    'objetivo_cumplimiento' => 90,
    'umbral_riesgo' => 0.75,
    'horas_por_prioridad' => [
        'urgente' => 4,
        'alta' => 8,
        'media' => 24,
        'baja' => 48,
    ],
];
