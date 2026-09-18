<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('demo.admin_password');
        if (! config('demo.enabled') || ! is_string($password) || strlen($password) < 16) {
            throw new RuntimeException('Enable demo and configure a separate DEMO_ADMIN_PASSWORD of at least 16 characters.');
        }
        foreach ([config('demo.login_password'), config('database.connections.pgsql.password')] as $other) {
            if (is_string($other) && hash_equals($other, $password)) {
                throw new RuntimeException('Admin password must differ from demo and database passwords.');
            }
        }
        $existing = User::where('email', 'admin@mofi.local')->first();
        if ($existing) {
            if (! $existing->isAdmin()) {
                throw new RuntimeException('Reserved admin email belongs to a regular user. No privileges were changed.');
            }

            return;
        }
        $user = new User;
        $user->forceFill(['name' => 'MOFI Quản trị viên', 'email' => 'admin@mofi.local', 'password' => Hash::make($password), 'role' => 'admin']);
        $user->save();
    }
}
