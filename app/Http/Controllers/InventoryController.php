<?php

namespace App\Http\Controllers;

use App\Inventory\ProductImportParser;
use App\Models\InventoryCategory;
use App\Models\InventoryCounter;
use App\Models\InventoryMovement;
use App\Models\InventoryProduct;
use App\Models\InventoryStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index()
    {
        $request = request();
        $counterId = $this->assignedCounterId($request);
        $canViewValue = $request->user()?->hasPermission('inventory', 'value') ?? false;
        $categoryIcons = collect(config('inventory.categories', []))->pluck('icon', 'id');
        $categories = InventoryCategory::query()->orderBy('name')->get()
            ->map(fn (InventoryCategory $category) => [
                'id' => $category->slug,
                'databaseId' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'label' => $category->name,
                'icon' => $categoryIcons->get($category->slug, '📦'),
            ]);
        $counterTotals = DB::table('inventory_stocks')
            ->join('inventory_products', 'inventory_products.id', '=', 'inventory_stocks.product_id')
            ->whereNull('inventory_products.deleted_at')
            ->when($counterId !== null, fn ($query) => $query->where('inventory_stocks.counter_id', $counterId))
            ->selectRaw('inventory_stocks.counter_id, COUNT(DISTINCT inventory_products.id) as products, COALESCE(SUM(inventory_stocks.quantity * inventory_products.buy_price), 0) as stock_value')
            ->groupBy('inventory_stocks.counter_id')
            ->get()->keyBy('counter_id');
        $counters = InventoryCounter::query()
            ->when($counterId !== null, fn ($query) => $query->whereKey($counterId))
            ->orderBy('name')->get()->map(function (InventoryCounter $counter) use ($counterTotals, $canViewValue) {
            $totals = $counterTotals->get($counter->id);
            return [
                'id' => $counter->id,
                'name' => $counter->name,
                'products' => (int) ($totals->products ?? 0),
                'stockValue' => $canViewValue ? (float) ($totals->stock_value ?? 0) : null,
            ];
        });
        $products = InventoryStock::query()
            ->with(['product.category', 'counter'])
            ->when($counterId !== null, fn ($query) => $query->where('counter_id', $counterId))
            ->whereHas('product')
            ->orderBy('product_id')
            ->orderBy('counter_id')
            ->get()
            ->map(fn (InventoryStock $stock) => $this->serializeStock($stock));

        return view('pages.inventory', [
            'activeNav' => 'inventory',
            'inventoryProducts' => $products,
            'inventoryCategories' => $categories,
            'inventoryCounters' => $counters,
            'inventoryUnits' => \App\Models\InventoryUnit::query()->orderBy('name')->pluck('name'),
            'inventoryMovements' => InventoryMovement::query()
                ->with(['product' => fn ($query) => $query->withTrashed(), 'counter'])
                ->when($counterId !== null, fn ($query) => $query->where('counter_id', $counterId))
                ->latest()->limit(100)->get()->map(fn (InventoryMovement $movement) => [
                    'id' => $movement->id,
                    'product' => $movement->product?->name ?? 'Deleted product',
                    'counter' => $movement->counter?->name ?? '—',
                    'type' => $movement->type,
                    'qty' => (float) $movement->quantity_delta,
                    'date' => $movement->created_at->format('Y-m-d H:i'),
                    'user' => $movement->user_id ? 'User #'.$movement->user_id : 'System',
                ]),
            'importColumns' => \App\Inventory\ImportSchema::columns(),
            'importAssignments' => \App\Inventory\ImportSchema::assignments(),
            'importDefaults' => \App\Inventory\ImportSchema::defaults(),
            'importApplyDefaults' => \App\Inventory\ImportSchema::assignmentDefaults(),
            'units' => config('inventory.units'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80', 'unique:inventory_products,sku'],
            'category_id' => ['required', 'exists:inventory_categories,id'],
            'unit' => ['required', 'string', 'max:40', 'exists:inventory_units,name'],
            'counter_id' => ['required', 'exists:inventory_counters,id'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'buy_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'sell_price' => ['required', 'numeric', 'gte:buy_price', 'max:999999999999.99'],
            'expiry_date' => ['nullable', 'date'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'image' => ['nullable', 'string', 'max:3000000'],
        ]);
        $this->ensureCounterAccess($request, (int) $data['counter_id']);

        $stock = DB::transaction(function () use ($data, $request) {
            $product = InventoryProduct::query()->create([
                'category_id' => $data['category_id'],
                'name' => trim($data['name']),
                'sku' => isset($data['sku']) ? trim($data['sku']) : null,
                'unit' => trim($data['unit']),
                'buy_price' => $data['buy_price'],
                'sell_price' => $data['sell_price'],
                'supplier' => isset($data['supplier']) ? trim($data['supplier']) : null,
                'image' => $data['image'] ?? null,
            ]);
            $stock = $product->stocks()->create([
                'counter_id' => $data['counter_id'],
                'quantity' => $data['quantity'],
                'reorder_level' => $data['reorder_level'] ?? 0,
                'expiry_date' => $data['expiry_date'] ?? null,
            ]);

            if ((float) $data['quantity'] > 0) {
                $this->recordMovement($product, $stock->counter_id, 'opening', (float) $data['quantity'], 'Initial stock', $request);
            }

            return $stock->load(['product.category', 'counter']);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Product added successfully.',
            'product' => $this->serializeStock($stock),
        ], 201);
    }

    public function update(Request $request, InventoryProduct $product): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('inventory_products', 'sku')->ignore($product->id)],
            'category_id' => ['required', 'exists:inventory_categories,id'],
            'unit' => ['required', 'string', 'max:40', 'exists:inventory_units,name'],
            'buy_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'sell_price' => ['required', 'numeric', 'gte:buy_price', 'max:999999999999.99'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'image' => ['nullable', 'string', 'max:3000000'],
            'stock_id' => ['required', 'integer', Rule::exists('inventory_stocks', 'id')->where('product_id', $product->id)],
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
        ]);
        $this->ensureStockAccess($request, (int) $data['stock_id']);
        $stock = DB::transaction(function () use ($data, $product) {
            $product->update([
                'category_id' => $data['category_id'],
                'name' => trim($data['name']),
                'sku' => isset($data['sku']) ? trim($data['sku']) : null,
                'unit' => trim($data['unit']),
                'buy_price' => $data['buy_price'],
                'sell_price' => $data['sell_price'],
                'supplier' => isset($data['supplier']) ? trim($data['supplier']) : null,
                'image' => $data['image'] ?? null,
            ]);
            $stock = InventoryStock::query()->with('product')->whereKey($data['stock_id'])->lockForUpdate()->firstOrFail();
            $stock->update(['reorder_level' => $data['reorder_level']]);

            return $stock->fresh(['product.category', 'counter']);
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Product updated successfully.',
            'product' => $this->serializeStock($stock),
        ]);
    }

    public function destroy(InventoryProduct $product): JsonResponse
    {
        $this->ensureProductAccess(request(), $product);
        $product->delete();

        return response()->json(['ok' => true, 'message' => 'Product moved to trash.']);
    }

    public function restoreProduct(int $product): JsonResponse
    {
        $record = InventoryProduct::query()->withTrashed()->findOrFail($product);
        $this->ensureProductAccess(request(), $record);
        if (! $record->trashed()) {
            return response()->json(['ok' => false, 'message' => 'This product is already active.'], 422);
        }
        $record->restore();

        return response()->json(['ok' => true, 'message' => 'Product restored.']);
    }

    public function permanentlyDeleteProduct(int $product): JsonResponse
    {
        $record = InventoryProduct::query()->withTrashed()->findOrFail($product);
        $this->ensureProductAccess(request(), $record);
        if ($record->movements()->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'This product has stock movement history and cannot be permanently deleted without losing the audit trail.',
            ], 422);
        }
        $record->forceDelete();

        return response()->json(['ok' => true, 'message' => 'Product permanently deleted.']);
    }

    public function adjust(Request $request, InventoryStock $stock): JsonResponse
    {
        $this->ensureStockAccess($request, $stock->id);
        $data = $request->validate([
            'mode' => ['required', Rule::in(['add', 'remove', 'set'])],
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $updated = DB::transaction(function () use ($request, $data, $stock) {
            $locked = InventoryStock::query()->whereHas('product')->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $before = (float) $locked->quantity;
            $entered = (float) $data['quantity'];
            $after = match ($data['mode']) {
                'add' => $before + $entered,
                'remove' => $before - $entered,
                default => $entered,
            };
            if ($after < 0) {
                abort(422, 'The adjustment cannot reduce stock below zero.');
            }
            if ($after === $before) {
                abort(422, 'The adjustment does not change the current stock.');
            }

            $locked->update(['quantity' => $after]);
            $this->recordMovement(
                $locked->product,
                $locked->counter_id,
                'adjustment',
                $after - $before,
                trim($data['reason']),
                $request
            );

            return $locked->fresh(['product.category', 'counter']);
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Stock adjusted successfully.',
            'product' => $this->serializeStock($updated),
        ]);
    }

    public function updateStock(Request $request, InventoryStock $stock): JsonResponse
    {
        $this->ensureStockAccess($request, $stock->id);
        $data = $request->validate([
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
        ]);

        $updated = DB::transaction(function () use ($data, $stock) {
            $locked = InventoryStock::query()->with('product')->whereHas('product')->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $locked->update(['reorder_level' => $data['reorder_level']]);

            return $locked->fresh(['product.category', 'counter']);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Product stock settings updated.',
            'product' => $this->serializeStock($updated),
        ]);
    }

    public function adjustBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.stock_id' => ['required', 'integer', 'distinct', 'exists:inventory_stocks,id'],
            'items.*.mode' => ['required', Rule::in(['set', 'remove'])],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
            'items.*.reason' => ['required', 'string', 'max:255'],
        ]);

        $updated = DB::transaction(function () use ($request, $data) {
            $ids = [];
            foreach ($data['items'] as $line) {
                $stock = InventoryStock::query()->with('product')->whereHas('product')->whereKey($line['stock_id'])->lockForUpdate()->firstOrFail();
                $this->ensureCounterAccess($request, (int) $stock->counter_id);
                $before = (float) $stock->quantity;
                $quantity = (float) $line['quantity'];
                $after = $line['mode'] === 'set' ? $quantity : $before - $quantity;
                if ($after < 0) {
                    abort(422, "Adjustment for {$stock->product->name} cannot reduce stock below zero.");
                }
                if ($after === $before) {
                    continue;
                }
                $stock->quantity = $after;
                $stock->save();
                $this->recordMovement($stock->product, $stock->counter_id, 'adjustment', $after - $before, $line['reason'], $request);
                $ids[] = $stock->id;
            }

            return InventoryStock::query()->with(['product.category', 'counter'])->whereIn('id', $ids)->get();
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => count($updated).' product stock adjustment'.(count($updated) === 1 ? '' : 's').' saved.',
            'products' => $updated->map(fn (InventoryStock $stock) => $this->serializeStock($stock)),
        ]);
    }

    public function restock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.stock_id' => ['required', 'integer', 'distinct', 'exists:inventory_stocks,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999.999'],
            'items.*.buy_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.sell_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ]);

        foreach ($data['items'] as $line) {
            $this->ensureStockAccess($request, (int) $line['stock_id']);
        }

        $stocks = DB::transaction(function () use ($request, $data) {
            $updated = [];
            foreach ($data['items'] as $line) {
                $stock = InventoryStock::query()->with('product')->whereHas('product')->whereKey($line['stock_id'])->lockForUpdate()->firstOrFail();
                $this->ensureCounterAccess($request, (int) $stock->counter_id);
                $quantity = (float) $line['quantity'];
                $buyPrice = array_key_exists('buy_price', $line) ? (float) $line['buy_price'] : (float) $stock->product->buy_price;
                $sellPrice = array_key_exists('sell_price', $line) ? (float) $line['sell_price'] : (float) $stock->product->sell_price;
                if ($sellPrice < $buyPrice) {
                    abort(422, "Selling price for {$stock->product->name} cannot be lower than its buying price.");
                }
                if (isset($line['buy_price'])) {
                    $stock->product->buy_price = $line['buy_price'];
                }
                if (isset($line['sell_price'])) {
                    $stock->product->sell_price = $line['sell_price'];
                }
                if (isset($line['buy_price']) || isset($line['sell_price'])) {
                    $stock->product->save();
                }
                $stock->quantity = (float) $stock->quantity + $quantity;
                if (array_key_exists('expiry_date', $line) && $line['expiry_date']) {
                    $stock->expiry_date = $line['expiry_date'];
                }
                $stock->save();
                $this->recordMovement($stock->product, $stock->counter_id, 'restock', $quantity, 'Restock', $request);
                $updated[] = $stock->id;
            }

            return InventoryStock::query()->with(['product.category', 'counter'])->whereIn('id', $updated)->get();
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Stock restocked successfully.',
            'products' => $stocks->map(fn (InventoryStock $stock) => $this->serializeStock($stock)),
        ]);
    }

    public function transfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to_counter_id' => ['required', 'exists:inventory_counters,id'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.stock_id' => ['required', 'integer', 'distinct', 'exists:inventory_stocks,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999.999'],
        ]);
        $this->ensureCounterAccess($request, (int) $data['to_counter_id']);
        $transferId = (string) Str::uuid();

        DB::transaction(function () use ($data, $request, $transferId) {
            foreach ($data['items'] as $line) {
                $source = InventoryStock::query()->with('product')->whereHas('product')->whereKey($line['stock_id'])->lockForUpdate()->firstOrFail();
                $this->ensureCounterAccess($request, (int) $source->counter_id);
                $quantity = (float) $line['quantity'];
                if ($source->counter_id === (int) $data['to_counter_id']) {
                    abort(422, 'Choose a destination counter different from the source.');
                }
                if ((float) $source->quantity < $quantity) {
                    abort(422, "Not enough {$source->product->name} in the source counter.");
                }

                $source->quantity = (float) $source->quantity - $quantity;
                $source->save();
                $destination = InventoryStock::query()->whereHas('product')->where('product_id', $source->product_id)
                    ->where('counter_id', $data['to_counter_id'])->lockForUpdate()->first();
                if ($destination) {
                    $destination->quantity = (float) $destination->quantity + $quantity;
                    $destination->save();
                } else {
                    $destination = $source->replicate();
                    $destination->counter_id = $data['to_counter_id'];
                    $destination->quantity = $quantity;
                    $destination->save();
                }

                $metadata = ['transfer_id' => $transferId];
                $this->recordMovement($source->product, $source->counter_id, 'transfer_out', -$quantity, null, $request, $metadata, $data['to_counter_id']);
                $this->recordMovement($source->product, $data['to_counter_id'], 'transfer_in', $quantity, null, $request, $metadata, $source->counter_id);
            }
        }, 3);

        return response()->json(['ok' => true, 'message' => 'Stock transfer completed.']);
    }

    public function import(Request $request, ProductImportParser $parser): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('inventory.import.max_size_kb'), 'mimes:xlsx,xls,csv,txt'],
            'category_id' => ['required', 'exists:inventory_categories,id'],
            'counter_id' => ['required', 'exists:inventory_counters,id'],
        ]);
        $this->ensureCounterAccess($request, (int) $data['counter_id']);
        $parsed = $parser->parse($request->file('file'));
        if (! $parsed['ok']) {
            return response()->json(['ok' => false, 'message' => $parsed['error'] ?? 'The file could not be read.'], 422);
        }

        $validRows = array_values(array_filter($parsed['products'], fn (array $row) => $row['_valid']));
        if ($validRows === []) {
            return response()->json(['ok' => false, 'message' => 'There are no valid rows to import.'], 422);
        }

        DB::transaction(function () use ($validRows, $data, $request) {
            foreach ($validRows as $row) {
                $product = InventoryProduct::query()->create([
                    'category_id' => $data['category_id'],
                    'name' => trim($row['name']),
                    'sku' => $row['sku'] ?? null,
                    'unit' => $row['unit'] ?? config('inventory.import_defaults.unit', 'Units'),
                    'buy_price' => $row['buyPrice'] ?? 0,
                    'sell_price' => $row['sellPrice'] ?? 0,
                    'supplier' => $row['sup'] ?? null,
                ]);
                $stock = $product->stocks()->create([
                    'counter_id' => $data['counter_id'],
                    'quantity' => $row['stock'] ?? 0,
                    'reorder_level' => $row['reorder'] ?? 0,
                    'expiry_date' => $row['expiryDate'] ?? null,
                ]);
                if ((float) $stock->quantity > 0) {
                    $this->recordMovement($product, $stock->counter_id, 'opening', (float) $stock->quantity, 'Spreadsheet import', $request);
                }
            }
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => count($validRows).' product'.(count($validRows) === 1 ? '' : 's').' imported successfully.',
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:inventory_categories,name']]);
        $slug = $this->uniqueSlug(InventoryCategory::class, $data['name']);
        $category = InventoryCategory::query()->create(['name' => trim($data['name']), 'slug' => $slug]);

        return response()->json(['ok' => true, 'message' => 'Inventory category added.', 'category' => $this->serializeCategory($category)], 201);
    }

    public function destroyCategory(InventoryCategory $category): JsonResponse
    {
        if ($category->products()->withTrashed()->exists()) {
            return response()->json(['ok' => false, 'message' => 'This category is assigned to products. Reassign them before deleting it.'], 422);
        }
        $category->delete();

        return response()->json(['ok' => true, 'message' => 'Inventory category deleted.']);
    }

    public function storeCounter(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:inventory_counters,name']]);
        $slug = $this->uniqueSlug(InventoryCounter::class, $data['name']);
        $counter = InventoryCounter::query()->create(['name' => trim($data['name']), 'slug' => $slug]);

        return response()->json(['ok' => true, 'message' => 'Counter added.', 'counter' => [
            'id' => $counter->id, 'name' => $counter->name, 'products' => 0, 'stockValue' => 0,
        ]], 201);
    }

    public function destroyCounter(InventoryCounter $counter): JsonResponse
    {
        if ($counter->stocks()->exists()) {
            return response()->json(['ok' => false, 'message' => 'This counter has stock records. Transfer or remove its stock before deleting it.'], 422);
        }
        $counter->delete();

        return response()->json(['ok' => true, 'message' => 'Counter deleted.']);
    }

    public function storeUnit(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:40', 'unique:inventory_units,name']]);
        $unit = \App\Models\InventoryUnit::query()->create(['name' => trim($data['name'])]);

        return response()->json(['ok' => true, 'message' => 'Unit added.', 'unit' => $unit->name], 201);
    }

    public function destroyUnit(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'exists:inventory_units,name']]);
        if (InventoryProduct::query()->withTrashed()->where('unit', $data['name'])->exists()) {
            return response()->json(['ok' => false, 'message' => 'This unit is used by products and cannot be deleted.'], 422);
        }
        \App\Models\InventoryUnit::query()->where('name', $data['name'])->delete();

        return response()->json(['ok' => true, 'message' => 'Unit deleted.']);
    }

    private function serializeStock(InventoryStock $stock): array
    {
        $product = $stock->product;

        return [
            'id' => $stock->id,
            'productId' => $product->id,
            'dateAdded' => $stock->created_at?->toDateString(),
            'name' => $product->name,
            'sku' => $product->sku,
            'cat' => $product->category->slug,
            'categoryId' => $product->category_id,
            'unit' => $product->unit,
            'stock' => (float) $stock->quantity,
            'reorder' => (float) $stock->reorder_level,
            'buyPrice' => (float) $product->buy_price,
            'sellPrice' => (float) $product->sell_price,
            'counter' => $stock->counter->name,
            'counterId' => $stock->counter_id,
            'expiryDate' => $stock->expiry_date?->format('Y-m-d'),
            'sup' => $product->supplier ?? '',
            'image' => $product->image ?? '',
        ];
    }

    private function assignedCounterId(Request $request): ?int
    {
        $user = $request->user();

        return $user && ! $user->isAdministrator() && $user->counter_id
            ? (int) $user->counter_id
            : null;
    }

    private function ensureCounterAccess(Request $request, int $counterId): void
    {
        $assignedCounterId = $this->assignedCounterId($request);
        if ($assignedCounterId !== null && $assignedCounterId !== $counterId) {
            abort(403, 'You do not have access to this counter.');
        }
    }

    private function ensureStockAccess(Request $request, int $stockId): void
    {
        $stock = InventoryStock::query()->findOrFail($stockId);
        $this->ensureCounterAccess($request, (int) $stock->counter_id);
    }

    private function ensureProductAccess(Request $request, InventoryProduct $product): void
    {
        $assignedCounterId = $this->assignedCounterId($request);
        if ($assignedCounterId !== null && ! $product->stocks()->where('counter_id', $assignedCounterId)->exists()) {
            abort(404);
        }
    }

    private function serializeCategory(InventoryCategory $category): array
    {
        $icons = collect(config('inventory.categories', []))->pluck('icon', 'id');

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'label' => $category->name,
            'icon' => $icons->get($category->slug, '📦'),
        ];
    }

    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base ?: 'item';
        $suffix = 2;
        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = ($base ?: 'item').'-'.$suffix++;
        }

        return $slug;
    }

    private function recordMovement(
        InventoryProduct $product,
        ?int $counterId,
        string $type,
        float $quantityDelta,
        ?string $reason,
        Request $request,
        ?array $metadata = null,
        ?int $otherCounterId = null,
    ): void {
        InventoryMovement::query()->create([
            'product_id' => $product->id,
            'counter_id' => $counterId,
            'from_counter_id' => $type === 'transfer_out' ? $counterId : ($type === 'transfer_in' ? $otherCounterId : null),
            'to_counter_id' => $type === 'transfer_in' ? $counterId : ($type === 'transfer_out' ? $otherCounterId : null),
            'user_id' => $request->user()?->id,
            'type' => $type,
            'quantity_delta' => $quantityDelta,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
