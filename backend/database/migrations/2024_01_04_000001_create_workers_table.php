<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('worker_code', 60);
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('trade', 100)->nullable();
            $table->string('skill_category', 100)->nullable();
            // Sensitive: daily_wage is stripped from API responses unless the
            // caller holds the 'labour.wages' permission (see WorkerResource).
            $table->decimal('daily_wage', 12, 2)->default(0);
            $table->date('joining_date')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            // Optional photo — private disk only, never public. Government ID
            // metadata is deliberately NOT modeled here per brief §15
            // ("avoid exposing sensitive personal documents to unauthorized
            // users") — if ever required, it belongs in the Phase 9 Document
            // vault with its own confidentiality-level enforcement, not as a
            // plain column on this table.
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'worker_code']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
