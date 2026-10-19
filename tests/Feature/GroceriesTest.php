<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use Carbon\CarbonImmutable;
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
    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday, so "this week" (Sunday to Saturday) holds today and tomorrow whatever day the suite runs.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-21 12:00', 'America/Chicago'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

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

    public function test_the_dashboard_list_is_grouped_by_store_with_kroger_first(): void
    {
        $this->fakeNotion();

        $groups = app(Groceries::class)->shoppingList();

        $this->assertSame(['Kroger', 'Costco'], array_column($groups, 'name'));
        $this->assertSame(['egg', 'mystery'], array_column($groups[0]['items'], 'name'), 'an item with no aisle counts as Kroger, alphabetical');
        $this->assertSame('need 1', $groups[0]['items'][0]['note']);
        $this->assertSame(['paper towels'], array_column($groups[1]['items'], 'name'));
        $this->assertNotContains('lime', array_column($groups[0]['items'], 'name'), 'items nobody needs are left off');
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

        Livewire::test(Shopping::class)->assertSee('Kroger')->assertSee('egg')->assertSee('(need 1)')->assertSee('Costco')
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
        $this->assertSame('Leftovers', $week['days'][3]['meals'][0]['name']);
        $this->assertSame('Tacos', $week['days'][4]['meals'][0]['name']);
        $this->assertSame(['lime', 'egg'], array_column($week['days'][4]['meals'][0]['ingredients'], 'name'));
        $this->assertSame(['Pasta'], array_column($week['flexible'], 'name'), 'a meal without a day is offered to pick from');
        $this->assertSame('2026-10-18', $week['days'][0]['date'], 'the week starts on the Sunday before today');
        $this->assertSame([false, false, false, true, false, false, false], array_column($week['days'], 'today'));
        $this->assertTrue($week['days'][2]['past']);
        $this->assertFalse($week['days'][3]['past']);

        Livewire::test(Meals::class)->assertSee('Tacos')->assertDontSee('Friday favorite')->call('pick', $week['days'][4]['date'])->assertSee('Friday favorite')->assertSee('Clear this meal');
    }

    public function test_a_meal_without_a_day_can_be_made_tonights_and_only_such_meals(): void
    {
        $this->fakeNotion();

        Livewire::test(Meals::class)->assertSee('Pick one for the week')->assertSee('Pasta')->call('makeToday', 'p-flex');

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/p-flex') && $r['properties']['Date']['date']['start'] === now('America/Chicago')->toDateString());

        $this->assertFalse(app(Groceries::class)->makeToday('p-1'), 'a meal that already has a day is left alone');
    }

    public function test_a_day_can_be_given_a_meal_from_the_weeks_list_and_cleared_again(): void
    {
        $this->fakeNotion();

        Livewire::test(Meals::class)->call('pick', '2026-10-23')->assertSet('picking', '2026-10-23')->assertSee('Pasta')
            ->call('assign', 'p-flex', '2026-10-23')->assertSet('picking', '2026-10-23')->call('close')->assertSet('picking', null);

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/p-flex') && $r['properties']['Date']['date']['start'] === '2026-10-23');

        $groceries = app(Groceries::class);
        $this->assertFalse($groceries->assign('p-flex', '2026-11-30'), 'only days of this week');
        $this->assertFalse($groceries->assign('p-1', '2026-10-23'), 'a meal that already has a day is left alone');
        $this->assertFalse($groceries->clear('p-flex'), 'an undated meal has nothing to clear');

        $this->assertTrue($groceries->clear('p-1'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/p-1') && array_key_exists('date', $r['properties']['Date']) && $r['properties']['Date']['date'] === null);
    }

    public function test_tapping_a_meal_without_a_day_opens_it_with_its_ingredients_and_a_day_can_be_chosen(): void
    {
        $this->fakeNotion();

        Livewire::test(Meals::class)->call('view', 'p-flex')->assertSet('viewing', 'p-flex')->assertSee('Give it a day')
            ->call('assign', 'p-flex', '2026-10-24')->assertSet('viewing', null);

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/p-flex') && $r['properties']['Date']['date']['start'] === '2026-10-24');
    }

    public function test_the_plan_query_asks_for_this_week_and_undated_meals_that_are_not_done(): void
    {
        $this->fakeNotion();

        app(Groceries::class)->week();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'ds-plan/query')
            && isset($r['filter']['or'][1]['and'][0]['date']['is_empty'])
            && $r['filter']['or'][1]['and'][1]['property'] === 'Done'
            && $r['filter']['or'][0]['and'][0]['date']['on_or_after'] === '2026-10-18'
            && $r['filter']['or'][0]['and'][1]['date']['on_or_before'] === '2026-10-24'
            && $r['filter']['or'][1]['and'][2]['created_time']['on_or_after'] === '2026-10-16');
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

        app(Groceries::class)->shoppingList();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/pages')
            && $r['properties']['Name']['title'][0]['text']['content'] === 'Aisle 14'
            && $r['properties']['Order']['number'] == 1014
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

        app(Groceries::class)->shoppingList();

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/pages'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/i-mystery') && $r['properties']['Aisle']['relation'][0]['id'] === 'a-14');
    }

    public function test_an_item_kroger_cannot_place_stays_unsorted_and_is_not_retried_on_every_refresh(): void
    {
        $this->fakeNotion($this->fakeKroger([]));

        app(Groceries::class)->shoppingList();
        app(Groceries::class)->shoppingList();

        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.kroger.com/v1/products')), 'asked Kroger once, then remembered');
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/pages'));
    }

    public function test_kroger_being_down_does_not_break_the_list(): void
    {
        $this->fakeNotion(['api.kroger.com/*' => Http::response('nope', 500)]);
        config(['home-dashboard.kroger' => ['client_id' => 'kid', 'client_secret' => 'ksecret', 'location_id' => '02500622']]);

        Livewire::test(Shopping::class)->assertOk()->assertSee('mystery');
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

    public function test_the_walking_order_follows_the_store(): void
    {
        $kroger = app(Kroger::class);
        $walk = ['PRODUCE', 'PRODUCE TABLE 3', 'DELI', 'DELI 5', 'BAKERY 2', 'AISLE 2', 'AISLE 14', 'MEAT', 'PACKAGED MEAT', 'AISLE 16', 'AISLE 20', 'AISLE 34', 'AISLE 54', 'DAIRY', 'AISLE 56', 'AISLE 59', 'PHARMACY', 'AISLE 62', 'AISLE 55', 'AISLE 31', 'AISLE 5', 'AISLE 1', 'CHECKLANE 4'];

        $orders = array_map(fn ($label) => $kroger->order($label), $walk);

        $sorted = $orders;
        sort($sorted);
        $this->assertSame($sorted, $orders, 'labels are listed in walking order, so their orders must already be ascending');
        $this->assertSame(1014.0, $kroger->order('AISLE 14'));
    }

    public function test_an_item_can_be_added_by_typing_and_a_known_one_just_goes_back_on_the_list(): void
    {
        $this->fakeNotion();

        Livewire::test(Shopping::class)->set('query', 'sparkling water')->call('add')->assertSee('Added sparkling water.')->assertSet('query', '');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/pages')
            && $r['parent']['data_source_id'] === 'ds-items'
            && $r['properties']['Name']['title'][0]['text']['content'] === 'sparkling water'
            && $r['properties']['Need it']['checkbox'] === true);

        Http::fake(['api.notion.com/*' => Http::response(['id' => 'x'])]);
        $this->assertSame('lime', app(Groceries::class)->addByName('LIME'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/i-lime') && $r['properties']['Need it']['checkbox'] === true);
    }

    public function test_typing_suggests_items_that_are_not_on_the_list_and_tapping_one_adds_it(): void
    {
        $this->fakeNotion();

        Livewire::test(Shopping::class)->set('query', 'li')->assertSee('+ lime')->assertDontSee('+ egg')
            ->call('addItem', 'i-lime')->assertSet('query', '');

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/v1/pages/i-lime') && $r['properties']['Need it']['checkbox'] === true);
        $this->assertFalse(app(Groceries::class)->need('not-an-item'));
    }

    public function test_a_blank_or_oversized_name_is_not_added(): void
    {
        $this->fakeNotion();

        $this->assertNull(app(Groceries::class)->addByName('   '));
        $this->assertNull(app(Groceries::class)->addByName(str_repeat('x', 121)));

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/pages'));
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
