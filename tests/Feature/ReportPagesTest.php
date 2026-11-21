<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Exports\ReportExport;
use App\Livewire\Dashboard;
use App\Livewire\Reports\Index;
use App\Livewire\Reports\Show;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Services\PaymentService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Services\StockService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ReportPagesTest extends TestCase
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

    private function sale(string $name = 'Widget', ?Company $company = null): void
    {
        app(CurrentCompany::class)->runAs($company ?? $this->company, function () use ($name) {
            $this->actingAs($this->userWithRole('company-administrator', $this->company));
            $p = Product::factory()->create(['name' => $name, 'selling_price' => 50]);
            app(StockService::class)->add($p, 10, StockMovementType::Purchase, 20);
            app(SaleService::class)->create(['items' => [['product_id' => $p->id, 'quantity' => 2]], 'payment' => ['amount' => 30, 'method' => 'cash']]);
        });
    }

    public function test_dashboard_shows_only_the_sections_the_role_may_see(): void
    {
        $this->sale('Widget');

        $this->actAs('sales-officer');
        Livewire::test(Dashboard::class)->assertSee('Sales this month')->assertSee('100.00')
            ->assertDontSee('Finance (this month)')->assertDontSee('Procurement')->assertDontSee('Active employees');

        $this->actAs('accountant');
        Livewire::test(Dashboard::class)->assertSee('Finance (this month)')->assertDontSee('Stock value');

        $this->actAs('inventory-officer');
        Livewire::test(Dashboard::class)->assertSee('Stock value')->assertDontSee('Sales this month');
    }

    public function test_dashboard_for_a_role_without_dashboard_permission_is_empty(): void
    {
        $this->sale();
        $this->actAs('sales-officer');
        \App\Models\Role::where('slug', 'sales-officer')->first()->permissions()->detach(\App\Models\Permission::where('slug', 'dashboard.view')->value('id'));
        $this->actingAs(\App\Models\User::find(auth()->id()));

        Livewire::test(Dashboard::class)->assertSee('nothing to show')->assertDontSee('Sales this month');
    }

    public function test_dashboard_emits_chart_configuration_for_chartjs(): void
    {
        $this->sale('Widget');
        $this->actAs('company-administrator');

        $html = Livewire::test(Dashboard::class)->html();

        $this->assertStringContainsString('data-chart=', $html);
        $this->assertStringContainsString('Bar chart of monthly sales', $html);
        $this->assertStringContainsString('Widget', $html); // top products label
    }

    public function test_dashboard_is_isolated_per_company(): void
    {
        $this->sale('SecretProduct');
        $other = Company::factory()->create();
        $this->actAs('company-administrator', $other);

        Livewire::test(Dashboard::class)->assertDontSee('SecretProduct')->assertDontSee('100.00');
    }

    public function test_report_index_lists_only_permitted_reports(): void
    {
        $this->actAs('inventory-officer'); // reports.view + inventory.view only
        Livewire::test(Index::class)->assertSee('Inventory valuation')->assertDontSee('Customer receivables ageing')->assertDontSee('Income and expenses');

        $this->actAs('accountant');
        Livewire::test(Index::class)->assertSee('Customer receivables ageing')->assertSee('Income and expenses');
    }

    public function test_users_without_report_permissions_are_refused(): void
    {
        $this->actAs('sales-officer'); // no reports.* at all
        Livewire::test(Index::class)->assertForbidden();
        $this->get(route('reports.show', 'sales'))->assertNotFound();
    }

    public function test_financial_reports_need_financial_permission(): void
    {
        $this->actAs('inventory-officer');
        $this->get(route('reports.show', 'receivables'))->assertNotFound();
        $this->get(route('reports.show', 'product-profit'))->assertNotFound();
        $this->get(route('reports.show', 'nonexistent'))->assertNotFound();
    }

    public function test_report_page_renders_figures_and_validates_dates(): void
    {
        $this->sale('Widget');
        $this->actAs('company-administrator');

        Livewire::test(Show::class, ['report' => 'sales'])->assertSee('100.00')->assertSee('Walk-in customer')
            ->set('from', '2099-01-01')->assertSee('Choose a valid date range')
            ->set('from', today()->startOfMonth()->toDateString())->assertSee('100.00');
    }

    public function test_pdf_export_downloads_a_pdf_and_is_audited(): void
    {
        $this->sale();
        $this->actAs('manager');

        $response = $this->get(route('reports.export', ['report' => 'sales', 'format' => 'pdf']));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertTrue(ActivityLog::where('action', 'report.exported')->exists());
    }

    public function test_excel_export_contains_the_report_rows_as_numbers(): void
    {
        $this->sale();
        $this->actAs('manager');
        Excel::fake();
        Excel::matchByRegex();

        $this->get(route('reports.export', ['report' => 'sales', 'format' => 'xlsx']))->assertOk();

        Excel::assertDownloaded('/-sales-.*\.xlsx$/', function (ReportExport $export) {
            $rows = $export->array();
            $this->assertSame(['Date', 'Invoice', 'Customer', 'Total', 'Paid', 'Balance', 'Payment'], $export->headings());
            $this->assertSame(100.0, $rows[0][3]);   // numeric, so it can be summed
            $this->assertSame(100.0, end($rows)[3]); // totals row

            return true;
        });
    }

    public function test_excel_export_neutralises_formula_injection(): void
    {
        app(CurrentCompany::class)->set($this->company);
        $report = ['title' => 'X', 'columns' => ['name' => ['label' => 'Name', 'type' => 'text']], 'rows' => [['name' => '=HYPERLINK("http://evil","x")'], ['name' => '@SUM(1)'], ['name' => 'Normal']], 'totals' => null];

        $rows = (new ReportExport($report))->array();

        $this->assertSame('\'=HYPERLINK("http://evil","x")', $rows[0][0]);
        $this->assertSame("'@SUM(1)", $rows[1][0]);
        $this->assertSame('Normal', $rows[2][0]);
    }

    public function test_export_requires_the_export_permission(): void
    {
        $this->sale();
        $this->actAs('inventory-officer'); // can view the inventory report but has no reports.export
        $this->get(route('reports.show', 'inventory'))->assertOk();

        $this->get(route('reports.export', ['report' => 'inventory', 'format' => 'pdf']))->assertForbidden();
    }

    public function test_export_rejects_unknown_formats_and_bad_dates(): void
    {
        $this->actAs('manager');

        $this->get(route('reports.export', ['report' => 'sales', 'format' => 'exe']))->assertNotFound();
        $this->get(route('reports.export', ['report' => 'sales', 'format' => 'pdf', 'from' => '2026-02-01', 'to' => '2026-01-01']))->assertSessionHasErrors('to');
    }

    public function test_export_contains_only_the_current_companys_data(): void
    {
        $this->sale('SecretProduct');
        $other = Company::factory()->create();
        $this->actAs('manager', $other);
        Excel::fake();
        Excel::matchByRegex();

        $this->get(route('reports.export', ['report' => 'product-profit', 'format' => 'xlsx']))->assertOk();

        Excel::assertDownloaded('/-product-profit-.*\.xlsx$/', function (ReportExport $export) {
            $this->assertCount(1, $export->array(), 'only the totals row, no rows from the other company');
            $this->assertStringNotContainsString('SecretProduct', json_encode($export->array()));

            return true;
        });
    }
}
