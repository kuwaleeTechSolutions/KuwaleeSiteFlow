<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('equipment_code', 60);
            $table->string('equipment_name');
            $table->string('type', 100)->nullable(); // e.g. Excavator, JCB, Crane, Generator, Truck
            $table->string('registration_number', 100)->nullable();
            $table->foreignId('assigned_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('assigned_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('current_operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['available', 'in_use', 'maintenance', 'breakdown', 'inactive'])->default('available');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'equipment_code']);
            $table->index(['organization_id', 'status']);
            $table->index('assigned_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
