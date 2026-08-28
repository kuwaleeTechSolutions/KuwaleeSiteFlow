<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            // Denormalized from projects.organization_id at creation time —
            // allows direct organization-scoped queries on sites without a
            // join, and is re-validated (never trusted from client input)
            // against the parent project's actual organization on create.
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('site_code', 60);
            $table->string('site_name');
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('site_manager_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'inactive', 'completed'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'site_code']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
