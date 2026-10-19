<?php

namespace JoshDaugherty\HomeDashboard\Support;

use DateTimeZone;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Reads the configured read-only iCal feeds, expands recurring events, and
 * buckets the result by local day. Feed URLs stay on the server: they are only
 * used for the fetch and never appear in errors, logs or cache keys.
 */
class CalendarFeeds
{
    private const PALETTE = [
        'sky' => '#38bdf8', 'amber' => '#fbbf24', 'rose' => '#fb7185', 'emerald' => '#34d399',
        'violet' => '#a78bfa', 'orange' => '#fb923c', 'teal' => '#2dd4bf', 'pink' => '#f472b6',
    ];

    /** @var array<string, mixed> per-instance memo */
    private array $memo = [];

    public function timezone(): string
    {
        return (string) config('home-dashboard.timezone', 'UTC');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /**
     * Parse the `calendars` config (array or JSON string).
     *
     * @return array{feeds: list<array{name: string, color: string, url: string}>, error: string|null}
     */
    public function feeds(): array
    {
        $raw = config('home-dashboard.calendars');

        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return ['feeds' => [], 'error' => null];
            }
            $raw = json_decode($raw, true);
            if (! is_array($raw)) {
                return ['feeds' => [], 'error' => 'DASHBOARD_CALENDARS is not valid JSON.'];
            }
        }

        $feeds = [];
        $colors = array_values(self::PALETTE);

        foreach (array_values($raw ?? []) as $i => $item) {
            $url = is_array($item) ? trim((string) ($item['url'] ?? '')) : null;

            // A blank url or a bare scheme ("https://") is a placeholder: skip it so the
            // other feeds still load while the list is being filled in.
            if ($url === '' || (is_string($url) && preg_match('#^(https?|webcal)://?$#i', $url))) {
                continue;
            }

            if (! is_string($url) || ! preg_match('#^(https?|webcal)://#i', $url)) {
                return ['feeds' => [], 'error' => 'Calendar '.($i + 1).' needs an http(s) or webcal url.'];
            }
            $color = (string) ($item['color'] ?? '');
            $feeds[] = [
                'name' => (string) ($item['name'] ?? 'Calendar '.($i + 1)),
                'color' => self::PALETTE[$color] ?? (preg_match('/^#[0-9a-f]{3,8}$/i', $color) ? $color : $colors[$i % count($colors)]),
                'url' => preg_replace('#^webcal://#i', 'https://', $url),
            ];
        }

