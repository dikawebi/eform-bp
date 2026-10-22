<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ProductionAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class ProductionAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'eform.admin.name' => 'Production Administrator',
            'eform.admin.email' => 'production.admin@borneoprima.com',
            'eform.admin.initial_password' => 'StrongPassword123!',
        ]);
    }

    public function test_it_creates_a_verified_active_admin_and_is_idempotent(): void
    {
        $this->seed(ProductionAdminSeeder::class);

        $user = User::query()->where('email', 'production.admin@borneoprima.com')->firstOrFail();

        $this->assertSame('Production Administrator', $user->name);
        $this->assertTrue(Hash::check('StrongPassword123!', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->active);
        $this->assertTrue($user->hasRole('admin'));

        $originalHash = $user->password;
        config(['eform.admin.initial_password' => 'DifferentPassword123!']);

        $this->seed(ProductionAdminSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'production.admin@borneoprima.com')->count());
        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertTrue(Hash::check('StrongPassword123!', $user->fresh()->password));
    }

    public function test_it_promotes_an_existing_user_without_resetting_the_password(): void
    {
        $existing = User::factory()->create([
            'name' => 'Existing Account',
            'email' => 'production.admin@borneoprima.com',
            'password' => Hash::make('ExistingPassword123!'),
        ]);

        $this->seed(ProductionAdminSeeder::class);

        $existing->refresh();

        $this->assertSame('Existing Account', $existing->name);
        $this->assertTrue(Hash::check('ExistingPassword123!', $existing->password));
        $this->assertTrue($existing->hasRole('admin'));
    }

    public function test_production_requires_an_initial_password_for_a_new_account(): void
    {
        config(['eform.admin.initial_password' => null]);
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_INITIAL_PASSWORD wajib diisi');

        (new ProductionAdminSeeder)->run();
    }

    public function test_it_uses_the_default_admin_email_when_config_is_empty(): void
    {
        config([
            'eform.admin.email' => null,
            'eform.admin.initial_password' => 'StrongPassword123!',
        ]);
        $this->app['env'] = 'production';

        (new ProductionAdminSeeder)->run();

        $this->assertDatabaseHas('users', [
            'email' => 'admin.it@borneoprima.com',
            'active' => true,
        ]);
    }
}
