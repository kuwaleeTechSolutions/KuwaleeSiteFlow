<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boq_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('boq_revision_id')->constrained('boq_revisions')->cascadeOnDelete();
            // Stable business key across revisions — e.g. "1.01". A revised
            // item creates a NEW row with the SAME item_number tied to a
            // NEW boq_revision_id; the prior row for this item_number is
            // NEVER updated or deleted (brief §19: "Do not silently
            // overwrite historical contract values").
            $table->string('item_number', 60);
            $table->text('description');
            $table->string('unit', 30);
            $table->decimal('contract_quantity', 15, 3);
            $table->decimal('contract_rate', 14, 2);
            // Stored (not a DB-generated column, for cross-database
            // portability between MySQL and the SQLite test driver) —
            // always recomputed server-side as quantity * rate via BCMath
            // whenever a row is created; never independently edited.
            $table->decimal('contract_value', 18, 2);
            $table->timestamps();

            $table->unique(['boq_revision_id', 'item_number']);
            $table->index(['project_id', 'item_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boq_items');
    }
};
