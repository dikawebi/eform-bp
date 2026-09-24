<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number', 32)->unique();
            $table->enum('source_type', ['leave_request', 'travel_request']);
            $table->unsignedBigInteger('source_id');
            $table->string('source_key')->nullable()->unique();
            $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->restrictOnDelete();
            $table->foreignId('travel_request_id')->nullable()->constrained('travel_requests')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('status', 32)->default('draft');
            $table->decimal('advance_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->decimal('difference_amount', 15, 2)->default(0);
            $table->enum('difference_type', ['overpayment', 'underpayment', 'balanced'])->nullable();
            $table->boolean('allow_partial')->default(false);
            $table->boolean('is_final')->default(true);
            $table->json('employee_snapshot_json')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
            $table->index(['employee_id', 'status']);
            $table->index(['leave_request_id', 'is_final']);
            $table->index(['travel_request_id', 'is_final']);
        });

        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('description');
            $table->enum('category', ['transport', 'hotel', 'meal', 'other']);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('receipt_no', 100)->nullable();
            $table->timestamps();
            $table->index('settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlements');
    }
};
