<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_item_options', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label', 100);
            // device = perangkat utama (pilih satu), accessory = perangkat tambahan (multi).
            $table->string('kind', 20);
            $table->boolean('requires_note')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['kind', 'is_active']);
        });

        // Katalog paket tier tidak lagi dipakai (spesifikasi ditentukan internal IT).
        Schema::dropIfExists('it_hardware_packages');
    }

    public function down(): void
    {
        Schema::dropIfExists('it_item_options');
    }
};
