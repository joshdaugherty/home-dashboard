<?php

namespace JoshDaugherty\HomeDashboard;

use Illuminate\Support\ServiceProvider;
use JoshDaugherty\HomeDashboard\Console\CreateUserCommand;
use JoshDaugherty\HomeDashboard\Console\InstallCommand;
use JoshDaugherty\HomeDashboard\Livewire\SettingsPage;
use JoshDaugherty\HomeDashboard\Livewire\Shell;
use JoshDaugherty\HomeDashboard\Support\Settings;
use JoshDaugherty\HomeDashboard\Livewire\Tabs;
use Livewire\Livewire;

class HomeDashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/home-dashboard.php', 'home-dashboard');

        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'home-dashboard');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('home-dashboard.route.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        Livewire::component('home-dashboard.shell', Shell::class);
        Livewire::component('home-dashboard.settings', SettingsPage::class);
        Livewire::component('home-dashboard.tabs.today', Tabs\Today::class);
        Livewire::component('home-dashboard.tabs.calendar', Tabs\Calendar::class);
        Livewire::component('home-dashboard.tabs.chores', Tabs\Chores::class);
        Livewire::component('home-dashboard.tabs.shopping', Tabs\Shopping::class);
        Livewire::component('home-dashboard.tabs.photos', Tabs\Photos::class);
        Livewire::component('home-dashboard.tabs.home', Tabs\Home::class);

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, CreateUserCommand::class]);

            $this->publishes([
                __DIR__.'/../config/home-dashboard.php' => config_path('home-dashboard.php'),
            ], 'home-dashboard-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/home-dashboard'),
            ], 'home-dashboard-views');
        }
    }
}
