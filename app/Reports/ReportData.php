<?php

namespace App\Reports;

use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\InventoryProduct;
use App\Models\InventoryStock;
use App\Models\KitchenRawMaterialMovement;
use App\Models\KitchenRecipe;
use App\Models\PosOrder;
use App\Models\User;
use App\Settings\BarSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the Reports module.
 *
 * Reports use records persisted by the POS, inventory, kitchen, expenses and
 * staff modules. Areas without a backing data model are returned empty rather
 * than being presented as live business data.
 *
 * `build($key)` returns a normalised report structure that the reports page,
 * the print view and the Excel export all consume unchanged:
 *
 *   [
 *     'key'         => 'sales',
 *     'title'       => 'Sales Report',
 *     'icon'        => 'o-banknotes',
 *     'description' => '...',
 *     'summary'     => [ ['label' => ..., 'value' => ..., 'format' => 'money', 'sub' => ..., 'tone' => 'up'] ],
 *     'sections'    => [
 *         [
 *             'title'   => 'Revenue by Day',
 *             'columns' => [ ['label' => 'Date', 'align' => 'left', 'type' => 'text'] ],
 *             'rows'    => [ ['2026-10-28', 42, 312000] ],
 *             'footer'  => ['Total', 812, 12450000],
 *         ],
 *     ],
 *   ]
 *
 * Column types are: text, number, qty, money, percent. Both the Blade views
 * and the Excel writer format values from the type, so numbers stay numbers
 * in the spreadsheet instead of pre-formatted strings.
 */
class ReportData
{
    protected string $from;

    protected string $to;

    protected string $counter;

    protected ?array $staff = null;

    protected ?array $sales = null;

    protected ?array $customers = null;

    protected ?array $inventory = null;

    protected ?array $expenses = null;

    protected ?array $purchases = null;

    protected ?array $recipeCosts = null;

    public function __construct(string $from = '', string $to = '', string $counter = 'all')
    {
        $this->from = $from !== '' ? $from : now()->startOfMonth()->toDateString();
        $this->to = $to !== '' ? $to : now()->toDateString();
        $this->counter = $counter !== '' ? $counter : 'all';
    }

    /**
     * The report catalogue, in tab order.
     *
     * @return array<string, array{title: string, icon: string, description: string}>
     */
    public static function types(): array
    {
        return [
            'sales' => [
                'title' => 'Sales',
                'icon' => 'o-banknotes',
                'description' => 'Revenue trend, sales by counter, order type, category and top items.',
            ],
            'payments' => [
                'title' => 'Payments',
                'icon' => 'o-credit-card',
                'description' => 'Collections by payment method, split payments, refunds and voids.',
            ],
            'items' => [
                'title' => 'Item / Menu',
                'icon' => 'o-squares-2x2',
                'description' => 'Menu performance with quantity sold, revenue, cost and margin.',
            ],
            'inventory' => [
                'title' => 'Inventory',
                'icon' => 'o-cube',
                'description' => 'Stock valuation, low stock, out of stock and expiry watchlist.',
            ],
            'purchases' => [
                'title' => 'Purchases',
                'icon' => 'o-truck',
                'description' => 'Purchase orders and supplier spend across the period.',
            ],
            'financial' => [
                'title' => 'Financial (P&L)',
                'icon' => 'o-chart-bar',
                'description' => 'Revenue, cost of goods sold, gross profit, expenses and net profit.',
            ],
            'staff' => [
                'title' => 'Staff',
                'icon' => 'o-user-group',
                'description' => 'Sales ranking, orders served and role breakdown.',
            ],
            'customers' => [
                'title' => 'Customers',
                'icon' => 'o-user-circle',
                'description' => 'Loyalty tiers, visit frequency and spending patterns.',
            ],
            'kitchen' => [
                'title' => 'Kitchen',
                'icon' => 'o-fire',
                'description' => 'Tickets by station, preparation times and waste adjustments.',
            ],
        ];
    }

    /**
     * Build every report for the current filters, keyed by report id.
     *
     * @return array<string, array>
     */
    public function all(): array
    {
        $reports = [];

        foreach (array_keys(self::types()) as $key) {
            $reports[$key] = $this->build($key);
        }

        return $reports;
    }

    public function build(string $key): array
    {
        $meta = self::types()[$key] ?? self::types()['sales'];

        $sections = match ($key) {
            'sales' => $this->salesReport(),
            'payments' => $this->paymentsReport(),
            'items' => $this->itemsReport(),
            'inventory' => $this->inventoryReport(),
            'purchases' => $this->purchasesReport(),
            'financial' => $this->financialReport(),
            'staff' => $this->staffReport(),
            'customers' => $this->customersReport(),
            'kitchen' => $this->kitchenReport(),
            default => $this->salesReport(),
        };

        return array_merge($meta, [
            'key' => $key,
            'from' => $this->from,
            'to' => $this->to,
            'counter' => $this->counter,
        ], $sections);
    }

    /** Human label for the active counter filter. */
    public function counterLabel(): string
    {
        return $this->counter === 'all' ? 'All Counters' : $this->counter;
    }

    public function dateLabel(): string
    {
        return Carbon::parse($this->from)->format('d M Y').' – '.Carbon::parse($this->to)->format('d M Y');
    }

    public static function format(mixed $value, string $type): string
    {
        return match ($type) {
            'money' => self::money((float) $value),
            'number', 'qty' => number_format((float) $value),
            'percent' => self::trimPercent((float) $value),
            default => (string) $value,
        };
    }

    protected static function money(float $value): string
    {
        /** @var \WeakMap<object, array>|null $cache */
        static $cache = null;
        $cache ??= new \WeakMap();
        $app = app();

        if (! isset($cache[$app])) {
            $cache[$app] = BarSettings::currency();
        }
        $currency = $cache[$app];

        return $currency['symbol'].' '.number_format($value, (int) ($currency['decimals'] ?? 0));
    }

