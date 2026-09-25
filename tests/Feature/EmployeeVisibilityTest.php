<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_hod_sees_own_and_recursive_subordinate_but_not_unrelated_leave(): void
    {
        [$heruUser, $heru] = $this->person('Heru', 'hod');
        [$andikaUser, $andika] = $this->person('Andika', 'employee');
        [, $unrelated] = $this->person('Unrelated', 'employee');

        // This deliberately uses the supervisor branch; the service also walks
        // hod_id, allowing both hierarchy paths to be used.
        $andika->update(['supervisor_id' => $heru->id]);
        $this->leave($heru, $heruUser, 'Heru leave');
        $this->leave($andika, $andikaUser, 'Andika leave');
        $this->leave($unrelated, null, 'Unrelated leave');

        $response = $this->actingAs($heruUser)->get(route('leaves.index'))->assertOk();
        $reasons = collect($response->original->getData()['page']['props']['leaves']['data'])->pluck('reason')->all();

        $this->assertContains('Heru leave', $reasons);
        $this->assertContains('Andika leave', $reasons);
        $this->assertNotContains('Unrelated leave', $reasons);
    }

    public function test_employee_cannot_see_unrelated_employee_leave(): void
    {
        [$user, $employee] = $this->person('Employee', 'employee');
        [, $other] = $this->person('Other', 'employee');
        $this->leave($other, null, 'Other private leave');

        $this->actingAs($user)->get(route('leaves.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('leaves.data', fn ($data) => collect($data)->pluck('reason')->doesntContain('Other private leave')));
    }

    private function person(string $name, string $role): array
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole($role);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'name' => $name]);

        return [$user, $employee];
    }

    private function leave(Employee $employee, ?User $creator, string $reason): LeaveRequest
    {
        return LeaveRequest::query()->forceCreate([
            'request_number' => 'CUTI-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT).'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'employee_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'reason' => $reason,
            'status' => RequestStatus::Draft->value,
            'created_by' => $creator?->id,
            'updated_by' => $creator?->id,
        ]);
    }
}
