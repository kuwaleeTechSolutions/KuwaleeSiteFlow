<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_report_photos', function (Blueprint $table) {
            $table->id();
            // Photos are surfaced via a policy-gated streaming endpoint
            // identified by UUID, never the auto-increment id, mirroring the
            // Document security model that will be generalised in Phase 9.
            $table->uuid('uuid')->unique();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // NEVER exposed to the client directly — private disk + a
            // randomized filename. See PhotoUploadService.
            $table->string('disk', 40)->default('private-documents');
            $table->string('disk_path');
            $table->string('original_filename'); // display only, never trusted for type
            $table->string('mime_type', 100);     // server-sniffed, not client-supplied
            $table->unsignedBigInteger('size');   // bytes
            $table->string('caption')->nullable();
            $table->boolean('exif_stripped')->default(false);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('daily_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_photos');
    }
};
