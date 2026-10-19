<?php

namespace JoshDaugherty\HomeDashboard\Livewire\Tabs;

use JoshDaugherty\HomeDashboard\Support\CalendarFeeds;

class Calendar extends Tab
{
    /** @var list<string> Show only these categories (empty = no restriction). */
    public array $only = [];

    /** @var list<string> Hide these categories. */
    public array $except = [];

    public function render(CalendarFeeds $calendars)
    {
        $events = $calendars->events();

        return view('home-dashboard::livewire.tabs.calendar', [
            'days' => (object) $calendars->byDay($this->only, $this->except),
            'people' => array_map(fn (array $p) => ['id' => $p['id'], 'name' => $p['name']], app(\JoshDaugherty\HomeDashboard\Support\People::class)->all()),
            'today' => $calendars->now()->toDateString(),
            'refresh' => $this->refreshSeconds(),
            'configured' => $calendars->feeds()['feeds'] !== [],
            'error' => $events['error'],
            'failed' => $events['failed'],
        ]);
    }
}
