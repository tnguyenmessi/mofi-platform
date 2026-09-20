<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaperTradingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function fixture(): array
    {
        config(['demo.enabled' => true, 'demo.login_password' => 'test-only-demo-password']);
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'demo@mofi.local')->firstOrFail();
        $this->actingAs($user);

        return [$user, $user->portfolio, Instrument::where('symbol', 'MOFI')->firstOrFail()];
    }

    private function payload(int $instrumentId, array $extra = []): array
    {
        return array_merge([
            'request_key' => (string) Str::uuid(), 'instrument_id' => $instrumentId, 'side' => 'BUY',
            'order_type' => 'LIMIT', 'quantity' => '10', 'limit_price' => '124000',
        ], $extra);
    }

    public function test_market_board_returns_quote_levels_and_simulated_time(): void
    {
        [, , $instrument] = $this->fixture();
        $response = $this->getJson(route('api.v1.instruments.market-board', $instrument))->assertOk();
        $response->assertJsonPath('instrument.symbol', 'MOFI')
            ->assertJsonCount(3, 'quote.bids')->assertJsonCount(3, 'quote.asks')
            ->assertJsonPath('session.simulated_interval_minutes', 5)
            ->assertJsonPath('session.current_tick', 0);
        $this->assertNotEmpty($response->json('session.simulated_time'));
        $this->assertNotEmpty($response->json('quote.last_price'));
    }

    public function test_board_advances_once_after_real_interval_and_not_on_every_poll(): void
    {
        config(['demo.market_real_interval_seconds' => 5]);
        [, , $instrument] = $this->fixture();
        $first = $this->getJson(route('api.v1.instruments.market-board', $instrument))->assertOk();
        $this->assertSame(0, $first->json('session.current_tick'));
        $second = $this->getJson(route('api.v1.instruments.market-board', $instrument))->assertOk();
        $this->assertSame(0, $second->json('session.current_tick'));
        Carbon::setTestNow(now()->addSeconds(6));
        try {
            $third = $this->getJson(route('api.v1.instruments.market-board', $instrument))->assertOk();
            $this->assertSame(1, $third->json('session.current_tick'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_market_buy_consumes_ask_and_writes_uuid_safe_execution(): void
    {
        [, $portfolio, $instrument] = $this->fixture();
        $board = $this->getJson(route('api.v1.instruments.market-board', $instrument))->json();
        $response = $this->postJson(route('api.v1.orders.store', $portfolio), $this->payload($instrument->id, [
            'order_type' => 'MARKET', 'limit_price' => null, 'quantity' => '10',
        ]))->assertCreated()->assertJsonPath('filled', true)->assertJsonPath('data.status', 'FILLED');
        $execution = $response->json('data.executions.0');
        $this->assertSame($board['quote']['asks'][0]['price'], $execution['unit_price']);
        $this->assertDatabaseCount('executions', 1);
        $this->assertDatabaseHas('executions', ['order_id' => $response->json('data.id'), 'source' => 'demo_market_board']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', (string) \DB::table('executions')->value('execution_key'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', (string) \DB::table('transactions')->where('id', 6)->value('request_key'));
    }

    public function test_limit_buy_below_ask_stays_open_after_next_tick(): void
    {
        config(['demo.market_real_interval_seconds' => 1]);
        [, $portfolio, $instrument] = $this->fixture();
        $board = $this->getJson(route('api.v1.instruments.market-board', $instrument))->json();
        $order = $this->postJson(route('api.v1.orders.store', $portfolio), $this->payload($instrument->id, [
            'limit_price' => (string) ((int) $board['quote']['floor_price']),
        ]))->assertCreated()->assertJsonPath('data.status', 'OPEN');
        Carbon::setTestNow(now()->addSeconds(2));
        try {
            $this->getJson(route('api.v1.instruments.market-board', $instrument))->assertOk();
        } finally {
            Carbon::setTestNow();
        }
        $this->assertDatabaseHas('orders', ['id' => $order->json('data.id'), 'status' => 'OPEN']);
        $this->assertDatabaseCount('executions', 0);
    }

    public function test_large_market_buy_can_be_partially_filled_across_depth_levels(): void
    {
        [, $portfolio, $instrument] = $this->fixture();
        $this->postJson(route('api.v1.transactions.store', $portfolio), [
            'request_key' => (string) Str::uuid(), 'kind' => 'DEPOSIT', 'gross_amount' => '100000000',
        ])->assertCreated();
        $board = $this->getJson(route('api.v1.instruments.market-board', $instrument))->json();
        $depth = array_sum(array_map(fn (array $level): int => (int) $level['quantity'], $board['quote']['asks'])) + 50;
        $order = $this->postJson(route('api.v1.orders.store', $portfolio), $this->payload($instrument->id, [
            'order_type' => 'MARKET', 'limit_price' => null, 'quantity' => (string) $depth,
        ]))->assertCreated();
        $orderRow = Order::findOrFail($order->json('data.id'));
        $this->assertSame('PARTIALLY_FILLED', $orderRow->status);
        $this->assertGreaterThan(0, (float) $orderRow->filled_quantity);
        $this->assertSame(3, $orderRow->executions()->count());
    }

    public function test_cancel_releases_remaining_reservation_and_is_idempotent(): void
    {
        [, $portfolio, $instrument] = $this->fixture();
        $created = $this->postJson(route('api.v1.orders.store', $portfolio), $this->payload($instrument->id))->assertCreated();
        $url = route('api.v1.orders.cancel', $created->json('data.id'));
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertNotNull(Order::findOrFail($created->json('data.id'))->reservation->released_at);
        $this->assertDatabaseCount('executions', 0);
    }

    public function test_order_retry_is_idempotent_and_owner_isolation_is_preserved(): void
    {
        [, $portfolio, $instrument] = $this->fixture();
        $payload = $this->payload($instrument->id, ['order_type' => 'MARKET', 'limit_price' => null]);
        $first = $this->postJson(route('api.v1.orders.store', $portfolio), $payload)->assertCreated();
        $this->postJson(route('api.v1.orders.store', $portfolio), $payload)->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('executions', 1);
        $other = User::factory()->create();
        $this->actingAs($other)->getJson(route('api.v1.orders.index', $portfolio))->assertNotFound();
        $this->actingAs($portfolio->user)->getJson(route('api.v1.orders.index', $portfolio))->assertOk();
        $this->assertSame($first->json('data.id'), Order::sole()->id);
    }
}
