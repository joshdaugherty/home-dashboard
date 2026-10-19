<?php

namespace JoshDaugherty\HomeDashboard\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Current conditions and a short daily forecast from Open-Meteo (no API key).
 * Results are cached; if a refresh fails the last good copy (up to 6 hours old) is served.
 */
class Weather
{
    private function settings(): Settings
    {
        return app(Settings::class);
    }

    public function configured(): bool
    {
        return is_numeric($this->settings()->get('latitude'))
            && is_numeric($this->settings()->get('longitude'));
    }

    /**
     * @return array{current: array<string, mixed>, daily: list<array<string, mixed>>, unit: string}|null
     */
    public function forecast(): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        $key = 'home-dashboard:weather:'.md5(json_encode([
            [$this->settings()->get('latitude'), $this->settings()->get('longitude')],
            $this->settings()->get('units'),
            $this->settings()->get('timezone'),
        ]));

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        // After a failure, wait a minute before hitting the API again and serve the stale copy.
        if (Cache::has($key.':failed')) {
            return Cache::get($key.':stale');
        }

        try {
            $data = $this->fetch();
        } catch (Throwable $e) {
            Log::warning('home-dashboard: weather refresh failed: '.$e->getMessage());
            Cache::put($key.':failed', true, 60);

            return Cache::get($key.':stale');
        }

        Cache::put($key, $data, (int) config('home-dashboard.cache.weather', 600));
        Cache::put($key.':stale', $data, now()->addHours(6));

