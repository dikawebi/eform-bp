<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('medical_claims')->where('payment_reference', '')->update(['payment_reference' => null]);
        DB::table('medical_claims')->whereNotNull('payment_reference')->orderBy('id')->eachById(function ($claim): void {
            DB::table('medical_claims')->where('id', $claim->id)->update(['payment_reference' => strtoupper(trim($claim->payment_reference))]);
        });
        $duplicates = DB::table('medical_claims')->select('payment_reference')->whereNotNull('payment_reference')->groupBy('payment_reference')->havingRaw('COUNT(*) > 1')->pluck('payment_reference')->all();
        if ($duplicates !== []) {
            throw new RuntimeException('Cannot add unique medical payment_reference; duplicate values exist: '.implode(', ', $duplicates));
        }
        Schema::table('medical_claims', fn (Blueprint $table) => $table->unique('payment_reference', 'medical_claims_payment_reference_unique'));
    }

    public function down(): void
    {
        Schema::table('medical_claims', fn (Blueprint $table) => $table->dropUnique('medical_claims_payment_reference_unique'));
    }
};
