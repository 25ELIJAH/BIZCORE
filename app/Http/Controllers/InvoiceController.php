<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\InvoicePdf;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    /** Printable invoice as PDF: inline by default, attachment with ?download=1. */
    public function show(Request $request, Sale $sale, CurrentCompany $currentCompany, InvoicePdf $invoices): Response
    {
        Gate::authorize('sales.view');
        $sale->load(['items', 'invoice', 'customer', 'payments' => fn ($q) => $q->active()]);

        $pdf = $invoices->make($sale, $currentCompany->get());
        $filename = $sale->invoice->invoice_number.'.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
