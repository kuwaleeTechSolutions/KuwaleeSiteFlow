<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Nullable organization_id = system role template (Owner, Admin,
            // Project Manager, Site Supervisor, Store Manager, HR/Labour
            // Manager, Accounts Manager, Client). Cloned into an organization
            // as concrete (organization_id set) rows when the org is created,
            // and organizations may additionally define fully custom roles.
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 80);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            // org_wide_visibility: bypasses the project/site *assignment*
            // check in policies (still subject to the permission check).
            // Used for Owner/Admin style roles.
            $table->boolean('org_wide_visibility')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            // Denormalized for fast, index-only organization-scoped lookups
            // without joining through users/roles.
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
