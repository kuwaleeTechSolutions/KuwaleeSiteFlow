<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->string('payment_reference', 100)->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('payment_mode', 60)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'bill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
