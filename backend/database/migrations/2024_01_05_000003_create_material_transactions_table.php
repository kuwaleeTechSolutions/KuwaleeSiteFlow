<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->enum('transaction_type', ['inward', 'issue', 'return', 'transfer', 'adjustment']);
            // Always stored as a POSITIVE magnitude; direction/sign is
            // derived from transaction_type for inward/issue/return/transfer.
            // For 'adjustment' specifically, direction is ambiguous by
            // nature (a stock correction can go either way), so it is
            // captured explicitly here rather than inferred.
            $table->decimal('quantity', 15, 3);
            $table->enum('direction', ['increase', 'decrease'])->nullable();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            // For 'transfer' only: the destination site (must belong to the
            // SAME project as site_id in this schema version — cross-project
            // transfers are out of scope for this phase).
            $table->foreignId('to_site_id')->nullable()->constrained('sites')->restrictOnDelete();
            $table->string('reference_number', 100)->nullable();
            $table->string('remarks', 500)->nullable();
            // True only when this transaction was force-committed past a
            // negative-stock condition via the 'materials.negative_stock_override'
            // permission — always audited alongside the audit_logs entry.
            $table->boolean('is_override')->default(false);
            // Set when this row is an offsetting correction for a prior
            // entry (brief §16: corrections are new ledger rows, never
            // edits to history).
            $table->foreignId('reversal_of_id')->nullable()->constrained('material_transactions')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                [
                    'organization_id',
                    'project_id',
                    'site_id',
                    'created_at',
                ],
                'mat_txn_org_proj_site_date_idx'
            );
            $table->index(['material_id', 'project_id', 'site_id']);
            $table->index('transaction_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_transactions');
    }
};
