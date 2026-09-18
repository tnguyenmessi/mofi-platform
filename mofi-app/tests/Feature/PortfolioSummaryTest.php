<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\MarketPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortfolioSummaryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function fixture(): Portfolio
    {
        config(['demo.enabled' => true, 'demo.login_password' => 'test-only-demo-password']);
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'demo@mofi.local')->firstOrFail()->portfolio;
    }

    private function endpoint(Portfolio $portfolio): string
    {
        return route('api.v1.portfolios.summary', $portfolio);
    }

    public function test_fixture_summary_and_history_match_documented_values(): void
    {
        $portfolio = $this->fixture();
        $this->travelTo('2030-01-01');

        $response = $this->actingAs($portfolio->user)->getJson($this->endpoint($portfolio));

        $response->assertOk()->assertJsonPath('data.cash', '14565000.00000000')
            ->assertJsonPath('data.cost_basis', '16515000.00000000')
            ->assertJsonPath('data.securities_value', '18750000.00000000')
            ->assertJsonPath('data.realized_pnl', '985000.00000000')
            ->assertJsonPath('data.unrealized_pnl', '2235000.00000000')
            ->assertJsonPath('data.net_income', '95000.00000000')
            ->assertJsonPath('data.total_pnl', '3315000.00000000')
            ->assertJsonPath('data.portfolio_value', '33315000.00000000')
            ->assertJsonPath('data.total_assets', '38315000.00000000')
            ->assertJsonPath('data.goals.0.progress_percent', '20.00')
            ->assertJsonPath('data.holdings.0.quantity', '150.00000000')
            ->assertJsonPath('data.status', 'complete')
            ->assertJsonPath('data.as_of', '2026-09-15')
            ->assertJsonPath('data.is_demo', true)
            ->assertJsonPath('data.reserved_cash', '0.00000000')
            ->assertJsonPath('data.available_cash', '14565000.00000000')
            ->assertJsonCount(30, 'data.history')
            ->assertJsonPath('data.history.0.date', '2026-08-17')
            ->assertJsonPath('data.history.0.value', '30000000.00000000')
            ->assertJsonPath('data.history.29.value', '33315000.00000000');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('transactions', 5);
    }

    public function test_open_buy_order_reduces_available_cash_without_changing_cash_history(): void
    {
        $portfolio = $this->fixture();
        $instrument = Instrument::where('symbol', 'MOFI')->firstOrFail();

        $this->actingAs($portfolio->user)->postJson(route('api.v1.orders.store', $portfolio), [
            'request_key' => (string) Str::uuid(), 'instrument_id' => $instrument->id,
            'side' => 'BUY', 'order_type' => 'LIMIT', 'quantity' => '10', 'limit_price' => '124000',
        ])->assertCreated()->assertJsonPath('data.status', 'OPEN');

        $this->getJson($this->endpoint($portfolio))->assertOk()
            ->assertJsonPath('data.cash', '14565000.00000000')
            ->assertJsonPath('data.reserved_cash', '1240000.00000000')
            ->assertJsonPath('data.available_cash', '13325000.00000000');
    }

    public function test_guest_receives_json_401_even_without_accept_header(): void
    {
        $this->get('/api/v1/portfolios/1/summary')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_other_owner_and_unknown_portfolio_return_404(): void
    {
        $portfolio = $this->fixture();
        $other = User::where('email', 'empty@mofi.local')->firstOrFail();
        $this->actingAs($other)->getJson($this->endpoint($portfolio))->assertNotFound();
        $this->getJson('/api/v1/portfolios/999999/summary')->assertNotFound();
        $this->getJson($this->endpoint($other->portfolio))->assertOk()
            ->assertJsonPath('data.total_assets', '0.00000000')
            ->assertJsonPath('data.total_pnl', '0.00000000')
            ->assertJsonPath('data.unrealized_return_percent', null)
            ->assertJsonCount(0, 'data.holdings')->assertJsonCount(0, 'data.goals');
    }

    public function test_deposit_changes_assets_but_not_profit_and_future_events_are_excluded(): void
    {
        $portfolio = $this->fixture();
        Transaction::factory()->for($portfolio)->create(['gross_amount' => '10000000', 'cash_delta' => '10000000']);
        Transaction::factory()->for($portfolio)->create(['trade_date' => '2026-09-16', 'gross_amount' => '999999', 'cash_delta' => '999999']);

        $this->actingAs($portfolio->user)->getJson($this->endpoint($portfolio))->assertOk()
            ->assertJsonPath('data.total_assets', '48315000.00000000')
            ->assertJsonPath('data.total_pnl', '3315000.00000000')
            ->assertJsonPath('data.history.29.value', '43315000.00000000');
    }

    public function test_new_buy_updates_quantity_and_basis_without_changing_wealth(): void
    {
        $portfolio = $this->fixture();
        $instrument = Instrument::where('symbol', 'MOFI')->firstOrFail();
        Transaction::factory()->for($portfolio)->create([
            'instrument_id' => $instrument->id, 'kind' => 'BUY', 'quantity' => '10', 'unit_price' => '125000',
            'gross_amount' => '1250000', 'cash_delta' => '-1250000',
        ]);

        $this->actingAs($portfolio->user)->getJson($this->endpoint($portfolio))->assertOk()
            ->assertJsonPath('data.cash', '13315000.00000000')
            ->assertJsonPath('data.cost_basis', '17765000.00000000')
            ->assertJsonPath('data.holdings.0.quantity', '160.00000000')
            ->assertJsonPath('data.securities_value', '20000000.00000000')
            ->assertJsonPath('data.total_assets', '38315000.00000000');
    }

    public function test_missing_quote_returns_null_totals_and_keeps_known_cash_and_manual_assets(): void
    {
        $portfolio = $this->fixture();
        $instrument = Instrument::where('symbol', 'MOFI')->firstOrFail();
        $instrument->marketPrices()->delete();
        MarketPrice::factory()->for($instrument)->create(['price_date' => '2026-09-16']);

        $this->actingAs($portfolio->user)->getJson($this->endpoint($portfolio))->assertOk()
            ->assertJsonPath('data.status', 'missing_price')
            ->assertJsonPath('data.total_assets', null)
            ->assertJsonPath('data.total_pnl', null)
            ->assertJsonPath('data.securities_value', null)
            ->assertJsonPath('data.known_total_assets', '19565000.00000000')
            ->assertJsonPath('data.holdings.0.market_value', null)
            ->assertJsonPath('data.history.29.value', null)
            ->assertJsonPath('data.history.0.value', '30000000.00000000');
    }

    public function test_partial_then_full_sale_leaves_no_cost_residue(): void
    {
        $portfolio = Portfolio::factory()->create();
        $instrument = Instrument::factory()->create();
        Transaction::factory()->for($portfolio)->create(['gross_amount' => '10', 'cash_delta' => '10']);
        Transaction::factory()->for($portfolio)->create(['kind' => 'BUY', 'instrument_id' => $instrument->id,
            'quantity' => '3', 'unit_price' => '1', 'gross_amount' => '3', 'fee' => '7', 'cash_delta' => '-10']);
        Transaction::factory()->for($portfolio)->create(['kind' => 'SELL', 'instrument_id' => $instrument->id,
            'quantity' => '1', 'unit_price' => '5', 'gross_amount' => '5', 'cash_delta' => '5']);
        MarketPrice::factory()->for($instrument)->create(['close' => '5']);
        $this->actingAs($portfolio->user)->getJson($this->endpoint($portfolio))->assertOk()
            ->assertJsonPath('data.cost_basis', '6.66666667')
            ->assertJsonPath('data.realized_pnl', '1.66666667');

        Transaction::factory()->for($portfolio)->create(['kind' => 'SELL', 'instrument_id' => $instrument->id,
            'quantity' => '2', 'unit_price' => '5', 'gross_amount' => '10', 'cash_delta' => '10']);
        $instrument->marketPrices()->delete();

        $this->getJson($this->endpoint($portfolio))->assertOk()
            ->assertJsonPath('data.cost_basis', '0.00000000')
            ->assertJsonPath('data.total_pnl', '5.00000000')
            ->assertJsonPath('data.status', 'complete')
            ->assertJsonCount(0, 'data.holdings');
    }

    public function test_latest_price_before_cutoff_is_used_without_leaking_future_price(): void
    {
        $portfolio = $this->fixture();
        $instrument = Instrument::where('symbol', 'MOFI')->firstOrFail();
        $instrument->marketPrices()->where('price_date', '2026-09-15')->delete();
        MarketPrice::factory()->for($instrument)->create(['price_date' => '2026-09-16', 'close' => '999999']);

        $this->actingAs($portfolio->user)->getJson($this->endpoint($portfolio))->assertOk()
            ->assertJsonPath('data.holdings.0.price_date', '2026-09-14')
            ->assertJsonPath('data.holdings.0.price', '124875.00000000');
    }
}
