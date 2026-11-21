<?php

namespace App\Livewire\Reports;

use App\Services\ReportService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Reports')]
class Index extends Component
{
    public function mount(): void
    {
        abort_unless(Gate::any(['reports.view', 'reports.financial.view']), 403);
    }

    public function render()
    {
        $available = array_filter(ReportService::CATALOG, fn ($def, $slug) => ReportService::allowed($slug), ARRAY_FILTER_USE_BOTH);

        return view('livewire.reports.index', ['reports' => $available]);
    }
}
