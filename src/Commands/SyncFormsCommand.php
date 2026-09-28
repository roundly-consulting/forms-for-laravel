<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Forms\FormsManager;

final class SyncFormsCommand extends Command
{
    protected $signature = 'forms:sync';

    protected $description = 'Sync forms defined in config into the database';

    public function handle(FormsManager $forms): int
    {
        $synced = $forms->sync();

        if ($synced === []) {
            $this->info('No form definitions configured.');

            return self::SUCCESS;
        }

        foreach ($synced as $key) {
            $this->line("Synced form [{$key}].");
        }

        $this->info(sprintf('Synced %d form(s).', count($synced)));

        return self::SUCCESS;
    }
}
