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

        // One atomic statement: creates the row on first use, otherwise increments it. The exclusive row lock is
        // taken directly, so concurrent requests queue instead of deadlocking (a separate "insert if missing"
        // followed by "select for update" upgrades a shared lock to exclusive and deadlocks under load).
        DB::statement(
            'INSERT INTO document_sequences (company_id, type, year, last_number) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE last_number = last_number + 1',
            [$companyId, $type, $year],
        );

        // We already hold the row lock, so this locking (current) read returns exactly the number we were given.
        $number = (int) DB::table('document_sequences')
            ->where(['company_id' => $companyId, 'type' => $type, 'year' => $year])
            ->lockForUpdate()
            ->value('last_number');

        return sprintf('%s-%d-%05d', $prefix, $year, $number);
    }
}
