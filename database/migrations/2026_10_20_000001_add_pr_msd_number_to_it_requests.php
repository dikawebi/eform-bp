<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_requests', function (Blueprint $table) {
            $table->string('pr_msd_number', 100)->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('it_requests', function (Blueprint $table) {
            $table->dropColumn('pr_msd_number');
        });
    }
};
