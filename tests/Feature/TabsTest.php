<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use JoshDaugherty\HomeDashboard\Livewire\Tabs\Calendar;
use JoshDaugherty\HomeDashboard\Livewire\Tabs\Today;
use JoshDaugherty\HomeDashboard\Support\Weather;
use JoshDaugherty\HomeDashboard\Tests\TestCase;
use Livewire\Livewire;

class TabsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('home-dashboard.timezone', 'America/Chicago');
        $app['config']->set('home-dashboard.location', ['latitude' => '41.88', 'longitude' => '-87.63']);
        $app['config']->set('home-dashboard.calendars', json_encode([
            ['name' => 'Family', 'url' => 'https://calendar.example.com/s/basic.ics'],
        ]));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-19 12:00', 'America/Chicago'));

        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => ['temperature_2m' => 61.6, 'apparent_temperature' => 58.2, 'weather_code' => 2, 'is_day' => 1],
                'daily' => [
                    'time' => ['2026-10-19', '2026-10-20'],
                    'weather_code' => [2, 61],
                    'temperature_2m_max' => [66.4, 55.0],
                    'temperature_2m_min' => [48.0, 41.2],
                    'precipitation_probability_max' => [10, 70],
                ],
            ]),
            'calendar.example.com/*' => Http::response(
                "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//t//EN\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20261001T000000Z\r\n".
                "DTSTART;TZID=America/Chicago:20261020T150000\r\nDTEND;TZID=America/Chicago:20261020T160000\r\nSUMMARY:Dentist\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
            ),
        ]);
    }

    public function test_weather_is_parsed_and_rounded(): void
    {
        $forecast = app(Weather::class)->forecast();

        $this->assertSame(62, $forecast['current']['temp']);
        $this->assertSame('Partly cloudy', $forecast['current']['label']);
        $this->assertSame('°F', $forecast['unit']);
        $this->assertSame(['Today', 'Tue'], array_column($forecast['daily'], 'name'));
        $this->assertSame(66, $forecast['daily'][0]['high']);
    }

    public function test_weather_is_null_when_not_configured(): void
    {
        config(['home-dashboard.location' => ['latitude' => null, 'longitude' => null]]);

        $this->assertNull(app(Weather::class)->forecast());
    }

    public function test_weather_failure_degrades_to_null_without_throwing(): void
    {
        // Fakes stack with the first match winning, so start from a fresh client.
        Http::swap($http = new Factory);
        $http->fake(['api.open-meteo.com/*' => Http::response('boom', 500)]);

        $this->assertNull(app(Weather::class)->forecast());
    }

    public function test_today_tab_renders_weather_and_up_next(): void
    {
        Livewire::test(Today::class)
            ->assertSee('62°F')
            ->assertSee('Partly cloudy')
            ->assertSee('Dentist')
            ->assertSee('Tomorrow');
    }

    public function test_today_tab_explains_missing_configuration(): void
    {
        config(['home-dashboard.location' => ['latitude' => null, 'longitude' => null], 'home-dashboard.calendars' => '{bad']);

        Livewire::test(Today::class)
            ->assertSee('Set DASHBOARD_LATITUDE')
            ->assertSee('DASHBOARD_CALENDARS is not valid JSON.');
    }

    public function test_calendar_tab_embeds_the_days_but_never_the_feed_url(): void
    {
        $component = Livewire::test(Calendar::class)->assertSee('Month')->assertSee('Dentist', false);

        $this->assertStringNotContainsString('calendar.example.com', $component->html());
    }

    public function test_calendar_tab_prompts_when_unconfigured(): void
    {
        config(['home-dashboard.calendars' => null]);

        Livewire::test(Calendar::class)->assertSee('Set DASHBOARD_CALENDARS');
    }
}
