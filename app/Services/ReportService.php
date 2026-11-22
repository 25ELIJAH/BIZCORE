<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FinanceEntry;
use App\Models\LeaveRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reporting. Every query goes through company-scoped models, so a report can only ever contain the current
 * company's data. Each report returns the same shape so the screen, PDF and Excel export share one source of truth:
 *
 *  ['title' => string, 'subtitle' => string, 'columns' => [key => ['label' => string, 'type' => text|money|number|date]],
 *   'rows' => list<array>, 'totals' => ?array]
 *
 * Money values are decimal strings (never floats).
 */
class ReportService
{
    /** slug => [title, permissions (all required), method, uses date range, needs financial access note] */
    public const CATALOG = [
        'sales' => ['title' => 'Sales', 'description' => 'Completed sales in a period with payment status.', 'permissions' => ['reports.view', 'sales.view'], 'dates' => true],
        'product-profit' => ['title' => 'Product profitability', 'description' => 'Revenue, cost and gross profit per product.', 'permissions' => ['reports.financial.view', 'sales.view'], 'dates' => true],
        'inventory' => ['title' => 'Inventory valuation', 'description' => 'Stock on hand valued at weighted-average cost.', 'permissions' => ['reports.view', 'inventory.view'], 'dates' => false],
        'receivables' => ['title' => 'Customer receivables ageing', 'description' => 'What customers owe, by how overdue it is.', 'permissions' => ['reports.financial.view', 'sales.view'], 'dates' => false],
        'payables' => ['title' => 'Supplier payables', 'description' => 'What the company owes each supplier.', 'permissions' => ['reports.financial.view', 'purchase_orders.view'], 'dates' => false],
        'profit-loss' => ['title' => 'Income and expenses', 'description' => 'Income, expenses and net result by category.', 'permissions' => ['reports.financial.view', 'finance.view'], 'dates' => true],
        'leave' => ['title' => 'Leave balances', 'description' => 'Annual leave allowance, taken and remaining per employee.', 'permissions' => ['reports.view', 'employees.view'], 'dates' => false],
    ];

    public function __construct(private FinanceService $finance, private LeaveService $leave) {}

    /** True when the current user holds every permission the report needs. Unknown reports are never allowed. */
    public static function allowed(string $slug): bool
    {
        if (! isset(self::CATALOG[$slug])) {
            return false;
        }
        foreach (self::CATALOG[$slug]['permissions'] as $permission) {
            if (! \Illuminate\Support\Facades\Gate::allows($permission)) {
                return false;
            }
        }

        return true;
    }

    public function build(string $slug, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?: today()->startOfMonth()->toDateString();
        $to = $to ?: today()->toDateString();

        return match ($slug) {
            'sales' => $this->sales($from, $to),
            'product-profit' => $this->productProfit($from, $to),
            'inventory' => $this->inventory(),
            'receivables' => $this->receivables(),
            'payables' => $this->payables(),
            'profit-loss' => $this->profitLoss($from, $to),
            'leave' => $this->leaveBalances(),
            default => throw new \InvalidArgumentException("Unknown report {$slug}"),
        };
    }

    // ---- reports -------------------------------------------------------------

    public function sales(string $from, string $to): array
    {
        $sales = Sale::with(['customer', 'invoice'])->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to)
            ->orderBy('sale_date')->orderBy('id')->get();

        $rows = [];
        $totals = ['total' => '0.00', 'paid' => '0.00', 'balance' => '0.00'];
        foreach ($sales as $s) {
            $balance = Money::sub((string) $s->total, (string) $s->amount_paid);
            $rows[] = [
                'date' => $s->sale_date->toDateString(), 'invoice' => $s->invoice?->invoice_number, 'customer' => $s->customer?->name ?? 'Walk-in customer',
                'total' => (string) $s->total, 'paid' => (string) $s->amount_paid, 'balance' => $balance, 'status' => ucfirst($s->payment_status),
            ];
            $totals['total'] = Money::add($totals['total'], (string) $s->total);
            $totals['paid'] = Money::add($totals['paid'], (string) $s->amount_paid);
            $totals['balance'] = Money::add($totals['balance'], $balance);
        }

