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

    public function __construct(private Notion $notion, private CalendarFeeds $calendars, private Kroger $kroger) {}

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

        [$items, $aisles] = $this->fileUnsorted($items, $aisles);

        // The frame groups by store; walking order (aisles) is for the shopping trip itself, in Notion.
        $groups = [];

        foreach ($items as $item) {
            if (! $item['need']) {
                continue;
            }

            $store = ($aisles[$item['aisle']]['store'] ?? null) ?: 'Kroger';
            $groups[$store] ??= ['name' => $store, 'items' => []];
            $groups[$store]['items'][] = ['id' => $item['id'], 'name' => $item['name'], 'note' => $item['note']];
        }

        foreach ($groups as &$group) {
            usort($group['items'], fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        }
        unset($group);

        uksort($groups, fn ($a, $b) => [$a === 'Kroger' ? 0 : 1, $a] <=> [$b === 'Kroger' ? 0 : 1, $b]);

        return array_values($groups);
    }

    /**
     * Items that are not on the list yet and match what was typed, to add with a tap.
     *
     * @return list<array{id: string, name: string}>
     */
    public function suggestions(string $query, int $limit = 8): array
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return [];
        }

        $matches = array_values(array_filter($this->items() ?? [], fn ($item) => ! $item['need'] && str_contains(mb_strtolower($item['name']), $query)));

        // Names that start with what was typed first.
        usort($matches, fn ($a, $b) => [! str_starts_with(mb_strtolower($a['name']), $query), mb_strtolower($a['name'])] <=> [! str_starts_with(mb_strtolower($b['name']), $query), mb_strtolower($b['name'])]);

        return array_map(fn ($item) => ['id' => $item['id'], 'name' => $item['name']], array_slice($matches, 0, $limit));
    }

    /** Put a known item on the list. */
    public function need(string $itemId): bool
    {
        $item = ($this->items() ?? [])[$itemId] ?? null;

        if (! $item) {
            return false;
        }

        if (! $item['need']) {
            try {
                $this->notion->update($itemId, ['Need it' => ['checkbox' => true]]);
            } catch (Throwable) {
                return false;
            }

            $this->patchItems([$itemId => true]);
        }

        return true;
    }

    /** Add by name: an item we already know goes back on the list, anything else becomes a new item. Returns its name. */
    public function addByName(string $name): ?string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        $items = $this->items();

        if ($name === '' || mb_strlen($name) > 120 || $items === null) {
            return null;
        }

        $existing = collect($items)->first(fn ($item) => mb_strtolower($item['name']) === mb_strtolower($name));

        if ($existing) {
            return $this->need($existing['id']) ? $existing['name'] : null;
        }

        try {
            $id = $this->notion->create((string) $this->notion->dataSource('items'), [
                'Name' => ['title' => [['text' => ['content' => $name]]]],
                'Need it' => ['checkbox' => true],
            ]);
        } catch (Throwable) {
            return null;
        }

        $items[$id] = ['id' => $id, 'name' => $name, 'aisle' => null, 'need' => true, 'note' => ''];
        $this->store('items', $items, 60);

        return $name;
    }

    /**
     * Items on the list with no aisle are filed where the Kroger store says they are. The aisle row is found by its
     * "Kroger aisle" label or created on first use, and the choice is written back to Notion. A few lookups per
     * refresh at most, so a long list sorts itself over a minute or two.
     *
     * @return array{0: array, 1: array}
     */
    private function fileUnsorted(array $items, array $aisles, bool $onlyNeeded = true, int $limit = 5): array
    {
        if (! $this->kroger->configured()) {
            return [$items, $aisles];
        }

        $todo = array_filter($items, fn ($item) => (! $onlyNeeded || $item['need']) && $item['aisle'] === null && $this->kroger->pending($item['name']));

        if ($todo === []) {
            return [$items, $aisles];
        }

        try {
            return Cache::lock('home-dashboard:notion:filing', max(30, $limit * 3))->block(3, function () use ($todo, $limit) {
                // Another request may have filed things while we waited for the lock.
                $items = $this->items() ?? [];
                $aisles = $this->aisles() ?? [];

                foreach (array_slice($todo, 0, $limit, true) as $id => $item) {
                    $label = $this->kroger->aisleFor($item['name']);

                    if ($label === null || ($items[$id]['aisle'] ?? null) !== null) {
                        continue;
                    }

                    try {
                        $aisleId = $this->aisleForLabel($label, $aisles);
                        $this->notion->update($id, ['Aisle' => ['relation' => [['id' => $aisleId]]]]);
                        $items[$id]['aisle'] = $aisleId;
                    } catch (Throwable) {
                        // Not filed: forget Kroger's answer so the next refresh tries again instead of skipping this item.
                        $this->kroger->forget($item['name']);

                        continue;
                    }
                }

                $this->store('items', $items, 60);
                $this->store('aisles', $aisles, 300);

                return [$items, $aisles];
            });
        } catch (Throwable) {
            return [$items, $aisles];
        }
    }

    /**
     * File the whole catalog (needed or not) that has no aisle yet, up to a limit. Returns how many items remain
     * without an aisle afterwards, so callers can run it again.
     */
    public function fileCatalog(int $limit = 25): ?int
    {
        $items = $this->items();
        $aisles = $this->aisles();

        if ($items === null || $aisles === null || ! $this->kroger->configured()) {
            return null;
        }

        [$items] = $this->fileUnsorted($items, $aisles, false, $limit);

        return count(array_filter($items, fn ($item) => $item['aisle'] === null && $this->kroger->pending($item['name'])));
    }

    /** The Aisles row standing for a Kroger label, creating it when there is none yet. */
    private function aisleForLabel(string $label, array &$aisles): string
    {
        foreach ($aisles as $aisle) {
            if (strcasecmp($aisle['kroger'], $label) === 0) {
                return $aisle['id'];
            }
        }

        $number = preg_match('/^aisle\s+(\d+)$/i', $label, $m) ? (int) $m[1] : null;
        $name = $number !== null ? 'Aisle '.$number : ($label === strtoupper($label) ? ucwords(strtolower($label), " /-") : $label);
        $order = $this->kroger->order($label);

        $id = $this->notion->create((string) $this->notion->dataSource('aisles'), [
            'Name' => ['title' => [['text' => ['content' => $name]]]],
            'Store' => ['select' => ['name' => 'Kroger']],
            'Order' => ['number' => $order],
            'Kroger aisle' => ['rich_text' => [['text' => ['content' => $label]]]],
        ]);

        $aisles[$id] = ['id' => $id, 'name' => $name, 'store' => 'Kroger', 'order' => (float) $order, 'kroger' => $label];

        return $id;
    }

    private function store(string $name, array $data, int $ttl): void
    {
        Cache::put(self::PREFIX.$name, $data, $ttl);
        Cache::put(self::PREFIX.$name.':stale', $data, now()->addDays(30));
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

    /** @return array<string, array{id: string, name: string, store: ?string, order: float, kroger: string}>|null */
    private function aisles(): ?array
    {
        return $this->cached('aisles', 300, function () {
            return $this->index(array_map(fn ($page) => [
                'id' => $page['id'],
                'name' => $this->title($page, 'Name'),
                'store' => $page['properties']['Store']['select']['name'] ?? null,
                'order' => (float) ($page['properties']['Order']['number'] ?? 9999),
                'kroger' => $this->text($page, 'Kroger aisle'),
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
