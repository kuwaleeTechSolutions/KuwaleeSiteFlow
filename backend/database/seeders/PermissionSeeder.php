<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PermissionCatalogue;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalogue::all() as $group => $permissions) {
            foreach ($permissions as $name) {
                Permission::updateOrCreate(
                    ['name' => $name],
                    ['group' => $group]
                );
            }
        }
    }
}
