<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Forms\Actions\SyncFormsAction;

final class SyncFormsCommand extends Command
{
    protected $signature = 'forms:sync';

    protected $description = 'Sync forms defined in config into the database';

    public function handle(SyncFormsAction $action): int
    {
        $synced = $action->execute();

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
