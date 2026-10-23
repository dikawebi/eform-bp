<?php

namespace Tests\Feature;

use App\Models\ItSpecTemplate;
use Database\Seeders\ItSpecTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItSpecTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_it_spec_templates_idempotently_and_keeps_admin_edits(): void
    {
        $this->seed(ItSpecTemplateSeeder::class);
        $this->seed(ItSpecTemplateSeeder::class);

        $this->assertSame(5, ItSpecTemplate::query()->count());

        $laptop = ItSpecTemplate::query()->where('code', 'laptop_office_standard')->firstOrFail();
        $this->assertSame('laptop', $laptop->device_type);
        $this->assertTrue($laptop->is_active);
        $this->assertNotEmpty($laptop->specs_json);
        $this->assertSame('RAM', $laptop->specs_json[1]['label']);

        $laptop->forceFill([
            'name' => 'Laptop Office Kustom',
            'specs_json' => [['label' => 'RAM', 'value' => '64 GB']],
            'is_active' => false,
        ])->save();

        $this->seed(ItSpecTemplateSeeder::class);

        $laptop->refresh();
        $this->assertSame('Laptop Office Kustom', $laptop->name);
        $this->assertSame('64 GB', $laptop->specs_json[0]['value']);
        $this->assertFalse($laptop->is_active);
        $this->assertSame(5, ItSpecTemplate::query()->count());
    }
}
