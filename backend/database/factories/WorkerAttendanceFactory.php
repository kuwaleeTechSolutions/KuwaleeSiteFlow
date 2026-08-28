<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WorkerAttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_date' => now()->toDateString(),
            'shift' => 'day',
            'status' => 'present',
            'overtime_hours' => 0,
        ];
    }
}
