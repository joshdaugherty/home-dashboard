<?php

namespace JoshDaugherty\HomeDashboard\Livewire\Tabs;

use JoshDaugherty\HomeDashboard\Support\Groceries;

class Shopping extends Tab
{
    /** Tap an item to take it off the list. */
    public function tick(string $id, Groceries $groceries): void
    {
        $groceries->tick($id);
    }

    public function render(Groceries $groceries)
    {
        return view('home-dashboard::livewire.tabs.shopping', [
            'refresh' => $this->refreshSeconds(),
            'configured' => $groceries->configured(),
            'groups' => $groceries->shoppingList(),
        ]);
    }
}
