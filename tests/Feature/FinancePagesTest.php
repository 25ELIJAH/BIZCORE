<?php

namespace Tests\Feature;

use App\Livewire\Finance\Categories;
use App\Livewire\Finance\Entries;
use App\Models\Company;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Services\FinanceService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class FinancePagesTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
    }

    private function actAs(string $role, ?Company $company = null): void
    {
        $company ??= $this->company;
        $this->actingAs($this->userWithRole($role, $company));
        app(CurrentCompany::class)->set($company);
    }

    private function category(string $type = 'expense', string $name = 'Rent'): FinanceCategory
    {
        return app(CurrentCompany::class)->runAs($this->company, function () use ($type, $name) {
            $this->actingAs($this->userWithRole('company-administrator', $this->company));

            return app(FinanceService::class)->saveCategory($type, $name);
        });
    }

    public function test_accountant_records_edits_and_voids_an_entry(): void
    {
        $cat = $this->category();
        $this->actAs('accountant');

        Livewire::test(Entries::class)
            ->call('create')->set('categoryId', $cat->id)->set('amount', '120.50')->set('description', 'Office rent')
            ->call('save')->assertHasNoErrors();

        $entry = FinanceEntry::first();
        $this->assertSame('expense', $entry->type);
        $this->assertSame('120.50', (string) $entry->amount);

        Livewire::test(Entries::class)->call('edit', $entry->id)->set('amount', '130')->call('save')->assertHasNoErrors();
        $this->assertSame('130.00', (string) $entry->refresh()->amount);

        Livewire::test(Entries::class)->call('openVoid', $entry->id)->set('voidReason', '')->call('void')->assertHasErrors(['voidReason'])
            ->set('voidReason', 'Duplicate')->call('void')->assertHasNoErrors();
        $this->assertTrue($entry->refresh()->isVoided());
    }

    public function test_form_validation(): void
    {
        $this->actAs('accountant');

        Livewire::test(Entries::class)->call('create')->set('amount', '-1')->call('save')->assertHasErrors(['categoryId', 'amount', 'description']);
        $this->assertSame(0, FinanceEntry::count());
    }

    public function test_cannot_use_another_companys_category(): void
    {
        $other = Company::factory()->create();
        $foreign = app(CurrentCompany::class)->runAs($other, function () use ($other) {
            $this->actingAs($this->userWithRole('company-administrator', $other));

            return app(FinanceService::class)->saveCategory('expense', 'Foreign');
        });
        $this->actAs('accountant');

        Livewire::test(Entries::class)->call('create')->set('categoryId', $foreign->id)->set('amount', '5')->set('description', 'x')->call('save')->assertHasErrors(['amount']);
        $this->assertSame(0, FinanceEntry::count());
    }

    public function test_manager_can_view_but_not_record(): void
    {
        $this->actAs('manager');

        Livewire::test(Entries::class)->assertOk()->assertDontSee('Record entry');
        Livewire::test(Entries::class)->call('create')->assertForbidden();
    }

    public function test_sales_officer_cannot_open_finance(): void
    {
        $this->actAs('sales-officer');

        Livewire::test(Entries::class)->assertForbidden();
        $this->get(route('finance.entries'))->assertForbidden();
    }

    public function test_only_users_with_category_permission_manage_categories(): void
    {
        $this->actAs('manager');
        Livewire::test(Categories::class)->assertForbidden();

        $this->actAs('accountant');
        Livewire::test(Categories::class)->call('create')->set('type', 'income')->set('name', 'Donations')->call('save')->assertHasNoErrors();
        $this->assertSame(1, FinanceCategory::where('name', 'Donations')->count());

        Livewire::test(Categories::class)->call('create')->set('type', 'income')->set('name', 'Donations')->call('save')->assertHasErrors(['name']);
    }

    public function test_entries_list_filters_by_type_and_hides_voided_by_default(): void
    {
        $exp = $this->category('expense', 'Rent');
        $inc = $this->category('income', 'Grants');
        app(CurrentCompany::class)->runAs($this->company, function () use ($exp, $inc) {
            $this->actingAs($this->userWithRole('accountant', $this->company));
            $svc = app(FinanceService::class);
            $svc->recordManual(['category_id' => $exp->id, 'amount' => 10, 'entry_date' => today()->toDateString(), 'description' => 'RENT-ENTRY']);
            $svc->recordManual(['category_id' => $inc->id, 'amount' => 20, 'entry_date' => today()->toDateString(), 'description' => 'GRANT-ENTRY']);
            $gone = $svc->recordManual(['category_id' => $inc->id, 'amount' => 30, 'entry_date' => today()->toDateString(), 'description' => 'VOID-ENTRY']);
            $svc->voidManual($gone, 'oops');
        });
        $this->actAs('accountant');

        Livewire::test(Entries::class)->assertSee('RENT-ENTRY')->assertSee('GRANT-ENTRY')->assertDontSee('VOID-ENTRY')
            ->set('type', 'income')->assertSee('GRANT-ENTRY')->assertDontSee('RENT-ENTRY')
            ->set('showVoided', true)->assertSee('VOID-ENTRY');
    }
}
