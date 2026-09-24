<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_cost_items', function (Blueprint $table): void {
            $table->string('origin', 255)->nullable();
            $table->string('destination', 255)->nullable();
            $table->string('flight_destination', 255)->nullable();
            $table->date('service_date')->nullable();
            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->time('departure_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leave_cost_items', function (Blueprint $table): void {
            $table->dropColumn(['origin', 'destination', 'flight_destination', 'service_date', 'check_in_date', 'check_out_date', 'departure_time']);
        });
    }
};
