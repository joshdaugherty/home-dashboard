<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use JoshDaugherty\HomeDashboard\Tests\TestCase;

class ShellTest extends TestCase
{
    public function test_dashboard_route_renders_the_shell_with_every_tab(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (config('home-dashboard.tabs') as $tab) {
            $response->assertSee($tab['label']);
        }

        $response->assertSee('Dashboard sections', false);
    }

    public function test_route_name_and_prefix_are_configurable(): void
    {
        $this->assertSame(url('/'), route('home-dashboard.index'));
    }

    public function test_layout_is_a_kiosk_page(): void
    {
        $this->get('/')
            ->assertSee('class="dark h-full"', false)
            ->assertSee('h-dvh', false);
    }
}
