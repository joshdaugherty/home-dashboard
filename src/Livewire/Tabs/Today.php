<?php

namespace JoshDaugherty\HomeDashboard\Livewire\Tabs;

use JoshDaugherty\HomeDashboard\Support\Background;
use JoshDaugherty\HomeDashboard\Support\CalendarFeeds;
use JoshDaugherty\HomeDashboard\Support\Weather;

class Today extends Tab
{
    public function render(CalendarFeeds $calendars, Weather $weather, Background $background)
    {
        $events = $calendars->events();

        return view('home-dashboard::livewire.tabs.today', [
            'timezone' => $calendars->timezone(),
            'background' => $background->current($calendars->now()),
            'refresh' => $this->refreshSeconds(),
            'weatherConfigured' => $weather->configured(),
            'forecast' => $weather->forecast(),
            'upcoming' => $calendars->upcoming(5, (array) config('home-dashboard.today_categories', CalendarFeeds::categories())),
            'calendarError' => $events['error'],
            'calendarsFailed' => $events['failed'],
        ]);
    }
}
