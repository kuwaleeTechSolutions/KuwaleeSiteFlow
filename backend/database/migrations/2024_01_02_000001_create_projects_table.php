<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('project_code', 60);
            $table->string('project_name');
            $table->string('client_name')->nullable();
            $table->string('contract_number', 100)->nullable();
            // Money: always decimal, never float/double, to avoid rounding drift.
            $table->decimal('contract_value', 18, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('expected_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->enum('status', ['planning', 'active', 'on_hold', 'completed', 'cancelled'])
                ->default('planning');
            $table->foreignId('project_manager_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'project_code']);
            $table->index(['organization_id', 'status']);
            $table->index('project_manager_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
