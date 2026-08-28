<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('bill_number', 100);
            $table->enum('bill_type', ['running', 'interim', 'final'])->default('running');
            $table->date('bill_date');
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            // Reference/cumulative figure — SUM of prior certified bills'
            // current_work_value for this project, computed by
            // BillingService, not independently editable by the client.
            $table->decimal('previous_certified_amount', 18, 2)->default(0);
            // SUM(bill_items.amount) for this bill — server-computed.
            $table->decimal('current_work_value', 18, 2)->default(0);
            $table->decimal('deductions', 18, 2)->default(0);
            $table->decimal('taxes', 18, 2)->default(0);
            // net_payable = current_work_value - deductions - taxes.
            // ALWAYS recomputed server-side via BCMath; never trusted from
            // client-submitted totals (brief §21).
            $table->decimal('net_payable', 18, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'certified', 'partially_paid', 'paid', 'cancelled'])
                ->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('certified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('certified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'bill_number']);
            $table->index(['organization_id', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
