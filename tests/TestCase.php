<?php

namespace JoshDaugherty\HomeDashboard\Tests;

use Flux\FluxServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JoshDaugherty\HomeDashboard\HomeDashboardServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            FluxServiceProvider::class,
            HomeDashboardServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        // The host app owns auth; the package smoke tests only need the plain web group.
        $app['config']->set('home-dashboard.route.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
