<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wage_computations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('days_present', 6, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            // All money columns are decimal — computed via BCMath in
            // WageCalculationService, never floating point.
            $table->decimal('base_wage_total', 14, 2)->default(0);
            $table->decimal('overtime_total', 14, 2)->default(0);
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');

            $table->index(
                [
                    'organization_id',
                    'project_id',
                    'period_start',
                    'period_end',
                ],
                'wage_comp_org_proj_period_idx'
            );
            $table->index('worker_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wage_computations');
    }
};
