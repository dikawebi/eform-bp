<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mailer', 16)->default('smtp');
            $table->string('host', 190)->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('encryption', 8)->nullable();
            $table->string('username', 190)->nullable();
            $table->text('password')->nullable();
            $table->string('from_address', 190)->nullable();
            $table->string('from_name', 100)->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
