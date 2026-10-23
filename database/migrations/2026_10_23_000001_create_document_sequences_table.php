<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('module', 32)->unique();
            $table->string('prefix', 20);
            $table->string('separator', 3)->default('-');
            $table->string('period_format', 10)->nullable();
            $table->unsignedTinyInteger('padding')->default(4);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('last_period', 16)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
