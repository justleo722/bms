<?php

namespace App\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Renders a normalised report (see ReportData::build) into an .xlsx workbook.
 *
 * Numeric columns keep their native type and get an Excel number format so
 * the exported file can be sorted, summed and charted rather than being a
 * sheet full of pre-formatted text.
 */
class ReportWorkbook
{
    protected const BRAND = 'FF1E40AF';

    protected const BRAND_SOFT = 'FFDBEAFE';

    protected const HEADER_BG = 'FF1E3A8A';

    protected const BORDER = 'FFCBD5E1';

    public function build(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $spreadsheet->getProperties()
            ->setCreator('BMS — Bar & Restaurant Management System')
            ->setTitle($report['title'].' — '.$report['from'].' to '.$report['to'])
            ->setDescription($report['description'] ?? '');

        $this->writeSheet($spreadsheet->getActiveSheet(), $report);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function filename(array $report): string
    {
        return 'bms-'.$report['key'].'-report-'.$report['from'].'_to_'.$report['to'].'.xlsx';
    }

    protected function writeSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Report');

        $maxColumns = $this->maxColumns($report);
        if ($maxColumns < 2) {
            $maxColumns = 2;
        }
        $lastColumn = $this->columnLetter($maxColumns);

        // ----- Title banner -----
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->getCell('A1')->setValue(strtoupper($report['title']));
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::BRAND]],
            'font' => ['bold' => true, 'size' => 15, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // ----- Filter / meta line -----
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->getCell('A2')->setValue(
            'Period: '.$report['from'].' to '.$report['to']
            .'   |   Counter: '.($report['counter'] === 'all' ? 'All Counters' : $report['counter'])
            .'   |   Generated: '.now()->format('d M Y, H:i')
        );
        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::BRAND_SOFT]],
            'font' => ['italic' => true, 'size' => 9, 'color' => ['argb' => self::BRAND]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $row = 4;

        // ----- Summary cards as a two-column block -----
        if (! empty($report['summary'])) {
            $sheet->getCell("A{$row}")->setValue('SUMMARY');
            $this->styleSectionHeading($sheet, "A{$row}:{$lastColumn}{$row}");
            $row++;

            foreach ($report['summary'] as $card) {
                $sheet->getCell("A{$row}")->setValue($card['label']);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(10);

                $value = $this->displayValue($card['value'], $card['format']);
                $cell = $sheet->getCell("B{$row}");
                $cell->setValue($value);
                $this->applyNumberFormat($sheet, "B{$row}", $card['format']);
                $sheet->getStyle("B{$row}")->getFont()->setBold(true);

                if (! empty($card['sub'])) {
                    $sheet->getCell("C{$row}")->setValue($card['sub']);
                    $sheet->getStyle("C{$row}")->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF64748B');
                }
                $row++;
            }
            $row++;
        }

        // ----- Sections -----
        foreach ($report['sections'] as $section) {
            $columns = $section['columns'];
            $count = max(count($columns), 1);
            $sectionLast = $this->columnLetter($count);

            $sheet->mergeCells("A{$row}:{$sectionLast}{$row}");
            $sheet->getCell("A{$row}")->setValue($section['title']);
            $this->styleSectionHeading($sheet, "A{$row}:{$sectionLast}{$row}");
            $row++;

            // Header row
            foreach ($columns as $i => $column) {
                $letter = $this->columnLetter($i + 1);
                $cell = $sheet->getCell("{$letter}{$row}");
                $cell->setValue($column['label']);
                $this->applyNumberFormat($sheet, "{$letter}{$row}", $column['type']);
            }
            $sheet->getStyle("A{$row}:{$sectionLast}{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_BG]],
                'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => $this->borderStyle(),
            ]);
            $row++;

            foreach ($section['rows'] as $dataRow) {
                foreach ($columns as $i => $column) {
                    $letter = $this->columnLetter($i + 1);
                    $value = $dataRow[$i] ?? '';
                    $cell = $sheet->getCell("{$letter}{$row}");
                    $cell->setValue($value);
                    $this->applyNumberFormat($sheet, "{$letter}{$row}", $column['type']);
                }
                $sheet->getStyle("A{$row}:{$sectionLast}{$row}")->applyFromArray([
                    'font' => ['size' => 10, 'color' => ['argb' => 'FF334155']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => $this->borderStyle(),
                ]);
                $row++;
            }

            if (! empty($section['footer'])) {
                foreach ($columns as $i => $column) {
                    $letter = $this->columnLetter($i + 1);
                    $cell = $sheet->getCell("{$letter}{$row}");
                    $cell->setValue($section['footer'][$i] ?? '');
                    $this->applyNumberFormat($sheet, "{$letter}{$row}", $column['type']);
                }
                $sheet->getStyle("A{$row}:{$sectionLast}{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']],
                    'borders' => $this->borderStyle(),
                ]);
                $row++;
            }

            $row++; // spacer between sections
        }

        // Column widths sized to the widest content.
        for ($i = 1; $i <= $maxColumns; $i++) {
            $letter = $this->columnLetter($i);
            $width = 16;
            foreach ($report['sections'] as $section) {
                foreach ($section['columns'] as $c => $column) {
                    if ($c + 1 !== $i) {
                        continue;
                    }
                    $width = max($width, min(strlen($column['label']) + 4, 34));
                }
            }
            $sheet->getColumnDimension($letter)->setWidth($width);
        }

        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getSheetView()->setZoomScale(100);
    }

    protected function styleSectionHeading(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    protected function applyNumberFormat(Worksheet $sheet, string $cell, string $type): void
    {
        $format = match ($type) {
            'money', 'number', 'qty' => '#,##0',
            'percent' => '0.0"%"',
            default => 'General',
        };

        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($format);

        if (in_array($type, ['money', 'number', 'qty', 'percent'], true)) {
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    protected function displayValue(mixed $value, string $format): mixed
    {
        if (in_array($format, ['money', 'number', 'qty', 'percent'], true)) {
            return $value;
        }

        return (string) $value;
    }

    protected function maxColumns(array $report): int
    {
        $max = 1;
        foreach ($report['sections'] as $section) {
            $max = max($max, count($section['columns']));
        }

        return $max;
    }

    protected function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    protected function borderStyle(): array
    {
        return [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => self::BORDER],
            ],
        ];
    }
}
