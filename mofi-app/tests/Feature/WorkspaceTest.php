<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\Goal;
use App\Models\Instrument;
use App\Models\ManualAsset;
use App\Models\MarketPrice;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Models\WatchlistItem;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_market_survives_file_cache_round_trip_with_class_unserialization_disabled(): void
    {
        $path = storage_path('framework/cache/test-'.Str::uuid());
        config(['cache.default' => 'market_test', 'cache.stores.market_test' => ['driver' => 'file', 'path' => $path], 'cache.serializable_classes' => false]);
        $user = User::factory()->create();
        $instrument = Instrument::factory()->create(['tradable' => true]);
        MarketPrice::factory()->create(['instrument_id' => $instrument->id, 'price_date' => config('demo.simulation_date'), 'is_demo' => true, 'source' => 'demo']);
        try {
            $this->actingAs($user);
            foreach (['/market', '/transactions', '/dashboard'] as $route) {
                $this->get($route)->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->has('market', 1)->where('market.0.id', $instrument->id)->has('market.0.market_prices', 1));
            }
        } finally {
            Cache::purge('market_test');
            File::deleteDirectory($path);
        }
    }

    public function test_page_specific_props_preserve_copilot_goals_and_alerts_notification_workspace(): void
    {
        config(['demo.enabled' => true, 'demo.login_password' => 'test-only-demo-password']);
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'demo@mofi.local')->firstOrFail();
        $notification = Notification::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->get('/copilot')->assertInertia(fn (Assert $page) => $page
            ->has('goals', 1)->where('goals.0.user_id', $user->id)->has('market', 0));
        foreach (['alerts', 'notifications'] as $route) {
            $this->get('/'.$route)->assertInertia(fn (Assert $page) => $page
                ->has('alerts', 1)->has('market', 4)
                ->where('notifications.0.id', $notification->id));
        }
        $this->get('/transactions')->assertInertia(fn (Assert $page) => $page
            ->has('goals', 0)->has('alerts', 0)->has('learning', 0)->has('transactions.data', 5));
    }

    public function test_transaction_history_filters_by_kind_symbol_and_date(): void
    {
        config(['demo.enabled' => true, 'demo.login_password' => 'test-only-demo-password']);
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'demo@mofi.local')->firstOrFail();

        $this->actingAs($user)->get('/transactions?kind=BUY&symbol=MOFI&from=2026-08-20&to=2026-08-30')
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.kind', 'BUY')
                ->where('transactions.data.0.instrument.symbol', 'MOFI'));
    }

    public function test_transaction_history_rejects_invalid_filter_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/transactions?kind=INVALID&from=not-a-date')
            ->assertSessionHasErrors(['kind', 'from']);
    }

    public function test_strategy_and_simulation_saves_are_user_scoped_and_validate_allocation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->post('/workspace/strategies', [
            'name' => 'Cân bằng mới', 'risk_profile' => 'balanced', 'cash_percent' => 30, 'stock_percent' => 50, 'other_percent' => 20,
        ])->assertRedirect();
        $this->assertDatabaseHas('investment_strategies', ['user_id' => $user->id, 'name' => 'Cân bằng mới']);
        $this->post('/workspace/strategies', [
            'name' => 'Sai tỷ trọng', 'risk_profile' => 'balanced', 'cash_percent' => 20, 'stock_percent' => 20, 'other_percent' => 20,
        ])->assertStatus(422);
        $this->post('/workspace/scenarios', [
            'name' => 'VN-Index giảm 20%', 'shock_percent' => 20, 'before_value' => '10000000', 'after_value' => '8000000', 'change_value' => '-2000000',
        ])->assertRedirect();
        $this->assertDatabaseHas('simulation_scenarios', ['user_id' => $user->id, 'shock_percent' => 20]);
        $this->actingAs($other)->get('/strategies')->assertInertia(fn (Assert $page) => $page->has('strategies', 0));
    }

    public function test_register_normalizes_email_creates_empty_portfolio_and_rejects_duplicate(): void
    {
        $this->post('/register', ['name' => 'Người thử', 'email' => ' New@Example.com ', 'password' => 'secure-test-password', 'password_confirmation' => 'secure-test-password'])->assertRedirect('/dashboard');
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('secure-test-password', $user->password));
        $this->assertSame('VND', $user->portfolio->currency);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('Workspace', false)->where('summary.total_assets', '0.00000000')->has('summary.holdings', 0));
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/register', ['name' => 'Duplicate', 'email' => 'NEW@example.com', 'password' => 'secure-test-password', 'password_confirmation' => 'secure-test-password'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_authentication_rejects_wrong_password_and_accepts_normalized_email(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com', 'password' => 'secure-test-password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => ' MEMBER@example.com ', 'password' => 'secure-test-password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_all_workspace_pages_require_auth_and_render_only_current_user_data(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/workspace/tasks', ['title' => 'Blocked'])->assertRedirect('/login');
        config(['demo.enabled' => true, 'demo.login_password' => 'test-only-demo-password']);
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'demo@mofi.local')->firstOrFail();
        $this->actingAs($user);
        foreach (['dashboard', 'assets', 'portfolio', 'transactions', 'goals', 'market', 'watchlist', 'tasks', 'alerts', 'notifications', 'copilot', 'strategies', 'simulation', 'learn', 'community', 'settings'] as $route) {
            $response = $this->get('/'.$route)->assertOk();
            $response->assertInertia(fn (Assert $page) => $page->component('Workspace', false)->where('user.id', $user->id));
            if (in_array($route, ['dashboard', 'assets', 'portfolio', 'copilot', 'simulation'], true)) {
                $response->assertInertia(fn (Assert $page) => $page->where('summary.total_assets', '38315000.00000000'));
            }
        }
        $empty = User::where('email', 'empty@mofi.local')->firstOrFail();
        $this->actingAs($empty)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('summary.total_assets', '0.00000000')->has('goals', 0)->has('transactions.data', 0));
    }

    public function test_goals_assets_and_tasks_persist_and_cross_owner_writes_return_404(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/workspace/goals', ['name' => 'Mua nhà', 'target_amount' => '10000000', 'saved_amount' => '2000000'])->assertSessionHasNoErrors();
        $goal = Goal::sole();
        $this->assertSame($user->id, $goal->user_id);
        $this->post('/workspace/goals/'.$goal->id, ['name' => 'Mua nhà', 'target_amount' => '10000000', 'saved_amount' => '3000000'])->assertSessionHasNoErrors();
        $this->assertSame('3000000', $goal->fresh()->saved_amount);
        $this->post('/workspace/assets', ['name' => 'Tiết kiệm', 'current_value' => '5000000'])->assertSessionHasNoErrors();
        $asset = ManualAsset::sole();
        $this->post('/workspace/tasks', ['title' => 'Đọc báo cáo'])->assertSessionHasNoErrors();
        $task = Task::sole();
        $this->post('/workspace/tasks/'.$task->id, ['completed' => true])->assertSessionHasNoErrors();
        $this->assertNotNull($task->fresh()->completed_at);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('summary.total_assets', '5000000.00000000')->where('summary.cash', '0.00000000'));
        $other = User::factory()->create();
        $this->actingAs($other);
        foreach (['goals' => $goal, 'assets' => $asset, 'tasks' => $task] as $section => $row) {
            $this->post('/workspace/'.$section.'/'.$row->id, [])->assertNotFound();
            $this->delete('/workspace/'.$section.'/'.$row->id)->assertNotFound();
        }
        $this->actingAs($user)->delete('/workspace/goals/'.$goal->id)->assertRedirect();
        $this->assertDatabaseMissing('goals', ['id' => $goal->id]);
    }

    public function test_watchlist_and_lesson_progress_are_idempotent_and_owned(): void
    {
        $user = User::factory()->create();
        $instrument = Instrument::factory()->create();
        $this->actingAs($user);
        for ($i = 0; $i < 2; $i++) {
            $this->post('/workspace/watchlist', ['instrument_id' => $instrument->id])->assertSessionHasNoErrors();
            $this->post('/workspace/learn', ['lesson_slug' => 'cash-flow'])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('watchlist_items', 1);
        $this->assertDatabaseCount('learning_progress', 1);
        $this->post('/workspace/learn', ['lesson_slug' => 'unknown'])->assertSessionHasErrors('lesson_slug');
        $this->actingAs(User::factory()->create())->delete('/workspace/watchlist/'.WatchlistItem::sole()->id)->assertNotFound();
    }

    public function test_alerts_fire_on_transition_rearm_and_ignore_missing_prices(): void
    {
        $user = User::factory()->create();
        $instrument = Instrument::factory()->create();
        $price = MarketPrice::factory()->for($instrument)->create(['close' => '100', 'price_date' => config('demo.simulation_date'), 'source' => 'demo', 'is_demo' => true]);
        $this->actingAs($user)->post('/workspace/alerts', ['instrument_id' => $instrument->id, 'operator' => 'GTE', 'threshold' => '90'])->assertSessionHasNoErrors();
        $this->post('/workspace/alerts/check')->assertRedirect();
        $this->post('/workspace/alerts/check')->assertRedirect();
        $this->assertDatabaseCount('notifications', 1);
        $notice = Notification::sole();
        $this->assertSame('100.00000000', $notice->observed_price);
        $price->update(['close' => '80']);
        $this->post('/workspace/alerts/check');
        $price->update(['close' => '100']);
        $this->post('/workspace/alerts/check');
        $this->assertDatabaseCount('notifications', 2);
        $price->delete();
        $this->post('/workspace/alerts/check');
        $this->assertDatabaseCount('notifications', 2);
        $this->post('/workspace/notifications/'.$notice->id)->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);
        $other = User::factory()->create();
        $this->actingAs($other)->post('/workspace/notifications/'.$notice->id)->assertNotFound();
        $this->post('/workspace/alerts/'.AlertRule::sole()->id, ['enabled' => false])->assertNotFound();
    }

    public function test_invalid_amounts_and_owner_injection_do_not_change_records(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->post('/workspace/assets', ['name' => 'Test', 'current_value' => '-1'])->assertSessionHasErrors('current_value');
        $this->post('/workspace/goals', ['name' => 'Test', 'target_amount' => '0', 'saved_amount' => '0'])->assertSessionHasErrors('target_amount');
        $this->post('/workspace/tasks', ['title' => '<script>alert(1)</script>', 'user_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame($user->id, Task::sole()->user_id);
        $this->post('/workspace/settings', ['name' => 'Tên mới', 'email' => 'hijack@example.com'])->assertSessionHasNoErrors();
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertSame('Tên mới', $user->fresh()->name);
    }

    public function test_live_market_contract_cache_allowlist_and_upstream_failures(): void
    {
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(['data-api.binance.vision/*' => Http::response([[1789516800000, '1', '2', '1', '2', '3', 1789603199999]], 200)]);
        $this->getJson('/api/v1/market/live?symbol=BTCUSDT&days=7')->assertOk()->assertJsonPath('points.0.date', '2026-09-16')->assertJsonPath('points.0.close', 2)->assertJsonPath('days', 7);
        $this->getJson('/api/v1/market/live?symbol=BTCUSDT&days=7')->assertOk();
        Http::assertSentCount(1);
        $this->getJson('/api/v1/market/live?symbol=OTHER')->assertUnprocessable();
        $this->getJson('/api/v1/market/live?symbol[]=BTCUSDT')->assertUnprocessable();
        Cache::flush();
        Http::fake(['*' => Http::failedConnection()]);
        $this->getJson('/api/v1/market/live')->assertStatus(502)->assertJsonPath('message', 'Nguồn giá công khai hiện không phản hồi. Vui lòng thử lại sau.');
        Http::fake(['*' => Http::response(['bad' => 'response'], 200)]);
        $this->getJson('/api/v1/market/live')->assertStatus(502);
    }
}
