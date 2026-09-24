<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_claims', function (Blueprint $table) {
            $table->foreignId('payment_processed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('payment_processed_at')->nullable()->after('payment_processed_by');
            $table->string('payment_reference', 100)->nullable()->after('payment_processed_at');
            $table->date('payment_date')->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('medical_claims', function (Blueprint $table) {
            $table->dropForeign(['payment_processed_by']);
            $table->dropColumn(['payment_processed_by', 'payment_processed_at', 'payment_reference', 'payment_date']);
        });
    }
};
