<?php

namespace JoshDaugherty\HomeDashboard\Http;

use Illuminate\Http\JsonResponse;
use JoshDaugherty\HomeDashboard\Support\Version;

class VersionController
{
    public function __invoke(): JsonResponse
    {
        // no-store: Cloud's edge cache must never answer for a version probe.
        return response()
            ->json(['version' => Version::current(), 'settings' => Version::settings()])
            ->header('Cache-Control', 'no-store, private');
    }
}
