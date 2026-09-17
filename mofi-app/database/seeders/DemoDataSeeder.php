<?php

namespace Database\Seeders;

use App\Models\Goal;
use App\Models\Instrument;
use App\Models\MarketPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('DEMO_LOGIN_PASSWORD');
        if (! is_string($password) || strlen($password) < 10) {
            throw new \RuntimeException('Set DEMO_LOGIN_PASSWORD before seeding demo data.');
        }

        $user = User::updateOrCreate(['email' => 'demo@mofi.local'], ['name' => 'MOFI Demo A', 'password' => Hash::make($password), 'email_verified_at' => now()]);
        $empty = User::updateOrCreate(['email' => 'empty@mofi.local'], ['name' => 'MOFI Demo B', 'password' => Hash::make($password), 'email_verified_at' => now()]);
        $portfolio = Portfolio::updateOrCreate(['user_id' => $user->id], ['name' => 'Danh muc VND Demo', 'currency' => 'VND']);
        Portfolio::updateOrCreate(['user_id' => $empty->id], ['name' => 'Danh muc trong', 'currency' => 'VND']);
        $instrument = Instrument::updateOrCreate(['market' => 'VN', 'symbol' => 'MOFI'], ['name' => 'MOFI Demo Equity', 'asset_class' => 'stock', 'sector' => 'Technology', 'currency' => 'VND', 'price_unit' => 'share', 'tradable' => true]);

        for ($day = 0; $day < 30; $day++) {
            $date = date('Y-m-d', strtotime('2026-08-17 +'.$day.' days'));
            MarketPrice::updateOrCreate(['instrument_id' => $instrument->id, 'price_date' => $date], ['close' => 112000 + ($day * 450) + (($day % 5) * 300), 'reference_close' => 125000, 'source' => 'demo', 'is_demo' => true]);
        }

        $rows = [
            ['kind' => 'DEPOSIT', 'trade_date' => '2026-08-17', 'gross_amount' => 30000000, 'cash_delta' => 30000000, 'instrument_id' => null],
            ['kind' => 'BUY', 'trade_date' => '2026-08-18', 'quantity' => 100, 'unit_price' => 100000, 'gross_amount' => 10000000, 'fee' => 10000, 'cash_delta' => -10010000, 'instrument_id' => $instrument->id],
            ['kind' => 'BUY', 'trade_date' => '2026-08-25', 'quantity' => 100, 'unit_price' => 120000, 'gross_amount' => 12000000, 'fee' => 10000, 'cash_delta' => -12010000, 'instrument_id' => $instrument->id],
            ['kind' => 'SELL', 'trade_date' => '2026-09-05', 'quantity' => 50, 'unit_price' => 130000, 'gross_amount' => 6500000, 'fee' => 10000, 'cash_delta' => 6490000, 'instrument_id' => $instrument->id],
            ['kind' => 'DIVIDEND', 'trade_date' => '2026-09-10', 'gross_amount' => 100000, 'tax' => 5000, 'cash_delta' => 95000, 'instrument_id' => $instrument->id],
        ];
        foreach ($rows as $index => $row) {
            $key = Str::uuid()->toString();
            Transaction::create(array_merge(['user_id' => $user->id, 'portfolio_id' => $portfolio->id, 'quantity' => null, 'unit_price' => null, 'fee' => 0, 'tax' => 0, 'request_key' => $key, 'request_hash' => hash('sha256', json_encode($row)), 'created_at' => now()->addSeconds($index)], $row));
        }
        Goal::updateOrCreate(['user_id' => $user->id, 'name' => 'Quy muc tieu'], ['category' => 'emergency', 'target_amount' => 10000000, 'saved_amount' => 2000000, 'target_date' => '2027-09-15']);
    }
}
