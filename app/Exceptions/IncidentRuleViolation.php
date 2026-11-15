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

    public static function reasonRequired(string $to): self
    {
        return new self("Para pasar una incidencia a «{$to}» es obligatorio indicar el motivo.");
    }

    public static function invalidInitialStatus(string $status, bool $hasAssignee): self
    {
        return new self($hasAssignee
            ? "Una incidencia no puede crearse en estado «{$status}»: solo «Abierta» o «En Revisión»."
            : "Una incidencia sin responsable solo puede crearse en estado «Abierta», no «{$status}».");
    }
}
