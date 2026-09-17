<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\Goal;
use App\Models\Instrument;
use App\Models\LearningProgress;
use App\Models\ManualAsset;
use App\Models\MarketPrice;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WatchlistItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function enableDemo(): void
    {
        config(['demo.enabled' => true, 'demo.login_password' => 'test-only-demo-password']);
    }

    public function test_fixture_matches_documented_balances_and_is_repeatable(): void
    {
        $this->enableDemo();
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@mofi.local')->firstOrFail();
        $empty = User::where('email', 'empty@mofi.local')->firstOrFail();
        $this->assertTrue(Hash::check('test-only-demo-password', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertSame(0, $empty->portfolio->transactions()->count());
        $rows = $user->portfolio->transactions()->orderBy('trade_date')->orderBy('id')->get();
        $this->assertSame(['DEPOSIT', 'BUY', 'BUY', 'SELL', 'DIVIDEND'], $rows->pluck('kind')->all());
        $this->assertSame(14565000, (int) $rows->sum('cash_delta'));
        $this->assertSame(150, (int) ($rows->where('kind', 'BUY')->sum('quantity') - $rows->where('kind', 'SELL')->sum('quantity')));
        $this->assertSame(5000000, (int) $user->manualAssets()->sum('current_value'));
        $quote = Instrument::where('symbol', 'MOFI')->firstOrFail()->marketPrices()->latest('price_date')->firstOrFail();
        $this->assertSame('125000.00000000', $quote->close);
        $this->assertSame('2026-09-15', $quote->price_date->toDateString());
        $this->assertSame(38315000, (int) $rows->sum('cash_delta') + 150 * (int) $quote->close + (int) $user->manualAssets()->sum('current_value'));
        $goal = $user->goals()->firstOrFail();
        $this->assertSame('10000000', $goal->target_amount);
        $this->assertSame('2000000', $goal->saved_amount);
        $snapshot = $rows->toArray();
        $goal->update(['saved_amount' => '2500000']);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($snapshot, $user->portfolio->transactions()->orderBy('trade_date')->orderBy('id')->get()->toArray());
        $this->assertSame('2500000', $goal->fresh()->saved_amount);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('portfolios', 2);
        $this->assertDatabaseCount('transactions', 5);
        $this->assertDatabaseCount('goals', 1);
        $this->assertDatabaseCount('manual_assets', 1);
        $this->assertDatabaseCount('market_prices', 120);
        foreach (Instrument::all() as $instrument) {
            $prices = $instrument->marketPrices()->orderBy('price_date')->get();
            $this->assertCount(30, $prices);
            $this->assertSame('2026-08-17', $prices->first()->price_date->toDateString());
            $this->assertNull($prices->first()->reference_close);
            $this->assertTrue($prices->every(fn ($price) => $price->is_demo && $price->source === 'demo'));
            foreach ($prices->skip(1) as $index => $price) {
                $this->assertSame($prices[$index - 1]->close, $price->reference_close);
            }
        }
    }

    public function test_factories_persist_valid_related_records_and_cast_values(): void
    {
        $transaction = Transaction::factory()->create();
        $this->assertSame($transaction->portfolio->user_id, $transaction->user_id);
        $this->assertTrue($transaction->user->is($transaction->portfolio->user));
        $this->assertSame('30000000', $transaction->fresh()->gross_amount);
        $this->assertNotNull($transaction->created_at);
        $this->assertNull($transaction->updated_at);
        $price = MarketPrice::factory()->create();
        $this->assertTrue($price->instrument->tradable);
        $this->assertSame('125000.00000000', $price->fresh()->close);
        foreach ([ManualAsset::class, Goal::class, WatchlistItem::class, AlertRule::class, Notification::class, Task::class, LearningProgress::class] as $model) {
            $record = $model::factory()->create();
            $this->assertNotNull($record->fresh());
            $this->assertNotNull($record->user);
        }
    }

    public function test_seed_rolls_back_all_rows_when_fixture_write_fails(): void
    {
        $this->enableDemo();
        DB::statement("CREATE TRIGGER reject_goal BEFORE INSERT ON goals BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('Expected fixture failure.');
        } catch (QueryException) {
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('market_prices', 0);
            $this->assertDatabaseCount('transactions', 0);
        }
    }

    public function test_demo_disabled_rejects_seed_without_writing(): void
    {
        config(['demo.enabled' => false]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Enable DEMO_ENABLED');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_missing_or_reused_database_password_is_rejected(): void
    {
        $this->enableDemo();
        foreach ([null, 'short', config('database.connections.pgsql.password')] as $password) {
            config(['demo.login_password' => $password]);
            try {
                $this->seed(DatabaseSeeder::class);
                $this->fail('Unsafe password accepted.');
            } catch (\RuntimeException) {
                $this->assertDatabaseCount('users', 0);
            }
        }
    }
}
