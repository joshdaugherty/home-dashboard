<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JoshDaugherty\HomeDashboard\Support\CalendarFeeds;
use JoshDaugherty\HomeDashboard\Tests\TestCase;

class CalendarFeedsTest extends TestCase
{
    private const ICS = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//test//EN
BEGIN:VEVENT
UID:dentist
DTSTAMP:20261001T000000Z
DTSTART;TZID=America/Chicago:20261020T150000
DTEND;TZID=America/Chicago:20261020T160000
SUMMARY:Dentist
LOCATION:Main St
END:VEVENT
BEGIN:VEVENT
UID:holiday
DTSTAMP:20261001T000000Z
DTSTART;VALUE=DATE:20261022
DTEND;VALUE=DATE:20261023
SUMMARY:Holiday
END:VEVENT
BEGIN:VEVENT
UID:standup
DTSTAMP:20261001T000000Z
DTSTART:20261019T140000Z
DTEND:20261019T143000Z
RRULE:FREQ=WEEKLY;COUNT=3
SUMMARY:Standup
END:VEVENT
BEGIN:VEVENT
UID:cancelled
DTSTAMP:20261001T000000Z
DTSTART:20261021T140000Z
DTEND:20261021T150000Z
STATUS:CANCELLED
SUMMARY:Cancelled thing
END:VEVENT
BEGIN:VEVENT
UID:trip
DTSTAMP:20261001T000000Z
DTSTART;VALUE=DATE:20261024
DTEND;VALUE=DATE:20261027
SUMMARY:Trip
END:VEVENT
END:VCALENDAR
ICS;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('home-dashboard.timezone', 'America/Chicago');
        $app['config']->set('home-dashboard.calendars', json_encode([
            ['name' => 'Family', 'color' => 'sky', 'url' => 'https://calendar.example.com/secret-token-123/basic.ics'],
        ]));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-19 12:00', 'America/Chicago'));
        Http::fake(['calendar.example.com/*' => Http::response(self::ICS)]);
    }

    public function test_timed_all_day_recurring_and_multi_day_events_land_on_the_right_days(): void
    {
        $days = app(CalendarFeeds::class)->byDay();

        $this->assertSame('3:00 PM', $days['2026-10-20'][0]['time']);
        $this->assertSame('Dentist', $days['2026-10-20'][0]['title']);
        $this->assertSame('Main St', $days['2026-10-20'][0]['location']);

        $this->assertSame('All day', $days['2026-10-22'][0]['time']);

        // The UTC 14:00 standup is 9:00 AM Chicago time, weekly for three weeks.
        $this->assertSame('9:00 AM', $days['2026-10-19'][0]['time']);
        $this->assertContains('Standup', array_column($days['2026-10-26'], 'title'));
        $this->assertContains('Standup', array_column($days['2026-11-02'], 'title'));
        // All-day entries sort ahead of timed ones.
        $this->assertSame(['Trip', 'Standup'], array_column($days['2026-10-26'], 'title'));
        $this->assertArrayNotHasKey('2026-11-09', $days);

        // A 3-day all-day event with an exclusive DTEND covers Sat, Sun and Mon only.
        foreach (['2026-10-24', '2026-10-25', '2026-10-26'] as $day) {
            $this->assertContains('Trip', array_column($days[$day], 'title'));
        }
        $this->assertNotContains('Trip', array_column($days['2026-10-27'] ?? [], 'title'));
    }

    public function test_cancelled_events_are_skipped(): void
    {
        $titles = array_column(array_merge(...array_values(app(CalendarFeeds::class)->byDay())), 'title');

        $this->assertNotContains('Cancelled thing', $titles);
    }

    public function test_events_carry_the_feed_name_and_resolved_color(): void
    {
        $event = app(CalendarFeeds::class)->byDay()['2026-10-20'][0];

        $this->assertSame('Family', $event['calendar']);
        $this->assertSame('#38bdf8', $event['color']);
    }

    public function test_upcoming_skips_finished_events_and_labels_days(): void
    {
        $upcoming = app(CalendarFeeds::class)->upcoming(3);

        // The 9:00 standup is already over at noon on the 19th.
        $this->assertSame('Dentist', $upcoming[0]['title']);
        $this->assertSame('Tomorrow', $upcoming[0]['day']);
        $this->assertSame('Holiday', $upcoming[1]['title']);
    }

    public function test_feed_is_fetched_once_then_served_from_cache(): void
    {
        app(CalendarFeeds::class)->events();
        app(CalendarFeeds::class)->events();

        Http::assertSentCount(1);
    }

    public function test_failed_refresh_serves_the_stale_copy_and_never_logs_the_secret_url(): void
    {
        Log::spy();
        app(CalendarFeeds::class)->events();

        // Expire the fresh copy but keep the 24h stale one, then make the feed fail.
        $this->travel(11)->minutes();
        Http::swap($http = new Factory);
        $http->fake(['calendar.example.com/*' => Http::response('nope', 500)]);

        $result = app(CalendarFeeds::class)->events();

        $this->assertNotEmpty($result['events']);
        $this->assertSame([], $result['failed']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => ! str_contains($message, 'secret-token-123'));
    }

    public function test_a_feed_that_has_never_loaded_is_reported_by_name(): void
    {
        Http::swap($http = new Factory);
        $http->fake(['calendar.example.com/*' => Http::response('nope', 500)]);

        $result = app(CalendarFeeds::class)->events();

        $this->assertSame([], $result['events']);
        $this->assertSame(['Family'], $result['failed']);
    }

    public function test_webcal_urls_are_rewritten_and_bad_config_is_reported(): void
    {
        config(['home-dashboard.calendars' => [['url' => 'webcal://calendar.example.com/x.ics']]]);
        $feeds = (new CalendarFeeds)->feeds();
        $this->assertSame('https://calendar.example.com/x.ics', $feeds['feeds'][0]['url']);
        $this->assertSame('Calendar 1', $feeds['feeds'][0]['name']);

        config(['home-dashboard.calendars' => '{not json']);
        $this->assertSame('DASHBOARD_CALENDARS is not valid JSON.', (new CalendarFeeds)->feeds()['error']);

        config(['home-dashboard.calendars' => [['name' => 'Wrong scheme', 'url' => 'ftp://example.com/x.ics']]]);
        $this->assertStringContainsString('needs an http(s) or webcal url', (new CalendarFeeds)->feeds()['error']);

        config(['home-dashboard.calendars' => null]);
        $this->assertSame([], (new CalendarFeeds)->feeds()['feeds']);
    }

    public function test_placeholder_entries_with_blank_or_bare_scheme_urls_are_skipped(): void
    {
        config(['home-dashboard.calendars' => json_encode([
            ['name' => 'Real', 'url' => 'https://calendar.example.com/real.ics'],
            ['name' => 'Blank', 'url' => ''],
            ['name' => 'Bare scheme', 'url' => 'https://'],
            ['name' => 'Missing url'],
        ])]);

        $result = (new CalendarFeeds)->feeds();

        $this->assertNull($result['error']);
        $this->assertSame(['Real'], array_column($result['feeds'], 'name'));
    }
}
