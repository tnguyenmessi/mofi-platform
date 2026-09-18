<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\PortfolioSummary;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function fixture(): Portfolio
    {
        config(['demo.enabled' => true, 'demo.login_password' => 'test-only-demo-password']);
        $this->seed(DemoDataSeeder::class);
        $portfolio = User::where('email', 'demo@mofi.local')->firstOrFail()->portfolio;
        $this->actingAs($portfolio->user);

        return $portfolio;
    }

    private function url(Portfolio $portfolio): string
    {
        return route('api.v1.transactions.store', $portfolio);
    }

    public function test_export_filters_rows_and_rejects_another_owner(): void
    {
        $portfolio = $this->fixture();
        $url = route('api.v1.transactions.export', $portfolio);
        $response = $this->get($url.'?kind=BUY&symbol=MOFI&from=2026-08-20&to=2026-08-30');
        $response->assertOk()->assertDownload('mofi-giao-dich.csv');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('BUY,MOFI', $csv);
        $this->assertStringNotContainsString('DEPOSIT', $csv);
        $this->assertStringNotContainsString('request_hash', $csv);
        $this->getJson($url.'?kind=INVALID')->assertUnprocessable();
        $this->getJson($url.'?from=2026-09-10&to=2026-09-01')->assertUnprocessable();
        $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
    }

    public function test_receipt_is_stable_when_replayed_and_hides_request_hash(): void
    {
        $portfolio = $this->fixture();
        $payload = $this->payload();
        $first = $this->postJson($this->url($portfolio), $payload)->assertCreated();
        $second = $this->postJson($this->url($portfolio), $payload)->assertOk();
        $first->assertJsonPath('receipt.replayed', false)->assertJsonMissingPath('data.request_hash');
        $second->assertJsonPath('receipt.replayed', true)->assertJsonPath('receipt.id', $first->json('receipt.id'));
        $this->assertSame($first->json('data.cash_delta'), $first->json('receipt.cash_delta'));
        $this->assertSame($first->json('receipt.created_at'), $second->json('receipt.created_at'));
        $this->assertDatabaseCount('transactions', 6);
    }

    private function payload(string $kind = 'DEPOSIT'): array
    {
        $data = ['kind' => $kind, 'request_key' => (string) Str::uuid()];
        if (in_array($kind, ['BUY', 'SELL'], true)) {
            return $data + ['instrument_id' => Instrument::where('symbol', 'MOFI')->value('id'), 'quantity' => '10', 'unit_price' => '125000'];
        }
        if ($kind === 'DIVIDEND') {
            $data['instrument_id'] = Instrument::where('symbol', 'MOFI')->value('id');
        }

        return $data + ['gross_amount' => '1000000'];
    }

    public static function operations(): array
    {
        return [
            'deposit' => ['DEPOSIT', '15565000.00000000', '39315000.00000000', '3315000.00000000'],
            'withdraw' => ['WITHDRAW', '13565000.00000000', '37315000.00000000', '3315000.00000000'],
            'buy' => ['BUY', '13315000.00000000', '38315000.00000000', '3315000.00000000'],
            'sell' => ['SELL', '15815000.00000000', '38315000.00000000', '3315000.00000000'],
            'dividend' => ['DIVIDEND', '15565000.00000000', '39315000.00000000', '4315000.00000000'],
        ];
    }

    #[DataProvider('operations')]
    public function test_records_supported_operations_and_returns_updated_summary(string $kind, string $cash, string $assets, string $profit): void
    {
        $portfolio = $this->fixture();
        $payload = $this->payload($kind);
        $cacheKey = PortfolioSummary::cacheKey($portfolio);
        Cache::put($cacheKey, ['stale' => true], 15);
        $response = $this->postJson($this->url($portfolio), $payload);
        $this->assertFalse(Cache::has($cacheKey));
        $response->assertCreated()->assertJsonPath('replayed', false)
            ->assertJsonPath('summary.cash', $cash)->assertJsonPath('summary.total_assets', $assets)
            ->assertJsonPath('summary.total_pnl', $profit)->assertJsonPath('data.trade_date', '2026-09-15');
        $this->assertDatabaseHas('transactions', ['id' => $response->json('data.id'), 'kind' => $kind, 'user_id' => $portfolio->user_id, 'request_key' => $payload['request_key']]);
        $this->assertArrayNotHasKey('request_hash', $response->json('data'));
        $this->assertDatabaseCount('transactions', 6);
        $this->getJson(route('api.v1.portfolios.summary', $portfolio))->assertJsonPath('data.cash', $cash);
    }

    public function test_equivalent_retry_returns_same_receipt_and_conflicting_retry_returns_409(): void
    {
        $portfolio = $this->fixture();
        $payload = $this->payload('BUY');
        $first = $this->postJson($this->url($portfolio), $payload)->assertCreated();
        $retry = array_replace($payload, ['quantity' => '010', 'unit_price' => '125000.00000000', 'fee' => '00', 'tax' => '0', 'request_key' => strtoupper($payload['request_key'])]);
        $this->postJson($this->url($portfolio), $retry)->assertOk()->assertJsonPath('replayed', true)
            ->assertJsonPath('data.id', $first->json('data.id'));
        $this->postJson($this->url($portfolio), array_replace($payload, ['quantity' => '11']))->assertConflict();
        $this->assertDatabaseCount('transactions', 6);
    }

    public function test_retry_after_full_sale_succeeds_without_rechecking_old_inventory(): void
    {
        $portfolio = $this->fixture();
        $payload = array_replace($this->payload('SELL'), ['quantity' => '150']);
        $this->postJson($this->url($portfolio), $payload)->assertCreated()->assertJsonCount(0, 'summary.holdings');
        $this->postJson($this->url($portfolio), $payload)->assertOk()->assertJsonPath('replayed', true);
        $this->postJson($this->url($portfolio), array_replace($payload, ['request_key' => (string) Str::uuid()]))
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseCount('transactions', 6);
    }

    public static function invalidInputs(): array
    {
        return [
            'negative fee' => ['BUY', ['fee' => '-1'], 'fee'],
            'fractional shares' => ['BUY', ['quantity' => '0.5'], 'quantity'],
            'zero quantity' => ['BUY', ['quantity' => '0'], 'quantity'],
            'too many shares' => ['BUY', ['quantity' => '1000000001'], 'quantity'],
            'zero price' => ['BUY', ['unit_price' => '0'], 'unit_price'],
            'excess price' => ['BUY', ['unit_price' => '1000000000001'], 'unit_price'],
            'exponent' => ['BUY', ['unit_price' => '1e5'], 'unit_price'],
            'too precise' => ['BUY', ['unit_price' => '1.000000001'], 'unit_price'],
            'float input' => ['BUY', ['unit_price' => 125000.1], 'unit_price'],
            'zero gross' => ['DEPOSIT', ['gross_amount' => '0'], 'gross_amount'],
            'oversized gross' => ['DEPOSIT', ['gross_amount' => '100000000000000000000'], 'gross_amount'],
            'forged cash' => ['BUY', ['cash_delta' => '99999'], 'cash_delta'],
            'forged owner' => ['DEPOSIT', ['user_id' => 999], 'user_id'],
            'backdate' => ['BUY', ['trade_date' => '2026-08-01'], 'trade_date'],
            'forged gross' => ['BUY', ['gross_amount' => '1'], 'gross_amount'],
            'cash fees' => ['DEPOSIT', ['fee' => '1'], 'fee'],
            'missing instrument' => ['BUY', ['instrument_id' => 999999], 'instrument_id'],
            'fees exceed proceeds' => ['SELL', ['fee' => '1250001'], 'fee'],
            'oversell' => ['SELL', ['quantity' => '151'], 'quantity'],
            'overdraw' => ['WITHDRAW', ['gross_amount' => '14565001'], 'gross_amount'],
            'buy exceeds cash' => ['BUY', ['quantity' => '150'], 'gross_amount'],
            'bad key' => ['DEPOSIT', ['request_key' => 'invalid'], 'request_key'],
            'bad kind' => ['DEPOSIT', ['kind' => 'TRANSFER'], 'kind'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_returns_422_and_does_not_write(string $kind, array $changes, string $field): void
    {
        $portfolio = $this->fixture();
        $this->postJson($this->url($portfolio), array_replace($this->payload($kind), $changes))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('transactions', 5);
    }

    public function test_guests_and_other_owners_cannot_read_or_write(): void
    {
        $portfolio = Portfolio::factory()->create();
        $this->postJson($this->url($portfolio), [])->assertUnauthorized();
        $other = User::factory()->create();
        $this->assertTrue(Gate::forUser($portfolio->user)->allows('recordTransaction', $portfolio));
        $this->assertSame(404, Gate::forUser($other)->inspect('recordTransaction', $portfolio)->status());
        $this->actingAs($other)->postJson($this->url($portfolio), [])->assertNotFound();
        $this->getJson($this->url($portfolio))->assertNotFound();
        $this->postJson('/api/v1/portfolios/999999/transactions', [])->assertNotFound();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_history_is_paginated_and_has_no_update_or_delete_route(): void
    {
        $portfolio = $this->fixture();
        $response = $this->getJson($this->url($portfolio).'?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 5);
        $this->assertSame('DIVIDEND', $response->json('data.0.kind'));
        $this->getJson($this->url($portfolio).'?per_page=101')->assertUnprocessable();
        $this->patchJson($this->url($portfolio).'/'.$response->json('data.0.id'), [])->assertNotFound();
        $this->deleteJson($this->url($portfolio).'/'.$response->json('data.0.id'))->assertNotFound();
    }

    public function test_rounding_fees_and_tax_are_server_calculated(): void
    {
        $portfolio = $this->fixture();
        $this->postJson($this->url($portfolio), array_replace($this->payload('BUY'), ['quantity' => '1', 'unit_price' => '100.5', 'fee' => '2', 'tax' => '3']))
            ->assertCreated()->assertJsonPath('data.gross_amount', '101')->assertJsonPath('data.cash_delta', '-106');
        $this->postJson($this->url($portfolio), array_replace($this->payload('DIVIDEND'), ['gross_amount' => '100', 'fee' => '2', 'tax' => '3']))
            ->assertCreated()->assertJsonPath('data.cash_delta', '95');
    }

    public function test_failure_while_building_summary_rolls_back_receipt(): void
    {
        $portfolio = $this->fixture();
        $cacheKey = PortfolioSummary::cacheKey($portfolio);
        Cache::put($cacheKey, ['existing' => true], 15);
        $this->mock(PortfolioSummary::class)->shouldReceive('forPortfolio')->once()->andThrow(new \RuntimeException('Calculation unavailable'));
        $this->postJson($this->url($portfolio), $this->payload())->assertServerError();
        $this->assertDatabaseCount('transactions', 5);
        $this->assertSame(['existing' => true], Cache::get($cacheKey));
    }

    public function test_non_tradable_instrument_is_rejected(): void
    {
        $portfolio = $this->fixture();
        $id = Instrument::where('symbol', 'BTC-DEMO')->value('id');
        $this->postJson($this->url($portfolio), array_replace($this->payload('BUY'), ['instrument_id' => $id]))->assertUnprocessable()->assertJsonValidationErrors('instrument_id');
    }

    public function test_csrf_is_required_outside_test_bypass(): void
    {
        $portfolio = $this->fixture();
        $this->app['env'] = 'local';
        $this->postJson($this->url($portfolio), $this->payload())->assertStatus(419);
        $this->assertDatabaseCount('transactions', 5);
    }
}
