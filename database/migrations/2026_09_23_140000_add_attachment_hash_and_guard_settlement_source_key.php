<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fail loudly instead of silently choosing a settlement if a legacy database
        // was imported without the source_key unique constraint.
        if (Schema::hasTable('settlements')) {
            $duplicates = DB::table('settlements')
                ->select('source_key')
                ->whereNotNull('source_key')
                ->groupBy('source_key')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($duplicates) {
                throw new RuntimeException('Duplicate settlements.source_key ditemukan; bersihkan data sebelum migrasi Phase 5.');
            }

            $hasUniqueSourceKey = collect(Schema::getIndexes('settlements'))->contains(
                fn (array $index): bool => $index['unique'] && $index['columns'] === ['source_key']
            );
            if (! $hasUniqueSourceKey) {
                Schema::table('settlements', function (Blueprint $table): void {
                    $table->unique('source_key', 'settlements_source_key_unique');
                });
            }
        }

        if (Schema::hasTable('attachments') && ! Schema::hasColumn('attachments', 'sha256_hash')) {
            Schema::table('attachments', function (Blueprint $table): void {
                $table->string('sha256_hash', 64)->nullable()->after('file_size');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('settlements') && collect(Schema::getIndexes('settlements'))->contains(fn (array $index): bool => $index['name'] === 'settlements_source_key_unique')) {
            Schema::table('settlements', function (Blueprint $table): void {
                $table->dropUnique('settlements_source_key_unique');
            });
        }
        if (Schema::hasTable('attachments') && Schema::hasColumn('attachments', 'sha256_hash')) {
            Schema::table('attachments', function (Blueprint $table): void {
                $table->dropColumn('sha256_hash');
            });
        }
    }
};
