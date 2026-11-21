<?php

namespace App\Services;

use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;

/** Sequential per-company, per-year document numbers, e.g. INV-2026-00001. Call inside a transaction. */
class DocumentNumberService
{
    public function __construct(private CurrentCompany $currentCompany) {}

    public function next(string $type, string $prefix): string
    {
        $companyId = $this->currentCompany->id();
        $year = (int) now()->format('Y');
        $key = ['company_id' => $companyId, 'type' => $type, 'year' => $year];

        DB::table('document_sequences')->insertOrIgnore($key + ['last_number' => 0]);

        // Row lock serialises concurrent requests so two documents never get the same number.
        $row = DB::table('document_sequences')->where($key)->lockForUpdate()->first();
        $number = $row->last_number + 1;
        DB::table('document_sequences')->where($key)->update(['last_number' => $number]);

        return sprintf('%s-%d-%05d', $prefix, $year, $number);
    }
}
