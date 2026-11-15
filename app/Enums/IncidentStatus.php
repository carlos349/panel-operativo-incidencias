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
    case Cancelled = 'Anulada';

    /**
     * Estados con los que puede nacer una incidencia: sin responsable solo
     * «Abierta»; con responsable también «En Revisión» (ya hay quien la atienda).
     *
     * @return list<self>
     */
    public static function allowedInitial(bool $hasAssignee): array
    {
        return $hasAssignee ? [self::Open, self::InReview] : [self::Open];
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InReview, self::Cancelled],
            self::InReview => [self::Blocked, self::Resolved, self::Cancelled],
            self::Blocked => [self::InReview, self::Cancelled],
            self::Resolved => [self::Open], // reapertura explícita; una resuelta no se anula
            self::Cancelled => [],          // estado final
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Pasar a estos estados exige confirmación explícita del usuario. */
    public function requiresConfirmation(): bool
    {
        return in_array($this, [self::Blocked, self::Resolved, self::Cancelled], true);
    }

    /** ¿Llegar a este estado desde $origin es una reapertura? */
    public function isReopenFrom(self $origin): bool
    {
        return $origin === self::Resolved && $this === self::Open;
    }

    /** Reabrir y anular deben quedar justificados en el historial. */
    public function requiresReasonFrom(self $origin): bool
    {
        return $this === self::Cancelled || $this->isReopenFrom($origin);
    }

    /** Estados en los que el reloj del SLA ya no corre. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Resolved, self::Cancelled], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InReview => 'warning',
            self::Blocked => 'danger',
            self::Resolved => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
