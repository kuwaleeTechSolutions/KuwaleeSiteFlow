<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'category' => 'other',
            'title' => $this->faker->sentence(3),
            'confidentiality_level' => 'organization',
            'disk' => 'private-documents',
            'disk_path' => 'documents/test/'.$this->faker->uuid().'.pdf',
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10240,
        ];
    }
}
