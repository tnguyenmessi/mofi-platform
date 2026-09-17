<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('users')->where('email', 'demo@mofi.local')->value('id');
        if ($id) {
            DB::table('goals')->where('user_id', $id)->where('name', 'Quy muc tieu')->update(['name' => 'Quỹ mục tiêu']);
            DB::table('manual_assets')->where('user_id', $id)->where('name', 'Tai san thu cong demo')->update(['name' => 'Tài sản thủ công demo']);
        }
    }

    public function down(): void
    {
        // Display-only translations preserve user records when rolling back.
    }
};
