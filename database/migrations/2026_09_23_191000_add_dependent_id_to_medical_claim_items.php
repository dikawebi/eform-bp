<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_claim_items', function (Blueprint $table) {
            $table->foreignId('dependent_id')->nullable()->after('relationship')->constrained('medical_dependents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('medical_claim_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dependent_id');
        });
    }
};
