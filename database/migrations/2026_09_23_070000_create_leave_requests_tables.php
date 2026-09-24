<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2 — Cuti/Izin (PRD §2 modul A, §6, §7).
     *
     * - leave_requests: header + snapshot employee + total server-side.
     * - leave_periods: beberapa periode tanggal, day_count inklusif server.
     * - leave_cost_items: amount = qty*price server, eligible_by_policy server.
     */
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            // Nomor atomik CUTI-YYYYMM-0001 (unique + generate dalam transaction).
            $table->string('request_number', 32)->unique();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->string('leave_type', 50);
            $table->text('reason')->nullable();
            $table->date('last_working_date')->nullable();
            $table->date('onsite_date')->nullable();
            // Server-side only: tidak fillable, dihitung dari periods/items.
            $table->unsignedInteger('total_days')->default(0);
            $table->decimal('total_advance', 15, 2)->default(0);
            // Diturunkan server dari employees.poh_status saat submit/store.
            $table->boolean('is_local')->default(false);
            $table->string('status', 32)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            // Snapshot agar histori tidak berubah saat master diupdate (PRD §6.3).
            $table->string('employee_number', 32)->nullable();
            $table->string('employee_name')->nullable();
            $table->string('department', 100)->nullable();
            $table->json('employee_snapshot_json')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('leave_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')
                ->constrained('leave_requests')
                ->cascadeOnDelete();
            // Kategori per App\Enums\LeavePeriodCategory (validasi di Form Request).
            $table->string('category', 50);
            $table->date('start_date');
            $table->date('end_date');
            // Server-side inklusif: diff + 1.
            $table->unsignedInteger('day_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('leave_request_id');
        });

        Schema::create('leave_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')
                ->constrained('leave_requests')
                ->cascadeOnDelete();
            // land_transport/hotel/meal/other — flight ditolak di cuti.
            $table->string('category', 50);
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            // Server-side: qty * price (0 jika tidak eligible).
            $table->decimal('amount', 15, 2)->default(0);
            // Server-side dari aturan lokal/non-lokal configurable.
            $table->boolean('eligible_by_policy')->default(true);
            $table->timestamps();

            $table->index('leave_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_cost_items');
        Schema::dropIfExists('leave_periods');
        Schema::dropIfExists('leave_requests');
    }
};
