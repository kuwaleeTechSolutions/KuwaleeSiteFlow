<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Nullable: a 'purchase' may be a bulk depot purchase not yet
            // tied to a specific piece of equipment; an 'issue' ALWAYS
            // requires equipment_id (enforced in the Form Request).
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->enum('transaction_type', ['purchase', 'issue']);
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->decimal('opening_reading', 12, 2)->nullable();
            $table->decimal('closing_reading', 12, 2)->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 10, 2)->nullable();
            // Computed at creation time by FuelTransactionService
            // (quantity * unit_cost) via BCMath — never trusted from the
            // client, never recalculated ad-hoc elsewhere.
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            // Lightweight review/finalization step gated by 'fuel.approve' —
            // once reviewed, the entry is immutable (brief-consistent with
            // the measurement/bill approval-locks-editing pattern).
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(
                [
                    'organization_id',
                    'project_id',
                    'site_id',
                    'created_at',
                ],
                'fuel_txn_org_proj_site_date_idx'
            );
            $table->index(['equipment_id', 'created_at']);
            $table->index('transaction_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_transactions');
    }
};
