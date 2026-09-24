<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->uuid('chain_generation')->nullable()->after('approvable_id');
            $table->index(['approvable_type', 'approvable_id', 'chain_generation', 'status', 'step_order'], 'approval_chain_lookup');
            $table->index(['approvable_type', 'approvable_id', 'chain_generation'], 'approval_chain_generation');
        });
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropIndex('approval_chain_lookup');
            $table->dropIndex('approval_chain_generation');
            $table->dropColumn('chain_generation');
        });
    }
};
