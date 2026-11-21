<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Services\ActivityLogger;
use App\Services\ReportService;
use App\Support\Tenancy\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    /** Download a report as PDF or Excel. Needs the report's own permissions plus reports.export. */
    public function __invoke(Request $request, string $report, string $format, ReportService $reports, CurrentCompany $currentCompany, ActivityLogger $log): Response
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true) && ReportService::allowed($report), 404);
        Gate::authorize('reports.export');

        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $result = $reports->build($report, $data['from'] ?? null, $data['to'] ?? null);
        $company = $currentCompany->get();

        $log->log('report.exported', $company, ['report' => $report, 'format' => $format]);
        $filename = Str::slug($company->name.'-'.$report.'-'.today()->toDateString()).'.'.$format;

        if ($format === 'xlsx') {
            return Excel::download(new ReportExport($result), $filename);
        }

        return Pdf::loadView('pdf.report', ['report' => $result, 'company' => $company])->setPaper('a4', count($result['columns']) > 5 ? 'landscape' : 'portrait')->download($filename);
    }
}
