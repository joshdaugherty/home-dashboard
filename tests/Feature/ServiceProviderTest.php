<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use JoshDaugherty\HomeDashboard\Tests\TestCase;
use Livewire\Livewire;

class ServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        $this->assertSame('today', config('home-dashboard.home_tab'));
        $this->assertCount(6, config('home-dashboard.tabs'));
    }

    public function test_views_namespace_is_registered(): void
    {
        $this->assertTrue(view()->exists('home-dashboard::layouts.kiosk'));
        $this->assertTrue(view()->exists('home-dashboard::livewire.shell'));
    }

    public function test_commands_are_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('home-dashboard:install', $commands);
        $this->assertArrayHasKey('home-dashboard:user', $commands);
    }

    public function test_every_configured_tab_component_resolves(): void
    {
        foreach (config('home-dashboard.tabs') as $tab) {
            Livewire::test($tab['component'])->assertOk();
        }
    }
}
