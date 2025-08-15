<?php

arch('it will not use debugging functions')
    ->expect('App')
    ->toUseStrictTypes()
    ->not->toUse(['die', 'dd', 'dump']);

arch()
    ->expect('Uzhlaravel\Telegramlogs\Commands')
    ->toBeClasses()
    ->toExtend('Illuminate\Console\Command')
    ->toOnlyBeUsedIn('src\Commands');

arch()
    ->expect('Uzhlaravel\Telegramlogs\Facades')
    ->toBeClasses()
    ->toExtend('Illuminate\Support\Facades\Facade');

arch('package classes should be in correct namespace')
    ->expect('Uzhlaravel\\Telegramlogs')
    ->toBeClasses();
