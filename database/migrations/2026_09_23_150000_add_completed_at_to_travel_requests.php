<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('travel_requests') && ! Schema::hasColumn('travel_requests', 'completed_at')) {
            Schema::table('travel_requests', function (Blueprint $table): void {
                $table->timestamp('completed_at')->nullable()->after('advance_paid_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('travel_requests') && Schema::hasColumn('travel_requests', 'completed_at')) {
            Schema::table('travel_requests', function (Blueprint $table): void {
                $table->dropColumn('completed_at');
            });
        }
    }
};
