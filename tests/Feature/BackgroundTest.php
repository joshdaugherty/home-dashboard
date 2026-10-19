<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

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
        return ['id' => $id, 'title' => "Work {$id}", 'artist_display' => "Artist {$id}\nFrench, 1840", 'image_id' => $image ? $image.$id : null, 'thumbnail' => ['width' => $w, 'height' => $h]];
    }

    private function fakeMuseum(array $works): void
    {
        Http::fake(['api.artic.edu/*' => Http::response(['pagination' => ['total' => count($works)], 'data' => $works])]);
    }

    public function test_it_picks_a_landscape_shaped_work_and_links_the_image_server(): void
    {
        $this->fakeMuseum([$this->work(1, 3000, 5000), $this->work(2, 4000, 2500), $this->work(3, 100, 100)]);

        $art = app(Background::class)->today('2026-10-19');

        $this->assertSame('https://www.artic.edu/iiif/2/img2/full/1686,/0/default.jpg', $art['url']);
        $this->assertSame('Work 2', $art['title']);
        $this->assertSame('Artist 2', $art['artist']);
        $this->assertStringContainsString('Art Institute of Chicago', $art['credit']);
    }

    public function test_the_search_is_an_allow_list_that_excludes_sensitive_subjects(): void
    {
        $this->fakeMuseum([$this->work(1, 4000, 2500)]);

        app(Background::class)->today('2026-10-19');

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
        app(Background::class)->today('2026-10-19');
        app(Background::class)->today('2026-10-19');
        Http::assertSentCount(2); // total + one page, once

        Http::swap(new Factory);
        Http::fake(['api.artic.edu/*' => Http::response('nope', 500)]);
        $this->assertNull(app(Background::class)->today('2026-10-20'));
        $this->assertNull(app(Background::class)->today('2026-10-20'));
        $this->assertTrue(Cache::has('home-dashboard:background:2026-10-20:failed'));
    }

    public function test_it_can_be_turned_off_without_any_request(): void
    {
        config(['home-dashboard.background.enabled' => false]);
        Http::fake();

        $this->assertNull(app(Background::class)->today('2026-10-19'));
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
            $art = app(Background::class)->today('2026-10-0'.$day);
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
            $this->assertStringContainsString('artic.edu', app(Background::class)->today('2026-11-0'.$day)['url']);
        }
    }}
