<?php

namespace App\Support\Sla;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Aritmética de tiempo laboral: el reloj solo avanza dentro del horario
 * de oficina configurado (config/sla.php).
 */
class BusinessHoursCalculator
{
    /**
     * @param  list<int>  $days  Días laborables ISO (1 = lunes ... 7 = domingo)
     * @param  list<string>  $holidays  Fechas Y-m-d no laborables
     */
    public function __construct(
        private readonly array $days,
        private readonly string $start,
        private readonly string $end,
        private readonly array $holidays = [],
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('sla.business_hours.days'),
            config('sla.business_hours.start'),
            config('sla.business_hours.end'),
            config('sla.holidays', []),
        );
    }

    /** Instante en que se habrán consumido $minutes minutos laborales desde $from. */
    public function addMinutes(DateTimeInterface $from, int $minutes): CarbonImmutable
    {
        $cursor = $this->nextBusinessMoment(CarbonImmutable::instance($from));
        $remaining = max(0, $minutes);

        while ($remaining > 0) {
            $available = (int) $cursor->diffInMinutes($this->closingOf($cursor));

            if ($remaining <= $available) {
                return $cursor->addMinutes($remaining);
            }

            $remaining -= $available;
            $cursor = $this->nextBusinessMoment($this->closingOf($cursor));
        }

        return $cursor;
    }

    /** Minutos laborales entre dos instantes (0 si $to no es posterior a $from). */
    public function minutesBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $to = CarbonImmutable::instance($to);
        $cursor = $this->nextBusinessMoment(CarbonImmutable::instance($from));
        $total = 0;

        while ($cursor < $to) {
            $segmentEnd = $this->closingOf($cursor)->min($to);
            $total += (int) $cursor->diffInMinutes($segmentEnd);
            $cursor = $this->nextBusinessMoment($this->closingOf($cursor));
        }

        return $total;
    }

    /** Primer instante laboral igual o posterior a $moment. */
    private function nextBusinessMoment(CarbonImmutable $moment): CarbonImmutable
    {
        // Límite defensivo: un calendario sin días laborables no debe colgar el proceso.
        for ($i = 0; $i < 370; $i++) {
            if ($this->isBusinessDay($moment)) {
                if ($moment < $this->openingOf($moment)) {
                    return $this->openingOf($moment);
                }

                if ($moment < $this->closingOf($moment)) {
                    return $moment;
                }
            }

            $moment = $this->openingOf($moment->addDay());
        }

        return $moment;
    }

    private function isBusinessDay(CarbonImmutable $day): bool
    {
        return in_array($day->dayOfWeekIso, $this->days, true)
            && ! in_array($day->toDateString(), $this->holidays, true);
    }

    private function openingOf(CarbonImmutable $day): CarbonImmutable
    {
        return $day->setTimeFromTimeString($this->start);
    }

    private function closingOf(CarbonImmutable $day): CarbonImmutable
    {
        return $day->setTimeFromTimeString($this->end);
    }
}
