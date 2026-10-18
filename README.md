# home-dashboard

A responsive, touch-first family dashboard kiosk for a wall-mounted photo frame, built as a Laravel package on Livewire 4 and the free tier of Flux UI. It fills whatever viewport it is given: no fixed pixel size and no page scroll. A bottom tab bar switches panes (Today, Calendar, Chores, Shopping, Photos, Home). After 60 s idle it returns to Today, and after 120 s it shows a dim ambient clock.

Requires PHP 8.3+, Laravel 13, Livewire 4.1+ and Flux 2.13+ (free).

**Status:** the shell and tab stubs are in place. The tab panes are placeholders. This package has not been booted or tested yet.

## Install into a Laravel app

The host app should be built on the Livewire starter kit (Tailwind 4, Flux, Vite). It owns authentication.

```bash
composer require joshdaugherty/home-dashboard
php artisan home-dashboard:install   # publishes config, adds the Tailwind @source line
npm run build
```

If the package is not on Packagist, add this to the app's `composer.json` first:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/joshdaugherty/home-dashboard" }]
```

then `composer require joshdaugherty/home-dashboard:dev-main`.

The dashboard is served at `/` by default (`DASHBOARD_PREFIX` to change it) behind the `web` and `auth` middleware. Remove any existing `/` route in the app.

## Single-user login for the frame

No public registration is needed. Set these env vars and run `php artisan home-dashboard:user` on each deploy; it creates or updates the user and does nothing if they are unset:

```
DASHBOARD_USER_NAME=Family
DASHBOARD_USER_EMAIL=frame@example.com
DASHBOARD_USER_PASSWORD=...
```

For a frame that stays signed in, use `SESSION_DRIVER=database` and a long `SESSION_LIFETIME` (minutes).

## Configuration

`config/home-dashboard.php`: route and middleware, idle and ambient timers, the tab list (swap in your own Livewire components, add or remove tabs), the Vite entry points, and the user env mapping.
