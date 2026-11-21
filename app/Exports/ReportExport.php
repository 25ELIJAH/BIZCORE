<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Spreadsheet version of a ReportService report. Money / number columns are written as numbers so they can be summed. */
class ReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private array $report) {}

    public function headings(): array
    {
        return array_column($this->report['columns'], 'label');
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $this->report['title']), 0, 31);
    }

    public function array(): array
    {
        $out = array_map(fn ($row) => $this->line($row), $this->report['rows']);
        if ($this->report['totals']) {
            $out[] = $this->line($this->report['totals']);
        }

        return $out;
    }

    private function line(array $row): array
    {
        $cells = [];
        foreach ($this->report['columns'] as $key => $col) {
            $value = $row[$key] ?? null;
            $cells[] = $this->safe(in_array($col['type'], ['money', 'number'], true) && is_numeric($value) ? (float) $value : $value);
        }

        return $cells;
    }

    /** Text starting with = + - @ would run as a formula in Excel; user-entered names must never do that. */
    private function safe(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
