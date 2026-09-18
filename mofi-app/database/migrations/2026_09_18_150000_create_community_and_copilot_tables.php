<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestampsTz();
            $table->index(['created_at', 'user_id']);
        });
        Schema::create('copilot_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('question', 500);
            $table->text('answer');
            $table->string('source', 24)->default('rules');
            $table->timestampsTz();
            $table->index(['user_id', 'created_at']);
        });
        if (DB::getDriverName() === 'pgsql') {
            $roles = ['PUBLIC'];
            foreach (['anon', 'authenticated'] as $role) {
                if (DB::selectOne('select 1 from pg_roles where rolname = ?', [$role])) {
                    $roles[] = $role;
                }
            }
            foreach (['community_posts', 'copilot_questions'] as $table) {
                DB::statement('REVOKE ALL PRIVILEGES ON TABLE public.'.$table.' FROM '.implode(', ', $roles));
                DB::statement('ALTER TABLE public.'.$table.' ENABLE ROW LEVEL SECURITY');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_questions');
        Schema::dropIfExists('community_posts');
    }
};
