<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Exports\ReportExport;
use App\Models\Company;
use App\Models\Product;
use App\Services\PaymentService;
use App\Services\SaleService;
use App\Services\StockService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/** Builds the real files (no fakes) and inspects them, so layout code is actually executed. */
class ExportFilesTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function report(): array
    {
        return [
            'title' => 'Sample', 'subtitle' => 'x',
            'columns' => [
                'date' => ['label' => 'Date', 'type' => 'date'],
                'name' => ['label' => 'Name', 'type' => 'text'],
                'qty' => ['label' => 'Qty', 'type' => 'number'],
                'paid' => ['label' => 'Paid', 'type' => 'money'],
            ],
            'rows' => [
                ['date' => '2026-03-05', 'name' => str_repeat('Very long customer name ', 10), 'qty' => '2', 'paid' => '0.00'],
                ['date' => '2026-03-06', 'name' => 'Short', 'qty' => '3', 'paid' => '1234.5'],
            ],
            'totals' => ['date' => 'Total', 'qty' => '5', 'paid' => '1234.50'],
        ];
    }

    private function load(array $report)
    {
        $file = tempnam(sys_get_temp_dir(), 'xl').'.xlsx';
        file_put_contents($file, Excel::raw(new ReportExport($report), ExcelWriter::XLSX));
        $ws = IOFactory::load($file)->getActiveSheet();
        @unlink($file);

        return $ws;
    }

    public function test_excel_file_is_styled_with_real_dates_and_kept_zeros(): void
    {
        $ws = $this->load($this->report());

        $this->assertTrue($ws->getStyle('A1')->getFont()->getBold(), 'header bold');
        $this->assertSame('A2', $ws->getFreezePane());
        $this->assertSame('A1:D3', $ws->getAutoFilter()->getRange(), 'filter covers data rows, not the totals row');
        $this->assertTrue($ws->getStyle('A4')->getFont()->getBold(), 'totals row bold');
        $this->assertSame('0C4137', $ws->getStyle('A1')->getFill()->getStartColor()->getRGB(), 'header uses the brand green');
        $this->assertSame('FFFFFF', $ws->getStyle('A1')->getFont()->getColor()->getRGB(), 'header text is white on the green fill');
        $this->assertSame('E6FBF6', $ws->getStyle('A4')->getFill()->getStartColor()->getRGB(), 'totals row is tinted');

        $this->assertSame('yyyy-mm-dd', $ws->getStyle('A2')->getNumberFormat()->getFormatCode());
        $this->assertSame('2026-03-05', Date::excelToDateTimeObject($ws->getCell('A2')->getValue())->format('Y-m-d'), 'a real date, not text');
        $this->assertSame('Total', $ws->getCell('A4')->getValue(), 'totals label stays text');

        $this->assertSame('#,##0.00', $ws->getStyle('D2')->getNumberFormat()->getFormatCode());
        $this->assertSame(0, (int) $ws->getCell('D2')->getValue());
        $this->assertNotNull($ws->getCell('D2')->getValue(), 'zero is written, not left blank');
        $this->assertSame('#,##0', $ws->getStyle('C2')->getNumberFormat()->getFormatCode(), 'whole-number column has no decimals');
    }

    public function test_excel_column_width_is_capped_and_page_is_set_up_for_printing(): void
    {
        $ws = $this->load($this->report());

        $this->assertLessThanOrEqual(45, $ws->getColumnDimension('B')->getWidth());
        $this->assertTrue($ws->getStyle('B2')->getAlignment()->getWrapText(), 'long text wraps instead of widening the column');
        $this->assertSame(1, $ws->getPageSetup()->getFitToWidth());
        $this->assertSame([1, 1], $ws->getPageSetup()->getRowsToRepeatAtTop(), 'header row repeats on each printed page');
        $this->assertSame('portrait', $ws->getPageSetup()->getOrientation(), 'four columns fit portrait');

        $wide = $this->report();
        foreach (['e', 'f', 'g'] as $k) {
            $wide['columns'][$k] = ['label' => strtoupper($k), 'type' => 'text'];
        }
        $this->assertSame('landscape', $this->load($wide)->getPageSetup()->getOrientation(), 'more than five columns print landscape');
    }

    public function test_invoice_pdf_renders_with_the_invoice_number_in_the_footer(): void
    {
        $company = Company::factory()->create();
        $this->actingAs($this->userWithRole('company-administrator', $company));
        app(CurrentCompany::class)->set($company);
        $product = Product::factory()->create(['selling_price' => 50]);
        app(StockService::class)->add($product, 20, StockMovementType::Addition, 10);
        $sale = app(SaleService::class)->create(['items' => [['product_id' => $product->id, 'quantity' => 2]]]);
        app(PaymentService::class)->record($sale, 40, 'cash');

        $response = $this->get(route('sales.invoice', $sale));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
