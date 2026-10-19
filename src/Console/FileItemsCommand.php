<?php

namespace JoshDaugherty\HomeDashboard\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use JoshDaugherty\HomeDashboard\Support\Groceries;

class FileItemsCommand extends Command
{
    protected $signature = 'home-dashboard:file-items';

    protected $description = 'File every Notion item that has no aisle under the aisle Kroger says it is in';

    public function handle(Groceries $groceries): int
    {
        if (! $groceries->configured()) {
            $this->components->warn('Notion is not configured.');

            return self::FAILURE;
        }

        // Start from fresh data, since this is run by hand after editing Notion.
        foreach (['items', 'aisles'] as $name) {
            Cache::forget('home-dashboard:notion:v1:'.$name);
        }

        $left = null;

        do {
            $before = $left;
            $left = $groceries->fileCatalog(25);

            if ($left === null) {
                $this->components->warn('Kroger is not configured (KROGER_CLIENT_ID, KROGER_CLIENT_SECRET, KROGER_LOCATION_ID).');

                return self::FAILURE;
            }

            $this->components->info($left.' without an aisle yet.');
        } while ($left > 0 && $left !== $before);

        $this->components->info('Done. Items Kroger could not place stay under Unsorted until you pick an aisle in Notion.');

        return self::SUCCESS;
    }
}