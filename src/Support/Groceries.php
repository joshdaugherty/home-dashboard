<?php

namespace JoshDaugherty\HomeDashboard\Support;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The shopping list and the week's meals, kept in Notion (Aisles, Items, Meals and Meal Plan
 * databases). Everything is cached; a failed refresh serves the last good copy, so a flaky
 * network never blanks the screen. Notion stays the single source of truth.
 */
class Groceries
{
    private const PREFIX = 'home-dashboard:notion:v1:';

    public function __construct(private Notion $notion, private CalendarFeeds $calendars) {}

    public function configured(): bool
    {
        return $this->notion->configured();
    }

    /**
     * Items that are needed, grouped by aisle in store order. Null when Notion cannot be reached
     * and nothing has been cached yet.
     */
    public function shoppingList(): ?array
    {
        $items = $this->items();
        $aisles = $this->aisles();

        if ($items === null || $aisles === null) {
            return null;
        }

        $groups = [];

        foreach ($items as $item) {
            if (! $item['need']) {
                continue;
            }

            $aisle = $aisles[$item['aisle']] ?? ['id' => '', 'name' => 'Unsorted', 'store' => null, 'order' => PHP_INT_MAX];
            $groups[$aisle['id']] ??= $aisle + ['items' => []];
            $groups[$aisle['id']]['items'][] = ['id' => $item['id'], 'name' => $item['name'], 'note' => $item['note']];
        }

        foreach ($groups as &$group) {
            usort($group['items'], fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        }

        usort($groups, fn ($a, $b) => [$a['order'], $a['name']] <=> [$b['order'], $b['name']]);

        return $groups;
    }

    /** Take an item off the list (Notion's "Need it" unticked). Only items we know about are touched. */
    public function tick(string $itemId): bool
    {
        $item = ($this->items() ?? [])[$itemId] ?? null;

        if (! $item) {
            return false;
        }

        try {
            $this->notion->update($itemId, ['Need it' => ['checkbox' => false]]);
        } catch (Throwable) {
            return false;
        }

        $this->patchItems([$itemId => false]);

        return true;
    }

    /**
     * The next seven days of the meal plan, starting today, plus the meals planned for the week without a day
     * (picked day-of). Null when unavailable.
     *
     * @return array{days: list<array{date: string, label: string, today: bool, meals: list<array>}>, flexible: list<array>}|null
     */
    public function week(): ?array
    {
        $today = $this->calendars->now()->startOfDay();
        $plan = $this->plan($today);
        $meals = $this->meals();
        $items = $this->items();

        if ($plan === null || $meals === null || $items === null) {
            return null;
        }

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $today->addDays($i);
            $days[$day->toDateString()] = ['date' => $day->toDateString(), 'label' => $day->format('l, M j'), 'today' => $i === 0, 'meals' => []];
        }

        $flexible = [];

        foreach ($plan as $entry) {
            $meal = $meals[$entry['meal']] ?? null;

            $row = [
                'id' => $entry['id'],
                'name' => $meal['name'] ?? ($entry['name'] ?: 'Meal'),
                'notes' => $meal['notes'] ?? '',
                'added' => $entry['added'],
                'ingredients' => collect($meal['ingredients'] ?? [])
                    ->map(fn ($id) => isset($items[$id]) ? ['name' => $items[$id]['name'], 'need' => $items[$id]['need']] : null)
                    ->filter()->values()->all(),
            ];

            if ($entry['date'] === '') {
                $flexible[] = $row;
            } elseif (isset($days[$entry['date']])) {
                $days[$entry['date']]['meals'][] = $row;
            }
        }

        return ['days' => array_values($days), 'flexible' => $flexible];
    }

    /** Decide a day-of meal: give an undated plan entry today's date, so it moves into today's card. */
    public function makeToday(string $planId): bool
    {
        $today = $this->calendars->now()->startOfDay();
        $entry = collect($this->plan($today) ?? [])->firstWhere('id', $planId);

        if (! $entry || $entry['date'] !== '') {
            return false;
        }

        try {
            $this->notion->update($planId, ['Date' => ['date' => ['start' => $today->toDateString()]]]);
        } catch (Throwable) {
            return false;
        }

        Cache::forget(self::PREFIX.$this->planKey($today));

        return true;
    }

    /** Put the ingredients of the week's planned meals on the shopping list. Returns how many items were added. */
    public function addWeekToList(): int
    {
        $today = $this->calendars->now()->startOfDay();
        $plan = $this->plan($today);
        $meals = $this->meals();
        $items = $this->items();

        if ($plan === null || $meals === null || $items === null) {
            return 0;
        }

        $added = [];
        $done = [];

        foreach ($plan as $entry) {
            $meal = $meals[$entry['meal']] ?? null;

            if ($entry['added'] || ! $meal || ($entry['date'] === '' && $entry['done'])) {
                continue;
            }

            foreach ($meal['ingredients'] as $id) {
                if (isset($items[$id]) && ! $items[$id]['need'] && ! isset($added[$id])) {
                    try {
                        $this->notion->update($id, ['Need it' => ['checkbox' => true]]);
                        $added[$id] = true;
                    } catch (Throwable) {
                        continue 2;
                    }
                }
            }

            try {
                $this->notion->update($entry['id'], ['Added to list' => ['checkbox' => true]]);
                $done[] = $entry['id'];
            } catch (Throwable) {
                // The ingredients are on the list; the plan entry is simply retried next time.
            }
        }

        $this->patchItems(array_map(fn () => true, $added));
        Cache::forget(self::PREFIX.$this->planKey($today));

        return count($added);
    }

