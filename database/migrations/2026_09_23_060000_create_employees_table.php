<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1 — Master Karyawan (PRD §4, §6, §7).
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('employee_number', 32)->unique();
            $table->string('name');
            $table->string('department')->index();
            $table->string('level');
            $table->string('job_title');
            $table->string('roster')->nullable();
            $table->string('employment_status')->default('permanent');
            $table->enum('poh_status', ['local', 'non_local'])->default('non_local');
            $table->string('poh_city')->nullable();
            $table->string('poh_province')->nullable();
            $table->foreignId('supervisor_id')
                ->nullable()
                ->index()
                ->constrained('employees')
                ->nullOnDelete();
            $table->foreignId('hod_id')
                ->nullable()
                ->index()
                ->constrained('employees')
                ->nullOnDelete();
            $table->boolean('is_project_based')->default(false);
            $table->boolean('active')->default(true)->index();
            $table->date('joined_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