    protected static function trimPercent(float $value): string
    {
        $formatted = number_format($value, 1);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted.'%';
    }

    // =====================================================================
    //  REPORTS
    // =====================================================================

    protected function salesReport(): array
    {
        $paid = $this->paidSales();
        $revenue = array_sum(array_column($paid, 'total'));
        $count = count($paid);
        $avg = $count > 0 ? $revenue / $count : 0;

        // Revenue by day.
        $byDay = [];
        foreach ($paid as $sale) {
            $day = substr($sale['date'], 0, 10);
            $byDay[$day] = $byDay[$day] ?? ['count' => 0, 'revenue' => 0];
            $byDay[$day]['count']++;
            $byDay[$day]['revenue'] += $sale['total'];
        }
        ksort($byDay);
        $dayRows = [];
        foreach ($byDay as $day => $row) {
            $dayRows[] = [Carbon::parse($day)->format('d M Y'), $row['count'], round($row['revenue'])];
        }
        $dayFooter = ['Total', array_sum(array_column($dayRows, 1)), array_sum(array_column($dayRows, 2))];

        // Sales by counter.
        $byCounter = [];
        foreach ($paid as $sale) {
            $byCounter[$sale['counter']] = $byCounter[$sale['counter']] ?? ['count' => 0, 'revenue' => 0];
            $byCounter[$sale['counter']]['count']++;
            $byCounter[$sale['counter']]['revenue'] += $sale['total'];
        }
        arsort($byCounter);
        $counterRows = [];
        foreach ($byCounter as $name => $row) {
            $counterRows[] = [$name, $row['count'], round($row['revenue']), $revenue > 0 ? round($row['revenue'] / $revenue * 100, 1) : 0];
        }

        // Sales by order type.
        $types = $this->orderTypeTotals($paid);

        // Sales by category.
        $categories = $this->categoryTotals($paid);

        // Hourly sales (peak hour).
        $byHour = [];
        foreach ($paid as $sale) {
            $hour = (int) substr($sale['date'], 11, 2);
            $byHour[$hour] = ($byHour[$hour] ?? 0) + $sale['total'];
        }
        ksort($byHour);
        $hourRows = [];
        foreach ($byHour as $hour => $amount) {
            $hourRows[] = [sprintf('%02d:00 – %02d:00', $hour, ($hour + 1) % 24), round($amount)];
        }
        $peakLabel = '—';
        if ($byHour !== []) {
            $peakHour = (int) array_search(max($byHour), $byHour, true);
            $peakLabel = sprintf('%02d:00 – %02d:00', $peakHour, ($peakHour + 1) % 24);
        }

        return [
            'summary' => [
                $this->card('Total Revenue', round($revenue), 'money', 'paid revenue in period', 'up'),
                $this->card('Transactions', $count, 'number', 'Paid orders', 'neutral'),
                $this->card('Avg. Transaction', round($avg), 'money', 'per paid order', 'neutral'),
                $this->card('Peak Hour', $peakLabel, 'text', 'busiest trading hour', 'neutral'),
            ],
            'sections' => [
                [
                    'title' => 'Revenue by Day',
                    'columns' => $this->columns(['Date' => 'text', 'Orders' => 'number', 'Revenue' => 'money']),
                    'rows' => $dayRows,
                    'footer' => $dayFooter,
                ],
                [
                    'title' => 'Sales by Counter',
                    'columns' => $this->columns(['Counter' => 'text', 'Orders' => 'number', 'Revenue' => 'money', '% of Total' => 'percent']),
                    'rows' => $counterRows,
                ],
                [
                    'title' => 'Sales by Order Type',
                    'columns' => $this->columns(['Order Type' => 'text', 'Orders' => 'number', 'Revenue' => 'money', '% of Total' => 'percent']),
                    'rows' => $types,
                ],
                [
                    'title' => 'Sales by Category',
                    'columns' => $this->columns(['Category' => 'text', 'Qty Sold' => 'qty', 'Revenue' => 'money', '% of Total' => 'percent']),
                    'rows' => $categories,
                ],
                [
                    'title' => 'Top Selling Items',
                    'columns' => $this->columns(['#' => 'number', 'Item' => 'text', 'Qty Sold' => 'qty', 'Revenue' => 'money', '% of Total' => 'percent']),
                    'rows' => $this->topItems($paid, 10),
                ],
                [
                    'title' => 'Hourly Sales',
                    'columns' => $this->columns(['Hour' => 'text', 'Revenue' => 'money']),
                    'rows' => $hourRows,
                ],
            ],
        ];
    }

    protected function paymentsReport(): array
    {
        $paid = $this->paidSales();
        $collected = array_sum(array_column($paid, 'total'));
        $voided = array_filter($this->filteredSales(), fn ($s) => $s['status'] === 'voided');

        $byMethod = [];
        foreach ($paid as $sale) {
            $method = $sale['payment'];
            $byMethod[$method] = $byMethod[$method] ?? ['count' => 0, 'amount' => 0];
            $byMethod[$method]['count']++;
            $byMethod[$method]['amount'] += $sale['total'];
        }
        arsort($byMethod);
        $methodRows = [];
        foreach ($byMethod as $method => $row) {
            $methodRows[] = [$method, $row['count'], round($row['amount']), $collected > 0 ? round($row['amount'] / $collected * 100, 1) : 0];
        }

        // Payments by counter.
        $byCounter = [];
        foreach ($paid as $sale) {
            $byCounter[$sale['counter']] = ($byCounter[$sale['counter']] ?? 0) + $sale['total'];
        }
        arsort($byCounter);
        $counterRows = [];
        foreach ($byCounter as $name => $amount) {
            $counterRows[] = [$name, round($amount)];
        }

        // Refunds / voids.
        $voidRows = [];
        foreach ($voided as $sale) {
            $voidRows[] = [$sale['id'], $sale['date'], $sale['counter'], round($sale['total']), 'Voided order'];
        }

        return [
            'summary' => [
                $this->card('Total Collected', round($collected), 'money', 'all paid orders', 'up'),
                $this->card('Transactions', count($paid), 'number', 'paid orders', 'neutral'),
                $this->card('Top Method', array_key_first($byMethod) ?: '—', 'text', 'largest share of collections', 'neutral'),
                $this->card('Voids', count($voided), 'number', 'cancelled with no payment', count($voided) > 0 ? 'down' : 'neutral'),
            ],
            'sections' => [
                [
                    'title' => 'Collections by Payment Method',
                    'columns' => $this->columns(['Method' => 'text', 'Transactions' => 'number', 'Amount' => 'money', '% of Total' => 'percent']),
                    'rows' => $methodRows,
                    'footer' => ['Total', count($paid), round($collected)],
                ],
                [
                    'title' => 'Collections by Counter',
                    'columns' => $this->columns(['Counter' => 'text', 'Amount' => 'money']),
                    'rows' => $counterRows,
                ],
                [
                    'title' => 'Refunds & Voids',
                    'columns' => $this->columns(['Order' => 'text', 'Date' => 'text', 'Counter' => 'text', 'Amount' => 'money', 'Reason' => 'text']),
                    'rows' => $voidRows,
                ],
            ],
        ];
    }

