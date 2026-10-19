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
            ->assertSee('h-dvh', false)
            ->assertSee('rel="manifest"', false);
    }

    public function test_manifest_requests_fullscreen_landscape(): void
    {
        $response = $this->get('/manifest.webmanifest')->assertOk();

        $this->assertStringContainsString(
            'application/manifest+json',
            (string) $response->headers->get('content-type'),
        );

        $manifest = json_decode($response->getContent(), true);

        $this->assertSame('fullscreen', $manifest['display']);
        $this->assertSame('landscape', $manifest['orientation']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('/manifest-icon-192.png', $manifest['icons'][0]['src']);
        $this->assertSame('192x192', $manifest['icons'][0]['sizes']);

        $this->get('/manifest-icon-192.png')
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_today_has_a_button_to_start_the_idle_screen(): void
    {
        $this->get('/')->assertOk()->assertSee('aria-label="Idle screen"', false)->assertSee('ambient = true', false);
    }
}
