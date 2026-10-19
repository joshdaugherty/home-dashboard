<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use JoshDaugherty\HomeDashboard\Support\Version;
use JoshDaugherty\HomeDashboard\Tests\TestCase;

class VersionTest extends TestCase
{
    public function test_version_endpoint_returns_the_current_version_uncached(): void
    {
        $response = $this->getJson('/version')->assertOk()->assertJson(['version' => Version::current()]);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_explicit_version_overrides_the_derived_one(): void
    {
        config(['home-dashboard.version' => 'release-42']);

        $this->getJson('/version')->assertJson(['version' => 'release-42']);
    }

    public function test_derived_version_is_stable_and_short(): void
    {
        $this->assertSame(Version::current(), Version::current());
        $this->assertSame(12, strlen(Version::current()));
    }

    public function test_shell_embeds_the_version_and_check_interval(): void
    {
        config(['home-dashboard.version' => 'abc123', 'home-dashboard.version_check_every' => 90]);

        $this->get('/')
            ->assertOk()
            ->assertSee('abc123', false)
            ->assertSee('/version', false)
            ->assertSee('90000', false);
    }

    public function test_checking_can_be_disabled(): void
    {
        config(['home-dashboard.version_check_every' => 0]);

        $this->get('/')->assertOk()->assertDontSee('checkVersion(), ', false);
    }
}
