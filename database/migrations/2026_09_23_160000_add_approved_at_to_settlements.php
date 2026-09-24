<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settlements') && ! Schema::hasColumn('settlements', 'approved_at')) {
            Schema::table('settlements', function (Blueprint $table): void {
                $table->timestamp('approved_at')->nullable()->after('submitted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('settlements') && Schema::hasColumn('settlements', 'approved_at')) {
            Schema::table('settlements', function (Blueprint $table): void {
                $table->dropColumn('approved_at');
            });
        }
    }
};
