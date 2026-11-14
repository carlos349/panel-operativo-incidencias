<?php

namespace App\Enums;

/**
 * Estados funcionales de una incidencia. El valor coincide con statuses.name,
 * de modo que la tabla es el catálogo y este enum concentra las reglas.
 */
enum IncidentStatus: string
{
    case Open = 'Abierta';
    case InReview = 'En Revisión';
    case Blocked = 'Bloqueada';
    case Resolved = 'Resuelta';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InReview],
            self::InReview => [self::Blocked, self::Resolved],
            self::Blocked => [self::InReview],
            self::Resolved => [self::Open], // reapertura explícita
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Pasar a estos estados exige confirmación explícita del usuario. */
    public function requiresConfirmation(): bool
    {
        return in_array($this, [self::Blocked, self::Resolved], true);
    }

    /** ¿Llegar a este estado desde $origin es una reapertura? */
    public function isReopenFrom(self $origin): bool
    {
        return $origin === self::Resolved && $this === self::Open;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InReview => 'warning',
            self::Blocked => 'danger',
            self::Resolved => 'success',
        };
    }
}
