<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global, system-wide catalogue of atomic permission strings.
        // Not organization-scoped — the catalogue itself is a fixed vocabulary
        // (e.g. "projects.view"); what varies per organization is which ROLE
        // is granted which permission (see role_permissions).
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g. "projects.view"
            $table->string('group', 60);       // e.g. "projects"
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
