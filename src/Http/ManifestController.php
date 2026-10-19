<?php

namespace JoshDaugherty\HomeDashboard\Http;

use Illuminate\Http\Response;

class ManifestController
{
    public function __invoke(): Response
    {
        $prefix = trim((string) config('home-dashboard.route.prefix', ''), '/');
        $start = $prefix === '' ? '/' : '/'.$prefix;
        $title = (string) config('home-dashboard.title', 'Home');

        $manifest = [
            'id' => $start,
            'name' => $title,
            'short_name' => $title,
            'start_url' => $start,
            'scope' => $start,
            'display' => 'fullscreen',
            'orientation' => 'landscape',
            'background_color' => '#09090b',
            'theme_color' => '#09090b',
        ];

        return response(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }
}
