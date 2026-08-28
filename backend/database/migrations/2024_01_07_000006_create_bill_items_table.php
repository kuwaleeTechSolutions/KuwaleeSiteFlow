<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            // Ties this billed quantity back to the SPECIFIC approved
            // measurement entry that certifies the work was actually done —
            // a bill_item can never exist without a corresponding approved
            // measurement_item (enforced in BillingService).
            $table->foreignId('measurement_item_id')->constrained('measurement_items')->restrictOnDelete();
            $table->foreignId('boq_item_id')->constrained('boq_items')->restrictOnDelete();
            $table->decimal('quantity_billed', 15, 3);
            $table->decimal('rate', 14, 2);
            // quantity_billed * rate, computed server-side via BCMath.
            $table->decimal('amount', 18, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index('bill_id');
            $table->index('boq_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
    }
};
