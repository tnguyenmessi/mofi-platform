<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        $tables = ['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'portfolios', 'instruments', 'market_prices', 'transactions', 'manual_assets', 'goals', 'watchlist_items', 'alert_rules', 'notifications', 'tasks', 'learning_progress'];
        $roles = ['PUBLIC'];
        foreach (['anon', 'authenticated'] as $role) {
            if (DB::selectOne('select 1 from pg_roles where rolname = ?', [$role])) {
                $roles[] = $role;
            }
        }
        foreach ($tables as $table) {
            DB::statement('REVOKE ALL PRIVILEGES ON TABLE public.'.$table.' FROM '.implode(', ', $roles));
            DB::statement('ALTER TABLE public.'.$table.' ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        // Rolling back application code must not reopen financial tables to the Data API.
    }
};