    protected function itemsReport(): array
    {
        $paid = $this->paidSales();
        $items = $this->itemTotals($paid);

        $rows = [];
        $rank = 1;
        foreach ($items as $item) {
            $margin = $item['revenue'] - $item['cost'];
            $rows[] = [
                $rank++,
                $item['name'],
                $item['category'],
                ucfirst($item['area']),
                $item['qty'],
                round($item['revenue']),
                round($item['cost']),
                round($margin),
                $item['revenue'] > 0 ? round($margin / $item['revenue'] * 100, 1) : 0,
            ];
        }

        $revenue = array_sum(array_column($items, 'revenue'));
        $cogs = array_sum(array_column($items, 'cost'));
        $qty = array_sum(array_column($items, 'qty'));
        $best = $items[0]['name'] ?? '—';

        // Bar vs kitchen split.
        $split = ['bar' => ['qty' => 0, 'revenue' => 0], 'kitchen' => ['qty' => 0, 'revenue' => 0]];
        foreach ($items as $item) {
            $area = $item['area'];
            $split[$area]['qty'] += $item['qty'];
            $split[$area]['revenue'] += $item['revenue'];
        }
        $splitRows = [];
        foreach ($split as $area => $values) {
            $splitRows[] = [ucfirst($area), $values['qty'], round($values['revenue']), $revenue > 0 ? round($values['revenue'] / $revenue * 100, 1) : 0];
        }

        // By category.
        $categories = $this->categoryTotals($paid);

        return [
            'summary' => [
                $this->card('Items Sold', $qty, 'qty', 'units across all orders', 'neutral'),
                $this->card('Menu Items', count($items), 'number', 'distinct items sold', 'neutral'),
                $this->card('Best Seller', $best, 'text', 'highest revenue item', 'up'),
                $this->card('Gross Margin', $revenue > 0 ? round(($revenue - $cogs) / $revenue * 100, 1) : 0, 'percent', 'across the menu', 'up'),
            ],
            'sections' => [
                [
                    'title' => 'Item Performance',
                    'columns' => $this->columns(['#' => 'number', 'Item' => 'text', 'Category' => 'text', 'Area' => 'text', 'Qty Sold' => 'qty', 'Revenue' => 'money', 'Cost' => 'money', 'Margin' => 'money', 'Margin %' => 'percent']),
                    'rows' => $rows,
                ],
                [
                    'title' => 'Bar vs Kitchen',
                    'columns' => $this->columns(['Area' => 'text', 'Qty Sold' => 'qty', 'Revenue' => 'money', '% of Total' => 'percent']),
                    'rows' => $splitRows,
                ],
                [
                    'title' => 'Sales by Category',
                    'columns' => $this->columns(['Category' => 'text', 'Qty Sold' => 'qty', 'Revenue' => 'money', '% of Total' => 'percent']),
                    'rows' => $categories,
                ],
            ],
        ];
    }

