<?php

namespace JoshDaugherty\HomeDashboard\Livewire\Tabs;

use JoshDaugherty\HomeDashboard\Support\CalendarFeeds;
use JoshDaugherty\HomeDashboard\Support\People;

class Calendar extends Tab
{
    /** @var list<string> Show only these categories (empty = no restriction). */
    public array $only = [];

    /** @var list<string> Hide these categories. */
    public array $except = [];

    public function render(CalendarFeeds $calendars)
    {
        $events = $calendars->events();

        // Filter choices come from the calendars on this tab: someone with no calendar here,
        // or a tab with no shared calendar, gets no button.
        $feeds = array_filter($calendars->feeds()['feeds'], fn (array $feed) => ($this->only === [] || in_array($feed['category'], $this->only, true))
            && ! in_array($feed['category'], $this->except, true));
        $owners = array_column($feeds, 'person');
        $people = array_values(array_filter(
            app(People::class)->all(),
            fn (array $p) => in_array($p['id'], $owners, true),
        ));

        return view('home-dashboard::livewire.tabs.calendar', [
            'days' => (object) $calendars->byDay($this->only, $this->except),
            'people' => array_map(fn (array $p) => ['id' => $p['id'], 'name' => $p['name']], $people),
            'hasShared' => in_array(null, $owners, true),
            'today' => $calendars->now()->toDateString(),
            'refresh' => $this->refreshSeconds(),
            'configured' => $calendars->feeds()['feeds'] !== [],
            'error' => $events['error'],
            'failed' => $events['failed'],
        ]);
    }
}
