<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_availabilities', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->string('maintenance_message', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_availabilities');
    }
};