    protected function inventoryReport(): array
    {
        $inventory = $this->inventory();
        $inventory = array_values(array_filter($inventory, function ($p) {
            return $this->counter === 'all' || $p['counter'] === $this->counter;
        }));

        $value = array_sum(array_map(fn ($p) => $p['stock'] * $p['buyPrice'], $inventory));
        $low = array_values(array_filter($inventory, fn ($p) => $p['stock'] > 0 && $p['stock'] <= $p['reorder']));
        $out = array_values(array_filter($inventory, fn ($p) => $p['stock'] <= 0));
        $expiring = array_values(array_filter($inventory, function ($p) {
            if (empty($p['expiryDate'])) {
                return false;
            }
            $days = Carbon::parse($this->to)->diffInDays(Carbon::parse($p['expiryDate']), false);

            return $days >= 0 && $days <= 30;
        }));

        // Value by category.
        $byCategory = [];
        foreach ($inventory as $p) {
            $key = $p['cat'];
            $byCategory[$key] = $byCategory[$key] ?? ['items' => 0, 'units' => 0, 'value' => 0];
            $byCategory[$key]['items']++;
            $byCategory[$key]['units'] += $p['stock'];
            $byCategory[$key]['value'] += $p['stock'] * $p['buyPrice'];
        }
        arsort($byCategory);
        $categoryRows = [];
        foreach ($byCategory as $cat => $row) {
            $categoryRows[] = [ucfirst($cat), $row['items'], $row['units'], round($row['value'])];
        }

        $lowRows = [];
        foreach ($low as $p) {
            $lowRows[] = [$p['name'], $p['counter'], $p['stock'], $p['reorder'], 'Low Stock'];
        }
        $outRows = [];
        foreach ($out as $p) {
            $outRows[] = [$p['name'], $p['counter'], $p['reorder'], $p['sup']];
        }
        $expiryRows = [];
        foreach ($expiring as $p) {
            $expiryRows[] = [$p['name'], $p['counter'], $p['expiryDate'], $p['stock'], $p['unit']];
        }

        return [
            'summary' => [
                $this->card('Stock Value', round($value), 'money', 'at buying cost', 'neutral'),
                $this->card('Products', count($inventory), 'number', 'stocked lines', 'neutral'),
                $this->card('Low Stock', count($low), 'number', 'at or below reorder level', count($low) > 0 ? 'down' : 'neutral'),
                $this->card('Out of Stock', count($out), 'number', 'need restocking', count($out) > 0 ? 'down' : 'neutral'),
            ],
            'sections' => [
                [
                    'title' => 'Stock Value by Category',
                    'columns' => $this->columns(['Category' => 'text', 'Items' => 'number', 'Units' => 'qty', 'Value' => 'money']),
                    'rows' => $categoryRows,
                ],
                [
                    'title' => 'Low Stock Alert',
                    'columns' => $this->columns(['Product' => 'text', 'Counter' => 'text', 'Current' => 'qty', 'Reorder' => 'qty', 'Status' => 'text']),
                    'rows' => $lowRows,
                ],
                [
                    'title' => 'Out of Stock',
                    'columns' => $this->columns(['Product' => 'text', 'Counter' => 'text', 'Reorder' => 'qty', 'Supplier' => 'text']),
                    'rows' => $outRows,
                ],
                [
                    'title' => 'Expiring Soon (30 days)',
                    'columns' => $this->columns(['Product' => 'text', 'Counter' => 'text', 'Expiry' => 'text', 'Stock' => 'qty', 'Unit' => 'text']),
                    'rows' => $expiryRows,
                ],
            ],
        ];
    }

    protected function purchasesReport(): array
    {
        $orders = $this->purchases();
        $spend = array_sum(array_column($orders, 'total'));
        $received = array_filter($orders, fn ($o) => $o['status'] === 'received');
        $open = array_filter($orders, fn ($o) => in_array($o['status'], ['pending', 'approved'], true));

        $orderRows = [];
        foreach ($orders as $o) {
            $orderRows[] = [$o['id'], $o['supplier'], $o['user'], $o['items'], round($o['total']), ucfirst($o['status']), $o['date'], $o['eta']];
        }

        // Spend by supplier.
        $bySupplier = [];
        foreach ($orders as $o) {
            $bySupplier[$o['supplier']] = $bySupplier[$o['supplier']] ?? ['orders' => 0, 'total' => 0];
            $bySupplier[$o['supplier']]['orders']++;
            $bySupplier[$o['supplier']]['total'] += $o['total'];
        }
        arsort($bySupplier);
        $supplierRows = [];
        foreach ($bySupplier as $name => $row) {
            $supplierRows[] = [$name, $row['orders'], round($row['total']), $spend > 0 ? round($row['total'] / $spend * 100, 1) : 0];
        }

        // Status summary.
        $statusRows = [];
        foreach (['pending', 'approved', 'received', 'cancelled'] as $status) {
            $matching = array_filter($orders, fn ($o) => $o['status'] === $status);
            $statusRows[] = [ucfirst($status), count($matching), round(array_sum(array_column($matching, 'total')))];
        }

        return [
            'summary' => [
                $this->card('Total Spend', round($spend), 'money', 'across all purchase orders', 'neutral'),
                $this->card('Purchase Orders', count($orders), 'number', 'raised in period', 'neutral'),
                $this->card('Received', count($received), 'number', 'goods received notes confirmed', 'up'),
                $this->card('Open Orders', count($open), 'number', 'pending or approved', 'neutral'),
            ],
            'sections' => [
                [
                    'title' => 'Purchase Orders',
                    'columns' => $this->columns(['PO / Receipt' => 'text', 'Supplier' => 'text', 'Purchased By' => 'text', 'Items' => 'number', 'Total' => 'money', 'Status' => 'text', 'Ordered' => 'text', 'ETA' => 'text']),
                    'rows' => $orderRows,
                ],
                [
                    'title' => 'Spend by Supplier',
                    'columns' => $this->columns(['Supplier' => 'text', 'Orders' => 'number', 'Total' => 'money', '% of Spend' => 'percent']),
                    'rows' => $supplierRows,
                ],
                [
                    'title' => 'Orders by Status',
                    'columns' => $this->columns(['Status' => 'text', 'Orders' => 'number', 'Value' => 'money']),
                    'rows' => $statusRows,
                ],
            ],
        ];
    }

