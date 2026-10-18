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
    | Tabs
    |--------------------------------------------------------------------------
    | Order = display order. `component` is a Livewire component name; point any
    | tab at your own component, remove tabs, or add new ones. `icon` must be a
    | key in resources/views/components/icon.blade.php (or publish the views).
    */
    'tabs' => [
        'today' => ['label' => 'Today', 'icon' => 'sun', 'component' => 'home-dashboard.tabs.today'],
        'calendar' => ['label' => 'Calendar', 'icon' => 'calendar', 'component' => 'home-dashboard.tabs.calendar'],
        'chores' => ['label' => 'Chores', 'icon' => 'check', 'component' => 'home-dashboard.tabs.chores'],
        'shopping' => ['label' => 'Shopping', 'icon' => 'cart', 'component' => 'home-dashboard.tabs.shopping'],
        'photos' => ['label' => 'Photos', 'icon' => 'photo', 'component' => 'home-dashboard.tabs.photos'],
        'home' => ['label' => 'Home', 'icon' => 'home', 'component' => 'home-dashboard.tabs.home'],
    ],

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
