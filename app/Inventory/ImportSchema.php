<?php

namespace App\Inventory;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves the configured import columns against a real spreadsheet/CSV header
 * row, so the Excel template, the Add Product form and the importer can never
 * disagree about column names or order.
 */
class ImportSchema
{
    /** @return array<int, array<string, mixed>> */
    public static function columns(): array
    {
        return config('inventory.import_columns', []);
    }

    /** @return array<int, string> */
    public static function labels(): array
    {
        return array_map(fn ($c) => $c['label'], self::columns());
    }

    /**
     * Fields applied to every imported row, chosen once in the Import dialog.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function assignments(): array
    {
        return config('inventory.import_assignments', []);
    }

    /**
     * Fields applied to every imported row without asking.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return config('inventory.import_defaults', []);
    }

    /**
     * The values the import dialog starts with.
     *
     * @return array<string, mixed>
     */
    public static function assignmentDefaults(): array
    {
        $values = self::defaults();

        foreach (self::assignments() as $assignment) {
            $values[$assignment['key']] = $assignment['default'] ?? null;
        }

        return $values;
    }

    /**
     * Example products pre-filled on the first rows of the template's entry
     * area, resolved against today's date so relative expiry offsets do not
     * go stale.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function demoRows(): array
    {
        $today = Carbon::today();

        return array_values(array_map(function (array $row) use ($today) {
            $date = $row['expiryDate'] ?? null;

            if (is_string($date) && $date !== '') {
                $row['expiryDate'] = self::resolveDemoDate($date, $today);
            }

            return $row;
        }, config('inventory.import_demo_rows', [])));
    }

    /**
     * Demo dates accept either an absolute YYYY-MM-DD string or a relative
     * offset like "+6 months" / "-3 days".
     */
    protected static function resolveDemoDate(string $value, Carbon $today): string
    {
        if (preg_match('/^[+-]/', $value)) {
            return $today->copy()->modify(ltrim($value, '+'))->toDateString();
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return $value;
        }
    }

    public static function labelToKeyMap(): array
    {
        $map = [];

        foreach (self::columns() as $column) {
            $map[self::normalize($column['label'])] = $column['key'];
        }

        return $map;
    }

    /**
     * Match a spreadsheet header row to configured columns.
     *
     * The mapping is keyed by product field and holds the *column index* in the
     * file, not a running count of matched columns. Keeping the real index is
     * what lets a file carry extra columns we do not care about (e.g. someone
     * uploading an older template that still has Category/Unit/Counter)
     * without every value shifting one place to the left.
     *
     * @param  array<int, string>  $headers
     * @return array{mapping: array<string, int>, missing: array<int, array<string, mixed>>, unknown: array<int, string>}
     */
    public static function match(array $headers): array
    {
        $lookup = self::labelToKeyMap();
        $mapping = [];
        $unknown = [];

        foreach ($headers as $index => $header) {
            $normalized = self::normalize((string) $header);

            if ($normalized === '') {
                continue;
            }

            if (isset($lookup[$normalized])) {
                $key = $lookup[$normalized];

                // First match wins — protects against duplicated headers.
                if (! array_key_exists($key, $mapping)) {
                    $mapping[$key] = $index;
                }
            } else {
                $unknown[] = (string) $header;
            }
        }

        $missing = array_values(array_filter(
            self::columns(),
            fn ($c) => ! array_key_exists($c['key'], $mapping)
        ));

        return [
            'mapping' => $mapping,
            'missing' => $missing,
            'unknown' => $unknown,
        ];
    }

    /**
     * Required columns that the file did not provide.
     *
     * @param  array<int, array<string, mixed>>  $missing
     * @return array<int, string>
     */
    public static function missingRequired(array $missing): array
    {
        return array_values(array_map(
            fn ($c) => $c['label'].($c['required'] ?? false ? ' *' : ''),
            array_filter($missing, fn ($c) => $c['required'] ?? false)
        ));
    }

