<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert legacy FQCN values without assuming every installation has all
     * optional tables. Unknown values are deliberately fatal: silently
     * leaving one behind would make the related record inaccessible through
     * Eloquent after morphMap is enabled.
     */
    public function up(): void
    {
        $map = [
            'App\\Models\\LeaveRequest' => 'leave_request',
            'App\\Models\\TravelRequest' => 'travel_request',
            'App\\Models\\Settlement' => 'settlement',
            'App\\Models\\MedicalClaim' => 'medical_claim',
            'App\\Models\\ApprovalRequest' => 'approval_request',
            'App\\Models\\Attachment' => 'attachment',
            'App\\Models\\User' => 'user',
        ];

        $targets = [
            ['table' => 'approval_requests', 'columns' => ['approvable_type']],
            ['table' => 'attachments', 'columns' => ['attachable_type']],
            ['table' => config('activitylog.table_name', 'activity_log'), 'columns' => ['subject_type', 'causer_type'], 'connection' => config('activitylog.database_connection')],
        ];

        foreach ($targets as $target) {
            $connection = $target['connection'] ?? null;
            $schema = $connection ? Schema::connection($connection) : Schema::getFacadeRoot();
            $db = $connection ? DB::connection($connection) : DB::connection();
            $table = $target['table'];

            if (! $schema->hasTable($table)) {
                continue;
            }

            foreach ($target['columns'] as $column) {
                if (! $schema->hasColumn($table, $column)) {
                    continue;
                }

                $values = $db->table($table)->whereNotNull($column)->distinct()->pluck($column);
                $orphans = $values->reject(fn (mixed $value): bool => array_key_exists((string) $value, $map) || in_array((string) $value, $map, true));
                if ($orphans->isNotEmpty()) {
                    $message = sprintf('Morph compatibility migration found unknown %s.%s values: %s', $table, $column, $orphans->implode(', '));
                    Log::error($message, ['table' => $table, 'column' => $column, 'values' => $orphans->values()->all()]);
                    throw new RuntimeException($message.' Resolve these records before retrying migration.');
                }

                foreach ($map as $fqcn => $alias) {
                    $db->table($table)->where($column, $fqcn)->update([$column => $alias]);
                }
            }
        }
    }

    public function down(): void
    {
        // There is no safe inverse: aliases may have been written after this
        // migration and the original class is not recoverable from the row.
        Log::warning('Morph compatibility migration rollback is intentionally a no-op; aliases are not reverted.');
    }
};
