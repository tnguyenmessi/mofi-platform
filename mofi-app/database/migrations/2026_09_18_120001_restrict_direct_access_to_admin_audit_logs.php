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
        DB::statement('REVOKE ALL PRIVILEGES ON TABLE public.admin_audit_logs FROM '.implode(', ', $roles));
        DB::statement('ALTER TABLE public.admin_audit_logs ENABLE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        // Rollbacks must not expose the audit trail to the public Data API.
    }
};
