<?php

use Illuminate\Support\Facades\Route;
use JoshDaugherty\HomeDashboard\Livewire\Shell;

Route::middleware(config('home-dashboard.route.middleware', ['web']))
    ->prefix(config('home-dashboard.route.prefix', ''))
    ->group(function () {
        Route::get('/', Shell::class)->name(config('home-dashboard.route.name', 'home-dashboard.index'));
    });
