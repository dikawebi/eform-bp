<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number', 32)->unique();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('benefit_type', 80);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status', 32)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('employee_number', 32)->nullable();
            $table->string('employee_name')->nullable();
            $table->string('department', 100)->nullable();
            $table->json('employee_snapshot_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });

        Schema::create('medical_claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_claim_id')->constrained('medical_claims')->cascadeOnDelete();
            $table->string('patient_name', 150);
            $table->string('relationship', 20);
            $table->date('treatment_date');
            $table->string('facility_name', 255);
            $table->string('diagnosis_code', 80)->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->index(['medical_claim_id', 'relationship']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_claim_items');
        Schema::dropIfExists('medical_claims');
    }
};
