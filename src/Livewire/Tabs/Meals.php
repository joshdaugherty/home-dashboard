<?php

namespace JoshDaugherty\HomeDashboard\Livewire\Tabs;

use JoshDaugherty\HomeDashboard\Support\Groceries;

class Meals extends Tab
{
    /** How many items the last "add to the list" put on it; null until it has been used. */
    public ?int $added = null;

    public function addToList(Groceries $groceries): void
    {
        $this->added = $groceries->addWeekToList();
    }

    public function render(Groceries $groceries)
    {
        return view('home-dashboard::livewire.tabs.meals', [
            'refresh' => $this->refreshSeconds(),
            'configured' => $groceries->configured(),
            'week' => $groceries->week(),
        ]);
    }
}
