<?php

namespace Uzhlaravel\Telegramlogs\Commands;

use Illuminate\Console\Command;

class TelegramlogsCommand extends Command
{
    public $signature = 'telegramlogs';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
