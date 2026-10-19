<?php

namespace JoshDaugherty\HomeDashboard\Support;

/**
 * An opaque string that changes whenever the app is redeployed, so an open page
 * can tell it is stale. Cloud builds a fresh image per deploy, so the build
 * output, the lockfile and the file times all move with it.
 */
class Version
{
    public static function current(): string
    {
        if (is_string($fixed = config('home-dashboard.version')) && $fixed !== '') {
            return $fixed;
        }

        $parts = [];

        foreach ([public_path('build/manifest.json'), base_path('composer.lock')] as $file) {
            $parts[] = is_file($file) ? md5_file($file) : '-';
        }

        $bootstrap = base_path('bootstrap/app.php');
        $parts[] = is_file($bootstrap) ? (string) filemtime($bootstrap) : '-';

        return substr(md5(implode('|', $parts)), 0, 12);
    }
}
