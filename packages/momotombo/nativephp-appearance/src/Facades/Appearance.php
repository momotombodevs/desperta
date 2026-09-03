<?php

namespace Momotombo\NativephpAppearance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void set(string $mode)
 * @method static string|null get()
 *
 * @see \Momotombo\NativephpAppearance\Appearance
 */
class Appearance extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Momotombo\NativephpAppearance\Appearance::class;
    }
}
