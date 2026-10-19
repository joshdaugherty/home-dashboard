<?php

namespace JoshDaugherty\HomeDashboard\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A new picture every hour for the Today tab: a public-domain painting from the Art Institute of
 * Chicago. The search is a strict allow-list (landscapes and seascapes only, with any figures and
 * anything hinting at nudity, death, violence or war excluded), so no open search ever reaches the
 * frame. When a NASA API key is configured, NASA's Astronomy Picture of the Day (images only, never
 * copyrighted ones) takes every other hour; it is one picture per day. The keys stay on the server:
 * the browser only gets an image URL, loads it directly, and drops back to the plain background if
 * that fails.
 */
class Background
{
    private const API = 'https://api.artic.edu/api/v1/artworks/search';

    private const PAGE = 10;

    private const BLOCKED = 'nude nudity naked nudes bather bathers bathing bath bathe venus diana odalisque erotic sensual death dead dying violence wounded war battle crucifixion skull massacre execution martyrdom hell women woman girl girls figure figures men man boy children child portrait mythology bacchus nymph nymphs sleeping';

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
        $key = 'home-dashboard:background:v3:'.$date.'-'.$hour;

        if (Cache::has($key.':failed')) {
            return null;
        }

        $art = Cache::get($key);

        // An entry saved by an older release may lack fields the view needs: treat it as a miss.
        if ($art !== null && array_diff(['url', 'title', 'artist', 'credit'], array_keys((array) $art)) !== []) {
            $art = null;
        }

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

    /** Plain text, cut at a sentence end where possible, short enough for a small caption. */
    private static function tidy(string $text, int $max = 320): string
    {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($text))));

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $end = max(mb_strrpos($cut, '. '), mb_strrpos($cut, '? '), mb_strrpos($cut, '! '));

        return $end !== false && $end > $max / 2 ? mb_substr($cut, 0, $end + 1) : rtrim($cut, ' ,;:').'…';
    }

    private function nasaKey(): string
    {
        return trim((string) config('home-dashboard.background.nasa_key', ''));
    }

    /** @return array{url: string, title: string, artist: string, credit: string}|null */
    private function apod(string $date): ?array
    {
        // One picture per day, so ask NASA once a day however often the slot changes.
        $data = Cache::remember('home-dashboard:apod:v3:'.$date, now()->addDay(), fn () => Http::timeout(8)
            ->get('https://api.nasa.gov/planetary/apod', ['api_key' => $this->nasaKey(), 'date' => $date])
            ->throw()
            ->json());

        // Videos have no usable image, and copyrighted pictures stay on NASA's page. NASA sometimes answers with a
        // site asset (its own logo) instead of a picture, so only APOD's own image host counts.
        if (($data['media_type'] ?? '') !== 'image' || ! empty($data['copyright']) || empty($data['url']) || parse_url((string) $data['url'], PHP_URL_HOST) !== 'apod.nasa.gov') {
            return null;
        }

        return [
            'url' => (string) $data['url'],
            'title' => (string) ($data['title'] ?? 'Astronomy Picture of the Day'),
            'artist' => 'NASA',
            'date' => (string) ($data['date'] ?? ''),
            'about' => self::tidy($data['explanation'] ?? ''),
            'note' => '',
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
                    'date' => (string) ($work['date_display'] ?? ''),
                    'about' => self::tidy($work['short_description'] ?? ''),
                    'credit' => 'Art Institute of Chicago, public domain',
                    // The museum publishes its descriptions under CC BY 4.0, which asks for a credit.
                    'note' => ! empty($work['short_description']) ? 'Description: Art Institute of Chicago, CC BY 4.0' : '',
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
                    'should' => array_map(fn ($subject) => $match('subject_titles', $subject), ['landscapes', 'seascapes']),
                    'minimum_should_match' => 1,
                    // Strict on purpose: no figures at all, and anything that hints at nudity or violence is out.
                    'must_not' => [['multi_match' => [
                        'query' => self::BLOCKED,
                        'fields' => ['title', 'subject_titles', 'term_titles', 'short_description', 'description', 'alt_titles'],
                    ]]],
                ]],
                'sort' => [['id' => 'asc']],
                'fields' => ['id', 'title', 'artist_display', 'image_id', 'thumbnail', 'date_display', 'short_description'],
            ])
            ->throw()
            ->json();
    }
}
