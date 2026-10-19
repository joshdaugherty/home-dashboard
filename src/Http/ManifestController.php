<?php

namespace JoshDaugherty\HomeDashboard\Http;

use Illuminate\Http\Response;

class ManifestController
{
    public function __invoke(): Response
    {
        $prefix = trim((string) config('home-dashboard.route.prefix', ''), '/');
        $start = $prefix === '' ? '/' : '/'.$prefix;
        $base = rtrim($start, '/');
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
            'icons' => [
                ['src' => $base.'/manifest-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $base.'/manifest-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ],
        ];

        return response(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }

    public function icon(string $size): Response
    {
        abort_unless(in_array($size, ['192', '512'], true), 404);

        $path = dirname(__DIR__, 2)."/resources/manifest/icon-{$size}.png";

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'image/png',
        ]);
    }
}
