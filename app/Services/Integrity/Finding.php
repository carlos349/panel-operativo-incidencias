<?php

namespace App\Services\Integrity;

/** Una inconsistencia detectada y la acción prevista para ella. */
final readonly class Finding
{
    public const AUTO = 'auto';

    public const MANUAL = 'manual';

    public const ALREADY_FLAGGED = 'flagged';

    /**
     * @param  string  $code  Identificador estable del tipo de inconsistencia
     * @param  string  $resolution  self::AUTO | self::MANUAL | self::ALREADY_FLAGGED
     * @param  array<string, mixed>  $context  Datos necesarios para aplicar la corrección
     */
    public function __construct(
        public string $code,
        public int $incidentId,
        public string $problem,
        public string $resolution,
        public string $plan,
        public array $context = [],
    ) {}

    public function isActionable(): bool
    {
        return $this->resolution !== self::ALREADY_FLAGGED;
    }
}
