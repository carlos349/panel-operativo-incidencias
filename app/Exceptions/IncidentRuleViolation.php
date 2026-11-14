<?php

namespace App\Exceptions;

use DomainException;

/** Una operación viola una regla de negocio; el mensaje es apto para mostrarse al usuario. */
class IncidentRuleViolation extends DomainException
{
    public static function sameStatus(string $status): self
    {
        return new self("La incidencia ya está en estado «{$status}».");
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("No se permite pasar de «{$from}» a «{$to}».");
    }

    public static function reopenReasonRequired(): self
    {
        return new self('Para reabrir una incidencia resuelta es obligatorio indicar el motivo.');
    }
}
