<?php

namespace App\Enums;

enum SlaState: string
{
    case OnTrack = 'on_track';
    case AtRisk = 'at_risk';
    case Breached = 'breached';
    case Met = 'met';
    case Missed = 'missed';

    public function label(): string
    {
        return match ($this) {
            self::OnTrack => 'SLA en plazo',
            self::AtRisk => 'SLA en riesgo',
            self::Breached => 'SLA vencido',
            self::Met => 'SLA cumplido',
            self::Missed => 'SLA incumplido',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::OnTrack, self::Met => 'success',
            self::AtRisk => 'warning',
            self::Breached, self::Missed => 'danger',
        };
    }
}
