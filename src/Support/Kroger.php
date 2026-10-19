<?php

namespace JoshDaugherty\HomeDashboard\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Looks up where a grocery item lives in one Kroger store, using Kroger's public product API
 * (client-credentials, no customer sign-in). The client secret stays on the server.
 */
class Kroger
{
    public function configured(): bool
    {
        return $this->setting('client_id') !== null && $this->setting('client_secret') !== null && $this->setting('location_id') !== null;
    }

    /** True when this item has not been looked up recently (successfully or not). */
    public function pending(string $item): bool
    {
        $term = $this->term($item);

        return $term !== '' && ! Cache::has($this->key($term));
    }

    /** The store's own label for where the item is, such as "AISLE 14" or "DAIRY"; null when unknown. */
    public function aisleFor(string $item): ?string
    {
        $term = $this->term($item);

        if ($term === '' || ! $this->configured()) {
            return null;
        }

        $key = $this->key($term);
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        try {
            $label = null;

            foreach ($this->candidates($term) as $candidate) {
                if (($label = $this->search($candidate)) !== null) {
                    break;
                }
            }
        } catch (Throwable) {
            // Try again in a few minutes rather than on every refresh.
            Cache::put($key, '', now()->addMinutes(5));

            return null;
        }

        Cache::put($key, $label ?? '', $label ? now()->addDays(30) : now()->addDay());

        return $label;
    }

    /**
     * Where a Kroger label falls on a walk through the store, so the Aisles database sorts the way a shopping trip goes:
     * produce, deli, bakery, then the even aisles up the store (with meat after the last center-store aisle), dairy,
     * personal care, pharmacy, and the odd aisles back down from the highest. Anything else goes last.
     */
    public function order(string $label): float
    {
        $l = strtoupper(trim($label));
        $n = (int) preg_replace('/\D+/', '', $l);

        if (preg_match('/^AISLE \d+$/', $l)) {
            return match (true) {
                $n >= 60 => 1300 + $n,
                $n >= 56 => 1200 + $n,
                $n % 2 === 0 => 1000 + $n,
                default => 2000 + (60 - $n),
            };
        }

        return match (true) {
            $l === 'PRODUCE' => 100,
            $l === 'FRUIT' => 101,
            $l === 'VEGETABLES' => 102,
            str_starts_with($l, 'PRODUCE TABLE') => 110 + $n,
            $l === 'DELI/BAKE' => 250,
            $l === 'DELI' => 200,
            str_starts_with($l, 'DELI') => 200 + $n,
            $l === 'BAKERY - IN-STORE' => 300,
            str_starts_with($l, 'BAKERY') => 300 + $n,
            $l === 'MEAT' => 1014.1,
            $l === 'SEAFOOD' => 1014.2,
            $l === 'PACKAGED MEAT' => 1014.3,
            $l === 'DAIRY' => 1100,
            $l === 'PHARMACY' => 1300,
            default => 5000,
        };
    }

    /** Forget a remembered answer, so the next lookup asks Kroger again. */
    public function forget(string $item): void
    {
        Cache::forget($this->key($this->term($item)));
    }

    /**
     * Search phrases to try in order: the whole phrase, then without the container ("jar of red sauce" is shelved as
     * red sauce), then the first of an either-or ("ground beef or turkey" is shelved with ground beef).
     *
     * @return list<string>
     */
    private function candidates(string $term): array
    {
        $plain = trim((string) preg_replace('/^(?:(?:microwave|frozen)\s+)?(?:bag|box|jar|bottle|can|carton|package|pack|loaf|tub|block|bunch)\s+of\s+/', '', $term));
        $phrases = [$term, $plain];

        foreach ([$term, $plain] as $phrase) {
            if (str_contains($phrase, ' or ')) {
                $phrases[] = trim(explode(' or ', $phrase)[0]);
            }
        }

        return array_values(array_unique(array_filter($phrases)));
    }

    private function search(string $term): ?string
    {
        $response = Http::withToken($this->token())->acceptJson()->timeout(10)->get('https://api.kroger.com/v1/products', [
            'filter.term' => $term,
            'filter.locationId' => $this->setting('location_id'),
            'filter.limit' => 5,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Kroger product search failed.');
        }

        return collect($response->json('data') ?? [])
            ->map(fn ($product) => trim((string) ($product['aisleLocations'][0]['description'] ?? '')))
            ->first(fn ($label) => $label !== '');
    }

    private function token(): string
    {
        return Cache::remember('home-dashboard:kroger:v1:token', now()->addMinutes(25), function () {
            $response = Http::asForm()
                ->withBasicAuth((string) $this->setting('client_id'), (string) $this->setting('client_secret'))
                ->timeout(10)
                ->post('https://api.kroger.com/v1/connect/oauth2/token', ['grant_type' => 'client_credentials', 'scope' => 'product.compact'])
                ->throw();

            return (string) $response->json('access_token');
        });
    }

    /** "bag of mixed greens (2)" becomes a plain search phrase. */
    private function term(string $item): string
    {
        return trim((string) preg_replace('/\s+/', ' ', preg_replace('/\(.*?\)/', ' ', mb_strtolower($item))));
    }

    private function key(string $term): string
    {
        return 'home-dashboard:kroger:v2:aisle:'.$this->setting('location_id').':'.md5($term);
    }

    private function setting(string $name): ?string
    {
        $value = config("home-dashboard.kroger.{$name}");

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
