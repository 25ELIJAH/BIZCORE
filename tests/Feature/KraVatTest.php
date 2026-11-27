<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Livewire\Company\Settings;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Services\StockService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class KraVatTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->actingAs($this->userWithRole('company-administrator', $this->company));
        app(CurrentCompany::class)->set($this->company);
    }

    private function product(string $taxType, int $price = 100): Product
    {
        $p = Product::factory()->create(['selling_price' => $price, 'tax_type' => $taxType]);
        app(StockService::class)->add($p, 100, StockMovementType::Purchase, 5);

        return $p;
    }

    private function sell(array $products, array $extra = []): Sale
    {
        return app(SaleService::class)->create($extra + [
            'tax_rate' => 16,
            'items' => array_map(fn ($p) => ['product_id' => $p->id, 'quantity' => 1], $products),
        ]);
    }

    public function test_vat_is_charged_on_standard_lines_only_and_the_split_is_stored(): void
    {
        $sale = $this->sell([$this->product('standard'), $this->product('zero_rated'), $this->product('exempt')]);

        $this->assertSame('300.00', $sale->subtotal);
        $this->assertSame('100.00', $sale->vatable_amount);
        $this->assertSame('100.00', $sale->zero_rated_amount);
        $this->assertSame('100.00', $sale->exempt_amount);
        $this->assertSame('16.00', $sale->tax_amount);
        $this->assertSame('316.00', $sale->total);
        $this->assertSame(['standard', 'zero_rated', 'exempt'], $sale->items->pluck('tax_type')->all());
    }

    public function test_a_discount_is_shared_across_the_lines(): void
    {
        $sale = $this->sell([$this->product('standard'), $this->product('exempt')], ['discount_type' => 'percent', 'discount_value' => 10]);

        $this->assertSame('90.00', $sale->vatable_amount);
        $this->assertSame('90.00', $sale->exempt_amount);
        $this->assertSame('14.40', $sale->tax_amount);
        $this->assertSame('194.40', $sale->total);
    }

    public function test_the_company_saves_its_kra_pin_and_vat_status(): void
    {
        Livewire::test(Settings::class)
            ->set('name', 'Acme')->set('kra_pin', 'bad pin')->set('vat_registered', true)->call('save')->assertHasErrors('kra_pin');

        Livewire::test(Settings::class)
            ->set('name', 'Acme')->set('vat_registered', true)->set('kra_pin', '')->call('save')->assertHasErrors('kra_pin'); // required when registered

        Livewire::test(Settings::class)
            ->set('name', 'Acme')->set('kra_pin', 'p051234567a')->set('vat_registered', true)->set('vat_rate', '16')->call('save')->assertHasNoErrors();

        $company = Company::find($this->company->id);
        $this->assertSame('P051234567A', $company->setting('kra_pin'));
        $this->assertTrue($company->setting('vat_registered'));
        $this->assertEquals(16, $company->setting('tax_rate'));

        Livewire::test(Settings::class)->set('name', 'Acme')->set('vat_registered', false)->call('save');
        $this->assertEquals(0, Company::find($this->company->id)->setting('tax_rate'));
    }

    public function test_customer_pins_are_checked_and_stored_in_capitals(): void
    {
        Livewire::test(CustomersIndex::class)->call('create')->set('form.name', 'Zed Ltd')->set('form.tax_number', '12345')->call('save')->assertHasErrors('form.tax_number');

        Livewire::test(CustomersIndex::class)->call('create')->set('form.name', 'Zed Ltd')->set('form.tax_number', 'a001234567b')->call('save')->assertHasNoErrors();
        $this->assertSame('A001234567B', \App\Models\Customer::where('name', 'Zed Ltd')->value('tax_number'));
    }

    public function test_the_invoice_is_a_tax_invoice_with_pins_when_registered(): void
    {
        $this->company->forceFill(['settings' => ['vat_registered' => true, 'kra_pin' => 'P051234567A', 'tax_rate' => 16]])->save();
        $customer = \App\Models\Customer::factory()->create(['tax_number' => 'A001234567B']);
        $sale = $this->sell([$this->product('standard'), $this->product('zero_rated')], ['customer_id' => $customer->id]);

        $html = view('pdf.invoice', [
            'sale' => $sale->load(['items', 'invoice', 'customer', 'payments']),
            'company' => Company::find($this->company->id),
            'logo' => null,
            'methods' => [],
        ])->render();

        $this->assertStringContainsString('TAX INVOICE', $html);
        $this->assertStringContainsString('P051234567A', $html);
        $this->assertStringContainsString('A001234567B', $html);
        $this->assertStringContainsString('Zero-rated', $html);
    }

    public function test_the_vat_report_nets_input_vat_off_output_vat(): void
    {
        $this->sell([$this->product('standard', 1000), $this->product('exempt', 500)]); // output VAT 160.00

        $supplier = \App\Models\Supplier::factory()->create();
        \App\Models\PurchaseOrder::query()->forceCreate([
            'company_id' => $this->company->id, 'supplier_id' => $supplier->id, 'po_number' => 'PO-1', 'status' => 'approved',
            'order_date' => today(), 'subtotal' => 500, 'tax_rate' => 16, 'tax_amount' => 80, 'total' => 580, 'amount_paid' => 0,
        ]);

        $report = app(ReportService::class)->vat(today()->startOfMonth()->toDateString(), today()->toDateString());

        $this->assertSame('1000.00', $report['rows'][0]['net']);
        $this->assertSame('160.00', $report['rows'][0]['vat']);
        $this->assertSame('500.00', $report['rows'][2]['net']);
        $this->assertSame('80.00', $report['rows'][4]['vat']);
        $this->assertSame('80.00', $report['totals']['vat']);

        $this->get(route('reports.show', 'vat'))->assertOk()->assertSee('VAT return (KRA)');
    }

    public function test_products_carry_a_vat_category(): void
    {
        $this->assertSame('standard', Product::factory()->create()->refresh()->tax_type);
    }
}
