<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use JoshDaugherty\HomeDashboard\Livewire\SettingsPage;
use JoshDaugherty\HomeDashboard\Support\Settings;
use JoshDaugherty\HomeDashboard\Tests\TestCase;
use Livewire\Livewire;

class SettingsPageTest extends TestCase
{
    private const URL = 'https://calendar.example.com/secret-token-123/basic.ics';

    private const ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//t//EN\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20261001T000000Z\r\n"
        ."DTSTART;VALUE=DATE:20261020\r\nDTEND;VALUE=DATE:20261021\r\nSUMMARY:Thing\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('home-dashboard.timezone', 'America/Chicago');
    }

    public function test_page_renders_for_the_route(): void
    {
        $this->get('/dashboard-settings')->assertOk()->assertSee('Calendars')->assertSee('Location and weather');
    }

    public function test_full_page_has_a_heading_and_back_link_but_the_embedded_form_does_not(): void
    {
        $this->get('/dashboard-settings')->assertSee('Back to dashboard');

        Livewire::test(SettingsPage::class, ['embedded' => true])
            ->assertSee('Calendars')
            ->assertDontSee('Back to dashboard')
            ->assertDontSee('overflow-y-auto');
    }

    public function test_the_form_can_be_embedded_by_alias_in_a_host_page(): void
    {
        Livewire::test('home-dashboard.settings-form', ['embedded' => true])
            ->assertSee('Location and weather')
            ->call('addFeed')
            ->assertSet('feeds.0.name', '');
    }

    public function test_home_tab_settings_link_follows_the_configured_route(): void
    {
        config(['home-dashboard.settings_route' => 'home-dashboard.settings']);

        Livewire::test(\JoshDaugherty\HomeDashboard\Livewire\Tabs\Home::class)->assertSee('/dashboard-settings', false);
    }

    public function test_adding_a_calendar_saves_it_encrypted_and_selects_the_color(): void
    {
        Livewire::test(SettingsPage::class)
            ->call('addFeed')
            ->set('feeds.0.name', 'Family')
            ->set('feeds.0.url', self::URL)
            ->call('save')
            ->assertHasNoErrors();

        $saved = app(Settings::class)->get('calendars');

        $this->assertSame('Family', $saved[0]['name']);
        $this->assertSame(self::URL, $saved[0]['url']);
    }

    public function test_saved_urls_are_never_sent_to_the_browser(): void
    {
        app(Settings::class)->set('calendars', [['id' => 'abc', 'name' => 'Family', 'color' => 'sky', 'url' => self::URL]]);

        $component = Livewire::test(SettingsPage::class)->assertSee('Family');

        $this->assertStringNotContainsString('secret-token-123', $component->html());
        $this->assertStringNotContainsString('secret-token-123', json_encode($component->snapshot));
        $this->assertTrue($component->get('feeds')[0]['saved']);
        $this->assertSame('', $component->get('feeds')[0]['url']);
    }

    public function test_saving_without_retyping_the_url_keeps_the_saved_one(): void
    {
        app(Settings::class)->set('calendars', [['id' => 'abc', 'name' => 'Family', 'color' => 'sky', 'url' => self::URL]]);

        Livewire::test(SettingsPage::class)
            ->set('feeds.0.name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $saved = app(Settings::class)->get('calendars');

        $this->assertSame('Renamed', $saved[0]['name']);
        $this->assertSame(self::URL, $saved[0]['url']);
    }

    public function test_a_new_calendar_requires_an_address(): void
    {
        Livewire::test(SettingsPage::class)
            ->call('addFeed')
            ->set('feeds.0.name', 'No url')
            ->call('save')
            ->assertHasErrors(['feeds.0.url']);

        $this->assertFalse(app(Settings::class)->isSaved('calendars'));
    }

    public function test_validation_rejects_bad_values(): void
    {
        Livewire::test(SettingsPage::class)
            ->call('addFeed')
            ->set('feeds.0.name', '')
            ->set('feeds.0.url', 'ftp://nope')
            ->set('latitude', '123')
            ->set('longitude', '10')
            ->set('timezone', 'Mars/Olympus')
            ->call('save')
            ->assertHasErrors(['feeds.0.name', 'feeds.0.url', 'latitude', 'timezone']);
    }

    public function test_removing_and_reordering_feeds(): void
    {
        app(Settings::class)->set('calendars', [
            ['id' => 'a', 'name' => 'One', 'color' => 'sky', 'url' => self::URL],
            ['id' => 'b', 'name' => 'Two', 'color' => 'rose', 'url' => self::URL.'2'],
        ]);

        $component = Livewire::test(SettingsPage::class)->call('moveFeed', 1, -1);
        $this->assertSame(['b', 'a'], array_column($component->get('feeds'), 'id'));

        $component->call('removeFeed', 0)->call('save');
        $this->assertSame(['a'], array_column(app(Settings::class)->get('calendars'), 'id'));
    }

    public function test_feed_test_reports_success_without_leaking_the_url(): void
    {
        Http::fake(['calendar.example.com/*' => Http::response(self::ICS)]);

        $component = Livewire::test(SettingsPage::class)
            ->call('addFeed')
            ->set('feeds.0.url', self::URL)
            ->call('testFeed', 0);

        $this->assertStringContainsString('Works: 1 events', $component->get('tests')[$component->get('feeds')[0]['id']]);
    }

    public function test_failed_feed_test_never_echoes_the_exception(): void
    {
        Http::swap($http = new Factory);
        $http->fake(['calendar.example.com/*' => Http::response('nope', 500)]);

        $component = Livewire::test(SettingsPage::class)
            ->call('addFeed')
            ->set('feeds.0.url', self::URL)
            ->call('testFeed', 0);

        $message = $component->get('tests')[$component->get('feeds')[0]['id']];

        $this->assertStringContainsString("Couldn't load", $message);
        $this->assertStringNotContainsString('secret-token-123', $message);
    }

    public function test_place_search_fills_coordinates_and_timezone(): void
    {
        Http::fake(['geocoding-api.open-meteo.com/*' => Http::response(['results' => [
            ['name' => 'Conway', 'admin1' => 'Arkansas', 'country' => 'United States', 'latitude' => 35.0887, 'longitude' => -92.4421, 'timezone' => 'America/Chicago'],
        ]])]);

        Livewire::test(SettingsPage::class)
            ->set('placeQuery', 'Conway')
            ->call('searchPlace')
            ->assertSee('Conway, Arkansas, United States')
            ->call('pickPlace', 0)
            ->assertSet('latitude', '35.0887')
            ->assertSet('longitude', '-92.4421')
            ->assertSet('timezone', 'America/Chicago');
    }

    public function test_location_is_saved_and_reset_returns_to_the_environment(): void
    {
        config(['home-dashboard.location' => ['latitude' => '1.5', 'longitude' => '2.5']]);

        Livewire::test(SettingsPage::class)
            ->set('latitude', '35.1')
            ->set('longitude', '-92.4')
            ->set('timezone', 'America/Chicago')
            ->set('units', 'metric')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(Settings::class);
        $this->assertSame('35.1', $settings->get('latitude'));
        $this->assertSame('metric', $settings->get('units'));

        Livewire::test(SettingsPage::class)->call('resetToEnvironment');

        $this->assertSame('1.5', $settings->get('latitude'));
        $this->assertFalse($settings->isSaved('units'));
    }
}
