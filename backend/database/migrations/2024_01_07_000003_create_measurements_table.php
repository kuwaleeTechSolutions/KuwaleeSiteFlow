<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->date('measurement_date');
            $table->text('remarks')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_remarks')->nullable();
            // Self-reference for corrections to an already-approved (and
            // possibly billed) measurement — a new measurement row is
            // created referencing the original, rather than editing it
            // (brief §20: "Use adjustment/revision workflows instead of
            // silently changing historical approved financial data").
            $table->foreignId('revises_measurement_id')->nullable()
                ->constrained('measurements')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['organization_id', 'project_id', 'site_id', 'measurement_date'],
                'measurement_org_proj_site_date_idx'
            );
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
