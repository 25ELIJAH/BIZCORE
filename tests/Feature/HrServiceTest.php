<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EmployeeService;
use App\Services\LeaveService;
use App\Support\Tenancy\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class HrServiceTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    private User $hr;

    private EmployeeService $employees;

    private LeaveService $leave;

    private AttendanceService $attendance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->hr = $this->userWithRole('hr-officer', $this->company);
        $this->actingAs($this->hr);
        app(CurrentCompany::class)->set($this->company);
        $this->employees = app(EmployeeService::class);
        $this->leave = app(LeaveService::class);
        $this->attendance = app(AttendanceService::class);
    }

    private function employee(array $extra = []): Employee
    {
        return $this->employees->save(['first_name' => 'Amina', 'last_name' => 'Otieno', 'hire_date' => '2024-01-01'] + $extra, null, true);
    }

    private function monday(int $weeksAhead = 1): Carbon
    {
        return today()->next('Monday')->addWeeks($weeksAhead - 1);
    }

    // ---- employees ----------------------------------------------------------

    public function test_employee_gets_a_sequential_number_and_department(): void
    {
        $dept = $this->employees->saveDepartment('Finance');
        $a = $this->employee(['department_id' => $dept->id]);
        $b = $this->employee(['first_name' => 'Brian']);

        $this->assertMatchesRegularExpression('/^EMP-\d{4}-00001$/', $a->employee_number);
        $this->assertMatchesRegularExpression('/^EMP-\d{4}-00002$/', $b->employee_number);
        $this->assertSame('Finance', $a->department->name);
    }

    public function test_validation_rules(): void
    {
        foreach ([
            ['first_name' => '', 'last_name' => 'X'],
            ['first_name' => 'A', 'last_name' => 'B', 'email' => 'nope'],
            ['first_name' => 'A', 'last_name' => 'B', 'annual_leave_days' => 400],
            ['first_name' => 'A', 'last_name' => 'B', 'salary' => -5],
        ] as $bad) {
            try {
                $this->employees->save($bad + ['hire_date' => '2024-01-01'], null, true);
                $this->fail('Expected BusinessRuleException');
            } catch (BusinessRuleException) {
            }
        }
        $this->assertSame(0, Employee::count());
    }

    public function test_sensitive_fields_are_ignored_without_permission_and_kept_on_edit(): void
    {
        $e = $this->employee(['national_id' => '12345678', 'salary' => 5000, 'bank_account' => 'ACC-1']);
        $this->assertSame('5000.00', (string) $e->refresh()->salary);

        // an editor without the sensitive permission tries to change them
        $this->employees->save(['first_name' => 'Amina', 'last_name' => 'Changed', 'hire_date' => '2024-01-01', 'salary' => 1, 'national_id' => 'X'], $e, false);

        $e->refresh();
        $this->assertSame('Changed', $e->last_name);
        $this->assertSame('5000.00', (string) $e->salary);
        $this->assertSame('12345678', $e->national_id);
    }

    public function test_sensitive_fields_are_hidden_from_serialisation_and_logs(): void
    {
        $e = $this->employee(['national_id' => '99887766', 'salary' => 7777, 'bank_account' => 'SECRET-ACC']);

        $this->assertArrayNotHasKey('salary', $e->refresh()->toArray());
        $this->assertArrayNotHasKey('national_id', $e->toArray());
        $logs = ActivityLog::all()->map(fn ($l) => json_encode($l->getAttributes()))->implode(' ');
        $this->assertStringNotContainsString('99887766', $logs);
        $this->assertStringNotContainsString('7777', $logs);
        $this->assertStringNotContainsString('SECRET-ACC', $logs);
    }

    public function test_department_must_belong_to_the_company_and_cannot_be_deleted_with_staff(): void
    {
        $dept = $this->employees->saveDepartment('Ops');
        $this->employee(['department_id' => $dept->id]);

        try {
            $this->employees->deleteDepartment($dept);
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }

        $other = Company::factory()->create();
        $foreign = app(CurrentCompany::class)->runAs($other, fn () => $this->employees->saveDepartment('Foreign'));
        $this->expectException(BusinessRuleException::class);
        $this->employee(['department_id' => $foreign->id]);
    }

    public function test_duplicate_department_names_rejected(): void
    {
        $this->employees->saveDepartment('Ops');

        $this->expectException(BusinessRuleException::class);
        $this->employees->saveDepartment('Ops');
    }

    public function test_user_link_rules(): void
    {
        $member = $this->userWithRole('employee', $this->company);
        $outsider = User::factory()->create();

        $e = $this->employee(['user_id' => $member->id]);
        $this->assertSame($member->id, $e->user_id);

        try {
            $this->employee(['first_name' => 'Dup', 'user_id' => $member->id]);
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }
        $this->expectException(BusinessRuleException::class);
        $this->employee(['first_name' => 'Out', 'user_id' => $outsider->id]);
    }

    public function test_terminating_cancels_pending_leave_and_blocks_edits(): void
    {
        $e = $this->employee();
        $req = $this->leave->request($e, 'annual', $this->monday(), $this->monday());

        $this->employees->terminate($e, today());

        $this->assertSame('terminated', $e->status);
        $this->assertSame('cancelled', $req->refresh()->status);
        $this->expectException(BusinessRuleException::class);
        $this->employees->save(['first_name' => 'A', 'last_name' => 'B', 'hire_date' => '2024-01-01'], $e, true);
    }

    public function test_cannot_terminate_before_hire_date_or_twice(): void
    {
        $e = $this->employee();
        try {
            $this->employees->terminate($e, '2023-01-01');
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }
        $this->employees->terminate($e, today());
        $this->expectException(BusinessRuleException::class);
        $this->employees->terminate($e, today());
    }

    // ---- attendance ---------------------------------------------------------

    public function test_attendance_is_one_record_per_employee_per_day_and_can_be_corrected(): void
    {
        $e = $this->employee();
        $this->attendance->record($e, today(), 'late', '09:30', '17:00');
        $this->attendance->record($e, today(), 'present', '08:00', '17:00', 'Corrected');

        $this->assertSame(1, AttendanceRecord::count());
        $this->assertSame('present', AttendanceRecord::first()->status);
    }

    public function test_attendance_rules(): void
    {
        $e = $this->employee();
        foreach ([
            fn () => $this->attendance->record($e, today()->addDay(), 'present'),
            fn () => $this->attendance->record($e, '2023-12-31', 'present'),
            fn () => $this->attendance->record($e, today(), 'teleported'),
            fn () => $this->attendance->record($e, today(), 'present', '17:00', '08:00'),
        ] as $bad) {
            try {
                $bad();
                $this->fail('Expected BusinessRuleException');
            } catch (BusinessRuleException) {
            }
        }
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_cannot_record_attendance_after_termination(): void
    {
        $e = $this->employee();
        $this->employees->terminate($e, today()->subDays(3));

        $this->expectException(BusinessRuleException::class);
        $this->attendance->record($e, today(), 'present');
    }

    public function test_record_day_is_all_or_nothing(): void
    {
        $a = $this->employee();
        $b = $this->employee(['first_name' => 'Bea']);

        try {
            $this->attendance->recordDay(today(), [$a->id => ['status' => 'present'], $b->id => ['status' => 'bogus']]);
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }
        $this->assertSame(0, AttendanceRecord::count());

        $this->assertSame(1, $this->attendance->recordDay(today(), [$a->id => ['status' => 'present'], $b->id => ['status' => '']]));
    }

    // ---- leave --------------------------------------------------------------

    public function test_working_days_exclude_weekends(): void
    {
        $mon = $this->monday();
        $this->assertSame(5, LeaveService::workingDays($mon, $mon->copy()->addDays(6)));
        $this->assertSame(0, LeaveService::workingDays($mon->copy()->addDays(5), $mon->copy()->addDays(6)));
    }

    public function test_leave_request_counts_working_days_and_starts_pending(): void
    {
        $e = $this->employee();
        $req = $this->leave->request($e, 'annual', $this->monday(), $this->monday()->addDays(6), 'Holiday');

        $this->assertSame('pending', $req->status);
        $this->assertSame('5.0', (string) $req->days);
    }

    public function test_weekend_only_and_backwards_ranges_rejected(): void
    {
        $e = $this->employee();
        $sat = $this->monday()->addDays(5);

        foreach ([
            fn () => $this->leave->request($e, 'annual', $sat, $sat->copy()->addDay()),
            fn () => $this->leave->request($e, 'annual', $this->monday()->addDays(3), $this->monday()),
            fn () => $this->leave->request($e, 'annual', today()->subDays(60), today()->subDays(59)),
            fn () => $this->leave->request($e, 'mystery', $this->monday(), $this->monday()),
        ] as $bad) {
            try {
                $bad();
                $this->fail('Expected BusinessRuleException');
            } catch (BusinessRuleException) {
            }
        }
        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_leave_cannot_span_two_years(): void
    {
        $e = $this->employee();

        $this->expectException(BusinessRuleException::class);
        $this->leave->request($e, 'annual', '2027-12-29', '2028-01-04');
    }

    public function test_overlapping_requests_rejected_but_cancelled_ones_do_not_block(): void
    {
        $e = $this->employee();
        $first = $this->leave->request($e, 'sick', $this->monday(), $this->monday()->addDays(2));

        try {
            $this->leave->request($e, 'annual', $this->monday()->addDays(2), $this->monday()->addDays(4));
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }

        $this->leave->cancel($first);
        $this->assertSame('pending', $this->leave->request($e, 'annual', $this->monday()->addDays(2), $this->monday()->addDays(4))->status);
    }

    public function test_annual_allowance_is_enforced_including_pending_requests(): void
    {
        $e = $this->employee(['annual_leave_days' => 5]);
        $this->leave->request($e, 'annual', $this->monday(), $this->monday()->addDays(2)); // 3 days pending

        try {
            $this->leave->request($e, 'annual', $this->monday(2), $this->monday(2)->addDays(2)); // 3 more > 2 left
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException $ex) {
            $this->assertStringContainsString('Not enough annual leave', $ex->getMessage());
        }

        // Sick leave is not limited by the annual allowance
        $this->assertSame('pending', $this->leave->request($e, 'sick', $this->monday(2), $this->monday(2)->addDays(2))->status);
        $this->assertSame('2.0', $this->leave->balance($e, $this->monday()->year)['remaining']);
    }

    public function test_approval_uses_up_the_balance_and_rejection_frees_it(): void
    {
        $e = $this->employee(['annual_leave_days' => 5]);
        $manager = $this->userWithRole('manager', $this->company);
        $a = $this->leave->request($e, 'annual', $this->monday(), $this->monday()->addDays(3)); // 4 days
        $b = $this->leave->request($e, 'annual', $this->monday(3), $this->monday(3)); // 1 day

        $this->actingAs($manager);
        $this->leave->approve($a, 'Enjoy');
        $this->assertSame('approved', $a->status);
        $this->assertSame($manager->id, $a->decided_by);
        $this->assertSame('4.0', $this->leave->balance($e, $this->monday()->year)['used']);

        $this->leave->reject($b, 'Busy week');
        $this->assertSame('1.0', $this->leave->balance($e, $this->monday()->year)['remaining']);
    }

    public function test_rejection_needs_a_reason_and_decisions_are_final(): void
    {
        $e = $this->employee();
        $req = $this->leave->request($e, 'annual', $this->monday(), $this->monday());
        $this->actingAs($this->userWithRole('manager', $this->company));

        try {
            $this->leave->reject($req, ' ');
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }

        $this->leave->approve($req);
        $this->expectException(BusinessRuleException::class);
        $this->leave->reject($req, 'Changed my mind');
    }

    public function test_nobody_can_approve_their_own_leave(): void
    {
        $manager = $this->userWithRole('manager', $this->company);
        $e = $this->employee(['user_id' => $manager->id]);
        $req = $this->leave->request($e, 'annual', $this->monday(), $this->monday());

        $this->actingAs($manager);
        $this->expectException(BusinessRuleException::class);
        $this->leave->approve($req);
    }

    public function test_started_leave_cannot_be_cancelled(): void
    {
        $e = $this->employee();
        $req = $this->leave->request($e, 'sick', today()->subDay(), today()->subDay());
        $this->actingAs($this->userWithRole('manager', $this->company));
        $this->leave->approve($req);

        $this->expectException(BusinessRuleException::class);
        $this->leave->cancel($req);
    }

    public function test_hr_data_is_isolated_between_companies(): void
    {
        $e = $this->employee();
        $req = $this->leave->request($e, 'annual', $this->monday(), $this->monday());

        $other = Company::factory()->create();
        app(CurrentCompany::class)->set($other);

        $this->assertSame(0, Employee::count());
        $this->assertSame(0, LeaveRequest::count());
        $this->assertSame(0, Department::count());
        try {
            $this->leave->approve($req);
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException) {
        }
        $this->expectException(ModelNotFoundException::class);
        $this->employees->terminate($e, today());
    }
}
