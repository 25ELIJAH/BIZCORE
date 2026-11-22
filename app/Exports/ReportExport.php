<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Spreadsheet version of a ReportService report.
 *
 * Money / number columns are written as numbers (so they can be summed) and dates as real Excel dates
 * (so they sort and filter). Strict null comparison keeps genuine zeros instead of turning them into blank cells.
 */
class ReportExport implements FromArray, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithStrictNullComparison, WithTitle
{
    private const MAX_WIDTH = 45;

    private const MONEY_FORMAT = '#,##0.00';

    private const DATE_FORMAT = 'yyyy-mm-dd';

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
        $out = array_map(fn ($row) => $this->line($row, isTotals: false), $this->report['rows']);
        if ($this->report['totals']) {
            $out[] = $this->line($this->report['totals'], isTotals: true);
        }

        return $out;
    }

    /** @return array<string, string> column letter => number format */
    public function columnFormats(): array
    {
        $formats = [];
        foreach (array_values($this->report['columns']) as $i => $col) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $key = array_keys($this->report['columns'])[$i];
            $formats[$letter] = match ($col['type']) {
                'money' => self::MONEY_FORMAT,
                'date' => self::DATE_FORMAT,
                'number' => $this->columnHasFraction($key) ? '#,##0.00' : '#,##0',
                default => NumberFormat::FORMAT_GENERAL,
            };
        }

        return $formats;
    }

    /** Widths follow the content but are capped, so one very long name cannot push the other columns off screen. */
    public function columnWidths(): array
    {
        $widths = [];
        foreach (array_keys($this->report['columns']) as $i => $key) {
            $col = $this->report['columns'][$key];
            $longest = mb_strlen($col['label']) + 4; // room for the filter button
            foreach ($this->allRows() as $row) {
                $value = $row[$key] ?? null;
                $longest = max($longest, mb_strlen($this->display($value, $col['type'])));
            }
            $widths[Coordinate::stringFromColumnIndex($i + 1)] = min($longest + 2, self::MAX_WIDTH);
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $ws = $event->sheet->getDelegate();
            $colCount = count($this->report['columns']);
            $lastCol = Coordinate::stringFromColumnIndex($colCount);
            $rowCount = count($this->report['rows']);
            $lastRow = $rowCount + 1 + ($this->report['totals'] ? 1 : 0);

            // Header
            $ws->getStyle("A1:{$lastCol}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0C4137']],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '06D6A0']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $ws->freezePane('A2');
            if ($rowCount > 0) {
                $ws->setAutoFilter("A1:{$lastCol}".($rowCount + 1));
            }

            // Alignment: numbers right, text left and wrapped when it hits the width cap
            foreach (array_values($this->report['columns']) as $i => $col) {
                $letter = Coordinate::stringFromColumnIndex($i + 1);
                $numeric = in_array($col['type'], ['money', 'number'], true);
                $range = "{$letter}1:{$letter}{$lastRow}";
                $ws->getStyle($range)->getAlignment()
                    ->setHorizontal($numeric ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(! $numeric);
            }
            $ws->getStyle("A1:{$lastCol}1")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            // Light row separators on the data rows
            if ($rowCount > 0) {
                $ws->getStyle('A2:'.$lastCol.($rowCount + 1))->applyFromArray([
                    'borders' => [
                        'horizontal' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DBE5E2']],
                        'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DBE5E2']],
                    ],
                ]);
            }

            // Totals row
            if ($this->report['totals']) {
                $ws->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '0C4137']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6FBF6']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0C4137']]],
                ]);
            }

            // Printing: A4, landscape for wide reports, one page wide, header row repeated
            $setup = $ws->getPageSetup();
            $setup->setPaperSize(PageSetup::PAPERSIZE_A4);
            $setup->setOrientation($colCount > 5 ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT);
            $setup->setFitToWidth(1)->setFitToHeight(0);
            $setup->setFitToPage(true);
            $setup->setRowsToRepeatAtTopByStartAndEnd(1, 1);
            $ws->getPageMargins()->setLeft(0.4)->setRight(0.4);
            $ws->getHeaderFooter()->setOddFooter('&L&8'.$this->report['title'].'&R&8Page &P of &N');
        }];
    }

    // ---- helpers -------------------------------------------------------------

    private function line(array $row, bool $isTotals): array
    {
        $cells = [];
        foreach ($this->report['columns'] as $key => $col) {
            $value = $row[$key] ?? null;
            $type = $col['type'];

            if (in_array($type, ['money', 'number'], true)) {
                // A missing figure in a data row is a zero; in the totals row it means "not applicable".
                $value = is_numeric($value) ? (float) $value : ($isTotals ? null : (($value === null || $value === '') ? 0.0 : $value));
            } elseif ($type === 'date' && ! $isTotals && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $value = Date::PHPToExcel(strtotime($value.' 12:00:00'));
            }

            $cells[] = $this->safe($value);
        }

        return $cells;
    }

    /** Text starting with = + - @ would run as a formula in Excel; user-entered names must never do that. */
    private function safe(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /** @return list<array> */
    private function allRows(): array
    {
        return $this->report['totals'] ? [...$this->report['rows'], $this->report['totals']] : $this->report['rows'];
    }

    private function display(mixed $value, string $type): string
    {
        if ($value === null) {
            return '';
        }

        return match ($type) {
            'money' => is_numeric($value) ? number_format((float) $value, 2) : (string) $value,
            'date' => '2026-01-01',
            default => (string) $value,
        };
    }

    private function columnHasFraction(string $key): bool
    {
        foreach ($this->allRows() as $row) {
            $v = $row[$key] ?? null;
            if (is_numeric($v) && (float) $v !== floor((float) $v)) {
                return true;
            }
        }

        return false;
    }
}
