<?php

namespace Greeter\Text;

/**
 * Loaded by Wake's plugin autoloader ("autoload" in plugin.yml).
 */
final class Salutation
{
    public static function of(string $greeting): string
    {
        return $greeting.' from a plugin';
    }
}
