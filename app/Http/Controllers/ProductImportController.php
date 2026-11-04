<?php

namespace App\Http\Controllers;

use App\Inventory\ProductImportParser;
use App\Inventory\ProductImportTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductImportController extends Controller
{
    public function __construct(
        protected ProductImportTemplate $template,
        protected ProductImportParser $parser,
    ) {}

    /**
     * Stream the decorated, protected Excel template to the browser.
     */
    public function download(): BinaryFileResponse|Response
    {
        $spreadsheet = $this->template->build();

        $path = tempnam(sys_get_temp_dir(), 'bms_template_');

        (new Xlsx($spreadsheet))->save($path);

        $spreadsheet->disconnectWorksheets();

        return response()->download($path, $this->template->filename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Parse an uploaded spreadsheet and return every product row for preview.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.config('inventory.import.max_size_kb'),
                'mimes:xlsx,xls,csv,txt',
            ],
        ], [], ['file' => 'file']);

        $result = $this->parser->parse($request->file('file'));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
