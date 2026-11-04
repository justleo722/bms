<?php

namespace App\Kitchen;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RawMaterialImportTemplate
{
    private const HEADERS = [
        'Name',
        'Current Quantity',
        'Alert Level',
        'Cost Per Unit',
        'Supplier',
        'Expiry Date',
    ];

    public function download()
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle('Raw Materials');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->fromArray([
            'Chicken Breast',
            25.5,
            10,
            8000,
            'Sample Supplier',
            '2027-12-31',
        ], null, 'A2');

        $sheet->getStyle('A1:F1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E40AF']],
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            'protection' => ['locked' => Protection::PROTECTION_PROTECTED],
        ]);
        $sheet->getStyle('A2:F2')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEF3C7']],
            'font' => ['italic' => true, 'color' => ['argb' => 'FF92400E']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            'protection' => ['locked' => Protection::PROTECTION_UNPROTECTED],
        ]);
        $sheet->getStyle('A2:F1001')->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        $sheet->getStyle('B2:D1001')->getNumberFormat()->setFormatCode('#,##0.####');
        $sheet->getStyle('F2:F1001')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        foreach (['A' => 28, 'B' => 20, 'C' => 16, 'D' => 18, 'E' => 26, 'F' => 18] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setPassword('');
        $sheet->getProtection()->setFormatCells(false);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F1');

        $workbook->getProperties()
            ->setCreator('BMS')
            ->setTitle('Raw Materials Import Template')
            ->setDescription('Update the sample row or remove it. Select the category and unit during import.');

        $writer = new Xlsx($workbook);
        $tempPath = tempnam(sys_get_temp_dir(), 'bms-raw-materials-');
        $writer->save($tempPath);
        $workbook->disconnectWorksheets();

        return response()->download($tempPath, 'raw-materials-import-template.xlsx')->deleteFileAfterSend(true);
    }

    /** @return array<int, string> */
    public static function headers(): array
    {
        return self::HEADERS;
    }
}