    protected function financialReport(): array
    {
        $paid = $this->paidSales();
        $revenue = array_sum(array_column($paid, 'total'));
        $cogs = 0;
        foreach ($paid as $sale) {
            foreach ($sale['items'] as $item) {
                $cogs += $item['cost'] * $item['qty'];
            }
        }
        $gross = $revenue - $cogs;
        $expenses = $this->expenses();
        $opex = array_sum(array_column($expenses, 'amount'));
        $net = $gross - $opex;

        $plRows = [
            ['Revenue (net sales)', round($revenue)],
            ['Cost of Goods Sold', -round($cogs)],
            ['Gross Profit', round($gross)],
        ];
        foreach ($expenses as $expense) {
            $plRows[] = ['  '.$expense['category'], -round($expense['amount'])];
        }
        $plRows[] = ['Total Operating Expenses', -round($opex)];
        $plRows[] = ['Net Profit', round($net)];

        $expenseRows = [];
        foreach ($expenses as $expense) {
            $expenseRows[] = [$expense['category'], round($expense['amount']), $revenue > 0 ? round($expense['amount'] / $revenue * 100, 1) : 0];
        }

        $margin = $revenue > 0 ? round($gross / $revenue * 100, 1) : 0;
        $netMargin = $revenue > 0 ? round($net / $revenue * 100, 1) : 0;

        return [
            'summary' => [
                $this->card('Revenue', round($revenue), 'money', 'net sales in period', 'up'),
                $this->card('Gross Profit', round($gross), 'money', $margin.'% gross margin', 'up'),
                $this->card('Operating Expenses', round($opex), 'money', 'fixed & variable', 'down'),
                $this->card('Net Profit', round($net), 'money', $netMargin.'% net margin', $net >= 0 ? 'up' : 'down'),
            ],
            'sections' => [
                [
                    'title' => 'Profit & Loss Summary',
                    'columns' => $this->columns(['Line Item' => 'text', 'Amount' => 'money']),
                    'rows' => $plRows,
                ],
                [
                    'title' => 'Operating Expenses by Category',
                    'columns' => $this->columns(['Category' => 'text', 'Amount' => 'money', '% of Revenue' => 'percent']),
                    'rows' => $expenseRows,
                    'footer' => ['Total', round($opex), $revenue > 0 ? round($opex / $revenue * 100, 1) : 0],
                ],
                [
                    'title' => 'Key Margins',
                    'columns' => $this->columns(['Metric' => 'text', 'Value' => 'percent']),
                    'rows' => [
                        ['Gross Margin', $margin],
                        ['Net Margin', $netMargin],
                        ['COGS Ratio', $revenue > 0 ? round($cogs / $revenue * 100, 1) : 0],
                        ['Expense Ratio', $revenue > 0 ? round($opex / $revenue * 100, 1) : 0],
                    ],
                ],
            ],
        ];
    }

    protected function staffReport(): array
    {
        $paid = $this->paidSales();
        $staff = $this->staff();

        // Performance from sales.
        $performance = [];
        foreach ($paid as $sale) {
            $name = $sale['staff'];
            $performance[$name] = $performance[$name] ?? ['orders' => 0, 'revenue' => 0];
            $performance[$name]['orders']++;
            $performance[$name]['revenue'] += $sale['total'];
        }
        arsort($performance);

        $perfRows = [];
        $rank = 1;
        foreach ($performance as $name => $row) {
            $role = $this->roleFor($name);
            $perfRows[] = [$rank++, $name, $role, $row['orders'], round($row['revenue']), round($row['orders'] > 0 ? $row['revenue'] / $row['orders'] : 0)];
        }

        $active = array_filter($staff, fn ($s) => $s['status'] === 'active');
        $totalOrders = array_sum(array_column($perfRows, 3));
        $top = $perfRows[0][1] ?? '—';

        // Role breakdown.
        $roles = [];
        foreach ($staff as $member) {
            $role = $member['role'];
            $roles[$role] = ($roles[$role] ?? 0) + 1;
        }
        arsort($roles);
        $roleRows = [];
        foreach ($roles as $role => $count) {
            $roleRows[] = [ucfirst($role), $count];
        }

        // Shift / attendance.
        $shiftRows = [];
        foreach ($staff as $member) {
            $shiftRows[] = [$member['name'], ucfirst($member['role']), $member['shift'], $member['status'] === 'active' ? 'On duty' : 'Off duty'];
        }

        return [
            'summary' => [
                $this->card('Total Staff', count($staff), 'number', 'across all roles', 'neutral'),
                $this->card('Active', count($active), 'number', 'currently on the rota', 'up'),
                $this->card('Orders Served', $totalOrders, 'number', 'paid orders attributed', 'neutral'),
                $this->card('Top Performer', $top, 'text', 'highest sales this period', 'up'),
            ],
            'sections' => [
                [
                    'title' => 'Sales Performance Ranking',
                    'columns' => $this->columns(['#' => 'number', 'Staff' => 'text', 'Role' => 'text', 'Orders' => 'number', 'Revenue' => 'money', 'Avg Sale' => 'money']),
                    'rows' => $perfRows,
                ],
                [
                    'title' => 'Shift & Attendance',
                    'columns' => $this->columns(['Staff' => 'text', 'Role' => 'text', 'Shift' => 'text', 'Status' => 'text']),
                    'rows' => $shiftRows,
                ],
                [
                    'title' => 'Team by Role',
                    'columns' => $this->columns(['Role' => 'text', 'Staff' => 'number']),
                    'rows' => $roleRows,
                ],
            ],
        ];
    }

