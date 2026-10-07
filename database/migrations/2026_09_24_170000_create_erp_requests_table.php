<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('recipient_name', 150)->nullable();
            $table->string('recipient_site', 100)->nullable();
            $table->string('recipient_cost_code', 50)->nullable();
            $table->string('recipient_position', 100)->nullable();
            $table->string('action_type', 30)->default('new_account');
            $table->string('existing_erp_username', 150)->nullable();
            $table->text('business_purpose');
            $table->json('modules_json')->nullable();
            $table->string('status', 32)->default('draft');
            $table->json('employee_snapshot_json')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_requests');
    }
};
