<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            // Maintained EXCLUSIVELY by MaterialStockService as a side-effect
            // of committing a material_transactions row — never written to
            // directly by a controller. Locked via lockForUpdate() during
            // every mutation to prevent concurrent-update race conditions.
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['material_id', 'project_id', 'site_id']);
            $table->index(['organization_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stocks');
    }
};
