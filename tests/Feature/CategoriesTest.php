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

    public function test_each_calendar_tab_only_shows_its_categories(): void
    {
        app(Settings::class)->set('people', [['id' => 'josie', 'name' => 'Josie', 'email' => '']]);
        app(Settings::class)->set('calendars', [
            $this->feed('a', 'Family', null, 'Personal'),
            $this->feed('b', 'Homework', 'josie', 'Assignments'),
            $this->feed('c', 'Classes', 'josie', 'Class Schedule'),
            $this->feed('d', 'Josie', 'josie', 'Personal'),
        ]);
        Http::fake([
            'cal.example.com/d.ics' => Http::response($this->ics('Piano')),
            'cal.example.com/a.ics' => Http::response($this->ics('Dinner')),
            'cal.example.com/b.ics' => Http::response($this->ics('Essay')),
            'cal.example.com/c.ics' => Http::response($this->ics('Algebra')),
        ]);

        $tabs = config('home-dashboard.tabs');

        Livewire::test(Calendar::class, $tabs['calendar']['params'])->assertSee('Dinner')->assertDontSee('Essay')->assertDontSee('Algebra')->assertSee('Shared')->assertSee('Josie');
        Livewire::test(Calendar::class, $tabs['classes']['params'])->assertSee('Algebra')->assertDontSee('Dinner')->assertDontSee('Essay');
        Livewire::test(Calendar::class, $tabs['assignments']['params'])->assertSee('Essay')->assertDontSee('Dinner')->assertDontSee('Algebra');
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

    public function test_filter_buttons_only_offer_people_and_shared_calendars_that_exist_on_the_tab(): void
    {
        app(Settings::class)->set('people', [
            ['id' => 'josh', 'name' => 'Josh', 'email' => ''],
            ['id' => 'josie', 'name' => 'Josie', 'email' => ''],
            ['id' => 'amelia', 'name' => 'Amelia', 'email' => ''],
        ]);
        app(Settings::class)->set('calendars', [
            $this->feed('a', 'Family', null, 'Personal'),
            $this->feed('b', 'Josh', 'josh', 'Personal'),
            $this->feed('c', 'Josie classes', 'josie', 'Class Schedule'),
            $this->feed('d', 'Amelia classes', 'amelia', 'Class Schedule'),
        ]);
        Http::fake(['cal.example.com/*' => Http::response($this->ics('Thing'))]);

        $tabs = config('home-dashboard.tabs');

        Livewire::test(Calendar::class, $tabs['classes']['params'])
            ->assertSet('only', ['Class Schedule'])
            ->assertViewHas('hasShared', false)
            ->assertViewHas('people', fn ($p) => array_column($p, 'name') === ['Josie', 'Amelia']);

        Livewire::test(Calendar::class, $tabs['calendar']['params'])
            ->assertViewHas('hasShared', true)
            ->assertViewHas('people', fn ($p) => array_column($p, 'name') === ['Josh']);
    }}
