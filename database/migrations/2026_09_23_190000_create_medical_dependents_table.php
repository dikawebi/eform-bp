<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('relationship', 20);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['employee_id', 'relationship', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_dependents');
    }
};
