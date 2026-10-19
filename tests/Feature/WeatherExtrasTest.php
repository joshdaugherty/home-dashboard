<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Illuminate\Support\Facades\Http;
use JoshDaugherty\HomeDashboard\Livewire\Tabs\Today;
use JoshDaugherty\HomeDashboard\Support\Weather;
use JoshDaugherty\HomeDashboard\Tests\TestCase;
use Livewire\Livewire;

class WeatherExtrasTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('home-dashboard.location.latitude', '35.0887');
        $app['config']->set('home-dashboard.location.longitude', '-92.4421');
        $app['config']->set('home-dashboard.timezone', 'America/Chicago');
    }

    private function fakeWeather(array $alerts = []): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => ['temperature_2m' => 72, 'apparent_temperature' => 70, 'weather_code' => 0, 'is_day' => 1, 'relative_humidity_2m' => 64, 'wind_speed_10m' => 8.4],
                'daily' => [
                    'time' => ['2026-10-19', '2026-10-20'],
                    'weather_code' => [0, 61],
                    'temperature_2m_max' => [75, 70],
                    'temperature_2m_min' => [45, 50],
                    'precipitation_probability_max' => [40, 85],
                ],
            ]),
            'api.weather.gov/points/*' => Http::response(['properties' => ['radarStation' => 'KLZK']]),
            'api.weather.gov/alerts/*' => Http::response(['features' => $alerts]),
        ]);
    }

    public function test_current_humidity_wind_and_rain_chance_are_shown(): void
    {
        $this->fakeWeather();

        $forecast = app(Weather::class)->forecast();

        $this->assertSame(64, $forecast['current']['humidity']);
        $this->assertSame(8, $forecast['current']['wind']);
        $this->assertSame(40, $forecast['current']['rain']);

        Livewire::test(Today::class)->assertSee('Humidity 64%')->assertSee('Wind 8 mph')->assertSee('Rain 40%')->assertSee('85%');
    }

    public function test_the_radar_loop_comes_from_the_nearest_nws_station(): void
    {
        $this->fakeWeather();

        $this->assertSame('https://radar.weather.gov/ridge/standard/KLZK_loop.gif', app(Weather::class)->radarUrl());

        Livewire::test(Today::class)->assertSee('KLZK_loop.gif', false)->assertSee('Radar');
    }

    public function test_the_radar_is_hidden_outside_the_us_or_on_failure(): void
    {
        Http::fake(['api.weather.gov/*' => Http::response('nope', 404), 'api.open-meteo.com/*' => Http::response([])]);

        $this->assertNull(app(Weather::class)->radarUrl());
    }

    public function test_active_alerts_show_a_banner_most_severe_first(): void
    {
        $this->fakeWeather([
            ['properties' => ['event' => 'Wind Advisory', 'severity' => 'Minor', 'headline' => 'Wind Advisory until 9 PM']],
            ['properties' => ['event' => 'Tornado Warning', 'severity' => 'Extreme', 'headline' => 'Tornado Warning for Faulkner County']],
        ]);

        $alerts = app(Weather::class)->alerts();

        $this->assertSame(['Tornado Warning', 'Wind Advisory'], array_column($alerts, 'event'));

        Livewire::test(Today::class)->assertSee('Tornado Warning')->assertSee('Faulkner County');
    }

    public function test_no_alerts_means_no_banner(): void
    {
        $this->fakeWeather([]);

        Livewire::test(Today::class)->assertDontSee('⚠');
    }

    public function test_a_forecast_cached_by_an_older_release_is_refetched_instead_of_breaking_the_page(): void
    {
        $this->fakeWeather();
        $old = ['unit' => '°F', 'current' => ['temp' => 70, 'feels' => 69, 'label' => 'Clear', 'icon' => '☀️'], 'daily' => []];
        \Illuminate\Support\Facades\Cache::put('home-dashboard:weather:v2:'.md5(json_encode([['35.0887', '-92.4421'], 'imperial', 'America/Chicago'])), $old, 600);

        Livewire::test(Today::class)->assertOk()->assertSee('Humidity 64%');
    }}
