<?php

namespace Tests\Unit;

use App\Support\ReportCharts;
use PHPUnit\Framework\TestCase;

class ReportChartsTest extends TestCase
{
    public function test_sales_rows_become_a_daily_bar_chart_and_a_status_doughnut(): void
    {
        $rows = [
            ['date' => '2026-11-01', 'total' => '100.00', 'paid' => '40.00', 'status' => 'Partial'],
            ['date' => '2026-11-01', 'total' => '50.00', 'paid' => '50.00', 'status' => 'Paid'],
            ['date' => '2026-11-02', 'total' => '25.00', 'paid' => '0.00', 'status' => 'Unpaid'],
        ];

        $charts = ReportCharts::for('sales', ['rows' => $rows, 'totals' => []]);

        $this->assertCount(2, $charts);
        $this->assertSame(['2026-11-01', '2026-11-02'], $charts[0]['labels']);
        $this->assertSame([150.0, 25.0], $charts[0]['datasets'][0]['data']);
        $this->assertSame([90.0, 0.0], $charts[0]['datasets'][1]['data']);
        $this->assertSame('doughnut', $charts[1]['type']);
    }

    public function test_a_report_with_no_data_draws_no_charts(): void
    {
        foreach (['sales', 'product-profit', 'inventory', 'receivables', 'payables', 'profit-loss', 'leave'] as $slug) {
            $this->assertSame([], ReportCharts::for($slug, ['rows' => [], 'totals' => []]), $slug);
        }
    }

    public function test_largest_items_come_first_and_are_capped(): void
    {
        $rows = collect(range(1, 12))->map(fn ($i) => ['name' => "P{$i}", 'profit' => (string) ($i * 10)])->all();

        $chart = ReportCharts::for('product-profit', ['rows' => $rows, 'totals' => []])[0];

        $this->assertCount(8, $chart['labels']);
        $this->assertSame('P12', $chart['labels'][0]);
        $this->assertTrue($chart['horizontal']);
    }
}
