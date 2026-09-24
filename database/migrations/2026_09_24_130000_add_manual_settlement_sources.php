<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table): void {
            $table->enum('source_type', ['leave_request', 'travel_request', 'new_join', 'other'])->change();
            $table->string('source_reference', 100)->nullable()->after('source_id');
        });
    }

    public function down(): void
    {
        if (DB::table('settlements')->whereIn('source_type', ['new_join', 'other'])->exists()) {
            throw new RuntimeException('Settlement manual masih ada; ekspor/pindahkan data manual sebelum rollback migrasi.');
        }

        Schema::table('settlements', function (Blueprint $table): void {
            $table->dropColumn('source_reference');
            $table->enum('source_type', ['leave_request', 'travel_request'])->change();
        });
    }
};
