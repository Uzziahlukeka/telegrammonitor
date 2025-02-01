<?php

namespace Uzhlaravel\Telegramlogs\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Uzhlaravel\Telegramlogs\Telegramlogs
 */
class Telegramlogs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Uzhlaravel\Telegramlogs\Telegramlogs::class;
    }
}
