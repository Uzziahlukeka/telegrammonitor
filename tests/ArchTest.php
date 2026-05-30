<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect('App')
    ->toUseStrictTypes()
    ->not->toUse(['die', 'dd', 'dump']);

arch('it will verify commands')
    ->expect('Uzziahlukeka\TelegramMonitor\Commands')
    ->toBeClasses()
    ->toExtend('Illuminate\Console\Command')
    ->toOnlyBeUsedIn(['src\Commands', 'Uzziahlukeka\TelegramMonitor']);

arch('it will verify facades')
    ->expect('Uzziahlukeka\TelegramMonitor\Facades')
    ->toBeClasses()
    ->toExtend('Illuminate\Support\Facades\Facade');

arch('package classes should be in correct namespace')
    ->expect('Uzziahlukeka\\TelegramMonitor')
    ->toBeClasses()->ignoring('Uzziahlukeka\\TelegramMonitor\\Traits');
