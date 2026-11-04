<?php

namespace App\Inventory;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds the decorated, protected product-import workbook.
 *
 * Layout of the "Products" sheet:
 *
 *   Row 1        Banner title
 *   Row 2        Subtitle / lock notice
 *   Row 3        Spacer
 *   Row 4        Column headers  <- locked, autofiltered, frozen
 *   Row 5...     Data entry area <- unlocked, number-formatted
 *                A few tinted example rows are pre-filled at the top so the
 *                expected format is visible before typing anything.
 *
 * A second, read-only "Instructions" sheet explains the columns and lists the
 * fields that are chosen once in the Import dialog instead of per row.
 *
 * Sheet protection is enabled. Every cell is locked by default, so the banner,
 * instructions and header row cannot be edited or deleted. Only the data entry
 * range is explicitly unlocked, and the protection is granted without a
 * password so the range can be extended later if the user needs to.
 */
class ProductImportTemplate
{
    protected const HEADER_ROW = 4;

    protected const FIRST_DATA_ROW = 5;

    protected const LAST_DATA_ROW = 1004;

    protected const BANNER_BG = 'FF1E3A8A';

    protected const SUBTITLE_BG = 'FFDBEAFE';

    protected const HEADER_BG = 'FF1E40AF';

    protected const ENTRY_BG = 'FFF8FAFC';

    protected const BORDER = 'FFCBD5E1';

    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $spreadsheet->getProperties()
            ->setCreator('BMS — Bar & Restaurant Management System')
            ->setTitle('Product Import Template')
            ->setDescription('Fill in the Products sheet and upload it back to Inventory → Import Products.');

        $this->buildProductsSheet($spreadsheet->getActiveSheet());
        $this->buildInstructionsSheet($spreadsheet);

        $spreadsheet->setActiveSheetIndexByName('Products');

