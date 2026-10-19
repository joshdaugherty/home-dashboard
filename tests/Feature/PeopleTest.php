<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Illuminate\Support\Facades\Http;
use JoshDaugherty\HomeDashboard\Livewire\SettingsPage;
use JoshDaugherty\HomeDashboard\Livewire\Tabs\Today;
use JoshDaugherty\HomeDashboard\Support\CalendarFeeds;
use JoshDaugherty\HomeDashboard\Support\People;
use JoshDaugherty\HomeDashboard\Support\Settings;
use JoshDaugherty\HomeDashboard\Tests\TestCase;
use Livewire\Livewire;

class PeopleTest extends TestCase
{
    private const EMAIL = 'Josh@Example.com';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('home-dashboard.timezone', 'America/Chicago');
    }

    public function test_gravatar_url_uses_a_sha256_of_the_lowercased_email_and_404s_when_missing(): void
    {
        $hash = hash('sha256', 'josh@example.com');

        $this->assertSame("https://gravatar.com/avatar/{$hash}?s=128&d=404", People::gravatar(' '.self::EMAIL.' '));
        $this->assertNull(People::gravatar(null));
        $this->assertNull(People::gravatar(''));
    }

    public function test_people_resolve_to_initial_color_and_avatar(): void
    {
        app(Settings::class)->set('people', [
            ['id' => 'josh', 'name' => 'Josh', 'email' => self::EMAIL],
            ['id' => 'auri', 'name' => 'Auri', 'email' => ''],
        ]);

        $resolved = app(People::class)->resolve(['josh' => '#38bdf8', 'auri' => '#fbbf24', 'ghost' => '#000000']);

        $this->assertCount(2, $resolved);
        $this->assertSame('J', $resolved[0]['initial']);
        $this->assertSame('#38bdf8', $resolved[0]['color']);
        $this->assertStringContainsString(hash('sha256', 'josh@example.com'), $resolved[0]['avatar']);
        $this->assertNull($resolved[1]['avatar']);
    }

    public function test_settings_page_saves_people_and_assigns_a_calendar_to_one(): void
    {
        $component = Livewire::test(SettingsPage::class)->call('addPerson');
        $id = $component->get('people.0.id');

        $component
            ->set('people.0.name', 'Josh')
            ->set('people.0.email', self::EMAIL)
            ->call('addFeed')
            ->set('feeds.0.name', 'Josh')
            ->set('feeds.0.url', 'https://calendar.example.com/x/basic.ics')
            ->set('feeds.0.person', $id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($id, app(Settings::class)->get('calendars')[0]['person']);

        $people = app(Settings::class)->get('people');

        $this->assertSame('Josh', $people[0]['name']);
        $this->assertSame('josh@example.com', $people[0]['email']);

        Livewire::test(SettingsPage::class)->assertSet('people.0.name', 'Josh');
    }

    public function test_person_requires_a_name_and_a_valid_email(): void
    {
        Livewire::test(SettingsPage::class)
            ->call('addPerson')
            ->set('people.0.email', 'nope')
            ->call('save')
            ->assertHasErrors(['people.0.name', 'people.0.email']);
    }

    public function test_removing_a_person_clears_them_from_calendars(): void
    {
        $component = Livewire::test(SettingsPage::class)->call('addPerson');
        $id = $component->get('people.0.id');

        $component
            ->set('people.0.name', 'Josh')
            ->call('addFeed')
            ->set('feeds.0.person', $id)
            ->call('removePerson', 0)
            ->assertSet('feeds.0.person', '')
            ->assertSet('people', []);
    }

    public function test_the_page_shows_the_hashed_gravatar_but_never_the_raw_email(): void
    {
        app(Settings::class)->set('people', [['id' => 'josh', 'name' => 'Josh', 'email' => self::EMAIL]]);
        app(Settings::class)->set('calendars', [
            ['id' => 'a', 'name' => 'Josh', 'color' => 'sky', 'url' => 'https://calendar.example.com/x/basic.ics', 'person' => 'josh'],
        ]);

        $start = now('America/Chicago')->addDay()->format('Ymd\THis');
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//t//EN\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20261001T000000Z\r\n"
            ."DTSTART;TZID=America/Chicago:{$start}\r\nDTEND;TZID=America/Chicago:{$start}\r\nSUMMARY:Dentist\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        Http::fake(['calendar.example.com/*' => Http::response($ics)]);

        $html = Livewire::test(Today::class)->html();

        $this->assertStringContainsString('Dentist', $html);
        $this->assertStringContainsString(hash('sha256', 'josh@example.com'), $html);
        $this->assertStringNotContainsStringIgnoringCase('josh@example.com', $html);
    }

    public function test_a_section_only_shows_and_saves_its_own_part(): void
    {
        Livewire::test(SettingsPage::class, ['section' => 'people'])
            ->assertSee('Add a person')
            ->assertDontSee('Add a calendar')
            ->assertDontSee('Find a place')
            ->call('addPerson')
            ->set('people.0.name', 'Josh')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Josh', app(Settings::class)->get('people')[0]['name']);
        $this->assertFalse(app(Settings::class)->isSaved('timezone'));
    }

    public function test_removing_a_person_in_the_people_section_unassigns_saved_calendars(): void
    {
        app(Settings::class)->set('people', [['id' => 'josh', 'name' => 'Josh', 'email' => '']]);
        app(Settings::class)->set('calendars', [
            ['id' => 'a', 'name' => 'Josh', 'color' => 'sky', 'url' => 'https://calendar.example.com/x/basic.ics', 'person' => 'josh'],
        ]);

        Livewire::test(SettingsPage::class, ['section' => 'people'])->call('removePerson', 0)->call('save');

        $calendar = app(Settings::class)->get('calendars')[0];
        $this->assertNull($calendar['person']);
        $this->assertSame('https://calendar.example.com/x/basic.ics', $calendar['url']);
    }
    public function test_a_calendar_takes_its_persons_color_unless_it_has_no_person(): void
    {
        app(Settings::class)->set('people', [['id' => 'josh', 'name' => 'Josh', 'email' => '', 'color' => 'rose']]);
        app(Settings::class)->set('calendars', [
            ['id' => 'a', 'name' => 'Josh', 'color' => 'sky', 'url' => 'https://cal.example.com/a.ics', 'person' => 'josh'],
            ['id' => 'b', 'name' => 'Family', 'color' => 'amber', 'url' => 'https://cal.example.com/b.ics', 'person' => null],
        ]);

        $feeds = app(CalendarFeeds::class)->feeds()['feeds'];

        $this->assertSame(CalendarFeeds::PALETTE['rose'], $feeds[0]['color']);
        $this->assertSame(CalendarFeeds::PALETTE['amber'], $feeds[1]['color']);
    }

    public function test_the_calendar_color_picker_only_shows_without_a_person(): void
    {
        app(Settings::class)->set('people', [['id' => 'josh', 'name' => 'Josh', 'email' => '', 'color' => 'rose']]);
        app(Settings::class)->set('calendars', [
            ['id' => 'a', 'name' => 'Mine', 'color' => 'sky', 'url' => 'https://cal.example.com/a.ics', 'person' => 'josh'],
        ]);

        Livewire::test(SettingsPage::class, ['section' => 'calendars'])
            ->assertDontSee('feeds.0.color', false)
            ->set('feeds.0.person', '')
            ->assertSee('feeds.0.color', false);
    }}
