<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EmployeeService;
use App\Services\FinanceService;
use App\Services\GoodsReceiptService;
use App\Services\LeaveService;
use App\Services\PaymentService;
use App\Services\ProductService;
use App\Services\PurchaseOrderService;
use App\Services\SaleService;
use App\Services\SupplierPaymentService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * A realistic three-month trading history for the demo company "Acme Trading Ltd" (Nairobi, KES).
 *
 * Everything goes through the real services, so stock, invoices, payments, finance entries and
 * document numbers stay consistent exactly as they would in daily use. Local environment only,
 * and it refuses to run twice (it stops if the company already has sales).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DemoDataSeeder skipped: only runs in the local environment.');

            return;
        }

        $company = Company::where('name', 'Acme Trading Ltd')->first();
        $actor = User::where('email', 'admin@bizcore.test')->first();
        if (! $company || ! $actor) {
            $this->command?->error('Run DevelopmentSeeder first (php artisan db:seed).');

            return;
        }

        app(CurrentCompany::class)->runAs($company, function () use ($company, $actor) {
            Auth::login($actor);

            if (Sale::count() > 5) {
                $this->command?->warn('Acme already has sales; demo data not loaded again.');

                return;
            }

            mt_srand(2026);
            $this->profile($company);
            [$categories, $products] = $this->catalogue();
            $customers = $this->customers();
            $suppliers = $this->suppliers();
            $this->purchasing($products, $suppliers);
            $this->selling($products, $customers);
            $this->finance();
            $this->people();

            $this->command?->info('Demo data loaded for '.$company->name.'.');
        });
    }

    private function profile(Company $company): void
    {
        $company->forceFill([
            'registration_number' => 'PVT-ACM2019',
            'address' => "Mombasa Road, Industrial Area\nP.O. Box 40123-00100, Nairobi",
            'phone' => '+254 20 555 0142',
            'settings' => [
                'tax_rate' => 16, // Kenyan VAT
                'payment_terms_days' => 30,
                'allow_negative_stock' => false,
                'payment_methods' => ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'card' => 'Card'],
            ],
        ])->save();
    }

    /** @return array{0: array<string, ProductCategory>, 1: list<\App\Models\Product>} */
    private function catalogue(): array
    {
        $categories = [];
        foreach (['Groceries', 'Beverages', 'Household', 'Hardware'] as $name) {
            $categories[$name] = ProductCategory::create(['name' => $name]);
        }

        // [category, name, unit, buy, sell, reorder level]
        $items = [
            ['Groceries', 'Maize Flour 2kg', 'pkt', 165, 195, 120], ['Groceries', 'Wheat Flour 2kg', 'pkt', 190, 225, 100],
            ['Groceries', 'Sugar 2kg', 'pkt', 300, 345, 100], ['Groceries', 'Pishori Rice 5kg', 'bag', 880, 990, 40],
            ['Groceries', 'Cooking Oil 5L', 'jerrycan', 1150, 1320, 40], ['Groceries', 'Salt 1kg', 'pkt', 45, 60, 150],
            ['Groceries', 'Spaghetti 400g', 'pkt', 80, 105, 100], ['Groceries', 'Green Grams 1kg', 'pkt', 170, 210, 60],
            ['Groceries', 'Beans (Rosecoco) 1kg', 'pkt', 150, 190, 60], ['Groceries', 'Margarine 500g', 'tub', 190, 235, 80],
            ['Beverages', 'Tea Leaves 500g', 'pkt', 245, 295, 80], ['Beverages', 'Instant Coffee 100g', 'jar', 320, 385, 50],
            ['Beverages', 'Long-life Milk 500ml', 'pkt', 62, 75, 200], ['Beverages', 'Mineral Water 1.5L', 'btl', 58, 80, 150],
            ['Beverages', 'Juice 1L', 'ctn', 115, 150, 80], ['Beverages', 'Soda 500ml (crate of 24)', 'crate', 1050, 1250, 30],
            ['Household', 'Laundry Bar Soap 1kg', 'pkt', 175, 215, 80], ['Household', 'Washing Powder 1kg', 'pkt', 210, 260, 80],
            ['Household', 'Toilet Paper (10 rolls)', 'pkt', 330, 399, 60], ['Household', 'Dish Soap 750ml', 'btl', 145, 185, 60],
            ['Household', 'Bleach 1L', 'btl', 110, 145, 60], ['Household', 'Matchboxes (pack of 10)', 'pkt', 55, 75, 100],
            ['Hardware', 'Cement 50kg', 'bag', 690, 780, 100], ['Hardware', 'Iron Sheet 3m (Gauge 30)', 'pc', 1020, 1180, 60],
            ['Hardware', 'Nails 3-inch 1kg', 'kg', 190, 240, 80], ['Hardware', 'Paint Emulsion 4L (White)', 'tin', 1850, 2250, 30],
            ['Hardware', 'Padlock 50mm', 'pc', 260, 350, 40], ['Hardware', 'PVC Pipe 1-inch 6m', 'pc', 480, 620, 40],
            ['Hardware', 'Wheelbarrow', 'pc', 3900, 4800, 10], ['Hardware', 'Solar Lantern', 'pc', 950, 1350, 25],
        ];

        $products = [];
        foreach ($items as $i => [$cat, $name, $unit, $buy, $sell, $reorder]) {
            $products[] = app(ProductService::class)->create([
                'product_category_id' => $categories[$cat]->id, 'sku' => sprintf('ACM-%03d', $i + 1), 'name' => $name, 'unit' => $unit,
                'purchase_price' => $buy, 'selling_price' => $sell, 'min_stock' => $reorder,
            ]);
        }

        return [$categories, $products];
    }

    /** @return list<Customer> */
    private function customers(): array
    {
        $rows = [
            ['Kilimani Mini Mart', 'Wanjiru Kamau', '0712 345 678'], ['Eastleigh General Stores', 'Abdi Hassan', '0722 118 204'],
            ['Thika Road Pharmacy & Stores', 'Grace Achieng', '0733 902 551'], ['Nyali Cafe & Bakery', 'Omar Said', '0701 667 330'],
            ['Kisumu Lakeside Wholesalers', 'Peter Otieno', '0720 445 119'], ['Nakuru Farmers Agrovet', 'Joseph Kiprop', '0711 280 903'],
            ['Mama Njeri Kiosk', 'Njeri Mwangi', '0700 551 842'], ['Eldoret Highland Traders', 'Daniel Rotich', '0723 667 410'],
            ['Karen Fresh Grocers', 'Susan Wambui', '0734 118 766'], ['Machakos Builders Supplies', 'Mutua Mwende', '0722 903 125'],
            ['Kakamega Community SACCO Shop', 'Beatrice Shikuku', '0710 774 220'], ['Westlands Office Canteen', 'Hussein Ali', '0705 332 981'],
            ['Ruiru Construction Ltd', 'James Njoroge', '0728 410 657'], ['Naivasha Flower Farm Canteen', 'Lucy Chebet', '0715 603 774'],
        ];

        return array_map(fn ($r) => Customer::factory()->create([
            'name' => $r[0], 'contact_person' => $r[1], 'phone' => $r[2],
            'email' => str(strtolower(explode(' ', $r[0])[0]))->slug().'@example.co.ke', 'address' => 'Kenya', 'tax_number' => 'P0'.mt_rand(10000000, 99999999).'X',
        ]), $rows);
    }

    /** @return list<Supplier> */
    private function suppliers(): array
    {
        $rows = [
            ['Unga Millers Kenya Ltd', 'Samuel Karanja', '0720 100 201'], ['Bidco-style Oils & Fats Ltd', 'Anne Muthoni', '0733 200 302'],
            ['Mumias Sugar Distributors', 'Fredrick Wekesa', '0711 300 403'], ['Savannah Cement Dealers', 'Hellen Wafula', '0722 400 504'],
            ['Rift Valley Iron & Steel', 'Moses Kiplagat', '0702 500 605'], ['Kenya Tea & Beverages Co.', 'Mercy Wanjiku', '0734 600 706'],
            ['Pwani Household Products', 'Ali Mwinyi', '0716 700 807'],
        ];

        return array_map(fn ($r) => Supplier::factory()->create(['name' => $r[0], 'contact_person' => $r[1], 'phone' => $r[2], 'email' => str(explode(' ', $r[0])[0])->slug().'@suppliers.example.co.ke']), $rows);
    }

    private function purchasing(array $products, array $suppliers): void
    {
        $po = app(PurchaseOrderService::class);
        $grn = app(GoodsReceiptService::class);
        $pay = app(SupplierPaymentService::class);

        // Opening restock 85 days ago: every product, split across suppliers by category.
        $byCategory = collect($products)->groupBy('product_category_id')->values();
        foreach ($byCategory as $i => $group) {
            foreach ($group->chunk(8) as $chunk) {
                $order = $po->saveDraft([
                    'supplier_id' => $suppliers[($i * 2) % count($suppliers)]->id, 'order_date' => today()->subDays(85)->toDateString(), 'tax_rate' => 16,
                    'items' => $chunk->map(fn ($p) => ['product_id' => $p->id, 'quantity' => mt_rand(500, 1000) * ($p->min_stock <= 30 ? 0.2 : 1), 'unit_cost' => $p->purchase_price])->all(),
                ]);
                $po->submit($order);
                $po->approve($order);
                $this->receiveAll($grn, $order->refresh(), today()->subDays(83));
                // Wholesalers buy on credit: roughly 40% settled, the rest still owed to the supplier.
                $pay->record($order->refresh(), bcmul((string) $order->total, '0.4', 2), 'bank_transfer', today()->subDays(60));
            }
        }

        // Follow-up restocks over the next weeks, in different states.
        foreach ([[55, 'paid'], [40, 'partial_pay'], [30, 'part_received'], [20, 'received_unpaid'], [12, 'approved'], [6, 'pending'], [3, 'draft']] as [$daysAgo, $state]) {
            $picked = collect($products)->shuffle()->take(mt_rand(3, 6));
            $order = $po->saveDraft([
                'supplier_id' => $suppliers[array_rand($suppliers)]->id, 'order_date' => today()->subDays($daysAgo)->toDateString(), 'tax_rate' => 16,
                'expected_date' => today()->subDays($daysAgo)->addDays(7)->toDateString(),
                'items' => $picked->map(fn ($p) => ['product_id' => $p->id, 'quantity' => mt_rand(40, 200), 'unit_cost' => $p->purchase_price])->values()->all(),
            ]);
            if ($state === 'draft') {
                continue;
            }
            $po->submit($order);
            if ($state === 'pending') {
                continue;
            }
            $po->approve($order);
            if ($state === 'approved') {
                continue;
            }
            $partial = $state === 'part_received';
            $this->receiveAll($grn, $order->refresh(), today()->subDays(max($daysAgo - 3, 1)), $partial ? 0.5 : 1);
            $order->refresh();
            if ($state === 'paid') {
                $pay->record($order, $order->total, 'bank_transfer', today()->subDays($daysAgo - 5));
            } elseif ($state === 'partial_pay') {
                $pay->record($order, bcdiv((string) $order->total, '2', 2), 'cheque', today()->subDays($daysAgo - 5), 'CHQ-'.mt_rand(100000, 999999));
            }
        }
    }

    private function receiveAll(GoodsReceiptService $grn, $order, Carbon $date, float $fraction = 1.0): void
    {
        $lines = $order->items->map(fn ($l) => ['purchase_order_item_id' => $l->id, 'quantity' => round((float) $l->quantity_ordered * $fraction, 3)])->all();
        $grn->receive($order, $lines, $date);
    }

    private function selling(array $products, array $customers): void
    {
        $sales = app(SaleService::class);
        $pay = app(PaymentService::class);
        $methods = ['mpesa', 'mpesa', 'mpesa', 'cash', 'cash', 'bank_transfer', 'cheque'];

        for ($day = 80; $day >= 0; $day--) {
            $date = today()->subDays($day);
            if ($date->isSunday()) {
                continue;
            }
            $count = $date->isSaturday() ? mt_rand(2, 5) : mt_rand(4, 8);
            for ($n = 0; $n < $count; $n++) {
                $lines = collect($products)->shuffle()->take(mt_rand(1, 5))->map(fn ($p) => [
                    'product_id' => $p->id, 'quantity' => max(1, (int) round(mt_rand(5, 40) * (($p->selling_price > 2000) ? 0.2 : 1))),
                ])->values()->all();

                try {
                    $sale = $sales->create([
                        'customer_id' => mt_rand(1, 100) <= 15 ? null : $customers[array_rand($customers)]->id,
                        'sale_date' => $date->toDateString(), 'items' => $lines,
                        'discount_type' => 'percent', 'discount_value' => mt_rand(1, 100) <= 12 ? 5 : 0,
                    ]);
                } catch (\Throwable) {
                    continue; // not enough stock for this random basket: skip it, like a real counter would
                }

                $roll = mt_rand(1, 100);
                $method = $methods[array_rand($methods)];
                if ($roll <= 60 || $sale->customer_id === null) {
                    $pay->record($sale, $sale->total, $method, $date, $method === 'mpesa' ? 'MP'.strtoupper(str()->random(9)) : null);
                } elseif ($roll <= 82) {
                    $pay->record($sale, bcmul((string) $sale->total, '0.5', 2), $method, $date->copy()->addDays(mt_rand(0, 5)) > today() ? today() : $date->copy()->addDays(mt_rand(0, 5)));
                } // else: left unpaid, becomes receivable / overdue
            }
        }
    }

    private function finance(): void
    {
        $fin = app(FinanceService::class);
        $expenses = [
            'Rent' => [85000, 85000], 'Electricity (KPLC)' => [9000, 16000], 'Internet & Phones' => [6500, 8500],
            'Fuel & Transport' => [12000, 26000], 'Salaries & Wages' => [582000, 582000], 'Marketing' => [4000, 18000],
            'M-Pesa & Bank Charges' => [2500, 6000], 'Repairs & Maintenance' => [3000, 22000],
        ];
        foreach ($expenses as $name => [$min, $max]) {
            $cat = $fin->saveCategory('expense', $name);
            foreach ([2, 1, 0] as $monthsAgo) {
                $date = today()->subMonths($monthsAgo)->startOfMonth()->addDays(mt_rand(0, 24));
                if ($date->gt(today())) {
                    $date = today();
                }
                $fin->recordManual(['type' => 'expense', 'category_id' => $cat->id, 'amount' => mt_rand($min, $max), 'entry_date' => $date->toDateString(),
                    'description' => $name.' – '.$date->format('F Y')]);
            }
        }
        $inc = $fin->saveCategory('income', 'Delivery Fees');
        foreach ([40, 25, 9] as $daysAgo) {
            $fin->recordManual(['type' => 'income', 'category_id' => $inc->id, 'amount' => mt_rand(8000, 22000), 'entry_date' => today()->subDays($daysAgo)->toDateString(), 'description' => 'Delivery fees collected']);
        }
    }

    private function people(): void
    {
        $svc = app(EmployeeService::class);
        $departments = [];
        foreach (['Sales', 'Warehouse', 'Finance', 'Administration', 'Logistics'] as $name) {
            $departments[$name] = $svc->saveDepartment($name);
        }

        $staff = [
            ['Wanjiku', 'Kariuki', 'Sales Manager', 'Sales', 95000, '2019-03-04'], ['Brian', 'Otieno', 'Sales Executive', 'Sales', 42000, '2021-06-14'],
            ['Faith', 'Chebet', 'Sales Executive', 'Sales', 42000, '2022-01-10'], ['Hassan', 'Mohamed', 'Warehouse Supervisor', 'Warehouse', 52000, '2020-02-17'],
            ['Kevin', 'Mutua', 'Storekeeper', 'Warehouse', 32000, '2022-08-01'], ['Mercy', 'Akinyi', 'Storekeeper', 'Warehouse', 32000, '2023-03-20'],
            ['Samuel', 'Kamau', 'Accountant', 'Finance', 75000, '2019-09-02'], ['Esther', 'Nyambura', 'Accounts Clerk', 'Finance', 38000, '2023-01-16'],
            ['Peter', 'Wafula', 'Operations Manager', 'Administration', 90000, '2018-11-05'], ['Joyce', 'Njeri', 'Receptionist', 'Administration', 28000, '2023-07-03'],
            ['Dennis', 'Kiprono', 'Driver', 'Logistics', 32000, '2021-10-11'], ['Ali', 'Abdalla', 'Delivery Assistant', 'Logistics', 24000, '2024-02-05'],
        ];

        $employees = [];
        foreach ($staff as $i => [$first, $last, $title, $dept, $salary, $hired]) {
            $employees[] = $svc->save([
                'first_name' => $first, 'last_name' => $last, 'job_title' => $title, 'department_id' => $departments[$dept]->id, 'hire_date' => $hired,
                'email' => strtolower($first.'.'.$last).'@acme.test', 'phone' => '07'.mt_rand(10000000, 29999999), 'annual_leave_days' => 21,
                'national_id' => (string) mt_rand(20000000, 39999999), 'salary' => $salary, 'bank_account' => '01'.mt_rand(10000000000, 99999999999),
            ], canEditSensitive: true);
        }

        // Two weeks of attendance (weekdays only, never in the future)
        $attendance = app(AttendanceService::class);
        foreach (range(14, 0) as $daysAgo) {
            $date = today()->subDays($daysAgo);
            if ($date->isWeekend()) {
                continue;
            }
            foreach ($employees as $e) {
                $roll = mt_rand(1, 100);
                if ($roll <= 82) {
                    $attendance->record($e, $date, 'present', '07:'.str_pad((string) mt_rand(25, 59), 2, '0', STR_PAD_LEFT), '17:'.str_pad((string) mt_rand(0, 40), 2, '0', STR_PAD_LEFT));
                } elseif ($roll <= 92) {
                    $attendance->record($e, $date, 'late', '09:'.str_pad((string) mt_rand(5, 50), 2, '0', STR_PAD_LEFT), '17:30');
                } elseif ($roll <= 96) {
                    $attendance->record($e, $date, 'half_day', '08:00', '12:30');
                } else {
                    $attendance->record($e, $date, 'absent', notes: 'No notice given');
                }
            }
        }

        $leave = app(LeaveService::class);
        $approved = $leave->request($employees[1], 'annual', today()->subDays(20), today()->subDays(16), 'Family visit upcountry');
        $leave->approve($approved, 'Enjoy');
        $sick = $leave->request($employees[5], 'sick', today()->subDays(9), today()->subDays(8), 'Malaria – clinic note attached');
        $leave->approve($sick);
        $leave->request($employees[2], 'annual', today()->addDays(14), today()->addDays(23), 'Wedding');           // pending
        $leave->request($employees[7], 'annual', today()->addDays(30), today()->addDays(34), 'Personal');          // pending
        $rejected = $leave->request($employees[4], 'annual', today()->addDays(5), today()->addDays(9), 'Short break');
        $leave->reject($rejected, 'Stocktake week – please reschedule');
    }
}
