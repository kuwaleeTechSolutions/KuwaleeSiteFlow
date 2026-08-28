<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_attendance', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('shift', 30)->default('day');
            $table->enum('status', ['present', 'absent', 'half_day'])->default('present');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->foreignId('marked_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Prevents duplicate attendance entries for the same worker on
            // the same day/shift — the mechanism that stops accidental (or
            // deliberate) double-counting of wages.
            $table->unique(['worker_id', 'attendance_date', 'shift']);
            $table->index(
                [
                    'organization_id',
                    'project_id',
                    'site_id',
                    'attendance_date',
                ],
                'wrk_att_org_proj_site_date_idx'
            );
            $table->index('attendance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_attendance');
    }
};
