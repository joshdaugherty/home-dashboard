<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Illuminate\Support\Facades\Http;
use JoshDaugherty\HomeDashboard\Livewire\SettingsPage;
use JoshDaugherty\HomeDashboard\Livewire\Tabs\Calendar;
use JoshDaugherty\HomeDashboard\Support\CalendarFeeds;
use JoshDaugherty\HomeDashboard\Support\Settings;
use JoshDaugherty\HomeDashboard\Tests\TestCase;
use Livewire\Livewire;

class CategoriesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('home-dashboard.timezone', 'America/Chicago');
    }

    private function feed(string $id, string $name, ?string $person, ?string $category): array
    {
        return ['id' => $id, 'name' => $name, 'color' => 'sky', 'url' => "https://cal.example.com/{$id}.ics", 'person' => $person] + ($category ? ['category' => $category] : []);
    }

    private function ics(string $title): string
    {
        $day = now('America/Chicago')->addDay()->format('Ymd');

        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//t//EN\r\nBEGIN:VEVENT\r\nUID:{$title}\r\nDTSTAMP:20261001T000000Z\r\nDTSTART;VALUE=DATE:{$day}\r\nSUMMARY:{$title}\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    }

    public function test_calendars_default_to_the_first_category_and_events_carry_category_and_people(): void
    {
        app(Settings::class)->set('people', [['id' => 'josie', 'name' => 'Josie', 'email' => '']]);
        app(Settings::class)->set('calendars', [
            $this->feed('a', 'Family', null, null),
            $this->feed('b', 'Josie (Assignments)', 'josie', 'Assignments'),
            $this->feed('c', 'Bogus', null, 'Nonsense'),
        ]);
        Http::fake([
            'cal.example.com/a.ics' => Http::response($this->ics('Dinner')),
            'cal.example.com/b.ics' => Http::response($this->ics('Essay')),
            'cal.example.com/c.ics' => Http::response($this->ics('Other')),
        ]);

        $byTitle = collect(app(CalendarFeeds::class)->byDay())->flatten(1)->keyBy('title');

        $this->assertSame('Personal', $byTitle['Dinner']['category']);
        $this->assertSame([], $byTitle['Dinner']['person_ids']);
        $this->assertSame('Assignments', $byTitle['Essay']['category']);
        $this->assertSame(['josie'], $byTitle['Essay']['person_ids']);
        $this->assertSame('Personal', $byTitle['Other']['category']);
    }

    public function test_up_next_only_lists_the_configured_categories(): void
    {
        app(Settings::class)->set('calendars', [
            $this->feed('a', 'Family', null, 'Personal'),
            $this->feed('b', 'Homework', null, 'Assignments'),
        ]);
        Http::fake([
            'cal.example.com/a.ics' => Http::response($this->ics('Dinner')),
            'cal.example.com/b.ics' => Http::response($this->ics('Essay')),
        ]);

        $titles = array_column(app(CalendarFeeds::class)->upcoming(10, ['Personal']), 'title');

        $this->assertSame(['Dinner'], $titles);
    }

    public function test_the_calendar_tab_offers_category_and_person_filters(): void
    {
        app(Settings::class)->set('people', [['id' => 'josie', 'name' => 'Josie', 'email' => '']]);
        app(Settings::class)->set('calendars', [$this->feed('a', 'Family', null, 'Personal')]);
        Http::fake(['cal.example.com/*' => Http::response($this->ics('Dinner'))]);

        Livewire::test(Calendar::class)
            ->assertSee('Class Schedule')
            ->assertSee('Assignments')
            ->assertSee('Shared')
            ->assertSee('Josie');
    }

    public function test_the_settings_page_saves_a_calendars_category(): void
    {
        Livewire::test(SettingsPage::class)
            ->call('addFeed')
            ->assertSet('feeds.0.category', 'Personal')
            ->set('feeds.0.name', 'Homework')
            ->set('feeds.0.url', 'https://cal.example.com/h.ics')
            ->set('feeds.0.category', 'Assignments')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Assignments', app(Settings::class)->get('calendars')[0]['category']);

        Livewire::test(SettingsPage::class)
            ->call('addFeed')
            ->set('feeds.1.category', 'Nope')
            ->call('save')
            ->assertHasErrors('feeds.1.category');
    }
}
