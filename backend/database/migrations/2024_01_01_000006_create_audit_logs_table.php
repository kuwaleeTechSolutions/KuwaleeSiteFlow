<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            // Nullable: some entries are system-generated (e.g. scheduled
            // compliance scans) with no acting user.
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('action', 120);      // e.g. "document.downloaded"
            $table->string('entity_type', 120); // e.g. "App\Models\Document"
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
