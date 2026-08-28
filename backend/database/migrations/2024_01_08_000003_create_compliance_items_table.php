<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('title');
            $table->enum('type', [
                'insurance', 'labour_licence', 'equipment_certificate',
                'calibration', 'vehicle_document', 'other',
            ]);
            $table->date('issue_date')->nullable();
            $table->date('expiry_date');
            $table->foreignId('responsible_person_id')->nullable()->constrained('users')->nullOnDelete();
            // Polymorphic-by-convention (not a true morph relation, to keep
            // the FK constraint story simple) — related_entity_type names
            // which table related_entity_id points into.
            $table->enum('related_entity_type', ['project', 'site', 'equipment', 'worker', 'organization'])
                ->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->enum('status', ['valid', 'expiring', 'expired'])->default('valid');
            // Tracks the smallest alert threshold (60/30/15/7/0) already
            // notified for, so ScanComplianceExpiry never re-sends the same
            // alert on every subsequent daily run.
            $table->unsignedSmallInteger('last_alert_threshold_days')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('expiry_date');
            $table->index(['related_entity_type', 'related_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_items');
    }
};
