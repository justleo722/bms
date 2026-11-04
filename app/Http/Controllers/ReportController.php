<?php

namespace App\Http\Controllers;

use App\Models\InventoryCounter;
use App\Reports\ReportData;
use App\Reports\ReportWorkbook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportWorkbook $workbook,
    ) {}

    /**
     * The reports landing page. Every report is built for the current filters
     * so switching tabs is instant, and the export links reuse the same data.
     */
    public function index(Request $request): View
    {
        [$from, $to, $counter] = $this->filters($request);

        $data = new ReportData($from, $to, $counter);

        return view('pages.reports', [
            'activeNav' => 'reports',
            'reportTypes' => ReportData::types(),
            'reports' => $data->all(),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'counter' => $counter,
            ],
            'counters' => InventoryCounter::query()->orderBy('name')->pluck('name')->all(),
        ]);
    }

    /**
     * Printer-friendly view of a single report. The page auto-opens the
     * browser's print dialog so it can be saved as a PDF.
     */
    public function printReport(Request $request): View
    {
        [$from, $to, $counter] = $this->filters($request);
        $type = $this->reportKey($request);

        $data = new ReportData($from, $to, $counter);
        $report = $data->build($type);

        return view('reports.print', [
            'report' => $report,
            'counterLabel' => $data->counterLabel(),
            'dateLabel' => $data->dateLabel(),
        ]);
    }

    /**
     * Stream a single report as a real .xlsx workbook.
     */
    public function excel(Request $request): BinaryFileResponse|Response
    {
        [$from, $to, $counter] = $this->filters($request);
        $type = $this->reportKey($request);

        $data = new ReportData($from, $to, $counter);
        $report = $data->build($type);

        $spreadsheet = $this->workbook->build($report);

        $path = tempnam(sys_get_temp_dir(), 'bms_report_');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, $this->workbook->filename($report), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function filters(Request $request): array
    {
        $defaultFrom = Carbon::now()->startOfMonth()->toDateString();
        $defaultTo = Carbon::now()->toDateString();

        $from = (string) $request->query('from', $defaultFrom);
        $to = (string) $request->query('to', $defaultTo);
        $counter = (string) $request->query('counter', 'all');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = $defaultFrom;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = $defaultTo;
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $allowed = array_merge(
            ['all'],
            InventoryCounter::query()->orderBy('name')->pluck('name')->all(),
        );
        if (! in_array($counter, $allowed, true)) {
            $counter = 'all';
        }

        return [$from, $to, $counter];
    }

    protected function reportKey(Request $request): string
    {
        $type = (string) $request->query('type', 'sales');

        return array_key_exists($type, ReportData::types()) ? $type : 'sales';
    }
}
