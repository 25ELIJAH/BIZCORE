<?php

namespace App\Livewire\Reports;

use App\Services\ReportService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Report')]
class Show extends Component
{
    public string $report = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(string $report): void
    {
        abort_unless(ReportService::allowed($report), 404);
        $this->report = $report;
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function render(ReportService $reports)
    {
        $valid = strtotime($this->from) && strtotime($this->to) && $this->from <= $this->to;

        return view('livewire.reports.show', [
            'definition' => ReportService::CATALOG[$this->report],
            'result' => $valid ? $reports->build($this->report, $this->from, $this->to) : null,
            'currency' => app(CurrentCompany::class)->get()->currency,
            'canExport' => Gate::allows('reports.export'),
        ]);
    }
}
