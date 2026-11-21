<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use App\Services\StockService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Real parallel requests: several PHP processes, each with its own database connection,
 * race for the same stock / balance. Uses DatabaseMigrations (committed data) instead of
 * RefreshDatabase, because child processes cannot see an open test transaction.
 */
class ConcurrencyTest extends TestCase
{
    use CreatesTenants, DatabaseMigrations;

    private Company $company;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $user = $this->userWithRole('company-administrator', $this->company);
        $this->userId = $user->id;
        $this->actingAs($user);
        app(CurrentCompany::class)->set($this->company);
    }

    /** Starts every worker at once and returns their outputs. @return list<string> */
    private function race(int $workers, array $args): array
    {
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => config('database.default'),
            'DB_HOST' => config('database.connections.mysql.host'),
            'DB_PORT' => config('database.connections.mysql.port'),
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'DB_USERNAME' => config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ];

        $processes = [];
        for ($i = 0; $i < $workers; $i++) {
            $p = new Process([PHP_BINARY, base_path('tests/Support/concurrency_worker.php'), ...$args], base_path(), $env);
            $p->setTimeout(120);
            $processes[] = $p;
        }
        foreach ($processes as $p) {
            $p->start();
        }

        return array_map(function (Process $p) {
            $p->wait();

            return trim($p->getOutput().$p->getErrorOutput());
        }, $processes);
    }

    private function outcomes(array $outputs, string $prefix): int
    {
        return count(array_filter($outputs, fn ($o) => str_starts_with($o, $prefix)));
    }

    public function test_parallel_sales_of_the_last_unit_oversell_nothing(): void
    {
        $product = Product::factory()->create(['selling_price' => 10]);
        app(StockService::class)->add($product, 1, StockMovementType::Addition, 5);

        $out = $this->race(6, ['sale', $this->company->id, $this->userId, $product->id]);

        $this->assertSame(1, $this->outcomes($out, 'OK'), implode(' | ', $out));
        $this->assertSame(5, $this->outcomes($out, 'REFUSED'), implode(' | ', $out));
        $this->assertSame('0.000', $product->refresh()->quantity);
        $this->assertSame(1, Sale::count());
        $this->assertSame('0.000', bcadd((string) $product->stockMovements()->sum('quantity'), '0', 3), 'ledger balances');
    }

    public function test_parallel_payments_never_exceed_the_balance(): void
    {
        $product = Product::factory()->create(['selling_price' => 100]);
        app(StockService::class)->add($product, 5, StockMovementType::Addition, 5);
        $sale = app(SaleService::class)->create(['items' => [['product_id' => $product->id, 'quantity' => 1]]]);

        $out = $this->race(4, ['pay', $this->company->id, $this->userId, $sale->id, '60']);

        $this->assertSame(1, $this->outcomes($out, 'OK'), implode(' | ', $out));
        $this->assertSame(3, $this->outcomes($out, 'REFUSED'), implode(' | ', $out));
        $this->assertSame('60.00', $sale->refresh()->amount_paid);
        $this->assertSame(1, Payment::where('sale_id', $sale->id)->whereNull('voided_at')->count());
    }

    public function test_parallel_sales_get_unique_document_numbers_and_exact_stock(): void
    {
        $product = Product::factory()->create(['selling_price' => 10]);
        app(StockService::class)->add($product, 50, StockMovementType::Addition, 5);

        $out = $this->race(8, ['sale', $this->company->id, $this->userId, $product->id]);

        $this->assertSame(8, $this->outcomes($out, 'OK'), implode(' | ', $out));
        $this->assertSame(8, Invoice::count());
        $this->assertSame(8, Invoice::distinct()->count('invoice_number'), 'no duplicate invoice numbers');
        $this->assertSame('42.000', $product->refresh()->quantity);
    }
}
