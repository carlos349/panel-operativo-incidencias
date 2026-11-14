<?php

namespace App\Enums;

enum Priority: string
{
    case P1 = 'P1';
    case P2 = 'P2';
    case P3 = 'P3';
    case P4 = 'P4';

    public function label(): string
    {
        return match ($this) {
            self::P1 => 'P1 Critical',
            self::P2 => 'P2 Alta',
            self::P3 => 'P3 Media',
            self::P4 => 'P4 Baja',
        };
    }

    /** Tono visual consumido por los componentes Blade (x-ui.badge). */
    public function tone(): string
    {
        return match ($this) {
            self::P1 => 'danger',
            self::P2 => 'warning',
            self::P3 => 'info',
            self::P4 => 'neutral',
        };
    }
}
