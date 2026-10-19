<?php

namespace JoshDaugherty\HomeDashboard\Livewire;

use DateTimeZone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use JoshDaugherty\HomeDashboard\Support\CalendarFeeds;
use JoshDaugherty\HomeDashboard\Support\People;
use JoshDaugherty\HomeDashboard\Support\Settings;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Edit the calendars and location without touching environment variables.
 *
 * Livewire sends public properties to the browser, so feed URLs are never kept in
 * component state: `feeds.*.url` only holds what was typed this session, and an
 * existing URL is looked up on the server by feed id when saving or testing.
 */
#[Layout('home-dashboard::layouts.kiosk')]
class SettingsPage extends Component
{
    /** @var list<array{id: string, name: string, color: string, url: string, saved: bool, person: string}> */
    public array $feeds = [];

    /** @var list<array{id: string, name: string, email: string}> */
    public array $people = [];

    public string $latitude = '';

    public string $longitude = '';

    public string $timezone = '';

    public string $units = 'imperial';

    public string $placeQuery = '';

    /** @var list<array{label: string, lat: string, lon: string, tz: string}> */
    public array $places = [];

    /** @var array<string, string> feed id => test result */
    public array $tests = [];

    public ?string $status = null;

    /** True when rendered inside a host page (no kiosk chrome, no heading or back link). */
    public bool $embedded = false;

    /** Which part of the settings to show: people, calendars, location, or all of them. */
    public string $section = 'all';

    public function mount(bool $embedded = false, string $section = 'all'): void
    {
        $this->embedded = $embedded;
        $this->section = in_array($section, ['people', 'calendars', 'location'], true) ? $section : 'all';
        $this->load();
    }

    public function addFeed(): void
    {
        $colors = array_keys(CalendarFeeds::PALETTE);

        $this->feeds[] = [
            'id' => (string) Str::uuid(),
            'name' => '',
            'color' => $colors[count($this->feeds) % count($colors)],
            'url' => '',
            'saved' => false,
            'person' => '',
        ];
    }

    public function addPerson(): void
    {
        $this->people[] = ['id' => (string) Str::uuid(), 'name' => '', 'email' => ''];
    }

    public function removePerson(int $index): void
    {
        $id = $this->people[$index]['id'] ?? null;

        unset($this->people[$index]);
        $this->people = array_values($this->people);

        foreach ($this->feeds as $i => $feed) {
            if ($feed['person'] === $id) {
                $this->feeds[$i]['person'] = '';
            }
        }
    }

    public function removeFeed(int $index): void
    {
        unset($this->feeds[$index]);
        $this->feeds = array_values($this->feeds);
    }

    public function moveFeed(int $index, int $by): void
    {
        $to = $index + $by;

        if (! isset($this->feeds[$index], $this->feeds[$to])) {
            return;
        }

        [$this->feeds[$index], $this->feeds[$to]] = [$this->feeds[$to], $this->feeds[$index]];
    }

    public function testFeed(int $index): void
    {
        $feed = $this->feeds[$index] ?? null;

        if (! $feed) {
            return;
        }

        $url = $this->resolveUrl($feed);

        if ($url === null) {
            $this->tests[$feed['id']] = 'Enter a URL first.';

            return;
        }

        try {
            $count = app(CalendarFeeds::class)->probe($url);
            $this->tests[$feed['id']] = "Works: {$count} events in range.";
        } catch (Throwable) {
            // Never echo the exception: HTTP errors embed the secret URL.
            $this->tests[$feed['id']] = "Couldn't load that feed. Check the URL and that it is an iCal (.ics) link.";
        }
    }

    public function searchPlace(): void
    {
        $this->places = [];
        $query = trim($this->placeQuery);

        if (strlen($query) < 2) {
            return;
        }

        try {
            $results = Http::timeout(8)->get('https://geocoding-api.open-meteo.com/v1/search', [
                'name' => $query,
                'count' => 5,
                'language' => 'en',
            ])->throw()->json('results') ?? [];
        } catch (Throwable) {
            $this->status = "Place search is unavailable right now. Enter the coordinates by hand.";

            return;
        }

        foreach ($results as $result) {
            $this->places[] = [
                'label' => collect([$result['name'] ?? null, $result['admin1'] ?? null, $result['country'] ?? null])->filter()->implode(', '),
                'lat' => (string) ($result['latitude'] ?? ''),
                'lon' => (string) ($result['longitude'] ?? ''),
                'tz' => (string) ($result['timezone'] ?? ''),
            ];
        }
    }

    public function pickPlace(int $index): void
    {
        $place = $this->places[$index] ?? null;

        if (! $place) {
            return;
        }

        $this->latitude = $place['lat'];
        $this->longitude = $place['lon'];
        $this->timezone = $place['tz'] ?: $this->timezone;
        $this->places = [];
        $this->placeQuery = $place['label'];
    }

