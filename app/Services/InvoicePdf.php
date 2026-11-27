<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\Storage;

/** Builds the printable invoice PDF for a sale (used for viewing, downloading and emailing). */
class InvoicePdf
{
    public function make(Sale $sale, Company $company): DomPdf
    {
        $sale->loadMissing(['items', 'invoice', 'customer', 'payments' => fn ($q) => $q->active()]);

        return Pdf::loadView('pdf.invoice', [
            'sale' => $sale,
            'company' => $company,
            'logo' => $this->uploadedLogo($company) ?? $this->logoDataUri($company->logo_path),
            'methods' => $company->paymentMethods(),
        ]);
    }

    /** The logo uploaded on the Settings page (kept in the database). */
    private function uploadedLogo(Company $company): ?string
    {
        $asset = \App\Models\CompanyAsset::withoutGlobalScopes()->where('company_id', $company->id)->where('kind', \App\Models\CompanyAsset::LOGO)->first();

        return $asset ? 'data:'.$asset->mime.';base64,'.$asset->data : null;
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
