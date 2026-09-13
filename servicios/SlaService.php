<?php

class SlaService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require __DIR__ . '/../config/sla.php';
    }

    /**
     * Calcula el estado del SLA sin mezclar reglas de negocio con HTML o SQL.
     */
    public function calcular(array $tickets, ?DateTimeImmutable $ahora = null): array
    {
        $ahora ??= new DateTimeImmutable('now');
        $resumen = [
            'objetivo' => (int) $this->config['objetivo_cumplimiento'],
            'cumplimiento' => null,
            'cumplidos' => 0,
            'evaluados' => 0,
            'vencidos' => 0,
            'en_riesgo' => 0,
            'abiertos_en_plazo' => 0,
            'tiempo_medio_horas' => null,
            'por_prioridad' => [],
        ];
        $horasResueltas = [];

        foreach ($this->config['horas_por_prioridad'] as $prioridad => $objetivoHoras) {
            $resumen['por_prioridad'][$prioridad] = [
                'objetivo_horas' => (int) $objetivoHoras,
                'total' => 0,
                'cumplidos' => 0,
                'vencidos' => 0,
                'en_riesgo' => 0,
            ];
        }

        foreach ($tickets as $ticket) {
            $prioridad = $ticket['prioridad'] ?? 'media';
            $objetivo = (int) ($this->config['horas_por_prioridad'][$prioridad]
                ?? $this->config['horas_por_prioridad']['media']);
            $inicio = new DateTimeImmutable($ticket['fecha_creacion']);
            $fechaFin = $ticket['fecha_fin'] ?? null;
            $finalizado = $fechaFin !== null && $fechaFin !== '';
            $fin = $finalizado ? new DateTimeImmutable($fechaFin) : $ahora;
            $horas = max(0, ($fin->getTimestamp() - $inicio->getTimestamp()) / 3600);

            if (!isset($resumen['por_prioridad'][$prioridad])) {
                $prioridad = 'media';
            }
            $resumen['por_prioridad'][$prioridad]['total']++;

            if ($finalizado) {
                $resumen['evaluados']++;
                $horasResueltas[] = $horas;
                if ($horas <= $objetivo) {
                    $resumen['cumplidos']++;
                    $resumen['por_prioridad'][$prioridad]['cumplidos']++;
                } else {
                    $resumen['vencidos']++;
                    $resumen['por_prioridad'][$prioridad]['vencidos']++;
                }
                continue;
            }

            $avance = $objetivo > 0 ? $horas / $objetivo : 1;
            if ($avance > 1) {
                $resumen['vencidos']++;
                $resumen['por_prioridad'][$prioridad]['vencidos']++;
            } elseif ($avance >= (float) $this->config['umbral_riesgo']) {
                $resumen['en_riesgo']++;
                $resumen['por_prioridad'][$prioridad]['en_riesgo']++;
            } else {
                $resumen['abiertos_en_plazo']++;
            }
        }

        if ($resumen['evaluados'] > 0) {
            $resumen['cumplimiento'] = round($resumen['cumplidos'] * 100 / $resumen['evaluados'], 1);
        }
        if ($horasResueltas !== []) {
            $resumen['tiempo_medio_horas'] = round(array_sum($horasResueltas) / count($horasResueltas), 1);
        }

        return $resumen;
    }
}
