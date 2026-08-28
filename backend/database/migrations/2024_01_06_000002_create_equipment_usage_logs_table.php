<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // route-key; internal id never exposed
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->date('usage_date');
            $table->decimal('hours_used', 6, 2);
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks', 500)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(
                [
                    'organization_id',
                    'project_id',
                    'site_id',
                    'usage_date',
                ],
                'equip_usage_org_proj_site_date_idx'
            );
            $table->index(['equipment_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_usage_logs');
    }
};
