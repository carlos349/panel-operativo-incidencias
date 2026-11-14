<?php

namespace App\Support\Sla;

use App\Enums\Priority;
use App\Enums\SlaState;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Traduce la matriz de SLA (prioridad -> minutos laborales) a fechas límite
 * y evalúa el estado de cumplimiento.
 */
class SlaPolicy
{
    public function __construct(private readonly BusinessHoursCalculator $calendar) {}

    public function firstResponseMinutes(Priority $priority): int
    {
        return (int) config("sla.matrix.{$priority->value}.first_response");
    }

    public function resolutionMinutes(Priority $priority): int
    {
        return (int) config("sla.matrix.{$priority->value}.resolution");
    }

    public function firstResponseDueAt(Priority $priority, DateTimeInterface $from): CarbonImmutable
    {
        return $this->calendar->addMinutes($from, $this->firstResponseMinutes($priority));
    }

    public function resolutionDueAt(Priority $priority, DateTimeInterface $from): CarbonImmutable
    {
        return $this->calendar->addMinutes($from, $this->resolutionMinutes($priority));
    }

    /** Estado del SLA de resolución; null si la incidencia no tiene plazo calculado. */
    public function resolutionState(
        Priority $priority,
        ?DateTimeInterface $dueAt,
        ?DateTimeInterface $resolvedAt,
        ?DateTimeInterface $now = null,
    ): ?SlaState {
        if ($dueAt === null) {
            return null;
        }

        if ($resolvedAt !== null) {
            return $resolvedAt <= $dueAt ? SlaState::Met : SlaState::Missed;
        }

        $now ??= now();

        if ($now > $dueAt) {
            return SlaState::Breached;
        }

        $remaining = $this->calendar->minutesBetween($now, $dueAt);
        $threshold = $this->resolutionMinutes($priority) * (float) config('sla.at_risk_remaining_ratio');

        return $remaining <= $threshold ? SlaState::AtRisk : SlaState::OnTrack;
    }
}
