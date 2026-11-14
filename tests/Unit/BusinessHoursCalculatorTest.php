<?php

namespace Tests\Unit;

use App\Support\Sla\BusinessHoursCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BusinessHoursCalculatorTest extends TestCase
{
    private BusinessHoursCalculator $calendar;

    protected function setUp(): void
    {
        parent::setUp();

        // Lunes a viernes, 08:00-17:00.
        $this->calendar = new BusinessHoursCalculator([1, 2, 3, 4, 5], '08:00', '17:00');
    }

    #[Test]
    public function adds_minutes_within_the_same_working_day(): void
    {
        $due = $this->calendar->addMinutes(CarbonImmutable::parse('2026-11-16 09:00'), 240); // lunes

        $this->assertSame('2026-11-16 13:00', $due->format('Y-m-d H:i'));
    }

    #[Test]
    public function rolls_over_the_weekend(): void
    {
        // Viernes 16:30 + 4 h laborales => 30 min el viernes y 3 h 30 min el lunes.
        $due = $this->calendar->addMinutes(CarbonImmutable::parse('2026-11-13 16:30'), 240);

        $this->assertSame('2026-11-16 11:30', $due->format('Y-m-d H:i'));
    }

    #[Test]
    public function starts_counting_at_the_next_opening_when_created_out_of_hours(): void
    {
        // Sábado por la noche: el reloj arranca el lunes a las 08:00.
        $due = $this->calendar->addMinutes(CarbonImmutable::parse('2026-11-14 22:00'), 15);

        $this->assertSame('2026-11-16 08:15', $due->format('Y-m-d H:i'));
    }

    #[Test]
    public function skips_holidays(): void
    {
        $calendar = new BusinessHoursCalculator([1, 2, 3, 4, 5], '08:00', '17:00', ['2026-11-16']);

        $due = $calendar->addMinutes(CarbonImmutable::parse('2026-11-13 16:00'), 120);

        $this->assertSame('2026-11-17 09:00', $due->format('Y-m-d H:i'));
    }

    #[Test]
    public function measures_only_working_minutes_between_two_moments(): void
    {
        $minutes = $this->calendar->minutesBetween(
            CarbonImmutable::parse('2026-11-13 16:00'), // viernes
            CarbonImmutable::parse('2026-11-16 09:00'), // lunes
        );

        $this->assertSame(120, $minutes);
    }
}
