<?php

namespace Tests\Feature;

use App\Livewire\Hr\Attendance;
use App\Livewire\Hr\Employees;
use App\Livewire\Hr\Leave;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\LeaveService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class HrPagesTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
    }

    private function actAs(string $role, ?Company $company = null, array $attrs = []): User
    {
        $company ??= $this->company;
        $user = $this->userWithRole($role, $company, $attrs);
        $this->actingAs($user);
        app(CurrentCompany::class)->set($company);

        return $user;
    }

    private function employee(array $extra = []): Employee
    {
        return app(CurrentCompany::class)->runAs($this->company, function () use ($extra) {
            $this->actingAs($this->userWithRole('hr-officer', $this->company));

            return app(EmployeeService::class)->save(['first_name' => 'Amina', 'last_name' => 'Otieno', 'hire_date' => '2024-01-01'] + $extra, null, true);
        });
    }

    private function monday(): string
    {
        return today()->next('Monday')->toDateString();
    }

    public function test_hr_officer_adds_an_employee_with_sensitive_data(): void
    {
        $this->actAs('hr-officer');

        Livewire::test(Employees::class)->call('create')
            ->set('firstName', 'Joy')->set('lastName', 'Wanjiru')->set('jobTitle', 'Accountant')
            ->set('salary', '4500')->set('nationalId', '11112222')->call('save')->assertHasNoErrors();

        $e = Employee::first();
        $this->assertSame('Joy Wanjiru', $e->fullName());
        $this->assertSame('4500.00', (string) $e->salary);
    }

    public function test_manager_cannot_manage_employees_and_hr_without_sensitive_access_never_sees_salary(): void
    {
        $e = $this->employee(['salary' => 8888, 'national_id' => 'NID-SECRET']);

        $this->actAs('manager');
        Livewire::test(Employees::class)->assertOk()->assertDontSee('Add employee')->assertDontSee('8888');
        Livewire::test(Employees::class)->call('create')->assertForbidden();

        // an HR user whose role lacks employees.view_sensitive
        $hr = $this->userWithRole('hr-officer', $this->company);
        \App\Models\Role::where('slug', 'hr-officer')->first()->permissions()
            ->detach(\App\Models\Permission::where('slug', 'employees.view_sensitive')->value('id'));
        $this->actingAs(User::find($hr->id));
        app(CurrentCompany::class)->set($this->company);

        $component = Livewire::test(Employees::class)->call('edit', $e->id);
        $component->assertSet('salary', '')->assertSet('nationalId', '')->assertDontSee('8888')->assertDontSee('NID-SECRET');

        $component->set('lastName', 'Renamed')->call('save')->assertHasNoErrors();
        $e->refresh();
        $this->assertSame('Renamed', $e->last_name);
        $this->assertSame('8888.00', (string) $e->salary);
        $this->assertSame('NID-SECRET', $e->national_id);
    }

    public function test_sensitive_data_is_shown_in_the_edit_form_to_authorised_users_only(): void
    {
        $e = $this->employee(['salary' => 1234]);
        $this->actAs('hr-officer');

        Livewire::test(Employees::class)->call('edit', $e->id)->assertSet('salary', '1234.00');
    }

    public function test_employee_without_hr_permissions_cannot_open_employees_or_attendance(): void
    {
        $this->actAs('employee');

        Livewire::test(Employees::class)->assertForbidden();
        $this->get(route('hr.employees'))->assertForbidden();
        $this->get(route('hr.attendance'))->assertForbidden();
    }

    public function test_terminate_through_the_page(): void
    {
        $e = $this->employee();
        $this->actAs('hr-officer');

        Livewire::test(Employees::class)->call('openTerminate', $e->id)->call('terminate')->assertHasNoErrors();
        $this->assertSame('terminated', $e->refresh()->status);
    }

    public function test_attendance_sheet_saves_the_whole_day(): void
    {
        $a = $this->employee();
        $b = $this->employee(['first_name' => 'Brian']);
        $this->actAs('hr-officer');

        Livewire::test(Attendance::class)
            ->assertSet('date', today()->toDateString())
            ->set("rows.{$a->id}.status", 'present')->set("rows.{$a->id}.check_in", '08:00')->set("rows.{$a->id}.check_out", '17:00')
            ->set("rows.{$b->id}.status", 'absent')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(2, AttendanceRecord::count());
        $this->assertSame('absent', AttendanceRecord::where('employee_id', $b->id)->value('status'));
    }

    public function test_attendance_rejects_future_dates_and_bad_times(): void
    {
        $a = $this->employee();
        $this->actAs('hr-officer');

        Livewire::test(Attendance::class)->set('date', today()->addDay()->toDateString())->call('save')->assertHasErrors(['date']);
        Livewire::test(Attendance::class)->set("rows.{$a->id}.status", 'present')->set("rows.{$a->id}.check_in", '18:00')->set("rows.{$a->id}.check_out", '09:00')
            ->call('save')->assertHasErrors(['date']);
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_attendance_is_for_users_with_attendance_permission_only(): void
    {
        $this->actAs('sales-officer');
        Livewire::test(Attendance::class)->assertForbidden();
    }

    public function test_staff_member_requests_leave_for_themselves_and_sees_only_their_own(): void
    {
        $user = $this->actAs('employee');
        $mine = app(CurrentCompany::class)->runAs($this->company, fn () => app(EmployeeService::class)->save(['first_name' => 'Me', 'last_name' => 'Self', 'hire_date' => '2024-01-01', 'user_id' => $user->id], null, true));
        $other = $this->employee(['first_name' => 'Other']);
        app(CurrentCompany::class)->runAs($this->company, function () use ($other) {
            app(LeaveService::class)->request($other, 'annual', $this->monday(), $this->monday());
        });
        $this->actingAs($user);

        Livewire::test(Leave::class)->call('openRequest')->assertSet('employeeId', $mine->id)
            ->set('startDate', $this->monday())->set('endDate', $this->monday())->call('submit')->assertHasNoErrors();

        $this->assertSame(1, LeaveRequest::where('employee_id', $mine->id)->count());
        Livewire::test(Leave::class)->set('status', 'all')->assertSee('Me Self')->assertDontSee('Other Otieno');
    }

    public function test_staff_member_cannot_request_leave_for_someone_else(): void
    {
        $user = $this->actAs('employee');
        app(CurrentCompany::class)->runAs($this->company, fn () => app(EmployeeService::class)->save(['first_name' => 'Me', 'last_name' => 'Self', 'hire_date' => '2024-01-01', 'user_id' => $user->id], null, true));
        $other = $this->employee(['first_name' => 'Other']);
        $this->actingAs($user);

        Livewire::test(Leave::class)->call('openRequest')->set('employeeId', $other->id)
            ->set('startDate', $this->monday())->set('endDate', $this->monday())->call('submit')->assertHasErrors(['employeeId']);
        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_manager_approves_and_rejects_leave_but_staff_cannot(): void
    {
        $e = $this->employee();
        $req = app(CurrentCompany::class)->runAs($this->company, fn () => app(LeaveService::class)->request($e, 'annual', $this->monday(), $this->monday()));

        $this->actAs('employee');
        Livewire::test(Leave::class)->call('openDecision', $req->id, 'approve')->assertForbidden();
        $this->assertSame('pending', $req->refresh()->status);

        $this->actAs('manager');
        Livewire::test(Leave::class)->call('openDecision', $req->id, 'reject')->set('note', '')->call('decide')->assertHasErrors(['note'])
            ->set('note', 'Busy')->call('decide')->assertHasNoErrors();
        $this->assertSame('rejected', $req->refresh()->status);
    }

    public function test_leave_page_data_is_isolated_between_companies(): void
    {
        $e = $this->employee();
        app(CurrentCompany::class)->runAs($this->company, fn () => app(LeaveService::class)->request($e, 'annual', $this->monday(), $this->monday()));

        $otherCompany = Company::factory()->create();
        $this->actAs('manager', $otherCompany);

        Livewire::test(Leave::class)->set('status', 'all')->assertDontSee('Amina Otieno');
        Livewire::test(Employees::class)->assertDontSee('Amina');
    }
}
