<?php

namespace JoshDaugherty\HomeDashboard\Livewire\Tabs;

use Livewire\Component;

/**
 * Base for tab panes. The shell mounts every pane with lazy => on-load, so the
 * page paints immediately and each pane fills in (and refreshes) on its own.
 */
abstract class Tab extends Component
{
    public function placeholder()
    {
        return view('home-dashboard::livewire.tabs.placeholder');
    }

    protected function refreshSeconds(): int
    {
        return max(30, (int) config('home-dashboard.refresh', 300));
    }
}
