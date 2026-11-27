<?php

namespace App\Support;

/**
 * Turns a built report (see ReportService::build) into chart definitions for the report page.
 * Each chart: title, type (bar|doughnut), labels, datasets [label, data], and display hints.
 * Charts are only a view of the same rows the table and exports use, so the numbers always agree.
 */
class ReportCharts
{
    /** @return list<array<string, mixed>> */
    public static function for(string $slug, array $result): array
    {
        $rows = $result['rows'] ?? [];
        $totals = $result['totals'] ?? [];

        $charts = match ($slug) {
            'sales' => [
                self::bar('Sales by day', self::group($rows, 'date', ['total', 'paid']), ['Total', 'Paid']),
                self::doughnut('Sales by payment status', self::count($rows, 'status')),
            ],
            'product-profit' => [
                self::bar('Gross profit by product', self::top($rows, 'name', ['profit'], 8), ['Gross profit'], horizontal: true),
                self::bar('Revenue and cost by product', self::top($rows, 'name', ['revenue', 'cost'], 8, 'revenue'), ['Revenue', 'Cost']),
            ],
            'inventory' => [
                self::doughnut('Stock value by category', self::sum($rows, 'category', 'value')),
                self::bar('Highest-value stock', self::top($rows, 'name', ['value'], 8), ['Value'], horizontal: true),
            ],
            'receivables' => [
                self::bar('Owed, by how overdue', [
                    'Not yet due' => [(float) ($totals['current'] ?? 0)], '1-30 days' => [(float) ($totals['d30'] ?? 0)],
                    '31-60 days' => [(float) ($totals['d60'] ?? 0)], '61-90 days' => [(float) ($totals['d90'] ?? 0)], 'Over 90 days' => [(float) ($totals['over90'] ?? 0)],
                ], ['Owed']),
                self::bar('Biggest balances by customer', self::top($rows, 'customer', ['total'], 8), ['Owed'], horizontal: true),
            ],
            'payables' => [
                self::bar('Paid and owed by supplier', self::top($rows, 'supplier', ['paid', 'balance'], 8, 'total'), ['Paid', 'Owed'], stacked: true, horizontal: true),
            ],
            'profit-loss' => self::profitLoss($rows, $totals),
            'leave' => [
                self::bar('Leave by employee', self::top($rows, 'name', ['used', 'pending', 'remaining'], 10, 'allowance'), ['Taken', 'Pending', 'Remaining'], stacked: true, horizontal: true, money: false),
            ],
            default => [],
        };

        return array_values(array_filter($charts));
    }

    private static function profitLoss(array $rows, array $totals): array
    {
        $income = (float) (collect($rows)->firstWhere('category', 'Total income')['amount'] ?? 0);
        $expense = (float) (collect($rows)->firstWhere('category', 'Total expenses')['amount'] ?? 0);
        $byCategory = [];
        foreach ($rows as $r) {
            if (($r['section'] ?? '') === 'Expenses' && $r['category'] !== 'Total expenses') {
                $byCategory[$r['category']] = ($byCategory[$r['category']] ?? 0) + (float) $r['amount'];
            }
        }

        return [
            self::bar('Income, expenses and net result', ['Income' => [$income], 'Expenses' => [$expense], 'Net result' => [(float) ($totals['amount'] ?? 0)]], ['Amount']),
            self::doughnut('Expenses by category', $byCategory),
        ];
    }

    /** Sums the given columns per value of $key, in first-seen order. @return array<string, list<float>> */
    private static function group(array $rows, string $key, array $columns): array
    {
        $out = [];
        foreach ($rows as $r) {
            $label = (string) ($r[$key] ?? '');
            foreach ($columns as $i => $c) {
                $out[$label][$i] = ($out[$label][$i] ?? 0) + (float) ($r[$c] ?? 0);
            }
        }

        return $out;
    }

    /** The largest $limit rows by $sortBy (default: the first column), one list of values per row. @return array<string, list<float>> */
    private static function top(array $rows, string $key, array $columns, int $limit, ?string $sortBy = null): array
    {
        $sortBy ??= $columns[0];
        usort($rows, fn ($a, $b) => (float) ($b[$sortBy] ?? 0) <=> (float) ($a[$sortBy] ?? 0));
        $out = [];
        foreach (array_slice($rows, 0, $limit) as $r) {
            $out[(string) ($r[$key] ?? '')] = array_map(fn ($c) => (float) ($r[$c] ?? 0), $columns);
        }

        return $out;
    }

    /** @return array<string, float> */
    private static function sum(array $rows, string $key, string $column): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[(string) ($r[$key] ?? '')] = ($out[(string) ($r[$key] ?? '')] ?? 0) + (float) ($r[$column] ?? 0);
        }
        arsort($out);

        return $out;
    }

    /** @return array<string, float> */
    private static function count(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[(string) ($r[$key] ?? '')] = ($out[(string) ($r[$key] ?? '')] ?? 0) + 1;
        }

        return $out;
    }

    /** @param array<string, list<float>> $series label => one value per dataset */
    private static function bar(string $title, array $series, array $datasets, bool $stacked = false, bool $horizontal = false, bool $money = true): ?array
    {
        if (self::empty($series)) {
            return null;
        }

        return [
            'title' => $title, 'type' => 'bar', 'labels' => array_keys($series), 'stacked' => $stacked, 'horizontal' => $horizontal, 'money' => $money,
            'datasets' => collect($datasets)->map(fn ($label, $i) => ['label' => $label, 'data' => array_map(fn ($v) => round($v[$i] ?? 0, 2), array_values($series))])->all(),
        ];
    }

    /** @param array<string, float> $series */
    private static function doughnut(string $title, array $series): ?array
    {
        if (self::empty(array_map(fn ($v) => [$v], $series))) {
            return null;
        }

        return ['title' => $title, 'type' => 'doughnut', 'labels' => array_keys($series), 'money' => $title !== 'Sales by payment status', 'datasets' => [['label' => $title, 'data' => array_map(fn ($v) => round($v, 2), array_values($series))]]];
    }

    private static function empty(array $series): bool
    {
        foreach ($series as $values) {
            foreach ($values as $v) {
                if (abs((float) $v) > 0) {
                    return false;
                }
            }
        }

        return true;
    }
}
