<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgresTransactionConcurrencyTest extends TestCase
{
    public function test_concurrent_sells_and_duplicate_requests_are_serialized_on_postgres(): void
    {
        if (getenv('MOFI_PG_TEST') !== '1') {
            $this->markTestSkipped('Opt in with MOFI_PG_TEST=1 and the disposable local PostgreSQL on port 55439.');
        }
        config(['database.default' => 'pgsql', 'database.connections.pgsql' => [
            'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => '55439',
            'database' => 'mofi_transaction_test', 'username' => 'mofi_test', 'password' => '',
            'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'disable',
        ], 'demo.enabled' => true, 'demo.login_password' => 'test-only-demo-password']);
        DB::purge('pgsql');
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'demo@mofi.local')->firstOrFail();
        $portfolio = $user->portfolio;
        $sell = ['kind' => 'SELL', 'instrument_id' => Instrument::where('symbol', 'MOFI')->value('id'), 'quantity' => '100', 'unit_price' => '125000'];
        $results = $this->race($portfolio->id, $user->id, [
            $sell + ['request_key' => (string) Str::uuid()],
            $sell + ['request_key' => (string) Str::uuid()],
        ]);
        sort($results);
        $this->assertSame([201, 422], $results);
        $this->assertSame(2, Transaction::where('kind', 'SELL')->count());
        $summary = app(\App\Services\PortfolioSummary::class)->forPortfolio($portfolio);
        $this->assertSame('50.00000000', $summary['holdings'][0]['quantity']);

        $deposit = ['kind' => 'DEPOSIT', 'gross_amount' => '1000', 'request_key' => (string) Str::uuid()];
        $results = $this->race($portfolio->id, $user->id, [$deposit, $deposit]);
        sort($results);
        $this->assertSame([200, 201], $results);
        $this->assertSame(1, Transaction::where('request_key', $deposit['request_key'])->count());
        $this->assertSame('27066000', (string) Transaction::sum('cash_delta'));
    }

    /** @return list<int> */
    private function race(int $portfolioId, int $userId, array $payloads): array
    {
        $worker = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'pgsql','database.connections.pgsql'=>[
'driver'=>'pgsql','host'=>'127.0.0.1','port'=>'55439','database'=>'mofi_transaction_test',
'username'=>'mofi_test','password'=>'','charset'=>'utf8','prefix'=>'','search_path'=>'public','sslmode'=>'disable']]);
Illuminate\Support\Facades\DB::purge('pgsql');
try {
$result = app(App\Services\RecordTransaction::class)->handle(App\Models\User::findOrFail($argv[2]), App\Models\Portfolio::findOrFail($argv[1]), json_decode($argv[3],true,512,JSON_THROW_ON_ERROR));
echo $result['replayed'] ? '200' : '201';
} catch (Illuminate\Validation\ValidationException $e) { echo '422'; }
PHP;
        $processes = [];
        DB::beginTransaction();
        try {
            DB::table('portfolios')->where('id', $portfolioId)->lockForUpdate()->first();
            foreach ($payloads as $payload) {
                $process = new Process([PHP_BINARY, '-r', $worker, (string) $portfolioId, (string) $userId, json_encode($payload)], base_path());
                $process->setTimeout(30)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
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
