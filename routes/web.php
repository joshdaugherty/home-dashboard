<?php

use Illuminate\Support\Facades\Route;
use JoshDaugherty\HomeDashboard\Http\ManifestController;
use JoshDaugherty\HomeDashboard\Livewire\Shell;

Route::middleware('web')
    ->prefix(config('home-dashboard.route.prefix', ''))
    ->group(function () {
        Route::get('/manifest.webmanifest', ManifestController::class)
            ->name('home-dashboard.manifest');
    });

Route::middleware(config('home-dashboard.route.middleware', ['web']))
    ->prefix(config('home-dashboard.route.prefix', ''))
    ->group(function () {
        Route::get('/', Shell::class)->name(config('home-dashboard.route.name', 'home-dashboard.index'));
    });
