<?php

namespace JoshDaugherty\HomeDashboard\Livewire\Tabs;

use JoshDaugherty\HomeDashboard\Support\CalendarFeeds;

class Calendar extends Tab
{
    public function render(CalendarFeeds $calendars)
    {
        $events = $calendars->events();

        return view('home-dashboard::livewire.tabs.calendar', [
            'days' => (object) $calendars->byDay(),
            'today' => $calendars->now()->toDateString(),
            'refresh' => $this->refreshSeconds(),
            'configured' => $calendars->feeds()['feeds'] !== [],
            'error' => $events['error'],
            'failed' => $events['failed'],
        ]);
    }
}
