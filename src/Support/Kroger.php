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
            $response = Http::withToken($this->token())->acceptJson()->timeout(10)->get('https://api.kroger.com/v1/products', [
                'filter.term' => $term,
                'filter.locationId' => $this->setting('location_id'),
                'filter.limit' => 5,
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Kroger product search failed.');
            }
        } catch (Throwable) {
            // Try again in a few minutes rather than on every refresh.
            Cache::put($key, '', now()->addMinutes(5));

            return null;
        }

        $label = collect($response->json('data') ?? [])
            ->map(fn ($product) => trim((string) ($product['aisleLocations'][0]['description'] ?? '')))
            ->first(fn ($label) => $label !== '');

        Cache::put($key, $label ?? '', $label ? now()->addDays(30) : now()->addDay());

        return $label;
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
        return 'home-dashboard:kroger:v1:aisle:'.$this->setting('location_id').':'.md5($term);
    }

    private function setting(string $name): ?string
    {
        $value = config("home-dashboard.kroger.{$name}");

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
