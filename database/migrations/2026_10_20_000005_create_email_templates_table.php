<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('module', 32)->nullable(); // null = semua modul
            $table->string('event', 64); // misal: approval.assigned, it.submitted
            $table->text('subject')->nullable(); // null = pakai judul notifikasi asli
            $table->text('message')->nullable(); // null = pakai pesan notifikasi asli
            $table->text('subject_en')->nullable();
            $table->text('message_en')->nullable();
            $table->timestamps();

            $table->unique(['module', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
