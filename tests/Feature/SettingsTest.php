<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JoshDaugherty\HomeDashboard\Support\CalendarFeeds;
use JoshDaugherty\HomeDashboard\Support\Settings;
use JoshDaugherty\HomeDashboard\Tests\TestCase;

class SettingsTest extends TestCase
{
    public function test_falls_back_to_config_when_nothing_is_saved(): void
    {
        config(['home-dashboard.timezone' => 'America/Chicago']);

        $settings = app(Settings::class);

        $this->assertSame('America/Chicago', $settings->get('timezone'));
        $this->assertFalse($settings->isSaved('timezone'));
    }

    public function test_a_saved_value_wins_over_config(): void
    {
        config(['home-dashboard.timezone' => 'America/Chicago']);

        $settings = app(Settings::class);
        $settings->set('timezone', 'Europe/Paris');

        $this->assertSame('Europe/Paris', $settings->get('timezone'));
        $this->assertTrue($settings->isSaved('timezone'));

        $settings->forget('timezone');
        $this->assertSame('America/Chicago', $settings->get('timezone'));
    }

    public function test_values_are_encrypted_at_rest(): void
    {
        app(Settings::class)->set('calendars', [['id' => 'a', 'name' => 'Family', 'url' => 'https://calendar.example.com/secret-token-123/basic.ics']]);

        $raw = DB::table('home_dashboard_settings')->where('key', 'calendars')->value('value');

        $this->assertStringNotContainsString('secret-token-123', $raw);
        $this->assertStringNotContainsString('Family', $raw);
        $this->assertSame('Family', app(Settings::class)->get('calendars')[0]['name']);
    }

    public function test_a_row_that_cannot_be_decrypted_falls_back_instead_of_breaking(): void
    {
        config(['home-dashboard.units' => 'metric']);
        DB::table('home_dashboard_settings')->insert(['key' => 'units', 'value' => 'not-a-valid-payload']);

        $this->assertSame('metric', (new Settings)->get('units'));
    }

    public function test_a_missing_table_falls_back_to_config(): void
    {
        config(['home-dashboard.units' => 'metric']);
        Schema::drop('home_dashboard_settings');

        $this->assertSame('metric', (new Settings)->get('units'));
    }

    public function test_saved_calendars_drive_the_feeds_and_keep_their_ids(): void
    {
        config(['home-dashboard.calendars' => json_encode([['name' => 'From env', 'url' => 'https://env.example.com/a.ics']])]);

        app(Settings::class)->set('calendars', [
            ['id' => 'abc', 'name' => 'Saved', 'color' => 'rose', 'url' => 'https://saved.example.com/b.ics'],
        ]);

        $feeds = app(CalendarFeeds::class)->feeds()['feeds'];

        $this->assertCount(1, $feeds);
        $this->assertSame('abc', $feeds[0]['id']);
        $this->assertSame('Saved', $feeds[0]['name']);
        $this->assertSame('#fb7185', $feeds[0]['color']);
    }
}
