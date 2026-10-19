<?php

namespace JoshDaugherty\HomeDashboard\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A new picture every hour for the Today tab: a public-domain painting from the Art Institute of Chicago each day, for the
 * Today tab. The search is an allow-list (paintings of landscapes, seascapes, flowers and
 * animals, minus anything flagged nudity, death, violence or war), so no open search ever
 * reaches the frame. When a NASA API key is configured, NASA's Astronomy Picture of the Day
 * (images only, never copyrighted ones) takes every other hour (it is one picture per day). The keys stay on the server:
 * the browser only gets an image URL, loads it directly, and drops back to the plain
 * background if that fails.
 */
class Background
{
    private const API = 'https://api.artic.edu/api/v1/artworks/search';

    private const PAGE = 10;

    public function enabled(): bool
    {
        return (bool) config('home-dashboard.background.enabled', true);
    }

    /**
     * Today's artwork, or null when disabled or the museum can't be reached.
     *
     * @return array{url: string, title: string, artist: string, credit: string}|null
     */
    public function current(CarbonImmutable $now): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        // One slot per hour. With a NASA key the sources take turns by the hour; the painting
        // changes every hour, while NASA's picture is the same all day by nature.
        $date = $now->toDateString();
        $hour = (int) $now->format('G');
        $key = 'home-dashboard:background:'.$date.'-'.$hour;

        if (Cache::has($key.':failed')) {
            return null;
        }

        $art = Cache::get($key);

        if ($art === null) {
            try {
                $art = $this->choose($date, $hour);
            } catch (Throwable) {
                $art = null;
            }

            if ($art === null) {
                Cache::put($key.':failed', true, 600);

                return null;
            }

            Cache::put($key, $art, now()->addHours(2));
        }

        return $art;
    }

    /** @return array{url: string, title: string, artist: string, credit: string}|null */
    private function choose(string $date, int $hour): ?array
    {
        $sources = [fn () => $this->artwork($date.'-'.$hour)];

        if ($this->nasaKey() !== '') {
            // Alternate by the hour; whichever source is up next falls back to the other.
            $sources = $hour % 2 === 0 ? [...$sources, fn () => $this->apod($date)] : [fn () => $this->apod($date), ...$sources];
        }

        foreach ($sources as $source) {
            try {
                if ($art = $source()) {
                    return $art;
                }
            } catch (Throwable) {
                // Try the next source. The message is not logged: it can carry the API key in the URL.
            }
        }

        return null;
    }

    private function nasaKey(): string
    {
        return trim((string) config('home-dashboard.background.nasa_key', ''));
    }

    /** @return array{url: string, title: string, artist: string, credit: string}|null */
    private function apod(string $date): ?array
    {
        // One picture per day, so ask NASA once a day however often the slot changes.
        $data = Cache::remember('home-dashboard:apod:'.$date, now()->addDay(), fn () => Http::timeout(8)
            ->get('https://api.nasa.gov/planetary/apod', ['api_key' => $this->nasaKey(), 'date' => $date])
            ->throw()
            ->json());

        // Videos have no usable image, and copyrighted pictures stay on NASA's page.
        if (($data['media_type'] ?? '') !== 'image' || ! empty($data['copyright']) || empty($data['url'])) {
            return null;
        }

        return [
            'url' => (string) $data['url'],
            'title' => (string) ($data['title'] ?? 'Astronomy Picture of the Day'),
            'artist' => 'NASA',
            'credit' => 'NASA Astronomy Picture of the Day, public domain',
        ];
    }

    /** @return array{url: string, title: string, artist: string, credit: string}|null */
    private function artwork(string $date): ?array
    {
        $total = (int) $this->search(0, 1)['pagination']['total'];

        if ($total === 0) {
            return null;
        }

        // The same page for everyone on the same day; walk forward until a landscape-shaped image turns up.
        $pages = max(1, (int) floor(min($total, 1000) / self::PAGE));
        $start = crc32($date) % $pages;

        for ($i = 0; $i < min($pages, 5); $i++) {
            $page = ($start + $i) % $pages + 1;

            foreach ($this->search(self::PAGE, $page)['data'] ?? [] as $work) {
                $width = (int) ($work['thumbnail']['width'] ?? 0);
                $height = (int) ($work['thumbnail']['height'] ?? 0);

                if (empty($work['image_id']) || $height === 0 || $width / $height < 1.25 || $width / $height > 2.4) {
                    continue;
                }

                return [
                    // 1686 is one of the sizes the museum's image server recommends.
                    'url' => 'https://www.artic.edu/iiif/2/'.$work['image_id'].'/full/1686,/0/default.jpg',
                    'title' => (string) $work['title'],
                    'artist' => trim(strtok((string) ($work['artist_display'] ?? ''), "\n")) ?: 'Unknown artist',
                    'credit' => 'Art Institute of Chicago, public domain',
                ];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function search(int $limit, int $page = 1): array
    {
        $match = fn (string $field, string $value) => ['match' => [$field => $value]];

        return Http::timeout(8)
            ->withHeaders(['AIC-User-Agent' => 'home-dashboard (https://github.com/joshdaugherty/home-dashboard)'])
            ->post(self::API.'?limit='.$limit.'&page='.$page, [
                'query' => ['bool' => [
                    'filter' => [
                        ['term' => ['is_public_domain' => true]],
                        ['exists' => ['field' => 'image_id']],
                        $match('classification_titles', 'painting'),
                    ],
                    'should' => array_map(fn ($subject) => $match('subject_titles', $subject), ['landscapes', 'seascapes', 'flowers', 'animals']),
                    'minimum_should_match' => 1,
                    'must_not' => [
                        ...array_map(fn ($subject) => $match('subject_titles', $subject), ['nudity', 'nude', 'death', 'violence', 'war', 'crucifixion']),
                        $match('title', 'nude'),
                    ],
                ]],
                'sort' => [['id' => 'asc']],
                'fields' => ['id', 'title', 'artist_display', 'image_id', 'thumbnail'],
            ])
            ->throw()
            ->json();
    }
}