        return $this->shape('sales', "{$from} to {$to}", [
            'date' => ['Date', 'date'], 'invoice' => ['Invoice', 'text'], 'customer' => ['Customer', 'text'],
            'total' => ['Total', 'money'], 'paid' => ['Paid', 'money'], 'balance' => ['Balance', 'money'], 'status' => ['Payment', 'text'],
        ], $rows, ['date' => 'Total', 'invoice' => count($rows).' sale(s)'] + $totals);
    }

    public function productProfit(string $from, string $to): array
    {
        $items = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.sale_date', '>=', $from)->whereDate('sales.sale_date', '<=', $to)
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'sale_items.sku')
            ->selectRaw('sale_items.product_name as name, sale_items.sku as sku, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as revenue, SUM(sale_items.quantity * sale_items.unit_cost) as cost')
            ->orderByDesc('revenue')->get();

        $rows = [];
        $t = ['revenue' => '0.00', 'cost' => '0.00', 'profit' => '0.00'];
        foreach ($items as $i) {
            $revenue = Money::round((string) $i->revenue);
            $cost = Money::round((string) $i->cost);
            $profit = Money::sub($revenue, $cost);
            $rows[] = ['name' => $i->name, 'sku' => $i->sku, 'qty' => rtrim(rtrim(bcadd((string) $i->qty, '0', 3), '0'), '.'), 'revenue' => $revenue, 'cost' => $cost, 'profit' => $profit,
                'margin' => bccomp($revenue, '0', 2) > 0 ? bcmul(bcdiv($profit, $revenue, 4), '100', 1).'%' : '—'];
            $t['revenue'] = Money::add($t['revenue'], $revenue);
            $t['cost'] = Money::add($t['cost'], $cost);
            $t['profit'] = Money::add($t['profit'], $profit);
        }

        return $this->shape('product-profit', "{$from} to {$to}", [
            'name' => ['Product', 'text'], 'sku' => ['SKU', 'text'], 'qty' => ['Qty sold', 'number'], 'revenue' => ['Revenue', 'money'],
            'cost' => ['Cost', 'money'], 'profit' => ['Gross profit', 'money'], 'margin' => ['Margin', 'text'],
        ], $rows, ['name' => 'Total'] + $t);
    }

    public function inventory(): array
    {
        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get();

        $rows = [];
        $total = '0.00';
        foreach ($products as $p) {
            $value = Money::mul((string) $p->quantity, (string) $p->average_cost);
            $low = (float) $p->min_stock > 0 && (float) $p->quantity <= (float) $p->min_stock;
            $rows[] = [
                'sku' => $p->sku, 'name' => $p->name, 'category' => $p->category?->name ?? '—',
                'qty' => rtrim(rtrim(bcadd((string) $p->quantity, '0', 3), '0'), '.') ?: '0', 'cost' => Money::round((string) $p->average_cost), 'value' => $value,
                'status' => $low ? 'Low stock' : 'OK',
            ];
            $total = Money::add($total, $value);
        }

        return $this->shape('inventory', 'As of '.today()->toDateString(), [
            'sku' => ['SKU', 'text'], 'name' => ['Product', 'text'], 'category' => ['Category', 'text'], 'qty' => ['On hand', 'number'],
            'cost' => ['Avg cost', 'money'], 'value' => ['Value', 'money'], 'status' => ['Status', 'text'],
        ], $rows, ['sku' => 'Total value', 'value' => $total]);
    }

    public function receivables(): array
    {
        $sales = Sale::with('customer')->where('status', Sale::STATUS_COMPLETED)->where('payment_status', '!=', Sale::PAYMENT_PAID)->get();

        $byCustomer = [];
        foreach ($sales as $s) {
            $balance = Money::sub((string) $s->total, (string) $s->amount_paid);
            if (bccomp($balance, '0', 2) <= 0) {
                continue;
            }
            $key = $s->customer_id ?? 0;
            $byCustomer[$key] ??= ['customer' => $s->customer?->name ?? 'Walk-in customer', 'current' => '0.00', 'd30' => '0.00', 'd60' => '0.00', 'd90' => '0.00', 'over90' => '0.00', 'total' => '0.00'];
            $late = (int) $s->due_date->diffInDays(today(), false); // > 0 means overdue
            $bucket = match (true) { $late <= 0 => 'current', $late <= 30 => 'd30', $late <= 60 => 'd60', $late <= 90 => 'd90', default => 'over90' };
            $byCustomer[$key][$bucket] = Money::add($byCustomer[$key][$bucket], $balance);
            $byCustomer[$key]['total'] = Money::add($byCustomer[$key]['total'], $balance);
        }

        $rows = array_values($byCustomer);
        usort($rows, fn ($a, $b) => bccomp($b['total'], $a['total'], 2) ?: strcmp($a['customer'], $b['customer']));
        $totals = ['customer' => 'Total'] + array_fill_keys(['current', 'd30', 'd60', 'd90', 'over90', 'total'], '0.00');
        foreach ($rows as $r) {
            foreach (['current', 'd30', 'd60', 'd90', 'over90', 'total'] as $k) {
                $totals[$k] = Money::add($totals[$k], $r[$k]);
            }
        }

        return $this->shape('receivables', 'As of '.today()->toDateString().' (days past due date)', [
            'customer' => ['Customer', 'text'], 'current' => ['Not yet due', 'money'], 'd30' => ['1-30 days', 'money'], 'd60' => ['31-60 days', 'money'],
            'd90' => ['61-90 days', 'money'], 'over90' => ['Over 90 days', 'money'], 'total' => ['Total owed', 'money'],
        ], $rows, $totals);
    }

    public function payables(): array
    {
        $orders = PurchaseOrder::query()->whereIn('status', PurchaseOrder::PAYABLE_STATUSES)->get()->groupBy('supplier_id');
        $suppliers = Supplier::withTrashed()->whereIn('id', $orders->keys())->get()->keyBy('id');

        $rows = [];
        $t = ['orders' => 0, 'total' => '0.00', 'paid' => '0.00', 'balance' => '0.00'];
        foreach ($orders as $supplierId => $list) {
            $total = $paid = '0.00';
            foreach ($list as $o) {
                $total = Money::add($total, (string) $o->total);
                $paid = Money::add($paid, (string) $o->amount_paid);
            }
            $balance = Money::sub($total, $paid);
            if (bccomp($balance, '0', 2) <= 0) {
                continue;
            }
            $rows[] = ['supplier' => $suppliers[$supplierId]->name ?? 'Unknown', 'orders' => $list->count(), 'total' => $total, 'paid' => $paid, 'balance' => $balance];
            $t['orders'] += $list->count();
            $t['total'] = Money::add($t['total'], $total);
            $t['paid'] = Money::add($t['paid'], $paid);
            $t['balance'] = Money::add($t['balance'], $balance);
        }
        usort($rows, fn ($a, $b) => bccomp($b['balance'], $a['balance'], 2) ?: strcmp($a['supplier'], $b['supplier']));

        return $this->shape('payables', 'As of '.today()->toDateString().' (approved orders not fully paid)', [
            'supplier' => ['Supplier', 'text'], 'orders' => ['Orders', 'number'], 'total' => ['Ordered', 'money'], 'paid' => ['Paid', 'money'], 'balance' => ['Owed', 'money'],
        ], $rows, ['supplier' => 'Total'] + $t);
    }

    public function profitLoss(string $from, string $to): array
    {
        $s = $this->finance->summary($from, $to);
        $rows = [];
        foreach ($s['income_by_category'] as $name => $amount) {
            $rows[] = ['section' => 'Income', 'category' => $name, 'amount' => $amount];
        }
        $rows[] = ['section' => 'Income', 'category' => 'Total income', 'amount' => $s['income']];
        foreach ($s['expense_by_category'] as $name => $amount) {
            $rows[] = ['section' => 'Expenses', 'category' => $name, 'amount' => $amount];
        }
        $rows[] = ['section' => 'Expenses', 'category' => 'Total expenses', 'amount' => $s['expense']];

        return $this->shape('profit-loss', "{$from} to {$to}", [
            'section' => ['Section', 'text'], 'category' => ['Category', 'text'], 'amount' => ['Amount', 'money'],
        ], $rows, ['section' => 'Net result', 'amount' => $s['net']]);
    }

    public function leaveBalances(?int $year = null): array
    {
        $year ??= today()->year;
        $rows = [];
        foreach (Employee::with('department')->where('status', Employee::ACTIVE)->orderBy('first_name')->orderBy('last_name')->get() as $e) {
            $b = $this->leave->balance($e, $year);
            $rows[] = ['number' => $e->employee_number, 'name' => $e->fullName(), 'department' => $e->department?->name ?? '—',
                'allowance' => (string) $b['allowance'], 'used' => $b['used'], 'pending' => $b['pending'], 'remaining' => $b['remaining']];
        }

        return $this->shape('leave', "Annual leave {$year}", [
            'number' => ['No.', 'text'], 'name' => ['Employee', 'text'], 'department' => ['Department', 'text'], 'allowance' => ['Allowance', 'number'],
            'used' => ['Taken', 'number'], 'pending' => ['Pending', 'number'], 'remaining' => ['Remaining', 'number'],
        ], $rows, null);
    }

    // ---- dashboard -----------------------------------------------------------

    /**
     * Headline figures and chart series. Only the sections the caller asked for are computed, so a user never
     * triggers (or receives) data they have no permission to see.
     *
     * @param  list<string>  $sections  sales | finance | inventory | procurement | hr
     */
    public function dashboard(array $sections, ?Carbon $now = null): array
    {
        $now ??= now();
        $monthStart = $now->copy()->startOfMonth();
        $out = [];

        if (in_array('sales', $sections, true)) {
            $month = Sale::where('status', Sale::STATUS_COMPLETED)->whereDate('sale_date', '>=', $monthStart)->whereDate('sale_date', '<=', $now);
            $open = Sale::where('status', Sale::STATUS_COMPLETED)->where('payment_status', '!=', Sale::PAYMENT_PAID);
            $out['sales'] = [
                'month_total' => Money::round((string) (clone $month)->sum('total')),
                'month_count' => (clone $month)->count(),
                'receivable' => Money::round((string) (clone $open)->selectRaw('COALESCE(SUM(total - amount_paid), 0) as b')->value('b')),
                'overdue_count' => Sale::overdue()->count(),
                'trend' => $this->monthlySeries(Sale::query()->where('status', Sale::STATUS_COMPLETED), 'sale_date', 'total', $now),
                'top_products' => $this->topProducts($monthStart, $now),
                'aging' => $this->receivablesAging($now),
                'overdue' => $this->overdueInvoices($now),
            ];
        }

        if (in_array('finance', $sections, true)) {
            $m = $this->finance->summary($monthStart->toDateString(), $now->toDateString());
            $out['finance'] = [
                'income' => $m['income'], 'expense' => $m['expense'], 'net' => $m['net'],
                'income_trend' => $this->monthlySeries(FinanceEntry::active()->where('type', 'income'), 'entry_date', 'amount', $now),
                'expense_trend' => $this->monthlySeries(FinanceEntry::active()->where('type', 'expense'), 'entry_date', 'amount', $now),
            ];
        }

        if (in_array('inventory', $sections, true)) {
            $out['inventory'] = [
                'value' => Money::round((string) Product::where('is_active', true)->selectRaw('COALESCE(SUM(quantity * average_cost), 0) as v')->value('v')),
                'low_stock' => Product::where('is_active', true)->lowStock()->count(),
                'products' => Product::where('is_active', true)->count(),
                'low_list' => Product::where('is_active', true)->lowStock()->orderBy('quantity')->limit(5)->get(['id', 'name', 'sku', 'quantity', 'min_stock'])
                    ->map(fn ($p) => ['name' => $p->name, 'sku' => $p->sku, 'quantity' => $p->quantity, 'min' => $p->min_stock])->all(),
            ];
        }

        if (in_array('procurement', $sections, true)) {
            $out['procurement'] = [
                'payable' => Money::round((string) PurchaseOrder::whereIn('status', PurchaseOrder::PAYABLE_STATUSES)->selectRaw('COALESCE(SUM(total - amount_paid), 0) as b')->value('b')),
                'awaiting_approval' => PurchaseOrder::where('status', PurchaseOrder::PENDING)->count(),
                'awaiting_delivery' => PurchaseOrder::whereIn('status', [PurchaseOrder::APPROVED, PurchaseOrder::PARTIAL])->count(),
            ];
        }

        if (in_array('hr', $sections, true)) {
            $out['hr'] = [
                'headcount' => Employee::where('status', Employee::ACTIVE)->count(),
                'pending_leave' => LeaveRequest::where('status', LeaveRequest::PENDING)->count(),
                'on_leave_today' => LeaveRequest::where('status', LeaveRequest::APPROVED)->whereDate('start_date', '<=', $now)->whereDate('end_date', '>=', $now)->count(),
            ];
        }

        return $out;
    }

    /**
     * Money customers still owe, split by how long past due it is.
     *
     * @return array{current: string, d30: string, d60: string, d90: string}
     */
    private function receivablesAging(Carbon $now): array
    {
        $today = $now->toDateString();
        $row = Sale::where('status', Sale::STATUS_COMPLETED)->where('payment_status', '!=', Sale::PAYMENT_PAID)
            ->selectRaw('COALESCE(SUM(CASE WHEN due_date >= ? THEN total - amount_paid ELSE 0 END), 0) as current_b, '
                .'COALESCE(SUM(CASE WHEN DATEDIFF(?, due_date) BETWEEN 1 AND 30 THEN total - amount_paid ELSE 0 END), 0) as d30, '
                .'COALESCE(SUM(CASE WHEN DATEDIFF(?, due_date) BETWEEN 31 AND 60 THEN total - amount_paid ELSE 0 END), 0) as d60, '
                .'COALESCE(SUM(CASE WHEN DATEDIFF(?, due_date) > 60 THEN total - amount_paid ELSE 0 END), 0) as d90', [$today, $today, $today, $today])
            ->first();

        return ['current' => Money::round((string) $row->current_b), 'd30' => Money::round((string) $row->d30),
            'd60' => Money::round((string) $row->d60), 'd90' => Money::round((string) $row->d90)];
    }

    /** The five largest overdue invoices. */
    private function overdueInvoices(Carbon $now): array
    {
        return Sale::overdue()->with(['customer:id,name', 'invoice:id,sale_id,invoice_number'])
            ->orderByRaw('(total - amount_paid) desc')->limit(5)->get()
            ->map(fn (Sale $s) => [
                'id' => $s->id, 'number' => $s->invoice?->invoice_number ?? ('Sale #'.$s->id), 'customer' => $s->customer?->name ?? 'Walk-in customer',
                'balance' => $s->balance(), 'days' => (int) $s->due_date->diffInDays($now->copy()->startOfDay()),
            ])->all();
    }

    /**
     * Totals for each of the last six calendar months (oldest first), zero-filled.
     *
     * @return array{labels: list<string>, values: list<string>}
     */
    public function monthlySeries($query, string $dateColumn, string $sumColumn, Carbon $now, int $months = 6): array
    {
        $start = $now->copy()->startOfMonth()->subMonths($months - 1);
        $rows = (clone $query)->whereDate($dateColumn, '>=', $start)->whereDate($dateColumn, '<=', $now)
            ->selectRaw("DATE_FORMAT({$dateColumn}, '%Y-%m') as ym, SUM({$sumColumn}) as total")->groupBy('ym')->pluck('total', 'ym');

        $labels = $values = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $labels[] = $m->format('M Y');
            $values[] = Money::round((string) ($rows[$m->format('Y-m')] ?? 0));
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels: list<string>, values: list<string>} */
    private function topProducts(Carbon $from, Carbon $to, int $limit = 5): array
    {
        $rows = SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)->whereDate('sales.sale_date', '>=', $from)->whereDate('sales.sale_date', '<=', $to)
            ->groupBy('sale_items.product_id', 'sale_items.product_name')
            ->selectRaw('sale_items.product_name as name, SUM(sale_items.line_total) as revenue')->orderByDesc('revenue')->limit($limit)->get();

        return ['labels' => $rows->pluck('name')->all(), 'values' => $rows->map(fn ($r) => Money::round((string) $r->revenue))->all()];
    }

    private function shape(string $slug, string $subtitle, array $columns, array $rows, ?array $totals): array
    {
        return [
            'slug' => $slug, 'title' => self::CATALOG[$slug]['title'], 'subtitle' => $subtitle,
            'columns' => array_map(fn ($c) => ['label' => $c[0], 'type' => $c[1]], $columns),
            'rows' => $rows, 'totals' => $totals,
        ];
    }
}
