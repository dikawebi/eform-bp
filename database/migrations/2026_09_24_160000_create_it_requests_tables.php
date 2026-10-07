<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'site')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('site', 100)->nullable()->after('poh_province')->index();
                $table->string('cost_code', 50)->nullable()->after('site');
            });
        }

        Schema::create('it_hardware_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label', 100);
            $table->string('device_type', 30);
            $table->string('tier', 30)->nullable();
            $table->json('spec_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('site_pm_gm_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('site', 100)->unique();
            $table->foreignId('pm_gm_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('it_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('recipient_name', 150)->nullable();
            $table->string('recipient_id_number', 50)->nullable();
            $table->string('recipient_site', 100)->nullable();
            $table->string('recipient_cost_code', 50)->nullable();
            $table->string('recipient_position', 100)->nullable();
            $table->date('recipient_effective_date')->nullable();
            $table->boolean('is_new_employee')->default(false);
            $table->string('request_type', 30)->default('new_item');
            $table->string('replacement_reason', 50)->nullable();
            $table->text('replacement_note')->nullable();
            $table->string('device_type', 50)->nullable();
            $table->boolean('special_specification')->default(false);
            $table->text('description')->nullable();
            $table->text('purpose')->nullable();
            $table->json('software_standard_json')->nullable();
            $table->json('software_optional_json')->nullable();
            $table->json('accessories_json')->nullable();
            $table->string('accessory_other_note')->nullable();
            $table->date('needed_date')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 32)->default('draft');
            $table->json('employee_snapshot_json')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_requests');
        Schema::dropIfExists('site_pm_gm_assignments');
        Schema::dropIfExists('it_hardware_packages');

        if (Schema::hasColumn('employees', 'site')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn(['site', 'cost_code']);
            });
        }
    }
};
