# home-dashboard

A responsive, touch-first family dashboard kiosk for a wall-mounted photo frame, built as a Laravel package on Livewire 4 and the free tier of Flux UI. It fills whatever viewport it is given: no fixed pixel size and no page scroll. A bottom tab bar switches panes. After 60 s idle it returns to Today, and after 120 s it shows a dim ambient clock. Nothing relies on hover.

Requires PHP 8.3+, Laravel 13, Livewire 4.1+ and Flux 2.13+ (free components only).

**Status: pre-release (0.x).** Expect changes between minor versions.

| Tab | State |
| --- | --- |
| Today | Clock, weather (Open-Meteo) and the next events from your calendars |
| Calendar | Month grid plus a per-day agenda from read-only iCal feeds |
| Chores, Shopping, Photos, Home | Placeholders |

## Install into a Laravel app

The host app should be built on the Livewire starter kit (Tailwind 4, Flux, Vite). It owns authentication and deployment.

```bash
composer require joshdaugherty/home-dashboard
php artisan home-dashboard:install   # publishes config, adds the Tailwind @source line
npm run build
```

The dashboard is served at `/` by default (`DASHBOARD_PREFIX` to change it) behind the `web` and `auth` middleware. Remove any existing `/` route in the app. Set `route.enabled` to `false` in the config to register your own route to `JoshDaugherty\HomeDashboard\Livewire\Shell`.

The layout loads the Vite entries in `config('home-dashboard.vite')` (`resources/css/app.css` and `resources/js/app.js` by default) and hardcodes dark mode.

## Single-user login for the frame

No public registration is needed. Set these env vars and run `php artisan home-dashboard:user` on each deploy; it creates or updates the user and does nothing if they are unset:

```
DASHBOARD_USER_NAME=Family
DASHBOARD_USER_EMAIL=frame@example.com
DASHBOARD_USER_PASSWORD=...
```

For a frame that stays signed in, use `SESSION_DRIVER=database` and a long `SESSION_LIFETIME` (minutes, for example `525600`).

## Weather and calendars

All data is fetched server-side and cached. A failed refresh serves the last good copy, so a flaky network or feed never blanks the screen.

```
DASHBOARD_TIMEZONE=America/Chicago
DASHBOARD_LATITUDE=34.7465
DASHBOARD_LONGITUDE=-92.2896
DASHBOARD_UNITS=imperial            # or metric
DASHBOARD_CALENDARS='[{"name":"Family","color":"sky","url":"https://..."}]'
```

- **Weather** comes from [Open-Meteo](https://open-meteo.com/) and needs no API key.
- **Calendars** are read-only iCal feed URLs (for example Google Calendar's "Secret address in iCal format"). `DASHBOARD_CALENDARS` is a one-line JSON list. Wrap it in single quotes, since the value contains spaces and double quotes. Entries with a blank or bare-scheme `url` are skipped, so placeholders are fine. `webcal://` URLs are accepted.
- **Colors** are `sky`, `amber`, `rose`, `emerald`, `violet`, `orange`, `teal`, `pink`, or any hex value such as `#a3e635`. Feeds without a color get one automatically.
- Feed URLs are credentials. They are used only for the server-side fetch and never appear in rendered HTML, logs or cache keys.

Recurring, all-day and multi-day events are expanded with [sabre/vobject](https://github.com/sabre-io/vobject) and bucketed by day in `DASHBOARD_TIMEZONE`, so the device's own time zone never matters.

## Auto-reload after a deploy

A kiosk never refreshes on its own, so the shell asks an authenticated `/version` endpoint every 120 s and reloads itself, only while nobody is touching the screen, when the answer changes. The version is derived from the built asset manifest, `composer.lock` and the modified time of `bootstrap/app.php`. Set `DASHBOARD_VERSION` to any string to control it yourself, and `DASHBOARD_VERSION_CHECK` to change the interval in seconds (`0` disables the check).

## Configuration

`config/home-dashboard.php` (publish it with `php artisan vendor:publish --tag=home-dashboard-config`) covers the route and middleware, idle and ambient timers, location and data sources, cache lifetimes, the tab list (swap in your own Livewire components, add or remove tabs), the Vite entry points and the user env mapping. The views can be published with `--tag=home-dashboard-views`.

If you publish the config, remember Laravel merges package config one level deep: overriding the `route` array replaces all of it, so include every key you need.

## Testing

```bash
composer install
vendor/bin/phpunit
```

The suite uses Orchestra Testbench with Livewire and Flux.

## License

MIT. See [LICENSE](LICENSE).
