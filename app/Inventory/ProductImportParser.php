<?php

namespace App\Inventory;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class ProductImportParser
{
    /**
     * Read an uploaded spreadsheet and normalise every product row.
     *
     * @return array{
     *     ok: bool,
     *     error: ?string,
     *     products: array<int, array<string, mixed>>,
     *     summary: array<string, int>,
     *     detected_headers: array<int, string>,
     *     missing_columns: array<int, string>,
     *     unknown_columns: array<int, string>,
     *     file_name: string
     * }
     */
    public function parse(UploadedFile $file): array
    {
        $result = [
            'ok' => false,
            'error' => null,
            'products' => [],
            'summary' => ['total' => 0, 'valid' => 0, 'invalid' => 0],
            'detected_headers' => [],
            'missing_columns' => [],
            'unknown_columns' => [],
            'file_name' => $file->getClientOriginalName(),
        ];

        $maxKb = config('inventory.import.max_size_kb');

        if ($file->getSize() > $maxKb * 1024) {
            $result['error'] = "File is too large. Maximum size is {$maxKb}MB.";

            return $result;
        }

        try {
            $spreadsheet = $this->load($file);
        } catch (Throwable $e) {
            report($e);

            $result['error'] = 'We could not read that file. Make sure it is a valid .xlsx, .xls or .csv export.';

            return $result;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $maxRows = config('inventory.import.max_rows');

        // Walk backwards from the bottom of the used range so trailing empty
        // rows (the template ships with 1000 formatted blank rows) are ignored.
        $highestRow = $sheet->getHighestDataRow();
        $lastColumn = Coordinate::stringFromColumnIndex(
            Coordinate::columnIndexFromString($sheet->getHighestDataColumn())
        );

        // Find the header row: the first row where at least half of the
        // configured labels are recognised.
        $headerRow = $this->detectHeaderRow($sheet, $lastColumn);

        if ($headerRow === null) {
            $spreadsheet->disconnectWorksheets();

            $result['error'] = 'No product columns were recognised. Make sure the file still has the template header row '
                .'( '.implode(', ', ImportSchema::labels()).' ).';

            return $result;
        }

        $headers = $this->readRow($sheet, $headerRow, $lastColumn);
        $match = ImportSchema::match($headers);

        $result['detected_headers'] = array_values(array_map('strval', array_filter($headers, fn ($h) => trim((string) $h) !== '')));
        $result['missing_columns'] = array_map(fn ($c) => $c['label'], $match['missing']);
        $result['unknown_columns'] = $match['unknown'];

        if (empty($match['mapping'])) {
            $spreadsheet->disconnectWorksheets();

            $result['error'] = 'None of the columns in this file match the product template.';

            return $result;
        }

        // A missing *required* column cannot be validated row by row, because
        // there is no cell to read. Fail loudly instead of quietly importing
        // products with a blank name or price.
        $missingRequired = ImportSchema::missingRequired($match['missing']);

        if ($missingRequired !== []) {
            $spreadsheet->disconnectWorksheets();

            $result['error'] = 'This file is missing required column(s): '.implode(', ', $missingRequired)
                .'. Download the template again and copy your data into it, keeping the header row.';

            return $result;
        }

        $products = [];

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $values = $this->readRow($sheet, $row, $lastColumn);

            // Skip fully blank rows.
            if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $product = ImportSchema::validateRow($values, $match['mapping']);
            $product['_row'] = $row;
            $product['_valid'] = ImportSchema::isValid($product);

            $products[] = $product;

            if (count($products) >= $maxRows) {
                break;
            }
        }

        $spreadsheet->disconnectWorksheets();

        $valid = count(array_filter($products, fn ($p) => $p['_valid']));

        $result['ok'] = true;
        $result['products'] = $products;
        $result['summary'] = [
            'total' => count($products),
            'valid' => $valid,
            'invalid' => count($products) - $valid,
        ];

        return $result;
    }

    /**
     * Read a single row as raw values.
     *
     * `formatData` is disabled so numeric cells come back as numbers rather
     * than display strings (e.g. 4,000 instead of 4000).
     *
     * @return array<int, mixed>
     */
    protected function readRow(Worksheet $sheet, int $row, string $lastColumn): array
    {
        return $sheet->rangeToArray("A{$row}:{$lastColumn}{$row}", formatData: false)[0] ?? [];
    }

    protected function load(UploadedFile $file): Spreadsheet
    {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension());

        if (in_array($extension, ['csv', 'txt'], true)) {
            $reader = IOFactory::createReader('Csv');
            $reader->setInputEncoding('UTF-8');
            $reader->setEnclosure('"');

            return $reader->load($path);
        }

        return IOFactory::createReaderForFile($path)->load($path);
    }

    /**
     * Locate the header row by scoring rows against the configured labels.
     */
    protected function detectHeaderRow(Worksheet $sheet, string $lastColumn): ?int
    {
        $columns = ImportSchema::columns();
        $labels = array_map(fn ($c) => ImportSchema::normalize($c['label']), $columns);
        $needed = max(2, (int) ceil(count($columns) / 2));

        $limit = min($sheet->getHighestDataRow(), 25);

        for ($row = 1; $row <= $limit; $row++) {
            $cells = $this->readRow($sheet, $row, $lastColumn);

            $found = 0;

            foreach ($cells as $cell) {
                if (in_array(ImportSchema::normalize((string) $cell), $labels, true)) {
                    $found++;
                }
            }

            if ($found >= $needed) {
                return $row;
            }
        }

        return null;
    }
}
