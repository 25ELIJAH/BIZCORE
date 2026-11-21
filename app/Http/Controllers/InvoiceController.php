<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Support\Tenancy\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    /** Printable invoice as PDF: inline by default, attachment with ?download=1. */
    public function show(Request $request, Sale $sale, CurrentCompany $currentCompany): Response
    {
        Gate::authorize('sales.view');
        $sale->load(['items', 'invoice', 'customer', 'payments' => fn ($q) => $q->active()]);
        $company = $currentCompany->get();

        $pdf = Pdf::loadView('pdf.invoice', [
            'sale' => $sale,
            'company' => $company,
            'logo' => $this->logoDataUri($company->logo_path),
            'methods' => $company->paymentMethods(),
        ]);

        $filename = $sale->invoice->invoice_number.'.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    private function logoDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path);

        return str_starts_with((string) $mime, 'image/') ? 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path)) : null;
    }
}
