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

    /** A day-of decision: this meal is tonight's. */
    public function makeToday(string $id, Groceries $groceries): void
    {
        $groceries->makeToday($id);
    }

    /** The day whose meal picker is open. */
    public ?string $picking = null;

    public function pick(string $date): void
    {
        $this->picking = $this->picking === $date ? null : $date;
    }

    public function assign(string $id, string $date, Groceries $groceries): void
    {
        $groceries->assign($id, $date);
        $this->picking = null;
    }

    public function clear(string $id, Groceries $groceries): void
    {
        $groceries->clear($id);
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
