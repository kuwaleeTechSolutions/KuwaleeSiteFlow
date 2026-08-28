<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('measurement_id')->constrained('measurements')->cascadeOnDelete();
            // References the SPECIFIC boq_items row (a particular
            // revision) in effect at the time this measurement was taken —
            // preserved even if the BOQ is revised again later.
            $table->foreignId('boq_item_id')->constrained('boq_items')->restrictOnDelete();
            $table->decimal('previous_quantity', 15, 3)->default(0);
            $table->decimal('current_quantity', 15, 3);
            // previous_quantity + current_quantity, computed server-side —
            // never trusted from client input.
            $table->decimal('cumulative_quantity', 15, 3);
            $table->string('unit', 30);
            $table->string('remarks', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['measurement_id', 'boq_item_id']);
            $table->index('boq_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_items');
    }
};
