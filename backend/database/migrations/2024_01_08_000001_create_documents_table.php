<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // route-key; internal id never exposed
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->cascadeOnDelete();
            $table->enum('category', [
                'contract', 'work_order', 'purchase_order', 'drawing', 'boq_document',
                'invoice', 'bill', 'certificate', 'insurance', 'labour', 'equipment',
                'compliance', 'other',
            ]);
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('confidentiality_level', ['organization', 'project', 'restricted', 'management_only'])
                ->default('organization');
            // Private disk only — NEVER the 'public' disk. See brief §6.
            $table->string('disk', 40)->default('private-documents');
            $table->string('disk_path'); // never serialized to the client
            $table->string('original_filename'); // display only, not trusted for type
            $table->string('mime_type', 100); // server-sniffed
            $table->unsignedBigInteger('size'); // bytes
            $table->date('expiry_date')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'project_id', 'site_id']);
            $table->index(['organization_id', 'category']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
