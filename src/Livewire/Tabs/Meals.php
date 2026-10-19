<?php

namespace JoshDaugherty\HomeDashboard\Livewire\Tabs;

use JoshDaugherty\HomeDashboard\Support\Groceries;

class Meals extends Tab
{
    /** A day-of decision: this meal is tonight's. */
    public function makeToday(string $id, Groceries $groceries): void
    {
        $groceries->makeToday($id);
    }

    /** The day shown in the lightbox. */
    public ?string $picking = null;

    /** The plan entry (a meal without a day) shown in the lightbox. */
    public ?string $viewing = null;

    public function pick(string $date): void
    {
        $this->picking = $date;
        $this->viewing = null;
    }

    public function view(string $id): void
    {
        $this->viewing = $id;
        $this->picking = null;
    }

    public function close(): void
    {
        $this->picking = null;
        $this->viewing = null;
    }

    public function assign(string $id, string $date, Groceries $groceries): void
    {
        $groceries->assign($id, $date);

        // From a meal's own lightbox, assigning is the end of the job; from a day's, the day now shows the meal.
        if ($this->viewing !== null) {
            $this->close();
        }
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
