<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PortfolioSummary;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgresTransactionConcurrencyTest extends TestCase
{
    public function test_concurrent_paper_orders_and_duplicate_requests_are_serialized_on_postgres(): void
    {
        if (getenv('MOFI_PG_TEST') !== '1') {
            $this->markTestSkipped('Opt in with MOFI_PG_TEST=1 and the disposable local PostgreSQL on port 55439.');
        }
        config(['database.default' => 'pgsql', 'database.connections.pgsql' => [
            'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => '55439',
            'database' => 'mofi_transaction_test', 'username' => 'mofi_test', 'password' => '',
            'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'disable',
            'options' => [\PDO::ATTR_EMULATE_PREPARES => getenv('MOFI_PG_EMULATE_PREPARES') === '1'],
        ], 'demo.enabled' => true, 'demo.login_password' => 'test-only-demo-password']);
        DB::purge('pgsql');
        $note = "Giao dịch 'thử'; -- \\ ?";
        $bound = DB::selectOne('select cast(? as text) as note, cast(? as numeric(24,8)) as amount, cast(? as boolean) as enabled', [$note, '1234567890123456.12345678', true]);
        $this->assertSame($note, $bound->note);
        $this->assertSame('1234567890123456.12345678', $bound->amount);
        $this->assertTrue($bound->enabled);
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'demo@mofi.local')->firstOrFail();
        $portfolio = $user->portfolio;
        $sell = ['instrument_id' => Instrument::where('symbol', 'MOFI')->value('id'), 'side' => 'SELL', 'order_type' => 'MARKET', 'quantity' => '100', 'limit_price' => null];
        $results = $this->race($portfolio->id, $user->id, [
            $sell + ['request_key' => (string) Str::uuid()],
            $sell + ['request_key' => (string) Str::uuid()],
        ], 'order');
        sort($results);
        $this->assertSame([201, 422], $results);
        $this->assertSame(1, Order::where('side', 'SELL')->count());
        $this->assertGreaterThan(0, Transaction::where('kind', 'SELL')->count());
        $summary = app(PortfolioSummary::class)->forPortfolio($portfolio);
        $this->assertLessThanOrEqual(150, (float) $summary['holdings'][0]['quantity']);

        $deposit = ['kind' => 'DEPOSIT', 'gross_amount' => '1000', 'request_key' => (string) Str::uuid()];
        $results = $this->race($portfolio->id, $user->id, [$deposit, $deposit]);
        sort($results);
        $this->assertSame([200, 201], $results);
        $this->assertSame(1, Transaction::where('request_key', $deposit['request_key'])->count());
        $this->assertSame('30001000', (string) Transaction::where('kind', 'DEPOSIT')->sum('cash_delta'));
    }

    /** @return list<int> */
    private function race(int $portfolioId, int $userId, array $payloads, string $mode = 'transaction'): array
    {
        $worker = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'pgsql','database.connections.pgsql'=>[
'driver'=>'pgsql','host'=>'127.0.0.1','port'=>'55439','database'=>'mofi_transaction_test',
'username'=>'mofi_test','password'=>'','charset'=>'utf8','prefix'=>'','search_path'=>'public','sslmode'=>'disable',
'options'=>[PDO::ATTR_EMULATE_PREPARES=>getenv('MOFI_PG_EMULATE_PREPARES')==='1']]]);
Illuminate\Support\Facades\DB::purge('pgsql');
try {
$user = App\Models\User::findOrFail($argv[2]);
$portfolio = App\Models\Portfolio::findOrFail($argv[1]);
$payload = json_decode($argv[3],true,512,JSON_THROW_ON_ERROR);
$result = $argv[4] === 'order'
    ? app(App\Services\PaperTradingService::class)->place($user, $portfolio, $payload)
    : app(App\Services\RecordTransaction::class)->handle($user, $portfolio, $payload);
echo $result['replayed'] ? '200' : '201';
} catch (Illuminate\Validation\ValidationException $e) { echo '422'; }
PHP;
        $processes = [];
        DB::beginTransaction();
        try {
            DB::table('portfolios')->where('id', $portfolioId)->lockForUpdate()->first();
            foreach ($payloads as $payload) {
                $process = new Process([PHP_BINARY, '-r', $worker, (string) $portfolioId, (string) $userId, json_encode($payload), $mode], base_path());
                $process->setTimeout(60)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 45;
            do {
                DB::select('select pg_stat_clear_snapshot()');
                $waiting = (int) DB::selectOne("select count(*) as n from pg_stat_activity where datname = 'mofi_transaction_test' and wait_event_type = 'Lock'")->n;
                if ($waiting >= 2) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Both writers must contend on the held portfolio lock.');
        } finally {
            DB::rollBack();
        }
        $statuses = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $statuses[] = (int) trim($process->getOutput());
        }

        return $statuses;
    }
}