    /**
     * Validate + normalise one raw spreadsheet row.
     *
     * @param  array<int, mixed>  $values
     * @param  array<string, int>  $mapping  product field => column index
     * @return array<string, mixed>
     */
    public static function validateRow(array $values, array $mapping): array
    {
        $product = self::defaults();
        $errors = [];

        foreach (ImportSchema::columns() as $column) {
            $key = $column['key'];
            $index = $mapping[$key] ?? null;

            // Not in the file at all: fall back to the fixed import default,
            // or 0 for optional numeric columns so callers can always do
            // arithmetic without null checks.
            if ($index === null) {
                $fallback = self::defaults()[$key] ?? null;

                $product[$key] = ($fallback === null && ($column['type'] ?? null) === 'number')
                    ? 0
                    : $fallback;

                continue;
            }

            $raw = $values[$index] ?? null;
            $value = self::cast($raw, $column['type'] ?? 'text');
            $product[$key] = $value;

            $label = $column['label'] ?? $key;
            $isBlank = $value === null || $value === '';
            $rawIsBlank = $raw === null || (is_string($raw) && trim($raw) === '');

            // A date that was filled in but could not be understood must not
            // quietly become blank — that would drop the expiry the user set.
            if (($column['type'] ?? null) === 'date' && ! $rawIsBlank && $value === null) {
                $errors[] = "{$label} is not a valid date (use YYYY-MM-DD)";

                continue;
            }

            if (($column['required'] ?? false) && $isBlank) {
                $errors[] = "{$label} is required";

                continue;
            }

            if ($isBlank) {
                // Optional numeric columns become 0 rather than null so that
                // downstream maths (stock vs reorder) never has to guard.
                if ($column['type'] === 'number') {
                    $product[$key] = 0;
                }

                continue;
            }

            if (in_array($column['type'] ?? null, ['number', 'decimal'], true) && $value < 0) {
                $errors[] = "{$label} cannot be negative";
            }

            if (($column['type'] ?? null) === 'select') {
                $options = $column['options'] ?? [];
                $valid = array_map(fn ($o) => Str::lower($o), $options);

                if (! in_array(Str::lower((string) $value), $valid, true)) {
                    $errors[] = "{$label} must be one of: ".implode(', ', $options);
                }
            }
        }

        $product['_errors'] = array_merge($errors, self::crossFieldErrors($product));
        $product['_warnings'] = self::crossFieldWarnings($product);

        return $product;
    }

    /**
     * Rules that compare two columns of the same row.
     *
     * These cannot be expressed per cell, so they are evaluated once the whole
     * row is cast. A row failing an `error` rule is treated as invalid and
     * skipped; a `warning` rule still imports but is surfaced to the user.
     *
     * @param  array<string, mixed>  $product
     * @return array<int, string>
     */
    public static function crossFieldMessages(array $product, string $severity): array
    {
        $messages = [];

        foreach (config('inventory.import_rules', []) as $rule) {
            if (($rule['severity'] ?? 'error') !== $severity) {
                continue;
            }

            $left = $product[$rule['left'] ?? ''] ?? null;
            $right = self::resolveRuleOperand($rule['right'] ?? '', $product);

            // A blank cell is already reported by the per-cell pass; don't
            // pile a second, confusing error on top of it. Columns that are
            // absent from the file arrive as null too, for the same reason.
            if ($left === null || $right === null || $left === '' || $right === '') {
                continue;
            }

            $violated = match ($rule['type'] ?? null) {
                'less_than' => self::isNumericPair($left, $right) && $left < $right,
                'greater_than' => self::isNumericPair($left, $right) && $left > $right,
                'date_before' => self::isDatePair($left, $right) && strcmp($left, $right) < 0,
                'date_after' => self::isDatePair($left, $right) && strcmp($left, $right) > 0,
                default => false,
            };

            if (! $violated) {
                continue;
            }

            $leftLabel = self::column($rule['left'] ?? '')['label'] ?? $rule['left'] ?? '';
            $rightLabel = ($rule['right'] ?? '') === '@today'
                ? 'today'
                : (self::column($rule['right'] ?? '')['label'] ?? $rule['right'] ?? '');

            $messages[] = strtr($rule['message'] ?? 'Invalid value', [
                '%left%' => $leftLabel,
                '%right%' => $rightLabel,
            ]);
        }

        return $messages;
    }

    /**
     * Resolve the right-hand side of a rule, which may be a column key or one
     * of the `@`-prefixed pseudo-values.
     *
     * @param  array<string, mixed>  $product
     */
    protected static function resolveRuleOperand(string $operand, array $product): mixed
    {
        return match ($operand) {
            '@today' => today()->toDateString(),
            '@now' => now()->toDateTimeString(),
            default => $product[$operand] ?? null,
        };
    }

    protected static function isNumericPair(mixed $left, mixed $right): bool
    {
        return is_numeric($left) && is_numeric($right);
    }

    /**
     * Dates are normalised to Y-m-d before rules run, so lexicographic string
     * comparison is a correct chronological comparison.
     */
    protected static function isDatePair(mixed $left, mixed $right): bool
    {
        $pattern = '/^\d{4}-\d{2}-\d{2}$/';

        return is_string($left)
            && is_string($right)
            && preg_match($pattern, $left) === 1
            && preg_match($pattern, $right) === 1;
    }

