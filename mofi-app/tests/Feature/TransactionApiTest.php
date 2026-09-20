<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\User;
use App\Services\PortfolioSummary;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    private function payload(string $kind = 'DEPOSIT', array $extra = []): array
    {
        return array_merge(['kind' => $kind, 'request_key' => (string) Str::uuid(), 'gross_amount' => '1000000'], $extra);
    }

    public function test_export_keeps_seeded_trade_history_readable_and_rejects_another_owner(): void
    {
        $portfolio = $this->fixture();
        $url = route('api.v1.transactions.export', $portfolio);
        $response = $this->get($url.'?kind=BUY&symbol=MOFI&from=2026-08-20&to=2026-08-30');
        $response->assertOk()->assertDownload('mofi-giao-dich.csv');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('BUY,MOFI', $csv);
        $this->assertStringNotContainsString('request_hash', $csv);
        $this->getJson($url.'?kind=INVALID')->assertUnprocessable();
        $this->getJson($url.'?from=2026-09-10&to=2026-09-01')->assertUnprocessable();
        $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
    }

    public function test_receipt_is_stable_when_replayed(): void
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

    public static function cashOperations(): array
    {
        return [
            'deposit' => ['DEPOSIT', '15565000.00000000', '39315000.00000000'],
            'withdraw' => ['WITHDRAW', '13565000.00000000', '37315000.00000000'],
        ];
    }

    #[DataProvider('cashOperations')]
    public function test_records_only_cash_operations(string $kind, string $cash, string $assets): void
    {
        $portfolio = $this->fixture();
        $response = $this->postJson($this->url($portfolio), $this->payload($kind));
        $response->assertCreated()->assertJsonPath('replayed', false)
            ->assertJsonPath('summary.cash', $cash)->assertJsonPath('summary.total_assets', $assets)
            ->assertJsonPath('summary.total_pnl', '3315000.00000000')
            ->assertJsonPath('data.trade_date', '2026-09-15');
        $this->assertDatabaseHas('transactions', ['id' => $response->json('data.id'), 'kind' => $kind]);
        $this->assertDatabaseCount('transactions', 6);
    }

    public function test_direct_stock_operations_are_rejected(): void
    {
        $portfolio = $this->fixture();
        foreach (['BUY', 'SELL', 'DIVIDEND'] as $kind) {
            $response = $this->postJson($this->url($portfolio), $this->payload($kind, [
                'instrument_id' => 1, 'quantity' => '10', 'unit_price' => '125000',
            ]));
            $response->assertUnprocessable()->assertJsonValidationErrors('kind');
        }
        $this->assertDatabaseCount('transactions', 5);
    }

    public function test_trade_fields_are_not_accepted_on_cash_operations(): void
    {
        $portfolio = $this->fixture();
        $this->postJson($this->url($portfolio), $this->payload('DEPOSIT', [
            'instrument_id' => 1, 'quantity' => '10', 'unit_price' => '125000', 'fee' => '1',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['instrument_id', 'quantity', 'unit_price', 'fee']);
        $this->assertDatabaseCount('transactions', 5);
    }

    public function test_pending_cash_reservation_blocks_withdrawal(): void
    {
        $portfolio = $this->fixture();
        $this->postJson(route('api.v1.orders.store', $portfolio), [
            'request_key' => (string) Str::uuid(), 'instrument_id' => 1, 'side' => 'BUY', 'order_type' => 'LIMIT', 'quantity' => '10', 'limit_price' => '100000',
        ])->assertCreated();
        $this->postJson($this->url($portfolio), $this->payload('WITHDRAW', ['gross_amount' => '14565000']))->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 5);
    }

    public function test_history_is_paginated_and_has_no_update_or_delete_route(): void
    {
        $portfolio = $this->fixture();
        $response = $this->getJson(route('api.v1.transactions.index', $portfolio).'?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 5);
        $this->assertSame('DIVIDEND', $response->json('data.0.kind'));
        $this->getJson(route('api.v1.transactions.index', $portfolio).'?per_page=101')->assertUnprocessable();
        $this->patchJson(route('api.v1.transactions.index', $portfolio).'/'.$response->json('data.0.id'), [])->assertNotFound();
        $this->deleteJson(route('api.v1.transactions.index', $portfolio).'/'.$response->json('data.0.id'))->assertNotFound();
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

    public function test_csrf_is_required_outside_test_bypass(): void
    {
        $portfolio = $this->fixture();
        $this->app['env'] = 'local';
        $this->postJson($this->url($portfolio), $this->payload())->assertStatus(419);
        $this->assertDatabaseCount('transactions', 5);
    }
}
