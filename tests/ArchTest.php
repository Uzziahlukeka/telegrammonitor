<?php

arch('it will not use debugging functions')
    ->expect('App')
    ->toUseStrictTypes()
    ->not->toUse(['die', 'dd', 'dump']);

arch('it will verify commands')
    ->expect('Uzhlaravel\Telegramlogs\Commands')
    ->toBeClasses()
    ->toExtend('Illuminate\Console\Command')
    ->toOnlyBeUsedIn(['src\Commands', 'Uzhlaravel\Telegramlogs']);

arch('it will verify facades')
    ->expect('Uzhlaravel\Telegramlogs\Facades')
    ->toBeClasses()
    ->toExtend('Illuminate\Support\Facades\Facade');

arch('package classes should be in correct namespace')
    ->expect('Uzhlaravel\\Telegramlogs')
    ->toBeClasses();
