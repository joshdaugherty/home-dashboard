<?php

namespace JoshDaugherty\HomeDashboard\Livewire;

use JoshDaugherty\HomeDashboard\Support\Version;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The kiosk shell. It holds no server state: tab switching, the idle return
 * to the home tab and ambient mode all run client-side in Alpine.
 */
#[Layout('home-dashboard::layouts.kiosk')]
class Shell extends Component
{
    public function render()
    {
        return view('home-dashboard::livewire.shell', [
            'tabs' => config('home-dashboard.tabs'),
            'homeTab' => config('home-dashboard.home_tab', 'today'),
            'idleAfter' => (int) config('home-dashboard.idle_return_after', 60),
            'ambientAfter' => (int) config('home-dashboard.ambient_after', 120),
            'version' => Version::current(),
            'versionUrl' => route('home-dashboard.version'),
            'versionEvery' => (int) config('home-dashboard.version_check_every', 120),
        ]);
    }
}
