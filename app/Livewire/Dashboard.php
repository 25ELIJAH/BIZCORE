<?php

namespace App\Livewire;

use App\Services\ReportService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Company dashboard. Each section is computed and shown only if the user holds the matching permission. */
#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    /** section => permission that unlocks it */
    private const SECTIONS = [
        'sales' => 'sales.view', 'finance' => 'finance.view', 'inventory' => 'inventory.view',
        'procurement' => 'purchase_orders.view', 'hr' => 'employees.view',
    ];

    public function render(ReportService $reports)
    {
        $company = app(CurrentCompany::class)->get();
        $allowed = Gate::allows('dashboard.view')
            ? array_keys(array_filter(self::SECTIONS, fn ($permission) => Gate::allows($permission)))
            : [];

        $data = $reports->dashboard($allowed);

        return view('livewire.dashboard', [
            'company' => $company,
            'data' => $data,
            'currency' => $company->currency,
            'charts' => $this->charts($data),
        ]);
    }

    /** Chart.js configurations, built server-side from the already-authorised data. */
    private function charts(array $d): array
    {
        $charts = [];
        $num = fn (array $values) => array_map('floatval', $values);

        if (isset($d['sales'])) {
            $charts['sales'] = ['type' => 'bar', 'data' => ['labels' => $d['sales']['trend']['labels'], 'datasets' => [[
                'label' => 'Sales', 'data' => $num($d['sales']['trend']['values']), 'backgroundColor' => '#6366f1', 'borderRadius' => 4,
            ]]], 'options' => $this->options()];
            $charts['top'] = ['type' => 'bar', 'data' => ['labels' => $d['sales']['top_products']['labels'], 'datasets' => [[
                'label' => 'Revenue this month', 'data' => $num($d['sales']['top_products']['values']), 'backgroundColor' => '#0ea5e9', 'borderRadius' => 4,
            ]]], 'options' => $this->options(['indexAxis' => 'y', 'scales' => ['x' => ['beginAtZero' => true]]])];
        }
        if (isset($d['finance'])) {
            $charts['finance'] = ['type' => 'bar', 'data' => ['labels' => $d['finance']['income_trend']['labels'], 'datasets' => [
                ['label' => 'Income', 'data' => $num($d['finance']['income_trend']['values']), 'backgroundColor' => '#10b981', 'borderRadius' => 4],
                ['label' => 'Expenses', 'data' => $num($d['finance']['expense_trend']['values']), 'backgroundColor' => '#f43f5e', 'borderRadius' => 4],
            ]], 'options' => $this->options()];
        }

        return $charts;
    }

    private function options(array $extra = []): array
    {
        return $extra + ['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true]]];
    }
}
