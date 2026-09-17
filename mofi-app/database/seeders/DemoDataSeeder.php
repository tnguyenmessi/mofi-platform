<?php

namespace Database\Seeders;

use App\Models\Goal;
use App\Models\Instrument;
use App\Models\ManualAsset;
use App\Models\MarketPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo.enabled')) {
            throw new \RuntimeException('Enable DEMO_ENABLED explicitly on a demo database.');
        }
        $password = config('demo.login_password');
        if (! is_string($password) || strlen($password) < 10) {
            throw new \RuntimeException('Set DEMO_LOGIN_PASSWORD before seeding demo data.');
        }
        if (hash_equals((string) config('database.connections.pgsql.password'), $password)) {
            throw new \RuntimeException('Use a separate password for demo logins.');
        }
        DB::transaction(function () use ($password): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('select pg_advisory_xact_lock(6409152026)');
            }
            $this->seedDemo($password);
        });
    }

    private function seedDemo(string $password): void
    {
        $user = User::updateOrCreate(['email' => 'demo@mofi.local'], ['name' => 'MOFI Demo A', 'password' => Hash::make($password), 'email_verified_at' => now()]);
        $empty = User::updateOrCreate(['email' => 'empty@mofi.local'], ['name' => 'MOFI Demo B', 'password' => Hash::make($password), 'email_verified_at' => now()]);
        $portfolio = Portfolio::updateOrCreate(['user_id' => $user->id], ['name' => 'Danh mục VND Demo', 'currency' => 'VND']);
        Portfolio::updateOrCreate(['user_id' => $empty->id], ['name' => 'Danh mục trống', 'currency' => 'VND']);
        $instrument = Instrument::updateOrCreate(['market' => 'VN', 'symbol' => 'MOFI'], ['name' => 'Cổ phiếu MOFI mô phỏng', 'asset_class' => 'stock', 'sector' => 'Công nghệ', 'currency' => 'VND', 'price_unit' => 'share', 'tradable' => true]);

        $catalog = [[$instrument, 125000],
            [Instrument::firstOrCreate(['market' => 'VN', 'symbol' => 'VNINDEX-DEMO'], ['name' => 'Chỉ số Việt Nam mô phỏng', 'asset_class' => 'index', 'currency' => 'VND', 'price_unit' => 'point', 'tradable' => false]), 1300],
            [Instrument::firstOrCreate(['market' => 'DEMO', 'symbol' => 'GOLD-DEMO'], ['name' => 'Vàng mô phỏng', 'asset_class' => 'gold', 'currency' => 'VND', 'price_unit' => 'tael', 'tradable' => false]), 90000000],
            [Instrument::firstOrCreate(['market' => 'DEMO', 'symbol' => 'BTC-DEMO'], ['name' => 'Bitcoin mô phỏng', 'asset_class' => 'crypto', 'currency' => 'USD', 'price_unit' => 'coin', 'tradable' => false]), 60000],
        ];
        $priceRows = [];
        foreach ($catalog as [$asset, $finalPrice]) {
            $previous = null;
            for ($day = 0; $day < 30; $day++) {
                $date = CarbonImmutable::parse(config('demo.simulation_date'))->subDays(29 - $day)->toDateString();
                $close = intdiv($finalPrice * (971 + $day), 1000);
                $priceRows[] = ['instrument_id' => $asset->id, 'price_date' => $date, 'close' => $close, 'reference_close' => $previous, 'source' => 'demo', 'is_demo' => true];
                $previous = $close;
            }
        }
        MarketPrice::upsert($priceRows, ['instrument_id', 'price_date'], ['close', 'reference_close', 'source', 'is_demo']);

        $rows = [
            ['kind' => 'DEPOSIT', 'trade_date' => '2026-08-17', 'gross_amount' => 30000000, 'cash_delta' => 30000000, 'instrument_id' => null],
            ['kind' => 'BUY', 'trade_date' => '2026-08-18', 'quantity' => 100, 'unit_price' => 100000, 'gross_amount' => 10000000, 'fee' => 10000, 'cash_delta' => -10010000, 'instrument_id' => $instrument->id],
            ['kind' => 'BUY', 'trade_date' => '2026-08-25', 'quantity' => 100, 'unit_price' => 120000, 'gross_amount' => 12000000, 'fee' => 10000, 'cash_delta' => -12010000, 'instrument_id' => $instrument->id],
            ['kind' => 'SELL', 'trade_date' => '2026-09-05', 'quantity' => 50, 'unit_price' => 130000, 'gross_amount' => 6500000, 'fee' => 10000, 'cash_delta' => 6490000, 'instrument_id' => $instrument->id],
            ['kind' => 'DIVIDEND', 'trade_date' => '2026-09-10', 'gross_amount' => 100000, 'tax' => 5000, 'cash_delta' => 95000, 'instrument_id' => $instrument->id],
        ];
        foreach ($rows as $index => $row) {
            $key = sprintf('64091500-0000-4000-8000-%012d', $index + 1);
            $hash = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
            // Recognize rows from the initial seed, which used random request keys.
            $existing = Transaction::where('portfolio_id', $portfolio->id)->where(function ($query) use ($key, $hash): void {
                $query->where('request_key', $key)->orWhere('request_hash', $hash);
            })->first();
            if ($existing) {
                if ($existing->request_hash !== $hash) {
                    throw new \RuntimeException('Demo transaction conflicts with the existing fixture.');
                }

                continue;
            }
            Transaction::create(array_merge(['user_id' => $user->id, 'portfolio_id' => $portfolio->id, 'quantity' => null, 'unit_price' => null, 'fee' => 0, 'tax' => 0, 'request_key' => $key, 'request_hash' => $hash], $row));
        }
        Goal::where('user_id', $user->id)->where('name', 'Quy muc tieu')->update(['name' => 'Quỹ mục tiêu']);
        ManualAsset::where('user_id', $user->id)->where('name', 'Tai san thu cong demo')->update(['name' => 'Tài sản thủ công demo']);
        Goal::firstOrCreate(['user_id' => $user->id, 'name' => 'Quỹ mục tiêu'], ['category' => 'emergency', 'target_amount' => 10000000, 'saved_amount' => 2000000, 'target_date' => '2027-09-15']);
        ManualAsset::firstOrCreate(['user_id' => $user->id, 'name' => 'Tài sản thủ công demo'], ['category' => 'other', 'current_value' => 5000000, 'valued_on' => '2026-09-15']);
    }
}