    /** @return array<string, array{id: string, name: string, aisle: ?string, need: bool, note: string}>|null */
    private function items(): ?array
    {
        return $this->cached('items', 60, function () {
            return $this->index(array_map(fn ($page) => [
                'id' => $page['id'],
                'name' => $this->title($page, 'Name'),
                'aisle' => $this->relation($page, 'Aisle')[0] ?? null,
                'need' => (bool) ($page['properties']['Need it']['checkbox'] ?? false),
                'note' => $this->text($page, 'Note'),
            ], $this->notion->query($this->notion->dataSource('items'))));
        });
    }

    /** @return array<string, array{id: string, name: string, store: ?string, order: float}>|null */
    private function aisles(): ?array
    {
        return $this->cached('aisles', 300, function () {
            return $this->index(array_map(fn ($page) => [
                'id' => $page['id'],
                'name' => $this->title($page, 'Name'),
                'store' => $page['properties']['Store']['select']['name'] ?? null,
                'order' => (float) ($page['properties']['Order']['number'] ?? 9999),
            ], $this->notion->query($this->notion->dataSource('aisles'))));
        });
    }

    /** @return array<string, array{id: string, name: string, ingredients: list<string>, notes: string}>|null */
    private function meals(): ?array
    {
        return $this->cached('meals', 300, function () {
            return $this->index(array_map(fn ($page) => [
                'id' => $page['id'],
                'name' => $this->title($page, 'Name'),
                'ingredients' => $this->relation($page, 'Ingredients'),
                'notes' => $this->text($page, 'Notes'),
            ], $this->notion->query($this->notion->dataSource('meals'))));
        });
    }

    /** @return list<array{id: string, name: string, date: string, meal: ?string, added: bool, done: bool}>|null */
    private function plan(CarbonImmutable $from): ?array
    {
        return $this->cached($this->planKey($from), 300, function () use ($from) {
            $pages = $this->notion->query($this->notion->dataSource('plan'), [
                'filter' => ['or' => [
                    ['and' => [
                        ['property' => 'Date', 'date' => ['on_or_after' => $from->toDateString()]],
                        ['property' => 'Date', 'date' => ['on_or_before' => $from->addDays(6)->toDateString()]],
                    ]],
                    ['and' => [
                        ['property' => 'Date', 'date' => ['is_empty' => true]],
                        ['property' => 'Done', 'checkbox' => ['equals' => false]],
                    ]],
                ]],
                'sorts' => [['property' => 'Date', 'direction' => 'ascending']],
            ]);

            return array_map(fn ($page) => [
                'id' => $page['id'],
                'name' => $this->title($page, 'Name'),
                'date' => substr((string) ($page['properties']['Date']['date']['start'] ?? ''), 0, 10),
                'meal' => $this->relation($page, 'Meal')[0] ?? null,
                'added' => (bool) ($page['properties']['Added to list']['checkbox'] ?? false),
                'done' => (bool) ($page['properties']['Done']['checkbox'] ?? false),
            ], $pages);
        });
    }

    private function planKey(CarbonImmutable $from): string
    {
        return 'plan:'.$from->toDateString();
    }

    /** Keep the cached item list in step with a change we just made, so the screen updates at once. */
    private function patchItems(array $need): void
    {
        foreach ([self::PREFIX.'items', self::PREFIX.'items:stale'] as $key) {
            $cached = Cache::get($key);

            if (! is_array($cached)) {
                continue;
            }

            foreach ($need as $id => $value) {
                if (isset($cached[$id])) {
                    $cached[$id]['need'] = $value;
                }
            }

            Cache::put($key, $cached, $key === self::PREFIX.'items' ? 60 : now()->addDays(30));
        }
    }

    /**
     * The fresh copy, else a reload, else the last good copy. Cached data from an older release that
     * does not have the expected shape is ignored rather than trusted.
     */
    private function cached(string $name, int $ttl, Closure $load): ?array
    {
        if (! $this->notion->configured()) {
            return null;
        }

        $key = self::PREFIX.$name;
        $fresh = Cache::get($key);

        if ($this->usable($fresh)) {
            return $fresh;
        }

        try {
            $data = $load();
        } catch (Throwable) {
            $stale = Cache::get($key.':stale');

            return $this->usable($stale) ? $stale : null;
        }

        Cache::put($key, $data, $ttl);
        Cache::put($key.':stale', $data, now()->addDays(30));

        return $data;
    }

    private function usable(mixed $value): bool
    {
        return is_array($value) && collect($value)->every(fn ($row) => is_array($row) && isset($row['id']));
    }

    /** @param list<array{id: string}> $rows */
    private function index(array $rows): array
    {
        return collect($rows)->keyBy('id')->all();
    }

    private function title(array $page, string $property): string
    {
        return trim(collect($page['properties'][$property]['title'] ?? [])->pluck('plain_text')->implode(''));
    }

    private function text(array $page, string $property): string
    {
        return trim(collect($page['properties'][$property]['rich_text'] ?? [])->pluck('plain_text')->implode(''));
    }

    /** @return list<string> */
    private function relation(array $page, string $property): array
    {
        return collect($page['properties'][$property]['relation'] ?? [])->pluck('id')->values()->all();
    }
}