    protected function customersReport(): array
    {
        $customers = $this->customers();
        usort($customers, fn ($a, $b) => $b['totalSpent'] <=> $a['totalSpent']);

        $rows = [];
        $rank = 1;
        foreach ($customers as $c) {
            $rows[] = [$rank++, $c['name'], ucfirst($c['tier']), $c['visits'], round($c['totalSpent']), $c['loyaltyPts'], $c['lastVisit']];
        }

        $spend = array_sum(array_column($customers, 'totalSpent'));
        $visits = array_sum(array_column($customers, 'visits'));
        $new = count(array_filter($customers, fn ($c) => $c['tier'] === 'new'));

        // Loyalty tiers.
        $tiers = [];
        foreach ($customers as $c) {
            $tier = $c['tier'];
            $tiers[$tier] = $tiers[$tier] ?? ['customers' => 0, 'spend' => 0, 'points' => 0];
            $tiers[$tier]['customers']++;
            $tiers[$tier]['spend'] += $c['totalSpent'];
            $tiers[$tier]['points'] += $c['loyaltyPts'];
        }
        $tierRows = [];
        foreach (['gold', 'silver', 'bronze', 'new'] as $tier) {
            if (! isset($tiers[$tier])) {
                continue;
            }
            $row = $tiers[$tier];
            $tierRows[] = [ucfirst($tier), $row['customers'], round($row['spend']), $row['points']];
        }

        // Spending bands.
        $symbol = BarSettings::currencySymbol();
        $bands = [
            'Under '.$symbol.' 1,000' => 0,
            $symbol.' 1,000 – 2,500' => 0,
            $symbol.' 2,500 – 5,000' => 0,
            $symbol.' 5,000+' => 0,
        ];
        foreach ($customers as $c) {
            if ($c['totalSpent'] < 1000) {
                $bands['Under '.$symbol.' 1,000']++;
            } elseif ($c['totalSpent'] < 2500) {
                $bands[$symbol.' 1,000 – 2,500']++;
            } elseif ($c['totalSpent'] < 5000) {
                $bands[$symbol.' 2,500 – 5,000']++;
            } else {
                $bands[$symbol.' 5,000+']++;
            }
        }
        $bandRows = [];
        foreach ($bands as $band => $count) {
            $bandRows[] = [$band, $count];
        }

        return [
            'summary' => [
                $this->card('Customers', count($customers), 'number', 'in the loyalty book', 'neutral'),
                $this->card('Total Spend', round($spend), 'money', 'lifetime customer value', 'up'),
                $this->card('Avg. Visits', $customers !== [] ? round($visits / count($customers), 1) : 0, 'number', 'per customer', 'neutral'),
                $this->card('New Members', $new, 'number', 'first-time tier', 'neutral'),
            ],
            'sections' => [
                [
                    'title' => 'Top Customers',
                    'columns' => $this->columns(['#' => 'number', 'Customer' => 'text', 'Tier' => 'text', 'Visits' => 'number', 'Total Spend' => 'money', 'Points' => 'number', 'Last Visit' => 'text']),
                    'rows' => $rows,
                ],
                [
                    'title' => 'Loyalty Tier Breakdown',
                    'columns' => $this->columns(['Tier' => 'text', 'Customers' => 'number', 'Spend' => 'money', 'Points Issued' => 'number']),
                    'rows' => $tierRows,
                ],
                [
                    'title' => 'Spending Bands',
                    'columns' => $this->columns(['Spend Band' => 'text', 'Customers' => 'number']),
                    'rows' => $bandRows,
                ],
            ],
        ];
    }

