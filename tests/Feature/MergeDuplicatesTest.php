<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use JoshDaugherty\HomeDashboard\Support\CalendarFeeds;
use JoshDaugherty\HomeDashboard\Tests\TestCase;

class MergeDuplicatesTest extends TestCase
{
    private function event(string $title, string $calendar, string $color, string $start = '2026-10-20 15:00:00', string $end = '2026-10-20 16:00:00', bool $allDay = false): array
    {
        return ['title' => $title, 'start' => $start, 'end' => $end, 'all_day' => $allDay, 'location' => null, 'calendar' => $calendar, 'color' => $color];
    }

    public function test_the_same_event_on_two_calendars_becomes_one_item_with_a_split_swatch(): void
    {
        $merged = CalendarFeeds::mergeDuplicates([
            $this->event('Dentist', 'Josh', '#38bdf8'),
            $this->event('dentist ', 'Lauren', '#fb7185'),
        ]);

        $this->assertCount(1, $merged);
        $this->assertSame(['Josh', 'Lauren'], $merged[0]['calendars']);
        $this->assertSame('Josh, Lauren', $merged[0]['calendar']);
        $this->assertSame('#38bdf8', $merged[0]['color']);
        $this->assertSame('conic-gradient(#38bdf8 0% 50%, #fb7185 50% 100%)', $merged[0]['swatch']);
    }

    public function test_a_single_calendar_keeps_a_solid_swatch(): void
    {
        $merged = CalendarFeeds::mergeDuplicates([$this->event('Dentist', 'Josh', '#38bdf8')]);

        $this->assertSame('#38bdf8', $merged[0]['swatch']);
        $this->assertSame(['Josh'], $merged[0]['calendars']);
    }

    public function test_events_that_differ_in_time_or_all_day_are_not_merged(): void
    {
        $merged = CalendarFeeds::mergeDuplicates([
            $this->event('Dentist', 'Josh', '#38bdf8'),
            $this->event('Dentist', 'Lauren', '#fb7185', '2026-10-20 16:00:00', '2026-10-20 17:00:00'),
            $this->event('Dentist', 'Auri', '#fbbf24', '2026-10-20 00:00:00', '2026-10-21 00:00:00', true),
        ]);

        $this->assertCount(3, $merged);
    }

    public function test_a_repeat_on_the_same_calendar_does_not_duplicate_it(): void
    {
        $merged = CalendarFeeds::mergeDuplicates([
            $this->event('Dentist', 'Josh', '#38bdf8'),
            $this->event('Dentist', 'Josh', '#38bdf8'),
        ]);

        $this->assertCount(1, $merged);
        $this->assertSame(['Josh'], $merged[0]['calendars']);
    }

    public function test_same_color_calendars_merge_into_a_solid_swatch_with_both_names(): void
    {
        $merged = CalendarFeeds::mergeDuplicates([
            $this->event('Math', 'Josie (Class Schedule)', '#a78bfa'),
            $this->event('Math', 'Josie (Personal)', '#a78bfa'),
        ]);

        $this->assertSame('#a78bfa', $merged[0]['swatch']);
        $this->assertSame('Josie (Class Schedule), Josie (Personal)', $merged[0]['calendar']);
    }

    public function test_shared_events_across_real_feeds_show_once_per_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 12:00', 'America/Chicago'));
        config([
            'cache.default' => 'array',
            'home-dashboard.timezone' => 'America/Chicago',
            'home-dashboard.calendars' => json_encode([
                ['name' => 'Josh', 'color' => 'sky', 'url' => 'https://calendar.example.com/a.ics'],
                ['name' => 'Lauren', 'color' => 'rose', 'url' => 'https://calendar.example.com/b.ics'],
            ]),
        ]);

        Http::fake(['calendar.example.com/*' => Http::response(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//t//EN\r\nBEGIN:VEVENT\r\nUID:shared\r\nDTSTAMP:20261001T000000Z\r\n"
            ."DTSTART;TZID=America/Chicago:20261020T150000\r\nDTEND;TZID=America/Chicago:20261020T160000\r\nSUMMARY:Dentist\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        )]);

        $day = app(CalendarFeeds::class)->byDay()['2026-10-20'];

        $this->assertCount(1, $day);
        $this->assertSame(['Josh', 'Lauren'], $day[0]['calendars']);
        $this->assertStringStartsWith('conic-gradient(', $day[0]['swatch']);
    }
}
