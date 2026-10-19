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
    public function configured(): bool
    {
        return is_numeric(config('home-dashboard.location.latitude'))
            && is_numeric(config('home-dashboard.location.longitude'));
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
            config('home-dashboard.location'),
            config('home-dashboard.units'),
            config('home-dashboard.timezone'),
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
     * @return array{current: array<string, mixed>, daily: list<array<string, mixed>>, unit: string}
     */
    private function fetch(): array
    {
        $metric = config('home-dashboard.units') === 'metric';

        $json = Http::timeout(8)->retry(1, 200)->get('https://api.open-meteo.com/v1/forecast', [
            'latitude' => config('home-dashboard.location.latitude'),
            'longitude' => config('home-dashboard.location.longitude'),
            'timezone' => config('home-dashboard.timezone'),
            'current' => 'temperature_2m,apparent_temperature,weather_code,is_day',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
            'forecast_days' => 5,
            'temperature_unit' => $metric ? 'celsius' : 'fahrenheit',
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
            'current' => [
                'temp' => (int) round($current['temperature_2m']),
                'feels' => (int) round($current['apparent_temperature']),
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
