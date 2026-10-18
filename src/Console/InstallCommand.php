<?php

namespace JoshDaugherty\HomeDashboard\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'home-dashboard:install';

    protected $description = 'Publish the config and tell Tailwind to scan the dashboard views';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'home-dashboard-config']);

        $css = resource_path('css/app.css');
        $source = "@source '../../vendor/joshdaugherty/home-dashboard/resources/views';";

        if (is_file($css) && ! str_contains(file_get_contents($css), 'joshdaugherty/home-dashboard')) {
            file_put_contents($css, rtrim(file_get_contents($css))."\n\n{$source}\n");
            $this->components->info('Added the Tailwind @source line to resources/css/app.css.');
        } elseif (! is_file($css)) {
            $this->components->warn("resources/css/app.css not found. Add this to your Tailwind CSS:\n{$source}");
        }

        $this->components->info('Done. Run your asset build (npm run build) so Tailwind picks up the dashboard classes.');

        return self::SUCCESS;
    }
}
