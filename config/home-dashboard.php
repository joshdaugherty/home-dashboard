<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    | The dashboard is a single full-page Livewire component. Set `enabled` to
    | false to register your own route to JoshDaugherty\HomeDashboard\Livewire\Shell.
    | The host app owns authentication; the middleware below is applied as-is.
    */
    'route' => [
        'enabled' => true,
        'prefix' => env('DASHBOARD_PREFIX', ''),
        'name' => 'home-dashboard.index',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kiosk behaviour (seconds)
    |--------------------------------------------------------------------------
    */
    'title' => env('DASHBOARD_TITLE', 'Home'),
    'home_tab' => 'today',
    'idle_return_after' => (int) env('DASHBOARD_IDLE_RETURN', 60),
    'ambient_after' => (int) env('DASHBOARD_AMBIENT_AFTER', 120),

    /*
    |--------------------------------------------------------------------------
    | Auto-reload after a deploy
    |--------------------------------------------------------------------------
    | The shell asks /version every `version_check_every` seconds (0 disables) and
    | reloads itself, only while idle, when the answer changes. The default version
    | is derived from the built assets, composer.lock and bootstrap/app.php; set
    | DASHBOARD_VERSION to any string to control it yourself.
    */
    'version' => env('DASHBOARD_VERSION'),
    'version_check_every' => (int) env('DASHBOARD_VERSION_CHECK', 120),

    /*
    |--------------------------------------------------------------------------
    | Tabs
    |--------------------------------------------------------------------------
    | Order = display order. `component` is a Livewire component name; point any
    | tab at your own component, remove tabs, or add new ones. `icon` must be a
    | key in resources/views/components/icon.blade.php (or publish the views).
    */
    'tabs' => [
        'today' => ['label' => 'Today', 'icon' => 'sun', 'component' => 'home-dashboard.tabs.today'],
        // `params` are passed to the component: `only` / `except` filter by calendar category.
        'calendar' => ['label' => 'Calendar', 'icon' => 'calendar', 'component' => 'home-dashboard.tabs.calendar', 'params' => ['except' => ['Class Schedule', 'Assignments']]],
        'classes' => ['label' => 'Class Schedules', 'icon' => 'book', 'component' => 'home-dashboard.tabs.calendar', 'params' => ['only' => ['Class Schedule']]],
        'assignments' => ['label' => 'Assignments', 'icon' => 'check', 'component' => 'home-dashboard.tabs.calendar', 'params' => ['only' => ['Assignments']]],
        'shopping' => ['label' => 'Shopping', 'icon' => 'cart', 'component' => 'home-dashboard.tabs.shopping'],
    ],

    /*
    |--------------------------------------------------------------------------
    |    /*
    |--------------------------------------------------------------------------
    | Location, units and data sources
    |--------------------------------------------------------------------------
    | Weather comes from Open-Meteo (no API key). Calendars are read-only iCal
    | feeds fetched server-side; the URLs never reach the browser. `calendars`
    | is an array, or a one-line JSON string such as the DASHBOARD_CALENDARS env:
    |   [{"name":"Family","color":"sky","url":"https://..."}]
    */
    'timezone' => env('DASHBOARD_TIMEZONE', 'UTC'),
    'units' => env('DASHBOARD_UNITS', 'imperial'), // imperial or metric
    'location' => [
        'latitude' => env('DASHBOARD_LATITUDE'),
        'longitude' => env('DASHBOARD_LONGITUDE'),
    ],
    'calendars' => env('DASHBOARD_CALENDARS'),
    // The household, for avatars: [{"name":"Josh","email":"josh@example.com"}]. Calendars refer to a person by "person".
    'people' => env('DASHBOARD_PEOPLE'),
    // Calendar categories, shown as tabs on the Calendar page. Each calendar belongs to one;
    // the first is the default. Only `today_categories` feed the "Up next" list on Today.
    'categories' => ['Personal', 'Class Schedule', 'Assignments'],
    'today_categories' => ['Personal'],
    // A public-domain painting (Art Institute of Chicago), changing hourly, or a NASA picture behind the Today tab.
    'background' => [
        'enabled' => (bool) env('DASHBOARD_BACKGROUND', true),
        // Optional: with a NASA key (https://api.nasa.gov) the Astronomy Picture of the Day takes every other hour.
        'nasa_key' => env('DASHBOARD_NASA_KEY'),
    ],
    'cache' => [
        'weather' => 600,  // seconds
        'calendar' => 600,
    ],
    // Where the Home tab's Settings link goes. Point it at your own route if you embed the form in the host app's settings.
    'settings_route' => 'home-dashboard.settings',
    'refresh' => (int) env('DASHBOARD_REFRESH', 300), // seconds between background re-renders

    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    | Passed to @vite() in the kiosk layout.
    */
    'vite' => ['resources/css/app.css', 'resources/js/app.js'],

    /*
    |--------------------------------------------------------------------------
    | Dashboard user (for `php artisan home-dashboard:user`)
    |--------------------------------------------------------------------------
    | Environment is the source of truth: the command creates or updates this
    | user on every run, and does nothing if the variables are not set.
    */
    'user' => [
        'name' => env('DASHBOARD_USER_NAME', 'Family'),
        'email' => env('DASHBOARD_USER_EMAIL'),
        'password' => env('DASHBOARD_USER_PASSWORD'),
    ],
];
