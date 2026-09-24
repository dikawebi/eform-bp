<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3 — Perjalanan Dinas (PRD §2 modul B, §5.2, §6, §7).
     *
     * - travel_requests: header + snapshot employee + total_advance server-side.
     * - travel_request_items: repeater biaya, amount = qty*price server (bc).
     */
    public function up(): void
    {
        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();
            // Nomor atomik DINAS-YYYYMM-0001 (unique + generate dalam transaction).
            $table->string('request_number', 32)->unique();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->text('purpose');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('origin', 255);
            $table->string('destination', 255);
            // Penanda perjalanan terkait project (menentukan step PM di Phase 4).
            $table->boolean('is_project_trip')->default(false);
            // Server-side only: tidak fillable, dihitung dari items.
            $table->decimal('total_advance', 15, 2)->default(0);
            $table->string('status', 32)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('advance_paid_at')->nullable();
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

        Schema::create('travel_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_request_id')
                ->constrained('travel_requests')
                ->cascadeOnDelete();
            // land_transport/flight/hotel/meal/other (flight BOLEH di dinas).
            $table->string('category', 50);
            $table->date('transaction_date')->nullable();
            $table->string('origin', 255)->nullable();
            $table->string('destination', 255)->nullable();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            // Server-side: qty * price (string-presisi).
            $table->decimal('amount', 15, 2)->default(0);
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index('travel_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_request_items');
        Schema::dropIfExists('travel_requests');
    }
};
