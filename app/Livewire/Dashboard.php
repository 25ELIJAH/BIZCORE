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
        ]);
    }
}
