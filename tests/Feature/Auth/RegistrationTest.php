<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        // Registrasi mandiri aktif khusus email korporat (lihat routes/auth.php).
        $response = $this->get('/register');

        $response->assertOk();
    }

    public function test_new_users_cannot_register_with_non_corporate_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_new_users_can_register_with_corporate_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@borneoprima.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $user = User::where('email', 'test@borneoprima.com')->firstOrFail();
        $this->assertTrue($user->active);
        $this->assertTrue($user->hasRole('employee'));
    }
}