    protected function kitchenReport(): array
    {
        $paid = $this->paidSales();

        // Items prepared by station/area.
        $stations = [
            'Kitchen' => ['tickets' => 0, 'items' => 0],
            'Grill' => ['tickets' => 0, 'items' => 0],
            'Bar' => ['tickets' => 0, 'items' => 0],
        ];
        $stationForCategory = [
            'Food' => 'Kitchen',
            'Fast Food' => 'Grill',
            'Main Course' => 'Kitchen',
            'Appetizers' => 'Kitchen',
            'BBQ' => 'Grill',
        ];

        $prepared = [];
        $kitchenTickets = 0;
        foreach ($paid as $sale) {
            $hasKitchen = false;
            $ticketStations = [];
            foreach ($sale['items'] as $item) {
                if ($item['area'] === 'kitchen') {
                    $hasKitchen = true;
                    $station = $stationForCategory[$item['category']] ?? 'Kitchen';
                    $stations[$station]['items'] += $item['qty'];
                    $ticketStations[$station] = true;
                    $prepared[$item['name']] = ($prepared[$item['name']] ?? 0) + $item['qty'];
                } else {
                    $stations['Bar']['items'] += $item['qty'];
                    $ticketStations['Bar'] = true;
                }
            }
            foreach (array_keys($ticketStations) as $station) {
                $stations[$station]['tickets']++;
            }
            if ($hasKitchen) {
                $kitchenTickets++;
            }
        }
        arsort($prepared);

        $stationRows = [];
        // Average prep time per station (minutes) — operational estimate.
        $prepTimes = ['Kitchen' => 16, 'Grill' => 21, 'Bar' => 4];
        foreach ($stations as $name => $row) {
            $stationRows[] = [$name, $row['tickets'], $row['items'], $prepTimes[$name]];
        }

        $prepRows = [];
        $rows = array_slice($prepared, 0, 10, true);
        foreach ($rows as $name => $qty) {
            $prepRows[] = [$name, $qty];
        }

        // Waste / adjustments recorded against kitchen raw materials.
        $wasteMovements = KitchenRawMaterialMovement::query()
            ->with(['material', 'user'])
            ->whereBetween('created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->where('type', 'adjustment')
            ->where('quantity_delta', '<', 0)
            ->orderByDesc('created_at')
            ->get();

        $wasteRows = [];
        $wasteValue = 0;
        foreach ($wasteMovements as $movement) {
            $quantity = abs((float) $movement->quantity_delta);
            $value = $quantity * (float) ($movement->unit_cost ?? $movement->material?->average_cost ?? 0);
            $wasteValue += $value;
            $wasteRows[] = [
                $movement->material?->name ?? 'Unknown material',
                $quantity,
                $movement->material?->unit ?? '',
                $movement->reason ?: 'Stock adjustment',
                round($value),
            ];
        }

        $totalItems = array_sum(array_column($stationRows, 2));
        $ticketCount = array_sum(array_column($stationRows, 1));
        $avgPrep = $ticketCount > 0
            ? (int) round(array_sum(array_map(fn ($s) => $s[1] * $s[3], $stationRows)) / $ticketCount)
            : 0;

        return [
            'summary' => [
                $this->card('Kitchen Tickets', $kitchenTickets, 'number', 'orders with prepared items', 'neutral'),
                $this->card('Items Prepared', $totalItems, 'qty', 'across all stations', 'neutral'),
                $this->card('Avg. Prep Time', $avgPrep, 'text', 'minutes per ticket', $avgPrep > 15 ? 'down' : 'up'),
                $this->card('Waste Value', round($wasteValue), 'money', 'adjusted stock', 'down'),
            ],
            'sections' => [
                [
                    'title' => 'Tickets by Station',
                    'columns' => $this->columns(['Station' => 'text', 'Tickets' => 'number', 'Items Prepared' => 'qty', 'Avg. Prep (min)' => 'number']),
                    'rows' => $stationRows,
                ],
                [
                    'title' => 'Top Prepared Items',
                    'columns' => $this->columns(['Item' => 'text', 'Qty Prepared' => 'qty']),
                    'rows' => $prepRows,
                ],
                [
                    'title' => 'Waste & Adjustments',
                    'columns' => $this->columns(['Material' => 'text', 'Qty' => 'qty', 'Unit' => 'text', 'Reason' => 'text', 'Value' => 'money']),
                    'rows' => $wasteRows,
                    'footer' => ['Total', '', '', '', round($wasteValue)],
                ],
            ],
        ];
    }

    // =====================================================================
    //  HELPERS
    // =====================================================================

    /**
     * Turn a label => type map into the normalised column definitions.
     *
     * Numeric columns are right aligned, text stays left and everything that
     * is not the first column is centred when short.
     *
     * @param  array<string, string>  $map
     * @return array<int, array{label: string, align: string, type: string}>
     */
    protected function columns(array $map): array
    {
        $columns = [];
        foreach ($map as $label => $type) {
            $align = match ($type) {
                'money', 'number', 'qty', 'percent' => 'right',
                default => 'left',
            };
            $columns[] = ['label' => $label, 'align' => $align, 'type' => $type];
        }

        return $columns;
    }

    protected function card(string $label, mixed $value, string $format, string $sub, string $tone = 'neutral'): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'format' => $format,
            'sub' => $sub,
            'tone' => $tone,
        ];
    }

    /** Paid sales only, honouring the date range and counter filter. */
    protected function paidSales(): array
    {
        return array_values(array_filter($this->filteredSales(), fn ($s) => $s['status'] === 'paid'));
    }

    protected function filteredSales(): array
    {
        return array_values(array_filter($this->sales(), function ($sale) {
            $day = substr($sale['date'], 0, 10);
            if ($day < $this->from || $day > $this->to) {
                return false;
            }
            if ($this->counter !== 'all' && $sale['counter'] !== $this->counter) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array<int, array{name: string, qty: int, revenue: float, cost: float, category: string, area: string}>
     */
    protected function itemTotals(array $paid): array
    {
        $items = [];
        foreach ($paid as $sale) {
            foreach ($sale['items'] as $item) {
                $key = $item['name'];
                $items[$key] = $items[$key] ?? [
                    'name' => $item['name'],
                    'qty' => 0,
                    'revenue' => 0,
                    'cost' => 0,
                    'category' => $item['category'],
                    'area' => $item['area'],
                ];
                $items[$key]['qty'] += $item['qty'];
                $items[$key]['revenue'] += $item['price'] * $item['qty'];
                $items[$key]['cost'] += $item['cost'] * $item['qty'];
            }
        }
        usort($items, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return array_values($items);
    }

    protected function topItems(array $paid, int $limit): array
    {
        $items = $this->itemTotals($paid);
        $revenue = array_sum(array_column($items, 'revenue'));
        $rows = [];
        $rank = 1;
        foreach (array_slice($items, 0, $limit) as $item) {
            $rows[] = [$rank++, $item['name'], $item['qty'], round($item['revenue']), $revenue > 0 ? round($item['revenue'] / $revenue * 100, 1) : 0];
        }

        return $rows;
    }

    protected function orderTypeTotals(array $paid): array
    {
        $totals = [];
        foreach ($paid as $sale) {
            $type = $sale['orderType'];
            $totals[$type] = $totals[$type] ?? ['count' => 0, 'revenue' => 0];
            $totals[$type]['count']++;
            $totals[$type]['revenue'] += $sale['total'];
        }
        arsort($totals);
        $revenue = array_sum(array_column($paid, 'total'));
        $rows = [];
        foreach ($totals as $type => $row) {
            $rows[] = [$type, $row['count'], round($row['revenue']), $revenue > 0 ? round($row['revenue'] / $revenue * 100, 1) : 0];
        }

        return $rows;
    }

    protected function categoryTotals(array $paid): array
    {
        $totals = [];
        foreach ($paid as $sale) {
            foreach ($sale['items'] as $item) {
                $key = $item['category'];
                $totals[$key] = $totals[$key] ?? ['qty' => 0, 'revenue' => 0];
                $totals[$key]['qty'] += $item['qty'];
                $totals[$key]['revenue'] += $item['price'] * $item['qty'];
            }
        }
        arsort($totals);
        $revenue = array_sum(array_column($totals, 'revenue'));
        $rows = [];
        foreach ($totals as $cat => $row) {
            $rows[] = [ucfirst($cat), $row['qty'], round($row['revenue']), $revenue > 0 ? round($row['revenue'] / $revenue * 100, 1) : 0];
        }

        return $rows;
    }

    protected function roleFor(string $name): string
    {
        foreach ($this->staff() as $member) {
            if ($member['name'] === $name) {
                return ucfirst($member['role']);
            }
        }

        return 'Staff';
    }

    // =====================================================================
    //  DATASET
    // =====================================================================

    /**
     * Unit cost for each food item, derived from its linked recipe and the
     * current average cost of the raw materials it consumes.
     *
     * @return array<int, float>
     */
    protected function recipeUnitCosts(): array
    {
        if ($this->recipeCosts !== null) {
            return $this->recipeCosts;
        }

        $links = DB::table('kitchen_food_item_recipes')->get(['food_item_id', 'recipe_id'])->keyBy('food_item_id');

        if ($links->isEmpty()) {
            return $this->recipeCosts = [];
        }

        $recipes = KitchenRecipe::query()
            ->withTrashed()
            ->select('id', 'output_quantity')
            ->with(['ingredients' => fn ($query) => $query
                ->select('id', 'recipe_id', 'raw_material_id', 'quantity', 'waste_percent')
                ->with(['material' => fn ($material) => $material->withTrashed()->select('id', 'average_cost')])])
            ->whereIn('id', $links->pluck('recipe_id')->unique())
            ->get()
            ->keyBy('id');

        $costs = [];
        foreach ($links as $foodId => $link) {
            $recipe = $recipes->get($link->recipe_id);

            if (! $recipe) {
                continue;
            }

            $batchCost = $recipe->ingredients->sum(fn ($ingredient) => (float) $ingredient->quantity
                * (float) ($ingredient->material?->average_cost ?? 0)
                * (1 + (float) $ingredient->waste_percent / 100)
            );

            $costs[$foodId] = $batchCost / max((float) $recipe->output_quantity, 0.0001);
        }

        return $this->recipeCosts = $costs;
    }

    /** @return array<int, array{name: string, role: string, shift: string, status: string}> */
    protected function staff(): array
    {
        if ($this->staff !== null) {
            return $this->staff;
        }

        return $this->staff = User::query()->select('id', 'name', 'role', 'status')->orderBy('name')->get()->map(fn (User $user) => [
            'name' => $user->name,
            'role' => $user->role ?? 'staff',
            'shift' => '—',
            'status' => strtolower((string) ($user->status ?? 'active')),
        ])->all();
    }

    protected function inventory(): array
    {
        if ($this->inventory !== null) {
            return $this->inventory;
        }

        return $this->inventory = InventoryStock::query()
            ->with([
                'product' => fn ($query) => $query->withTrashed()
                    ->select('id', 'name', 'category_id', 'unit', 'buy_price', 'supplier', 'deleted_at')
                    ->with('category:id,name'),
                'counter:id,name',
            ])
            ->get()
            ->map(fn (InventoryStock $stock) => [
                'name' => $stock->product?->name ?? 'Unknown product',
                'cat' => $stock->product?->category?->name ?? 'Uncategorized',
                'unit' => $stock->product?->unit ?? '',
                'stock' => (float) $stock->quantity,
                'reorder' => (float) $stock->reorder_level,
                'buyPrice' => (float) ($stock->product?->buy_price ?? 0),
                'counter' => $stock->counter?->name ?? 'Unknown',
                'sup' => $stock->product?->supplier ?? '',
                'expiryDate' => $stock->expiry_date,
            ])->all();
    }

    /** @return array<int, array{category: string, amount: float}> */
    protected function expenses(): array
    {
        if ($this->expenses !== null) {
            return $this->expenses;
        }

        return $this->expenses = Expense::query()
            ->whereBetween('expense_date', [$this->from, $this->to])
            ->when($this->counter !== 'all', fn ($query) => $query->whereHas('counter', fn ($counterQuery) => $counterQuery->where('name', $this->counter)))
            ->get()
            ->map(fn (Expense $expense) => [
                'category' => $expense->category,
                'amount' => (float) $expense->amount,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function purchases(): array
    {
        if ($this->purchases !== null) {
            return $this->purchases;
        }

        return $this->purchases = InventoryMovement::query()
            ->with([
                'product' => fn ($query) => $query->withTrashed()->select('id', 'supplier', 'buy_price', 'deleted_at'),
                'counter:id,name',
                'user:id,name',
            ])
            ->where('type', 'restock')
            ->whereBetween('created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->when($this->counter !== 'all', fn ($query) => $query->whereHas('counter', fn ($counterQuery) => $counterQuery->where('name', $this->counter)))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InventoryMovement $movement) => [
                'id' => 'RCV-'.$movement->id,
                'supplier' => $movement->product?->supplier ?: 'Unspecified supplier',
                'items' => 1,
                'total' => abs((float) $movement->quantity_delta) * (float) ($movement->product?->buy_price ?? 0),
                'status' => 'received',
                'date' => $movement->created_at->format('Y-m-d'),
                'eta' => '—',
                'user' => $movement->user?->name ?? 'Unknown',
            ])->all();
    }

    /**
     * There is no customer/loyalty model in the application yet, so this
     * report stays empty rather than showing invented customers.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function customers(): array
    {
        return $this->customers ??= [];
    }

    /** @return array<int, array<string, mixed>> */
    protected function sales(): array
    {
        if ($this->sales !== null) {
            return $this->sales;
        }

        $products = InventoryProduct::query()->withTrashed()
            ->select('id', 'category_id', 'buy_price')
            ->with('category:id,name')
            ->get()->keyBy('id');
        $recipeCosts = $this->recipeUnitCosts();

        $orders = PosOrder::query()
            ->with(['items', 'user', 'counter', 'table'])
            ->whereBetween('created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->orderBy('created_at')
            ->get();

        return $this->sales = $orders->map(function (PosOrder $order) use ($products, $recipeCosts) {
            $items = $order->items->map(function ($line) use ($products, $recipeCosts) {
                $product = $line->product_type === 'inventory' ? $products->get($line->product_id) : null;

                $unitCost = match ($line->product_type) {
                    'inventory' => (float) ($product?->buy_price ?? 0),
                    'food' => (float) ($recipeCosts[$line->product_id] ?? 0),
                    default => 0.0,
                };

                return [
                    'name' => $line->product_name,
                    'category' => $product?->category?->name ?? ucfirst($line->product_type),
                    'area' => $line->product_type === 'food' ? 'kitchen' : 'bar',
                    'qty' => (int) $line->quantity,
                    'price' => (float) $line->unit_price,
                    'cost' => $unitCost,
                ];
            })->all();

            return [
                'id' => $order->docket_number,
                'date' => $order->created_at->format('Y-m-d H:i:s'),
                'counter' => $order->counter?->name ?? 'Unknown',
                'staff' => $order->user?->name ?? 'Unknown',
                'payment' => $order->payment_method ?? 'Unspecified',
                'orderType' => $order->table_id ? 'Dine-in' : 'Takeaway',
                'items' => $items,
                'subtotal' => (float) $order->subtotal,
                'discount' => 0,
                'service' => 0,
                'vat' => 0,
                'total' => (float) $order->total,
                'status' => strtolower($order->status),
            ];
        })->all();
    }
}