        return $data;
    }

    /**
     * Active National Weather Service alerts for the location (US only), most severe first.
     * Empty when there are none, outside the US, or when the lookup fails.
     *
     * @return list<array{event: string, severity: string, headline: string}>
     */
    public function alerts(): array
    {
        if (! $this->configured()) {
            return [];
        }

        $lat = round((float) $this->settings()->get('latitude'), 3);
        $lon = round((float) $this->settings()->get('longitude'), 3);

        return Cache::remember('home-dashboard:alerts:'.$lat.','.$lon, 300, function () use ($lat, $lon) {
            try {
                $features = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'home-dashboard (https://github.com/joshdaugherty/home-dashboard)', 'Accept' => 'application/geo+json'])
                    ->get('https://api.weather.gov/alerts/active', ['point' => "{$lat},{$lon}"])
                    ->throw()
                    ->json('features') ?? [];
            } catch (Throwable) {
                return [];
            }

            $rank = ['Extreme' => 0, 'Severe' => 1, 'Moderate' => 2, 'Minor' => 3];

            return collect($features)
                ->map(fn (array $f) => $f['properties'] ?? [])
                ->filter(fn (array $p) => ! empty($p['event']))
                ->map(fn (array $p) => [
                    'event' => (string) $p['event'],
                    'severity' => (string) ($p['severity'] ?? 'Unknown'),
                    'headline' => (string) ($p['headline'] ?? $p['event']),
                ])
                ->sortBy(fn (array $a) => $rank[$a['severity']] ?? 4)
                ->unique('event')
                ->take(3)
                ->values()
                ->all();
        });
    }
    /**
     * The animated NWS radar loop for the nearest radar to the configured location, or null
     * outside the US or when the lookup fails. The station rarely changes, so it is cached for a month.
     */
    public function radarUrl(): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        $lat = round((float) $this->settings()->get('latitude'), 3);
        $lon = round((float) $this->settings()->get('longitude'), 3);
        $key = 'home-dashboard:radar:'.$lat.','.$lon;

        $station = Cache::get($key);

        if ($station === null && ! Cache::has($key.':failed')) {
            try {
                $station = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'home-dashboard (https://github.com/joshdaugherty/home-dashboard)'])
                    ->get("https://api.weather.gov/points/{$lat},{$lon}")
                    ->throw()
                    ->json('properties.radarStation');
            } catch (Throwable) {
                $station = null;
            }

            if (is_string($station) && preg_match('/^[A-Z]{4}$/', $station)) {
                Cache::put($key, $station, now()->addMonth());
            } else {
                $station = null;
                Cache::put($key.':failed', true, 3600);
            }
        }

        return $station ? "https://radar.weather.gov/ridge/standard/{$station}_loop.gif" : null;
    }

    /**
     * @return array{current: array<string, mixed>, daily: list<array<string, mixed>>, unit: string}
     */
    private function fetch(): array
    {
        $metric = $this->settings()->get('units') === 'metric';

        $json = Http::timeout(8)->retry(1, 200)->get('https://api.open-meteo.com/v1/forecast', [
            'latitude' => $this->settings()->get('latitude'),
            'longitude' => $this->settings()->get('longitude'),
            'timezone' => $this->settings()->get('timezone'),
            'current' => 'temperature_2m,apparent_temperature,weather_code,is_day,relative_humidity_2m,wind_speed_10m',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
            'forecast_days' => 5,
            'temperature_unit' => $metric ? 'celsius' : 'fahrenheit',
            'wind_speed_unit' => $metric ? 'kmh' : 'mph',
        ])->throw()->json();

        $current = $json['current'] ?? null;
        $daily = $json['daily'] ?? null;

        if (! is_array($current) || ! is_array($daily) || ! isset($current['temperature_2m'])) {
            throw new RuntimeException('Unexpected Open-Meteo response.');
        }

        [$label, $icon] = self::describe((int) $current['weather_code'], (bool) ($current['is_day'] ?? 1));

        $days = [];
        foreach ($daily['time'] ?? [] as $i => $date) {
            [$dayLabel, $dayIcon] = self::describe((int) $daily['weather_code'][$i]);
            $days[] = [
                'name' => $i === 0 ? 'Today' : Carbon::parse($date)->format('D'),
                'high' => (int) round($daily['temperature_2m_max'][$i]),
                'low' => (int) round($daily['temperature_2m_min'][$i]),
                'precip' => $daily['precipitation_probability_max'][$i] ?? null,
                'label' => $dayLabel,
                'icon' => $dayIcon,
            ];
        }

        return [
            'unit' => $metric ? '°C' : '°F',
            'wind_unit' => $metric ? 'km/h' : 'mph',
            'current' => [
                'temp' => (int) round($current['temperature_2m']),
                'feels' => (int) round($current['apparent_temperature']),
                'humidity' => isset($current['relative_humidity_2m']) ? (int) round($current['relative_humidity_2m']) : null,
                'wind' => isset($current['wind_speed_10m']) ? (int) round($current['wind_speed_10m']) : null,
                // Today's chance of rain: the day's highest hourly chance.
                'rain' => $days[0]['precip'] ?? null,
                'label' => $label,
                'icon' => $icon,
            ],
            'daily' => $days,
        ];
    }

    /**
     * Map a WMO weather code to [label, emoji].
     *
     * @return array{0: string, 1: string}
     */
    public static function describe(int $code, bool $day = true): array
    {
        return match (true) {
            $code === 0 => ['Clear', $day ? '☀️' : '🌙'],
            $code === 1 => ['Mostly clear', $day ? '🌤️' : '🌙'],
            $code === 2 => ['Partly cloudy', '⛅'],
            $code === 3 => ['Overcast', '☁️'],
            $code === 45 || $code === 48 => ['Fog', '🌫️'],
            $code >= 51 && $code <= 57 => ['Drizzle', '🌦️'],
            $code >= 61 && $code <= 67 => ['Rain', '🌧️'],
            $code >= 71 && $code <= 77 => ['Snow', '❄️'],
            $code >= 80 && $code <= 82 => ['Showers', '🌧️'],
            $code === 85 || $code === 86 => ['Snow showers', '🌨️'],
            $code >= 95 => ['Thunderstorm', '⛈️'],
            default => ['Unknown', '❔'],
        };
    }
}
