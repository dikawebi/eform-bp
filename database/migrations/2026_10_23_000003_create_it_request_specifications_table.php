<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_request_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_request_id')->unique()->constrained('it_requests')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('it_spec_templates')->nullOnDelete();
            $table->string('device_type', 50)->nullable();
            $table->json('specs_json');
            $table->text('notes')->nullable();
            $table->foreignId('specified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('specified_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_request_specifications');
    }
};