        return $spreadsheet;
    }

    public function filename(): string
    {
        return 'bms-product-import-template.xlsx';
    }

    protected function buildProductsSheet(Worksheet $sheet): void
    {
        $columns = ImportSchema::columns();
        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

        $sheet->setTitle('Products');

        // ----- Banner (locked) -----
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->getCell('A1')->setValue('PRODUCT IMPORT TEMPLATE');
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::BANNER_BG]],
            'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FFFFFFFF'], 'name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(34);

        // ----- Subtitle (locked) -----
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->getCell('A2')->setValue(
            'Type your products starting from row '.self::FIRST_DATA_ROW.' — one product per row. '
            .'The header row is locked; do not rename, reorder or delete it. '
            .'Category and Counter are chosen once when you upload the file.'
            .(ImportSchema::demoRows() === [] ? '' : ' The tinted rows at the top are examples — overwrite them or clear their contents.')
        );
        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::SUBTITLE_BG]],
            'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FF1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->getRowDimension(3)->setRowHeight(6);

        // ----- Header row (locked) -----
        foreach ($columns as $i => $column) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $cell = $sheet->getCell("{$letter}".self::HEADER_ROW);

            $cell->setValue($column['label'].($column['required'] ?? false ? ' *' : ''));

            $sheet->getColumnDimension($letter)->setWidth($column['width']);
        }

        $headerRange = 'A'.self::HEADER_ROW.":{$lastColumn}".self::HEADER_ROW;

        $sheet->getStyle($headerRange)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_BG]],
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => $this->borderStyle(),
        ]);

        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(32);

        // ----- Data entry area (unlocked) -----
        $entryRange = 'A'.self::FIRST_DATA_ROW.":{$lastColumn}".self::LAST_DATA_ROW;

        $sheet->getStyle($entryRange)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::ENTRY_BG]],
            'font' => ['size' => 11],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $this->borderStyle(),
        ]);

        // Numeric alignment + number formats per column type.
        foreach ($columns as $i => $column) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $type = $column['type'] ?? 'text';

            if (in_array($type, ['number', 'decimal'], true)) {
                $sheet->getStyle("{$letter}".self::FIRST_DATA_ROW.":{$letter}".self::LAST_DATA_ROW)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $format = $type === 'number' ? '#,##0' : '#,##0.00';

                $sheet->getStyle("{$letter}".self::FIRST_DATA_ROW.":{$letter}".self::LAST_DATA_ROW)
                    ->getNumberFormat()
                    ->setFormatCode($format);
            } elseif ($type === 'date') {
                // Pre-formatting the column is what turns it into a real Excel
                // date field: the picker appears, entries sort chronologically,
                // and the parser receives a serial number it can convert.
                $sheet->getStyle("{$letter}".self::FIRST_DATA_ROW.":{$letter}".self::LAST_DATA_ROW)
                    ->getNumberFormat()
                    ->setFormatCode($column['format'] ?? 'yyyy-mm-dd');

                $sheet->getStyle("{$letter}".self::FIRST_DATA_ROW.":{$letter}".self::LAST_DATA_ROW)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        $this->writeDemoRows($sheet, $columns);

        $this->applyCellProtection($sheet, $lastColumn);

        // ----- View niceties -----
        $sheet->freezePane('A'.self::FIRST_DATA_ROW);
        $sheet->setAutoFilter($headerRange);
        $sheet->getSheetView()->setZoomScale(100);
        $sheet->getPageSetup()
            ->setOrientation('landscape')
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $this->protectSheet($sheet);
    }

    /**
     * Pre-fill the top of the entry area with a few example products.
     *
     * These are ordinary unlocked cells: the user overwrites them with real
     * data or deletes the rows outright. They are tinted so it is obvious at a
     * glance which rows are samples rather than real stock.
     */
    protected function writeDemoRows(Worksheet $sheet, array $columns): void
    {
        $demoRows = ImportSchema::demoRows();

        if ($demoRows === []) {
            return;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $lastRow = self::FIRST_DATA_ROW + count($demoRows) - 1;

        foreach ($demoRows as $index => $demo) {
            $row = self::FIRST_DATA_ROW + $index;

            foreach ($columns as $i => $column) {
                $letter = Coordinate::stringFromColumnIndex($i + 1);
                $value = $demo[$column['key']] ?? null;

                // Writing an ISO string into a date-formatted cell would leave a
                // text value the parser has to guess at, so store a real Excel
                // serial instead. That is also what makes the date picker and
                // chronological sorting work when the user edits the row.
                if (($column['type'] ?? 'text') === 'date' && $value !== null && $value !== '') {
                    $value = Date::PHPToExcel(Carbon::parse($value));
                }

                $sheet->setCellValue("{$letter}{$row}", $value);
            }
        }

        $demoRange = 'A'.self::FIRST_DATA_ROW.":{$lastColumn}{$lastRow}";

        $sheet->getStyle($demoRange)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEF3C7']],
            'font' => ['size' => 11, 'italic' => true, 'color' => ['argb' => 'FF92400E']],
        ]);

        // Numeric columns keep the right alignment/number format applied to the
        // whole entry area, so only the font is overridden back to the normal
        // (non-italic) size for the sample values.
        $sheet->getStyle('A'.self::FIRST_DATA_ROW.":{$lastColumn}{$lastRow}")
            ->getFont()
            ->setItalic(false);
    }

    /**
     * Lock the banner, subtitle and header row; unlock the data entry rows.
     *
     * PhpSpreadsheet's Protection::setLocked() expects the PROTECTION_*
     * string constants, not booleans. A boolean is coerced to "1"/"" by PHP,
     * which the writer then serialises as locked="false" — locking nothing and
     * leaving the header editable. So the constants are used explicitly here.
     */
    protected function applyCellProtection(Worksheet $sheet, string $lastColumn): void
    {
        $lockedRange = 'A1:'.$lastColumn.self::HEADER_ROW;
        $entryRange = 'A'.self::FIRST_DATA_ROW.':'.$lastColumn.self::LAST_DATA_ROW;

        $sheet->getStyle($lockedRange)->applyFromArray([
            'protection' => ['locked' => Protection::PROTECTION_PROTECTED],
        ]);

        $sheet->getStyle($entryRange)->applyFromArray([
            'protection' => ['locked' => Protection::PROTECTION_UNPROTECTED],
        ]);
    }

    protected function protectSheet(Worksheet $sheet): void
    {
        $protection = $sheet->getProtection();

        $protection->setSheet(true);
        $protection->setObjects(true);
        $protection->setScenarios(true);
        $protection->setFormatCells(false);
        $protection->setFormatColumns(false);
        $protection->setFormatRows(false);
        $protection->setInsertColumns(false);
        $protection->setInsertRows(false);
        $protection->setDeleteColumns(false);
        $protection->setDeleteRows(false);
        $protection->setSort(true);
        $protection->setAutoFilter(true);
        $protection->setPivotTables(true);

        // Both false = the user may freely click/select any cell, locked or
        // not. These read as "selection is blocked" when true.
        $protection->setSelectLockedCells(false);
        $protection->setSelectUnlockedCells(false);
    }

    protected function buildInstructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instructions');

        $columns = ImportSchema::columns();
        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->getCell('A1')->setValue('How to use this template');
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::BANNER_BG]],
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $demoCount = count(ImportSchema::demoRows());

        $steps = [
            '1. Open the "Products" sheet — this is the only sheet you need to edit.',
            '2. Start typing in row '.self::FIRST_DATA_ROW.' and keep going down. One product per row.',
            $demoCount > 0
                ? '2b. Rows '.self::FIRST_DATA_ROW.' to '.(self::FIRST_DATA_ROW + $demoCount - 1).' are tinted example products. Overwrite them with your own, or select them and press Delete to clear their contents. They are real data as far as the upload is concerned — a row left half-empty is reported as an invalid product.'
                : '',
            '3. Rows '.self::FIRST_DATA_ROW.' to '.self::LAST_DATA_ROW.' are unlocked and ready for typing. The banner and the header row above them are locked so they cannot be changed by accident.',
            '4. Do not rename, reorder or delete the header row in row '.self::HEADER_ROW.' — the import matches columns by name and will not recognise the file without it.',
            '5. Columns marked with * are required. The rest can be left blank — a blank Reorder Level simply means 0.',
            '6. Selling Price cannot be lower than the Buying Price, otherwise the row is rejected.',
            '7. Expiry Date is optional. Click the cell and use the date picker, or type YYYY-MM-DD. Leave it blank for non-perishable stock like spirits.',
            '8. Save as .xlsx, then go to Inventory → Import Products and upload the file.',
        ];

        $row = 3;

        foreach (array_filter($steps) as $step) {
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->getCell("A{$row}")->setValue($step);
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
        }

        $row++;

        // ---- Fields chosen in the Import dialog rather than per row ----
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->getCell("A{$row}")->setValue('Not in this file — you choose these once when uploading');
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_BG]],
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        $assignedHeader = $row;

        foreach (['Field', 'How it is set', 'Value', 'Notes'] as $i => $title) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$letter}{$assignedHeader}", $title);
        }

        $sheet->getStyle("A{$assignedHeader}:{$lastColumn}{$assignedHeader}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $this->borderStyle(),
        ]);

        $row++;

        $assigned = [];

        foreach (ImportSchema::assignments() as $assignment) {
            $assigned[] = [
                $assignment['label'],
                'Dropdown in the Import dialog',
                $assignment['default'] ?? '—',
                implode(', ', $assignment['options'] ?? []),
            ];
        }

        foreach (ImportSchema::defaults() as $key => $value) {
            $assigned[] = [
                Str::headline($key),
                'Fixed for every import',
                (string) $value,
                'Change per product afterwards if needed',
            ];
        }

        foreach (config('inventory.import_rules', []) as $rule) {
            $assigned[] = [
                Str::headline($rule['key'] ?? 'rule'),
                'Checked on every row',
                ucfirst($rule['severity'] ?? 'error'),
                strtr($rule['message'] ?? '', [
                    '%left%' => ImportSchema::column($rule['left'] ?? '')['label'] ?? '',
                    '%right%' => ImportSchema::column($rule['right'] ?? '')['label'] ?? '',
                ]),
            ];
        }

        foreach ($assigned as $line) {
            foreach ($line as $i => $value) {
                $letter = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValue("{$letter}{$row}", $value);
            }

            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                'font' => ['size' => 10, 'color' => ['argb' => 'FF334155']],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => $this->borderStyle(),
            ]);

            $sheet->getRowDimension($row)->setRowHeight(28);
            $row++;
        }

        $row++;

        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->getCell("A{$row}")->setValue('Column reference');
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_BG]],
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        $headerRow = $row;

        foreach (['Column', 'Required', 'Type', 'Allowed values', 'Example', 'Notes'] as $i => $title) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$letter}{$headerRow}", $title);
        }

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $this->borderStyle(),
        ]);

        $row++;

        foreach ($columns as $column) {
            $sheet->setCellValue("A{$row}", $column['label']);
            $sheet->setCellValue("B{$row}", ($column['required'] ?? false) ? 'Yes' : 'No');
            $sheet->setCellValue("C{$row}", ucfirst($column['type']));
            $sheet->setCellValue("D{$row}", ! empty($column['options'])
                ? implode(', ', $column['options'])
                : 'Any value');
            $sheet->setCellValue("E{$row}", $column['example'] ?? '');
            $sheet->setCellValue("F{$row}", $column['help'] ?? '');

            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                'font' => ['size' => 10, 'color' => ['argb' => 'FF334155']],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => $this->borderStyle(),
            ]);

            $sheet->getRowDimension($row)->setRowHeight(28);
            $row++;
        }

        $row++;

        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->getCell("A{$row}")->setValue(
            'Template generated '.Carbon::now()->format('j M Y, H:i').' · Maximum '.config('inventory.import.max_rows').' products per import.'
        );
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFont()
            ->setItalic(true)
            ->getColor()
            ->setARGB('FF64748B');

        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(34);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(46);
        $sheet->getColumnDimension('G')->setWidth(16);

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$row}")
            ->getProtection()
            ->setLocked(Protection::PROTECTION_PROTECTED);

        $sheet->getSheetView()->setZoomScale(100);
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
