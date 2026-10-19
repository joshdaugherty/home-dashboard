<?php

use Illuminate\Support\Facades\Route;
use JoshDaugherty\HomeDashboard\Http\ManifestController;
use JoshDaugherty\HomeDashboard\Http\VersionController;
use JoshDaugherty\HomeDashboard\Livewire\SettingsPage;
use JoshDaugherty\HomeDashboard\Livewire\Shell;

Route::middleware('web')
    ->prefix(config('home-dashboard.route.prefix', ''))
    ->group(function () {
        Route::get('/manifest.webmanifest', ManifestController::class)
            ->name('home-dashboard.manifest');
        Route::get('/manifest-icon-{size}.png', [ManifestController::class, 'icon'])
            ->where('size', '192|512')
            ->name('home-dashboard.manifest-icon');
    });

Route::middleware(config('home-dashboard.route.middleware', ['web']))
    ->prefix(config('home-dashboard.route.prefix', ''))
    ->group(function () {
        Route::get('/version', VersionController::class)->name('home-dashboard.version');
        Route::get('/dashboard-settings', SettingsPage::class)->name('home-dashboard.settings');
        Route::get('/', Shell::class)->name(config('home-dashboard.route.name', 'home-dashboard.index'));
    });
