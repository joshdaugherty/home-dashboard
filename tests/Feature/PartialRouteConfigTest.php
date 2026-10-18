<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use JoshDaugherty\HomeDashboard\Tests\TestCase;

class PartialRouteConfigTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Hosts override only some `route` keys; mergeConfigFrom is shallow, so `enabled` is dropped.
        $app['config']->set('home-dashboard.route', ['name' => 'dashboard', 'middleware' => ['web']]);
    }

    public function test_route_stays_enabled_when_host_omits_the_enabled_key(): void
    {
        $this->assertSame(url('/'), route('dashboard'));
        $this->get('/')->assertOk();
    }
}