    /** @return array<int, string> */
    public static function crossFieldErrors(array $product): array
    {
        return self::crossFieldMessages($product, 'error');
    }

    /** @return array<int, string> */
    public static function crossFieldWarnings(array $product): array
    {
        return self::crossFieldMessages($product, 'warning');
    }

    public static function isValid(array $product): bool
    {
        return empty($product['_errors']);
    }

    public static function errorsFor(array $product): array
    {
        return $product['_errors'] ?? [];
    }

    public static function warningsFor(array $product): array
    {
        return $product['_warnings'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function column(string $key): array
    {
        foreach (self::columns() as $column) {
            if ($column['key'] === $key) {
                return $column;
            }
        }

        return ['key' => $key, 'label' => Str::headline($key), 'type' => 'text'];
    }

    public static function cast(mixed $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        $value = is_string($raw) ? trim($raw) : $raw;

        if ($value === '' || $value === null) {
            return $type === 'text' ? '' : null;
        }

        return match ($type) {
            'number' => is_numeric($value) ? (int) $value : null,
            'decimal' => is_numeric(str_replace(',', '', (string) $value)) ? (float) str_replace(',', '', (string) $value) : null,
            'date' => self::castDate($value),
            'select' => (string) $value,
            default => (string) $value,
        };
    }

    /**
     * Normalise anything date-shaped to Y-m-d, or null if it isn't a date.
     *
     * Excel hands real date cells over as serial day numbers ("46000"), CSV
     * exports as almost any string a person or system felt like writing. All of
     * those have to resolve to the same format or the importer would reject
     * files the user considers perfectly valid.
     */
    public static function castDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // A numeric value is an Excel serial date. The 1900 leap-year bug means
        // serials below 60 are offset by one day, which PhpSpreadsheet handles.
        if (is_int($value) || is_float($value)) {
            if (! is_numeric($value)) {
                return null;
            }

            $serial = (int) $value;

            if ($serial <= 0 || $serial > 2958465) { // 1900-01-01 .. 9999-12-31
                return null;
            }

            return self::excelSerialToDate($serial);
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        // Numeric string: treat as an Excel serial, not a year.
        if (preg_match('/^\d+(\.\d+)?$/', $text) === 1) {
            return self::excelSerialToDate((int) (float) $text);
        }

        $text = str_replace(['/', '.'], '-', $text);

        // Drop any trailing time component: "2027-12-31 18:30:00".
        $text = preg_replace('/[T ]\d{1,2}:\d{2}.*$/', '', $text) ?? $text;
        $text = trim($text);

        // YYYY-M-D and DD-M-YYYY both appear in the wild.
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m) === 1) {
            return self::ymd((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $text, $m) === 1) {
            return self::ymd((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // Month name forms: "31 Dec 2027" / "Dec 31, 2027".
        $monthNames = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

        if (preg_match('/^(\d{1,2})\s+([a-z]{3,})\.?\s+(\d{4})$/i', $text, $m) === 1) {
            $mon = array_search(Str::lower(substr($m[2], 0, 3)), $monthNames, true);

            if ($mon !== false) {
                return self::ymd((int) $m[3], $mon + 1, (int) $m[1]);
            }
        }

        if (preg_match('/^([a-z]{3,})\.?\s+(\d{1,2}),?\s+(\d{4})$/i', $text, $m) === 1) {
            $mon = array_search(Str::lower(substr($m[1], 0, 3)), $monthNames, true);

            if ($mon !== false) {
                return self::ymd((int) $m[3], $mon + 1, (int) $m[2]);
            }
        }

        return null;
    }

    /**
     * Build a Y-m-d string, rejecting dates that do not exist (e.g. 31 Feb).
     */
    protected static function ymd(int $year, int $month, int $day): ?string
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Convert an Excel serial day number to Y-m-d.
     */
    protected static function excelSerialToDate(int $serial): ?string
    {
        // 25569 is 1970-01-01 in Excel's serial calendar.
        $timestamp = ($serial - 25569) * 86400;

        if ($timestamp < 0) {
            return null;
        }

        return gmdate('Y-m-d', $timestamp);
    }

    /**
     * Case/punctuation-insensitive header matching so "Stock Quantity",
     * "stock_quantity" and "STOCKQUANTITY" all resolve.
     */
    public static function normalize(string $value): string
    {
        $value = preg_replace('/\(.*?\)/', '', $value) ?? $value; // drop "(TSh)"
        $value = Str::lower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;

        return $value;
    }
}
