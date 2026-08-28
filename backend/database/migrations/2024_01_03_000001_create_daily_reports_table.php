<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->date('report_date');
            $table->string('weather', 100)->nullable();
            $table->text('work_activities')->nullable();
            $table->text('work_completed')->nullable();
            $table->decimal('quantity_completed', 15, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->unsignedInteger('manpower_deployed')->nullable();
            // Equipment/material used are captured as free-text/JSON summaries
            // here; the authoritative, transactional equipment-usage and
            // material-consumption ledgers are built in Phases 6-7 and MAY
            // be cross-referenced from a report in a later revision.
            $table->text('equipment_used')->nullable();
            $table->text('material_used')->nullable();
            $table->text('problems_delays')->nullable();
            $table->text('reason_for_delay')->nullable();
            $table->text('safety_incidents')->nullable();
            $table->text('tomorrow_plan')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['draft', 'submitted', 'returned', 'approved'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['organization_id', 'project_id', 'site_id', 'report_date'],
                'daily_reports_org_project_site_date_idx'
            );
            $table->index(['site_id', 'report_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
