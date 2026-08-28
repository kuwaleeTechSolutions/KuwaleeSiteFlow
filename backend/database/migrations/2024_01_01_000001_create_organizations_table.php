<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            // Used only to disambiguate login when the same email exists
            // under more than one organization (rare, but supported per
            // brief §10 fields). Auto-generated from name if not provided.
            $table->string('slug', 120)->unique();
            $table->string('legal_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('gst_number', 30)->nullable();
            $table->enum('status', ['active', 'suspended', 'trial'])->default('trial');
            // Organization-level settings: currency, timezone, date_format,
            // fy_start_month, default report settings, pdf branding, compliance
            // alert thresholds, etc. Kept as JSON to avoid churny schema changes.
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
