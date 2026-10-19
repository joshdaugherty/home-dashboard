<?php

namespace JoshDaugherty\HomeDashboard\Support;

use JoshDaugherty\HomeDashboard\Models\Setting;
use Throwable;

/**
 * Settings edited on the settings page, with the env-backed config as the
 * fallback. A saved value always wins; with nothing saved the env var applies,
 * so a deploy-time setup keeps working.
 */
class Settings
{
    /** Setting key => config key used when nothing has been saved. */
    public const FALLBACKS = [
        'calendars' => 'home-dashboard.calendars',
        'timezone' => 'home-dashboard.timezone',
        'units' => 'home-dashboard.units',
        'latitude' => 'home-dashboard.location.latitude',
        'longitude' => 'home-dashboard.location.longitude',
    ];

    /** @var array<string, mixed>|null */
    private ?array $stored = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->stored();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        return config(self::FALLBACKS[$key] ?? 'home-dashboard.'.$key, $default);
    }

    public function isSaved(string $key): bool
    {
        return array_key_exists($key, $this->stored());
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => ['v' => $value]]);
        $this->stored = null;
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
        $this->stored = null;
    }

    /**
     * Everything saved so far. A missing table (migrations not run yet) or a row
     * that no longer decrypts (APP_KEY changed) is treated as "not saved".
     *
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        if ($this->stored !== null) {
            return $this->stored;
        }

        $stored = [];

        try {
            foreach (Setting::query()->get() as $row) {
                try {
                    $stored[$row->key] = $row->value['v'] ?? null;
                } catch (Throwable) {
                    // Undecryptable row: fall back to the env value for this key.
                }
            }
        } catch (Throwable) {
            // No settings table yet.
        }

        return $this->stored = $stored;
    }
}
