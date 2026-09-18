<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
        return array_merge(['request_key' => (string) Str::uuid(), 'instrument_id' => $instrumentId, 'side' => 'BUY', 'order_type' => 'LIMIT', 'quantity' => '10', 'limit_price' => '124000'], $extra);
    }

    public function test_limit_order_reserves_cash_and_stays_open_until_quote_reaches_limit(): void
    {
        [, $portfolio, $instrument] = $this->fixture();
        $response = $this->postJson(route('api.v1.orders.store', $portfolio), $this->payload($instrument->id));
        $response->assertCreated()->assertJsonPath('filled', false)->assertJsonPath('data.status', 'OPEN');
        $this->assertDatabaseHas('order_reservations', ['order_id' => $response->json('data.id'), 'cash_amount' => '1240000', 'released_at' => null]);
        $this->assertDatabaseCount('executions', 0);
        $this->assertDatabaseCount('transactions', 5);
    }

    public function test_market_order_fills_once_and_retry_replays(): void
    {
        [, $portfolio, $instrument] = $this->fixture();
        $payload = $this->payload($instrument->id, ['order_type' => 'MARKET', 'limit_price' => null]);
        $first = $this->postJson(route('api.v1.orders.store', $portfolio), $payload)->assertCreated();
        $second = $this->postJson(route('api.v1.orders.store', $portfolio), $payload)->assertOk();
        $first->assertJsonPath('filled', true)->assertJsonPath('data.status', 'FILLED');
        $second->assertJsonPath('replayed', true)->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('executions', 1);
        $this->assertDatabaseCount('transactions', 6);
    }

    public function test_limit_sell_requires_available_position_and_owner_scope(): void
    {
        [, $portfolio, $instrument] = $this->fixture();
        $this->postJson(route('api.v1.orders.store', $portfolio), $this->payload($instrument->id, ['side' => 'SELL', 'quantity' => '999999', 'limit_price' => '125000']))->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $other = User::factory()->create();
        $this->actingAs($other)->getJson(route('api.v1.orders.index', $portfolio))->assertNotFound();
        $this->actingAs($other)->postJson(route('api.v1.orders.store', $portfolio), $this->payload($instrument->id))->assertNotFound();
    }

    public function test_cancel_releases_reservation_and_is_idempotent(): void
    {
        [, $portfolio, $instrument] = $this->fixture();
        $created = $this->postJson(route('api.v1.orders.store', $portfolio), $this->payload($instrument->id))->assertCreated();
        $url = route('api.v1.orders.cancel', $created->json('data.id'));
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertDatabaseCount('executions', 0);
        $this->assertDatabaseHas('order_reservations', ['order_id' => $created->json('data.id')]);
        $this->assertNotNull(Order::find($created->json('data.id'))->reservation->released_at);
    }
}
