<?php

namespace App\Support;

/**
 * La prueba no incluye autenticación: el "usuario" es un nombre guardado en
 * sesión que firma los logs y comentarios. Sustituible por auth()->user().
 */
class CurrentOperator
{
    private const SESSION_KEY = 'operator_name';

    public const DEFAULT = 'Operador Demo';

    public static function name(): string
    {
        return session(self::SESSION_KEY, self::DEFAULT);
    }

    public static function set(string $name): void
    {
        session([self::SESSION_KEY => trim($name) ?: self::DEFAULT]);
    }
}
