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
        $roles = ['PUBLIC'];
        foreach (['anon', 'authenticated'] as $role) {
            if (DB::selectOne('select 1 from pg_roles where rolname = ?', [$role])) {
                $roles[] = $role;
            }
        }
        foreach (['investment_strategies', 'simulation_scenarios'] as $table) {
            DB::statement('REVOKE ALL PRIVILEGES ON TABLE public.'.$table.' FROM '.implode(', ', $roles));
            DB::statement('ALTER TABLE public.'.$table.' ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        // Rollbacks must not expose private planning data through the Data API.
    }
};
