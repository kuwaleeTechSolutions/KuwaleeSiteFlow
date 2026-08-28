<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->index(['organization_id', 'status', 'report_date'], 'daily_reports_org_status_date_idx');
            $table->index(['project_id', 'status'], 'daily_reports_project_status_idx');
        });
        Schema::table('measurements', function (Blueprint $table) {
            $table->index(['organization_id', 'status', 'measurement_date'], 'measurements_org_status_date_idx');
            $table->index(['project_id', 'status'], 'measurements_project_status_idx');
        });
        Schema::table('bills', function (Blueprint $table) {
            $table->index(['project_id', 'status', 'bill_date'], 'bills_project_status_date_idx');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['bill_id', 'payment_date'], 'payments_bill_date_idx');
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->index(['organization_id', 'confidentiality_level', 'created_at'], 'documents_org_conf_created_idx');
            $table->index(['project_id', 'confidentiality_level'], 'documents_project_conf_idx');
        });
        Schema::table('compliance_items', function (Blueprint $table) {
            $table->index(['organization_id', 'status', 'expiry_date'], 'compliance_org_status_expiry_idx');
        });
        Schema::table('material_stocks', function (Blueprint $table) {
            $table->index(['project_id', 'quantity_on_hand'], 'material_stocks_project_quantity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', fn (Blueprint $table) => $table->dropIndex('daily_reports_org_status_date_idx'));
        Schema::table('daily_reports', fn (Blueprint $table) => $table->dropIndex('daily_reports_project_status_idx'));
        Schema::table('measurements', fn (Blueprint $table) => $table->dropIndex('measurements_org_status_date_idx'));
        Schema::table('measurements', fn (Blueprint $table) => $table->dropIndex('measurements_project_status_idx'));
        Schema::table('bills', fn (Blueprint $table) => $table->dropIndex('bills_project_status_date_idx'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex('payments_bill_date_idx'));
        Schema::table('documents', fn (Blueprint $table) => $table->dropIndex('documents_org_conf_created_idx'));
        Schema::table('documents', fn (Blueprint $table) => $table->dropIndex('documents_project_conf_idx'));
        Schema::table('compliance_items', fn (Blueprint $table) => $table->dropIndex('compliance_org_status_expiry_idx'));
        Schema::table('material_stocks', fn (Blueprint $table) => $table->dropIndex('material_stocks_project_quantity_idx'));
    }
};