        return ['feeds' => $feeds, 'error' => null];
    }

    /**
     * The range of days covered: two months back to six months ahead.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(): array
    {
        $month = $this->now()->startOfMonth();

        return [$month->subMonths(2), $month->addMonths(6)->endOfMonth()];
    }

    /**
     * Normalised events from every feed, sorted by start.
     *
     * @return array{events: list<array<string, mixed>>, failed: list<string>, error: string|null}
     */
    public function events(): array
    {
        if (isset($this->memo['events'])) {
            return $this->memo['events'];
        }

        ['feeds' => $feeds, 'error' => $error] = $this->feeds();
        [$start, $end] = $this->window();

        $events = [];
        $failed = [];

        foreach ($feeds as $feed) {
            $key = 'home-dashboard:ical:'.md5($feed['url'].$start->toDateString().$this->timezone());
            $ttl = (int) config('home-dashboard.cache.calendar', 600);

            $list = Cache::get($key);

            if ($list === null && ! Cache::has($key.':failed')) {
                try {
                    $list = $this->fetch($feed, $start, $end);
                    Cache::put($key, $list, $ttl);
                    Cache::put($key.':stale', $list, now()->addDay());
                } catch (Throwable $e) {
                    // The message is deliberately not logged: HTTP exceptions embed the secret URL.
                    Log::warning('home-dashboard: calendar "'.$feed['name'].'" refresh failed ('.class_basename($e).').');
                    Cache::put($key.':failed', true, 60);
                }
            }

            $list ??= Cache::get($key.':stale');

            if ($list === null) {
                $failed[] = $feed['name'];

                continue;
            }

            array_push($events, ...$list);
        }

        usort($events, fn ($a, $b) => [$a['start'], $a['title']] <=> [$b['start'], $b['title']]);

        return $this->memo['events'] = compact('events', 'failed', 'error');
    }

    /**
     * Events bucketed by local date (Y-m-d), all-day first, then by time.
     * Multi-day events appear on every day they cover.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function byDay(): array
    {
        [$windowStart, $windowEnd] = $this->window();
        $windowStart = Carbon::instance($windowStart)->startOfDay();
        $days = [];

        foreach ($this->events()['events'] as $event) {
            $first = Carbon::parse($event['start'])->startOfDay();
            $end = Carbon::parse($event['end']);

            // All-day and midnight-ending events end exclusively, so their last day is the one before.
            $last = ($event['all_day'] || $end->format('H:i') === '00:00') && $end->gt($first)
                ? $end->copy()->subDay()->startOfDay()
                : $end->copy()->startOfDay();

            for ($day = $first->copy()->max($windowStart)->copy();$day->lte($last) && $day->lte($windowEnd); $day->addDay()) {
                $isFirst = $day->isSameDay($first);
                $days[$day->toDateString()][] = [
                    'title' => $event['title'],
                    'calendar' => $event['calendar'],
                    'color' => $event['color'],
                    'location' => $event['location'],
                    'all_day' => $event['all_day'],
                    'time' => $event['all_day'] ? 'All day' : ($isFirst ? Carbon::parse($event['start'])->format('g:i A') : 'Continues'),
                    'sort' => $event['all_day'] || ! $isFirst ? '00:00' : Carbon::parse($event['start'])->format('H:i'),
                    'ends' => $event['end'],
                ];
            }
        }

        foreach ($days as &$list) {
            usort($list, fn ($a, $b) => [$a['sort'], $a['title']] <=> [$b['sort'], $b['title']]);
        }
        unset($list);

        ksort($days);

        return $days;
    }

    /**
     * The next events from now, each with a friendly day label.
     *
     * @return list<array<string, mixed>>
     */
    public function upcoming(int $limit = 5): array
    {
        $now = $this->now();
        $days = $this->byDay();
        $out = [];

        for ($i = 0; $i < 30 && count($out) < $limit; $i++) {
            $date = $now->addDays($i);

            foreach ($days[$date->toDateString()] ?? [] as $event) {
                // Skip timed events that have already finished today.
                if (! $event['all_day'] && $i === 0 && $event['ends'] < $now->format('Y-m-d H:i:s')) {
                    continue;
                }

                $out[] = $event + ['day' => match ($i) {
                    0 => 'Today',
                    1 => 'Tomorrow',
                    default => $date->format('D, M j'),
                }];

                if (count($out) >= $limit) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array{name: string, color: string, url: string}  $feed
     * @return list<array<string, mixed>>
     */
    private function fetch(array $feed, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $body = Http::timeout(15)->accept('text/calendar')->get($feed['url'])->throw()->body();

        $vcal = Reader::read($body, Reader::OPTION_FORGIVING);

        if (! $vcal instanceof VCalendar) {
            throw new RuntimeException('Not an iCalendar document.');
        }

        $tz = new DateTimeZone($this->timezone());
        $vcal = $vcal->expand($start->toDateTimeImmutable(), $end->toDateTimeImmutable(), $tz);

        $events = [];

        foreach ($vcal->VEVENT ?? [] as $vevent) {
            if (! isset($vevent->DTSTART) || strtoupper((string) ($vevent->STATUS ?? '')) === 'CANCELLED') {
                continue;
            }

            $allDay = ! $vevent->DTSTART->hasTime();

            if ($allDay) {
                $from = CarbonImmutable::instance($vevent->DTSTART->getDateTime())->startOfDay();
                $to = isset($vevent->DTEND)
                    ? CarbonImmutable::instance($vevent->DTEND->getDateTime())->startOfDay()
                    : $from->addDay();
            } else {
                $from = CarbonImmutable::instance($vevent->DTSTART->getDateTime())->setTimezone($tz);
                $to = isset($vevent->DTEND)
                    ? CarbonImmutable::instance($vevent->DTEND->getDateTime())->setTimezone($tz)
                    : $from;
            }

            $events[] = [
                'title' => trim((string) ($vevent->SUMMARY ?? '')) ?: '(No title)',
                'start' => $from->format('Y-m-d H:i:s'),
                'end' => $to->format('Y-m-d H:i:s'),
                'all_day' => $allDay,
                'location' => trim((string) ($vevent->LOCATION ?? '')) ?: null,
                'calendar' => $feed['name'],
                'color' => $feed['color'],
            ];
        }

        return $events;
    }
}