    public function save(Settings $settings, CalendarFeeds $calendars): void
    {
        $rules = [
            'people' => [
                'people.*.name' => ['required', 'string', 'max:60'],
                'people.*.email' => ['nullable', 'email', 'max:190'],
            ],
            'calendars' => [
                'feeds.*.name' => ['required', 'string', 'max:60'],
                'feeds.*.color' => ['required', 'string', 'regex:/^(#[0-9a-fA-F]{3,8}|'.implode('|', array_keys(CalendarFeeds::PALETTE)).')$/'],
                'feeds.*.person' => ['nullable', 'string'],
                'feeds.*.url' => ['nullable', 'string', 'max:2000', 'regex:#^(https?|webcal)://[^\s]+$#i'],
            ],
            'location' => [
                'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
                'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
                'units' => ['required', Rule::in(['imperial', 'metric'])],
            ],
        ];

        $parts = $this->section === 'all' ? array_keys($rules) : [$this->section];

        $this->validate(array_merge(...array_map(fn (string $part) => $rules[$part], $parts)), [
            'feeds.*.url.regex' => 'Enter a full http(s) or webcal address.',
            'timezone.in' => 'Use a time zone name like America/Chicago.',
        ]);
        if (in_array('calendars', $parts, true)) {
            $existing = collect($calendars->feeds()['feeds'])->keyBy('id');
            $ids = array_column($this->people, 'id');
            $list = [];

            foreach ($this->feeds as $i => $feed) {
                $url = trim($feed['url']) !== '' ? trim($feed['url']) : ($existing[$feed['id']]['url'] ?? null);

                if ($url === null) {
                    $this->addError("feeds.{$i}.url", 'Add the calendar address.');

                    return;
                }

                $list[] = ['id' => $feed['id'], 'name' => trim($feed['name']), 'color' => $feed['color'], 'url' => $url, 'person' => in_array($feed['person'], $ids, true) ? $feed['person'] : null];
            }

            $settings->set('calendars', $list);
        }

        if (in_array('people', $parts, true)) {
            $settings->set('people', array_map(fn (array $p) => ['id' => $p['id'], 'name' => trim($p['name']), 'email' => strtolower(trim($p['email']))], $this->people));

            // Saving people alone must not leave calendars pointing at someone who was removed.
            if (! in_array('calendars', $parts, true) && $settings->isSaved('calendars')) {
                $ids = array_column($this->people, 'id');
                $settings->set('calendars', array_map(
                    fn (array $c) => ['person' => in_array($c['person'] ?? null, $ids, true) ? $c['person'] : null] + $c,
                    $calendars->feeds()['feeds'],
                ));
            }
        }

        if (in_array('location', $parts, true)) {
            $settings->set('timezone', $this->timezone);
            $settings->set('units', $this->units);

            foreach (['latitude' => $this->latitude, 'longitude' => $this->longitude] as $key => $value) {
                $value === '' ? $settings->forget($key) : $settings->set($key, $value);
            }
        }
        $this->tests = [];
        $this->load();
        $this->status = 'Saved.';
    }

    /** Drop everything saved here, so the environment variables apply again. */
    public function resetToEnvironment(Settings $settings): void
    {
        foreach (array_keys(Settings::FALLBACKS) as $key) {
            $settings->forget($key);
        }

        $this->tests = [];
        $this->load();
        $this->status = 'Back to the environment defaults.';
    }

    public function render()
    {
        return view('home-dashboard::livewire.settings', [
            'palette' => CalendarFeeds::PALETTE,
            'back' => route(config('home-dashboard.route.name', 'home-dashboard.index')),
        ]);
    }

    private function load(): void
    {
        $settings = app(Settings::class);

        $this->feeds = array_map(fn (array $feed) => [
            'id' => $feed['id'],
            'name' => $feed['name'],
            'color' => array_search($feed['color'], CalendarFeeds::PALETTE, true) ?: $feed['color'],
            'url' => '',
            'saved' => true,
            'person' => $feed['person'] ?? '',
        ], app(CalendarFeeds::class)->feeds()['feeds']);

        $this->people = array_map(fn (array $p) => ['id' => $p['id'], 'name' => $p['name'], 'email' => $p['email'] ?? ''], app(People::class)->all());

        $this->latitude = (string) ($settings->get('latitude') ?? '');
        $this->longitude = (string) ($settings->get('longitude') ?? '');
        $this->timezone = (string) ($settings->get('timezone') ?: 'UTC');
        $this->units = (string) ($settings->get('units') ?: 'imperial');
        $this->placeQuery = '';
        $this->places = [];
        $this->status = null;
    }

    /** The URL to use for a feed: what was typed, else the saved one. Never sent to the browser. */
    private function resolveUrl(array $feed): ?string
    {
        if (trim($feed['url']) !== '') {
            return trim($feed['url']);
        }

        return collect(app(CalendarFeeds::class)->feeds()['feeds'])->firstWhere('id', $feed['id'])['url'] ?? null;
    }
}
