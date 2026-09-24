<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_requests') && ! Schema::hasColumn('leave_requests', 'advance_paid_at')) {
            Schema::table('leave_requests', fn (Blueprint $table) => $table->timestamp('advance_paid_at')->nullable()->after('approved_at'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_requests') && Schema::hasColumn('leave_requests', 'advance_paid_at')) {
            Schema::table('leave_requests', fn (Blueprint $table) => $table->dropColumn('advance_paid_at'));
        }
    }
};
