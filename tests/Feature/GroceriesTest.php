<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JoshDaugherty\HomeDashboard\Livewire\Tabs\Meals;
use JoshDaugherty\HomeDashboard\Livewire\Tabs\Shopping;
use JoshDaugherty\HomeDashboard\Support\Groceries;
use JoshDaugherty\HomeDashboard\Support\Kroger;
use JoshDaugherty\HomeDashboard\Tests\TestCase;
use Livewire\Livewire;

class GroceriesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('home-dashboard.timezone', 'America/Chicago');
        $app['config']->set('home-dashboard.notion', ['token' => 'secret_test', 'aisles' => 'ds-aisles', 'items' => 'ds-items', 'meals' => 'ds-meals', 'plan' => 'ds-plan']);
    }

    private function title(string $name): array
    {
        return ['title' => [['plain_text' => $name]]];
    }

    private function fakeNotion(array $overrides = []): void
    {
        $today = now('America/Chicago')->toDateString();
        $tomorrow = now('America/Chicago')->addDay()->toDateString();

        Http::fake($overrides + [
            'api.notion.com/v1/data_sources/ds-aisles/query' => Http::response(['results' => [
                ['id' => 'a-produce', 'properties' => ['Name' => $this->title('Produce'), 'Store' => ['select' => ['name' => 'Kroger']], 'Order' => ['number' => 10]]],
                ['id' => 'a-costco', 'properties' => ['Name' => $this->title('Costco'), 'Store' => ['select' => ['name' => 'Costco']], 'Order' => ['number' => 1]]],
            ], 'has_more' => false]),
            'api.notion.com/v1/data_sources/ds-items/query' => Http::response(['results' => [
                ['id' => 'i-eggs', 'properties' => ['Name' => $this->title('egg'), 'Aisle' => ['relation' => [['id' => 'a-produce']]], 'Need it' => ['checkbox' => true], 'Note' => ['rich_text' => [['plain_text' => 'need 1']]]]],
                ['id' => 'i-paper', 'properties' => ['Name' => $this->title('paper towels'), 'Aisle' => ['relation' => [['id' => 'a-costco']]], 'Need it' => ['checkbox' => true], 'Note' => ['rich_text' => []]]],
                ['id' => 'i-lime', 'properties' => ['Name' => $this->title('lime'), 'Aisle' => ['relation' => [['id' => 'a-produce']]], 'Need it' => ['checkbox' => false], 'Note' => ['rich_text' => []]]],
                ['id' => 'i-noodles', 'properties' => ['Name' => $this->title('noodles'), 'Aisle' => ['relation' => [['id' => 'a-produce']]], 'Need it' => ['checkbox' => false], 'Note' => ['rich_text' => []]]],
                ['id' => 'i-spare', 'properties' => ['Name' => $this->title('spare item'), 'Aisle' => ['relation' => []], 'Need it' => ['checkbox' => false], 'Note' => ['rich_text' => []]]],
                ['id' => 'i-mystery', 'properties' => ['Name' => $this->title('mystery'), 'Aisle' => ['relation' => []], 'Need it' => ['checkbox' => true], 'Note' => ['rich_text' => []]]],
            ], 'has_more' => false]),
            'api.notion.com/v1/data_sources/ds-meals/query' => Http::response(['results' => [
                ['id' => 'm-tacos', 'properties' => ['Name' => $this->title('Tacos'), 'Ingredients' => ['relation' => [['id' => 'i-lime'], ['id' => 'i-eggs']]], 'Notes' => ['rich_text' => [['plain_text' => 'Friday favorite']]]]],
                ['id' => 'm-pasta', 'properties' => ['Name' => $this->title('Pasta'), 'Ingredients' => ['relation' => [['id' => 'i-noodles']]], 'Notes' => ['rich_text' => []]]],
            ], 'has_more' => false]),
            'api.notion.com/v1/data_sources/ds-plan/query' => Http::response(['results' => [
                ['id' => 'p-1', 'properties' => ['Name' => $this->title('Tacos night'), 'Date' => ['date' => ['start' => $tomorrow]], 'Meal' => ['relation' => [['id' => 'm-tacos']]], 'Added to list' => ['checkbox' => false]]],
                ['id' => 'p-0', 'properties' => ['Name' => $this->title('Leftovers'), 'Date' => ['date' => ['start' => $today]], 'Meal' => ['relation' => []], 'Added to list' => ['checkbox' => true]]],
                ['id' => 'p-flex', 'properties' => ['Name' => $this->title('Pasta'), 'Date' => ['date' => null], 'Meal' => ['relation' => [['id' => 'm-pasta']]], 'Added to list' => ['checkbox' => false], 'Done' => ['checkbox' => false]]],
            ], 'has_more' => false]),
            'api.notion.com/v1/pages' => Http::response(['id' => 'a-new']),
            'api.notion.com/v1/pages/*' => Http::response(['id' => 'x']),
        ]);
    }

    public function test_the_list_is_grouped_by_aisle_in_store_order_with_unsorted_last(): void
    {
        $this->fakeNotion();

        $groups = app(Groceries::class)->shoppingList();

        $this->assertSame(['Costco', 'Produce', 'Unsorted'], array_column($groups, 'name'));
        $this->assertSame('egg', $groups[1]['items'][0]['name']);
        $this->assertSame('need 1', $groups[1]['items'][0]['note']);
        $this->assertCount(1, $groups[1]['items'], 'items nobody needs are left off');
    }

    public function test_requests_carry_the_token_and_api_version(): void
    {
        $this->fakeNotion();

        app(Groceries::class)->shoppingList();

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer secret_test') && $r->hasHeader('Notion-Version', '2025-09-03'));
    }

    public function test_the_shopping_tab_shows_items_and_ticking_one_unticks_it_in_notion(): void
    {
        $this->fakeNotion();

        Livewire::test(Shopping::class)->assertSee('Produce')->assertSee('egg')->assertSee('(need 1)')->assertSee('Costco')
            ->call('tick', 'i-eggs')->assertDontSee('egg');

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && str_ends_with($r->url(), '/v1/pages/i-eggs')
            && $r['properties']['Need it']['checkbox'] === false);
    }

    public function test_only_known_items_can_be_ticked(): void
    {
        $this->fakeNotion();

        $this->assertFalse(app(Groceries::class)->tick('some-other-page'));

        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
    }

    public function test_an_unconfigured_notion_says_so_without_calling_it(): void
    {
        config(['home-dashboard.notion.token' => null]);
        Http::fake();

        Livewire::test(Shopping::class)->assertSee('NOTION_TOKEN');

        Http::assertNothingSent();
    }

    public function test_a_failed_refresh_serves_the_last_good_list(): void
    {
        $this->fakeNotion();
        $this->assertNotNull(app(Groceries::class)->shoppingList());

        Cache::forget('home-dashboard:notion:v1:items');
        Http::fake(['api.notion.com/*' => Http::response('nope', 500)]);

        $this->assertNotNull(app(Groceries::class)->shoppingList());
        Livewire::test(Shopping::class)->assertOk()->assertSee('egg');
    }

    public function test_notion_down_with_nothing_cached_shows_a_message_not_an_error(): void
    {
        Http::fake(['api.notion.com/*' => Http::response('nope', 500)]);

        Livewire::test(Shopping::class)->assertOk()->assertSee('unavailable');
        Livewire::test(Meals::class)->assertOk()->assertSee('unavailable');
    }

    public function test_the_week_lists_planned_meals_with_their_ingredients(): void
    {
        $this->fakeNotion();

        $week = app(Groceries::class)->week();

        $this->assertCount(7, $week['days']);
        $this->assertSame('Leftovers', $week['days'][0]['meals'][0]['name']);
        $this->assertSame('Tacos', $week['days'][1]['meals'][0]['name']);
        $this->assertSame(['lime', 'egg'], array_column($week['days'][1]['meals'][0]['ingredients'], 'name'));
        $this->assertSame(['Pasta'], array_column($week['flexible'], 'name'), 'a meal without a day is offered to pick from');

        Livewire::test(Meals::class)->assertSee('Tacos')->assertSee('Friday favorite')->assertSee('Add ingredients to the list');
    }

    public function test_a_meal_without_a_day_can_be_made_tonights_and_only_such_meals(): void
    {
        $this->fakeNotion();

        Livewire::test(Meals::class)->assertSee('Pick one for the week')->assertSee('Pasta')->call('makeToday', 'p-flex');

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/p-flex') && $r['properties']['Date']['date']['start'] === now('America/Chicago')->toDateString());

        $this->assertFalse(app(Groceries::class)->makeToday('p-1'), 'a meal that already has a day is left alone');
    }

    public function test_the_plan_query_asks_for_this_week_and_undated_meals_that_are_not_done(): void
    {
        $this->fakeNotion();

        app(Groceries::class)->week();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'ds-plan/query')
            && isset($r['filter']['or'][1]['and'][0]['date']['is_empty'])
            && $r['filter']['or'][1]['and'][1]['property'] === 'Done');
    }

    public function test_adding_the_week_ticks_only_ingredients_not_already_needed_and_marks_the_plan(): void
    {
        $this->fakeNotion();

        Livewire::test(Meals::class)->call('addToList')->assertSet('added', 2);

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/i-lime') && $r['properties']['Need it']['checkbox'] === true);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/i-eggs'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/p-1') && $r['properties']['Added to list']['checkbox'] === true);
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/i-noodles'));
        $this->assertContains('lime', collect(app(Groceries::class)->shoppingList())->pluck('items')->flatten(1)->pluck('name')->all());
    }

    public function test_cached_data_in_an_unexpected_shape_is_ignored(): void
    {
        $this->fakeNotion();
        Cache::put('home-dashboard:notion:v1:items', ['oops' => 'not rows'], 600);

        $this->assertNotNull(app(Groceries::class)->shoppingList());
    }

    private function fakeKroger(?array $products = null): array
    {
        config(['home-dashboard.kroger' => ['client_id' => 'kid', 'client_secret' => 'ksecret', 'location_id' => '02500622']]);

        return [
            'api.kroger.com/v1/connect/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 1800]),
            'api.kroger.com/v1/products*' => Http::response(['data' => $products ?? [['description' => 'Mystery Brand', 'aisleLocations' => [['description' => 'AISLE 14', 'number' => '14']]]]]),
        ];
    }

    public function test_an_item_with_no_aisle_is_filed_where_kroger_says_it_is_and_the_row_is_created(): void
    {
        $this->fakeNotion($this->fakeKroger());

        $groups = app(Groceries::class)->shoppingList();

        $this->assertContains('Aisle 14', array_column($groups, 'name'));
        $this->assertNotContains('Unsorted', array_column($groups, 'name'));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/pages')
            && $r['properties']['Name']['title'][0]['text']['content'] === 'Aisle 14'
            && $r['properties']['Order']['number'] === 140
            && $r['properties']['Kroger aisle']['rich_text'][0]['text']['content'] === 'AISLE 14');
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/i-mystery') && isset($r['properties']['Aisle']['relation'][0]['id']));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.kroger.com/v1/products') && $r['filter.locationId'] === '02500622' && $r['filter.term'] === 'mystery' && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_an_existing_aisle_row_with_the_same_kroger_label_is_reused(): void
    {
        $this->fakeNotion($this->fakeKroger() + [
            'api.notion.com/v1/data_sources/ds-aisles/query' => Http::response(['results' => [
                ['id' => 'a-14', 'properties' => ['Name' => $this->title('Breakfast aisle'), 'Store' => ['select' => ['name' => 'Kroger']], 'Order' => ['number' => 5], 'Kroger aisle' => ['rich_text' => [['plain_text' => 'AISLE 14']]]]],
            ], 'has_more' => false]),
        ]);

        $groups = app(Groceries::class)->shoppingList();

        $this->assertContains('Breakfast aisle', array_column($groups, 'name'));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/pages'));
    }

    public function test_an_item_kroger_cannot_place_stays_unsorted_and_is_not_retried_on_every_refresh(): void
    {
        $this->fakeNotion($this->fakeKroger([]));

        $this->assertContains('Unsorted', array_column(app(Groceries::class)->shoppingList(), 'name'));
        app(Groceries::class)->shoppingList();

        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.kroger.com/v1/products')), 'asked Kroger once, then remembered');
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/pages'));
    }

    public function test_kroger_being_down_does_not_break_the_list(): void
    {
        $this->fakeNotion(['api.kroger.com/*' => Http::response('nope', 500)]);
        config(['home-dashboard.kroger' => ['client_id' => 'kid', 'client_secret' => 'ksecret', 'location_id' => '02500622']]);

        Livewire::test(Shopping::class)->assertOk()->assertSee('Unsorted');
    }

    public function test_an_either_or_item_falls_back_to_the_first_thing_it_names(): void
    {
        config(['home-dashboard.kroger' => ['client_id' => 'kid', 'client_secret' => 'ksecret', 'location_id' => '02500622']]);
        Http::fake([
            'api.kroger.com/v1/connect/oauth2/token' => Http::response(['access_token' => 'tok']),
            'api.kroger.com/v1/products*' => fn (Request $r) => Http::response(['data' => $r['filter.term'] === 'ground beef'
                ? [['aisleLocations' => [['description' => 'PACKAGED MEAT']]]]
                : []]),
        ]);

        $this->assertSame('PACKAGED MEAT', app(Kroger::class)->aisleFor('ground beef or turkey'));
        $this->assertNull(app(Kroger::class)->aisleFor('side for crockpot meal'));
    }

    public function test_the_file_items_command_files_the_whole_catalog_not_just_what_is_on_the_list(): void
    {
        $this->fakeNotion($this->fakeKroger());

        $this->artisan('home-dashboard:file-items')->expectsOutputToContain('0 without an aisle yet')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/i-spare'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/i-mystery'));
    }

    public function test_the_file_items_command_says_so_when_kroger_is_not_configured(): void
    {
        $this->fakeNotion();

        $this->artisan('home-dashboard:file-items')->assertFailed();
    }

    public function test_kroger_is_not_called_unless_configured(): void
    {
        $this->fakeNotion();

        app(Groceries::class)->shoppingList();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'api.kroger.com'));
    }
}
