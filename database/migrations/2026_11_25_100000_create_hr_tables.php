<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();   // login account, if the employee has one
            $table->string('employee_number', 40);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->date('hire_date');
            $table->string('status', 20)->default('active');      // active | terminated
            $table->date('termination_date')->nullable();
            $table->unsignedSmallInteger('annual_leave_days')->default(21);
            // Sensitive: only shown to users with employees.view_sensitive
            $table->string('national_id', 50)->nullable();
            $table->decimal('salary', 15, 2)->nullable();
            $table->string('bank_account', 60)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'employee_number']);
            $table->unique(['company_id', 'user_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'department_id']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->string('status', 20);                          // present | absent | late | half_day | on_leave
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['company_id', 'date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);                            // annual | sick | unpaid | other
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 1);                         // working days (Mon-Fri) in the range
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('pending');      // pending | approved | rejected | cancelled
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
    }
};
