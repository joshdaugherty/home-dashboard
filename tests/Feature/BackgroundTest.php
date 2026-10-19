<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JoshDaugherty\HomeDashboard\Livewire\Tabs\Today;
use JoshDaugherty\HomeDashboard\Support\Background;
use JoshDaugherty\HomeDashboard\Tests\TestCase;
use Livewire\Livewire;

class BackgroundTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('home-dashboard.background.enabled', true);
    }

    private function work(int $id, int $w, int $h, ?string $image = 'img'): array
    {
        return ['id' => $id, 'title' => "Work {$id}", 'artist_display' => "Artist {$id}\nFrench, 1840", 'image_id' => $image ? $image.$id : null, 'thumbnail' => ['width' => $w, 'height' => $h], 'date_display' => '1884', 'short_description' => '<p>A calm <em>river</em> at dusk. More text follows here.</p>'];
    }

    private function fakeMuseum(array $works): void
    {
        Http::fake(['api.artic.edu/*' => Http::response(['pagination' => ['total' => count($works)], 'data' => $works])]);
    }

    public function test_it_picks_a_landscape_shaped_work_and_links_the_image_server(): void
    {
        $this->fakeMuseum([$this->work(1, 3000, 5000), $this->work(2, 4000, 2500), $this->work(3, 100, 100)]);

        $art = app(Background::class)->current(CarbonImmutable::parse('2026-10-19 09:00'));

        $this->assertSame('https://www.artic.edu/iiif/2/img2/full/1686,/0/default.jpg', $art['url']);
        $this->assertSame('Work 2', $art['title']);
        $this->assertSame('Artist 2', $art['artist']);
        $this->assertStringContainsString('Art Institute of Chicago', $art['credit']);
    }

    public function test_the_search_is_an_allow_list_that_excludes_sensitive_subjects(): void
    {
        $this->fakeMuseum([$this->work(1, 4000, 2500)]);

        app(Background::class)->current(CarbonImmutable::parse('2026-10-19 09:00'));

        Http::assertSent(function ($request) {
            $body = json_encode($request->data());

            return str_contains($body, '"is_public_domain":true')
                && str_contains($body, 'landscapes')
                && str_contains($body, 'nudity')
                && str_contains($body, 'violence');
        });
    }

    public function test_the_day_is_cached_and_a_failure_is_remembered_briefly(): void
    {
        $this->fakeMuseum([$this->work(1, 4000, 2500)]);
        app(Background::class)->current(CarbonImmutable::parse('2026-10-19 09:00'));
        app(Background::class)->current(CarbonImmutable::parse('2026-10-19 09:00'));
        Http::assertSentCount(2); // total + one page, once

        Http::swap(new Factory);
        Http::fake(['api.artic.edu/*' => Http::response('nope', 500)]);
        $this->assertNull(app(Background::class)->current(CarbonImmutable::parse('2026-10-20 09:00')));
        $this->assertNull(app(Background::class)->current(CarbonImmutable::parse('2026-10-20 09:00')));
        $this->assertTrue(Cache::has('home-dashboard:background:v3:2026-10-20-9:failed'));
    }

    public function test_it_can_be_turned_off_without_any_request(): void
    {
        config(['home-dashboard.background.enabled' => false]);
        Http::fake();

        $this->assertNull(app(Background::class)->current(CarbonImmutable::parse('2026-10-19 09:00')));
        Http::assertNothingSent();
    }

    public function test_today_renders_the_painting_and_its_credit(): void
    {
        $this->fakeMuseum([$this->work(7, 4000, 2500)]);

        Livewire::test(Today::class)
            ->assertSee('iiif/2/img7/full/1686', false)
            ->assertSee('Work 7')
            ->assertSee('Artist 7')
            ->assertSee('Art Institute of Chicago');
    }

    public function test_a_nasa_key_adds_the_astronomy_picture_on_alternate_days_and_stays_server_side(): void
    {
        config(['home-dashboard.background.nasa_key' => 'secret-nasa-key']);
        Http::fake([
            'api.nasa.gov/*' => Http::response(['media_type' => 'image', 'url' => 'https://apod.nasa.gov/apod/image/nebula.jpg', 'title' => 'A Nebula']),
            'api.artic.edu/*' => Http::response(['pagination' => ['total' => 1], 'data' => [$this->work(1, 4000, 2500)]]),
        ]);

        $sources = [];
        foreach (range(1, 6) as $day) {
            $art = app(Background::class)->current(CarbonImmutable::parse('2026-10-19 '.$day.':00'));
            $sources[] = str_contains($art['url'], 'nasa.gov') ? 'nasa' : 'artic';
            $this->assertStringNotContainsString('secret-nasa-key', json_encode($art));
        }

        $this->assertContains('nasa', $sources);
        $this->assertContains('artic', $sources);
    }

    public function test_copyrighted_or_video_pictures_fall_back_to_the_painting(): void
    {
        config(['home-dashboard.background.nasa_key' => 'k']);
        Http::fake([
            'api.nasa.gov/*' => Http::response(['media_type' => 'image', 'url' => 'https://x/y.jpg', 'copyright' => 'Someone', 'title' => 'T']),
            'api.artic.edu/*' => Http::response(['pagination' => ['total' => 1], 'data' => [$this->work(1, 4000, 2500)]]),
        ]);

        foreach (range(1, 4) as $day) {
            $this->assertStringContainsString('artic.edu', app(Background::class)->current(CarbonImmutable::parse('2026-11-19 '.$day.':00'))['url']);
        }
    }

    public function test_a_nasa_site_asset_such_as_its_logo_is_not_treated_as_the_picture(): void
    {
        config(['home-dashboard.background.nasa_key' => 'k']);
        Http::fake([
            'api.nasa.gov/*' => Http::response(['media_type' => 'image', 'url' => 'https://science.nasa.gov/wp-content/themes/nasa-child/assets/images/nasa-logo@2x.png', 'title' => 'NASA Science']),
            'api.artic.edu/*' => Http::response(['pagination' => ['total' => 0], 'data' => []]),
        ]);

        foreach ([10, 11] as $hour) {
            $art = app(Background::class)->current(CarbonImmutable::parse('2026-10-19 '.$hour.':00', 'America/Chicago'));

            $this->assertStringNotContainsString('nasa', json_encode($art));
        }
    }

    public function test_the_picture_rotates_hourly_and_nasa_takes_the_odd_hours(): void
    {
        config(['home-dashboard.background.nasa_key' => 'k']);
        Http::fake([
            'api.nasa.gov/*' => Http::response(['media_type' => 'image', 'url' => 'https://apod.nasa.gov/a.jpg', 'title' => 'N']),
            'api.artic.edu/*' => Http::response(['pagination' => ['total' => 1], 'data' => [$this->work(1, 4000, 2500)]]),
        ]);

        $at = fn (int $hour) => app(Background::class)->current(CarbonImmutable::parse('2026-10-19 '.$hour.':30'))['url'];

        $this->assertStringContainsString('artic.edu', $at(10));
        $this->assertStringContainsString('nasa.gov', $at(11));
        $this->assertStringContainsString('artic.edu', $at(12));
        $this->assertStringContainsString('nasa.gov', $at(13));

        // NASA's picture is fetched once for the whole day.
        Http::assertSentCount(5);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.nasa.gov'));
    }
    public function test_a_stale_cache_entry_from_an_older_release_is_replaced(): void
    {
        $this->fakeMuseum([$this->work(4, 4000, 2500)]);
        Cache::put('home-dashboard:background:v3:2026-10-19-9', ['url' => 'x', 'title' => 'Old', 'artist' => 'A', 'link' => 'l'], 3600);

        $art = app(Background::class)->current(CarbonImmutable::parse('2026-10-19 09:00'));

        $this->assertSame('Work 4', $art['title']);
        $this->assertArrayHasKey('credit', $art);
    }
    public function test_the_caption_carries_the_date_and_a_short_description_with_attribution(): void
    {
        $this->fakeMuseum([$this->work(5, 4000, 2500)]);

        $art = app(Background::class)->current(CarbonImmutable::parse('2026-10-19 09:00'));

        $this->assertSame('1884', $art['date']);
        $this->assertSame('A calm river at dusk. More text follows here.', $art['about']);
        $this->assertStringContainsString('CC BY 4.0', $art['note']);

        // The Today pane only announces the picture; the shell renders the icon, lightbox and idle box.
        Livewire::test(Today::class)->assertSee('background-info', false)->assertSee('A calm river at dusk.', false)->assertSee('1884', false)->assertSee('CC BY 4.0', false);

        $this->get('/')->assertSee('About this picture')->assertSee('background-info', false);
    }

    public function test_long_descriptions_are_cut_at_a_sentence(): void
    {
        config(['home-dashboard.background.nasa_key' => 'k']);
        Http::fake(['api.nasa.gov/*' => Http::response(['media_type' => 'image', 'url' => 'https://apod.nasa.gov/apod/image/y.jpg', 'title' => 'T', 'date' => '2026-10-19', 'explanation' => str_repeat('A sentence about a galaxy. ', 30)])]);

        $art = app(Background::class)->current(CarbonImmutable::parse('2026-10-19 11:00'));

        $this->assertLessThanOrEqual(320, mb_strlen($art['about']));
        $this->assertStringEndsWith('.', $art['about']);
    }}
