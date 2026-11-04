<?php

namespace App\Http\Controllers;

use App\Models\InventoryCounter;
use App\Models\InventoryMovement;
use App\Models\InventoryProduct;
use App\Models\InventoryStock;
use App\Models\KitchenFoodItem;
use App\Models\KitchenRawMaterial;
use App\Models\KitchenRawMaterialMovement;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPaymentMode;
use App\Models\PosTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function index()
    {
        $user = request()->user();
        $assignedCounterId = $user?->isAdministrator() ? null : $user?->counter_id;
        $countersQuery = InventoryCounter::query()->orderBy('name');
        if ($assignedCounterId !== null) {
            $countersQuery->whereKey($assignedCounterId);
        }
        $counters = $countersQuery->get();

        $stocks = InventoryStock::query()
            ->select('product_id', 'counter_id', 'quantity')
            ->whereHas('product')
            ->when($assignedCounterId !== null, fn ($query) => $query->where('counter_id', $assignedCounterId))
            ->get()
            ->groupBy('product_id');

        $inventoryProductsQuery = InventoryProduct::query()
            ->select('id', 'name', 'sell_price', 'category_id', 'image')
            ->with('category:id,name,slug');
        if ($assignedCounterId !== null) {
            $inventoryProductsQuery->whereHas('stocks', fn ($query) => $query->where('counter_id', $assignedCounterId));
        }
        $inventoryProducts = $inventoryProductsQuery
            ->orderBy('name')
            ->get()
            ->map(function (InventoryProduct $product) use ($stocks) {
                $stockByCounter = [];
                foreach ($stocks->get($product->id, collect()) as $stock) {
                    $stockByCounter[(string) $stock->counter_id] = (float) $stock->quantity;
                }

                return [
                    'id' => 'inventory:'.$product->id,
                    'sourceId' => $product->id,
                    'type' => 'inventory',
                    'name' => $product->name,
                    'price' => (float) $product->sell_price,
                    'category' => 'inventory:'.$product->category->slug,
                    'categoryLabel' => $product->category->name,
                    'productionMethod' => 'stock-item',
                    'stockByCounter' => $stockByCounter,
                    'image' => $product->image ?? '',
                ];
            });

        $foodItems = KitchenFoodItem::query()
            ->select('id', 'name', 'price', 'category', 'image')
            ->where('available', true)
            ->with(['recipes:id'])
            ->orderBy('name')
            ->get()
            ->map(function (KitchenFoodItem $item) {
                $recipe = $item->recipes->first();

                return [
                    'id' => 'food:'.$item->id,
                    'sourceId' => $item->id,
                    'type' => 'food',
                    'name' => $item->name,
                    'price' => (float) $item->price,
                    'category' => 'food:'.Str::slug($item->category),
                    'categoryLabel' => $item->category,
                    'productionMethod' => 'make-to-order',
                    'stockByCounter' => [],
                    'recipeId' => $recipe?->id,
                    'image' => $item->image ?? '',
                ];
            });

        $categories = $foodItems->map(fn (array $item) => [
            'id' => $item['category'],
            'label' => $item['categoryLabel'],
        ])->concat($inventoryProducts->map(fn (array $item) => [
            'id' => $item['category'],
            'label' => $item['categoryLabel'],
        ]))->unique('id')->sortBy('label')->values();

        $recipes = \App\Models\KitchenRecipe::query()
            ->select('id', 'output_quantity')
            ->where('active', true)
            ->with(['ingredients' => fn ($query) => $query
                ->select('id', 'recipe_id', 'raw_material_id', 'quantity', 'waste_percent')
                ->with(['material' => fn ($material) => $material->select('id', 'name')])])
            ->get()
            ->map(fn ($recipe) => [
                'id' => $recipe->id,
                'outputQty' => (float) $recipe->output_quantity,
                'ingredients' => $recipe->ingredients->map(fn ($ingredient) => [
                    'materialId' => $ingredient->raw_material_id,
                    'name' => $ingredient->material?->name ?? 'Deleted material',
                    'consumptionQty' => (float) $ingredient->quantity,
                    'wastePct' => (float) $ingredient->waste_percent,
                ])->values(),
            ]);

        $rawMaterials = KitchenRawMaterial::query()->select('id', 'name', 'unit', 'quantity')->get()->map(fn ($material) => [
            'id' => $material->id,
            'name' => $material->name,
            'unit' => $material->unit,
            'currentQty' => (float) $material->quantity,
        ]);

        return view('pages.pos', [
            'activeNav' => 'pos',
            'posCounters' => $counters->map(fn (InventoryCounter $counter) => [
                'id' => $counter->id,
                'name' => $counter->name,
            ]),
            'posProducts' => $foodItems->concat($inventoryProducts)->values(),
            'posCategories' => $categories,
            'posTables' => PosTable::query()->orderBy('name')->pluck('name'),
            'posPaymentModes' => $this->serializePaymentModes(PosPaymentMode::query()->orderBy('id')->get()),
            'posRecipes' => $recipes,
            'posRawMaterials' => $rawMaterials,
        ]);
    }

    /**
     * Small live payload so POS tills see stock changes made on other devices.
     */
    public function stockLevels(Request $request): JsonResponse
    {
        $user = $request->user();
        $assignedCounterId = $user?->isAdministrator() ? null : $user?->counter_id;
        $counterId = $assignedCounterId ?? ($request->integer('counter_id') ?: null);

        $stocks = InventoryStock::query()
            ->select('product_id', 'counter_id', 'quantity')
            ->whereHas('product')
            ->when($counterId !== null, fn ($query) => $query->where('counter_id', $counterId))
            ->get()
            ->map(fn (InventoryStock $stock) => [
                'productId' => (int) $stock->product_id,
                'counterId' => (int) $stock->counter_id,
                'quantity' => (float) $stock->quantity,
            ])->values();

        $materials = KitchenRawMaterial::query()
            ->select('id', 'quantity')
            ->get()
            ->map(fn (KitchenRawMaterial $material) => [
                'id' => (int) $material->id,
                'quantity' => (float) $material->quantity,
            ])->values();

        return response()->json([
            'ok' => true,
            'stocks' => $stocks,
            'materials' => $materials,
        ]);
    }

    public function storeTable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:pos_tables,name'],
        ]);

        $table = PosTable::query()->create(['name' => trim($data['name'])]);

        return response()->json(['ok' => true, 'table' => ['id' => $table->id, 'name' => $table->name]], 201);
    }

    public function deleteTable(PosTable $table): JsonResponse
    {
        if (PosOrder::query()->where('table_id', $table->id)->exists()) {
            return response()->json(['ok' => false, 'message' => 'This table is referenced by saved dockets and cannot be removed.'], 422);
        }

        $table->delete();

        return response()->json(['ok' => true]);
    }

    public function savePaymentModes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'modes' => ['present', 'array', 'max:100'],
            'modes.*.id' => ['nullable', 'integer', 'exists:pos_payment_modes,id'],
            'modes.*.name' => ['required', 'string', 'max:120'],
            'modes.*.enabled' => ['required', 'boolean'],
            'modes.*.requiresRef' => ['required', 'boolean'],
            'modes.*.refLabel' => ['nullable', 'string', 'max:120'],
            'modes.*.icon' => ['required', 'string', 'max:20'],
        ]);

        $names = array_map(fn (array $mode) => mb_strtolower(trim($mode['name'])), $data['modes']);
        if (count($names) !== count(array_unique($names))) {
            throw ValidationException::withMessages(['modes' => 'Payment mode names must be unique.']);
        }

        $savedModes = DB::transaction(function () use ($data) {
            $savedIds = [];
            foreach ($data['modes'] as $mode) {
                $record = isset($mode['id'])
                    ? PosPaymentMode::query()->findOrFail($mode['id'])
                    : PosPaymentMode::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($mode['name']))])
                        ->first() ?? new PosPaymentMode();
                $record->fill([
                    'name' => trim($mode['name']),
                    'enabled' => $mode['enabled'],
                    'requires_reference' => $mode['requiresRef'],
                    'reference_label' => $mode['requiresRef'] ? (trim($mode['refLabel'] ?? '') ?: 'Reference') : null,
                    'icon' => $mode['icon'],
                ])->save();
                $savedIds[] = $record->id;
            }

            PosPaymentMode::query()->whereNotIn('id', $savedIds ?: [0])->delete();

            return PosPaymentMode::query()->orderBy('id')->get();
        }, 3);

        return response()->json(['ok' => true, 'modes' => $this->serializePaymentModes($savedModes)]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'counter_id' => ['required', 'integer', 'exists:inventory_counters,id'],
            'table' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_mode_id' => ['nullable', 'integer', 'exists:pos_payment_modes,id'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.type' => ['required', 'in:food,inventory'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $user = $request->user();
        if (! $user->isAdministrator() && $user->counter_id !== null && (int) $user->counter_id !== (int) $data['counter_id']) {
            throw ValidationException::withMessages([
                'counter_id' => 'You are not assigned to the selected counter.',
            ]);
        }

        if (! empty($data['payment_mode_id'])) {
            $mode = PosPaymentMode::query()->findOrFail($data['payment_mode_id']);
            if (! $mode->enabled) {
                throw ValidationException::withMessages(['payment_mode_id' => 'The selected payment mode is disabled.']);
            }
            if ($mode->requires_reference && trim($data['payment_reference'] ?? '') === '') {
                throw ValidationException::withMessages(['payment_reference' => 'A payment reference is required.']);
            }
        } else {
            $mode = null;
        }

        $table = null;
        if (filled($data['table'] ?? null) && $data['table'] !== 'Select Table') {
            $table = PosTable::query()->where('name', $data['table'])->firstOrFail();
        }

        $order = DB::transaction(function () use ($data, $request, $table, $mode) {
            InventoryCounter::query()->whereKey($data['counter_id'])->lockForUpdate()->firstOrFail();

            $order = PosOrder::query()->create([
                'docket_number' => 'TEMP-'.Str::upper(Str::random(20)),
                'user_id' => $request->user()?->id,
                'counter_id' => $data['counter_id'],
                'table_id' => $table?->id,
                'payment_mode_id' => $mode?->id,
                'payment_method' => $mode?->name,
                'payment_reference' => filled($data['payment_reference'] ?? null) ? trim($data['payment_reference']) : null,
                'status' => 'paid',
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'subtotal' => 0,
                'total' => 0,
            ]);
            $order->update([
                'docket_number' => 'D-'.$order->created_at->format('ymd').'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            ]);

            $subtotal = 0;
            foreach ($data['items'] as $line) {
                $quantity = (int) $line['quantity'];
                if ($line['type'] === 'inventory') {
                    $product = InventoryProduct::query()->whereKey($line['id'])->firstOrFail();
                    $stock = InventoryStock::query()
                        ->where('product_id', $product->id)
                        ->where('counter_id', $data['counter_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $stock || (float) $stock->quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => $product->name.' has insufficient stock at the selected counter.',
                        ]);
                    }

                    $stock->decrement('quantity', $quantity);
                    InventoryMovement::query()->create([
                        'product_id' => $product->id,
                        'counter_id' => $data['counter_id'],
                        'user_id' => $request->user()?->id,
                        'type' => 'sale',
                        'quantity_delta' => -$quantity,
                        'reason' => 'POS docket '.$order->docket_number,
                        'metadata' => ['order_id' => $order->id],
                    ]);
                    $name = $product->name;
                    $price = (float) $product->sell_price;
                } else {
                    $foodItem = KitchenFoodItem::query()
                        ->where('available', true)
                        ->with(['recipes' => fn ($query) => $query->where('active', true)->with('ingredients.material')])
                        ->whereKey($line['id'])
                        ->firstOrFail();
                    $recipe = $foodItem->recipes->first();
                    if ($recipe) {
                        $this->consumeRecipeIngredients($recipe, $quantity, $request, $order);
                    }
                    $name = $foodItem->name;
                    $price = (float) $foodItem->price;
                }

                $lineTotal = round($price * $quantity, 2);
                $subtotal += $lineTotal;
                $order->items()->create([
                    'product_type' => $line['type'],
                    'product_id' => $line['id'],
                    'product_name' => $name,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'line_total' => $lineTotal,
                ]);
            }

            $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            return $order->load(['items', 'counter', 'table', 'user']);
        }, 3);

        return response()->json(['ok' => true, 'sale' => $this->serializeDocket($order)], 201);
    }

    public function docket(Request $request, PosOrder $order): JsonResponse
    {
        $user = $request->user();
        if (! $user->isAdministrator() && $user->counter_id !== null && (int) $user->counter_id !== (int) $order->counter_id) {
            abort(403, 'You are not assigned to this counter.');
        }

        return response()->json(['ok' => true, 'sale' => $this->serializeDocket($order->load(['items', 'counter', 'table', 'user']))]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, PosPaymentMode>  $modes
     * @return array<int, array{id:int,name:string,enabled:bool,requiresRef:bool,refLabel:string,icon:string}>
     */
    private function serializePaymentModes($modes): array
    {
        return $modes->map(fn (PosPaymentMode $mode) => [
            'id' => $mode->id,
            'name' => $mode->name,
            'enabled' => (bool) $mode->enabled,
            'requiresRef' => (bool) $mode->requires_reference,
            'refLabel' => $mode->reference_label ?? '',
            'icon' => $mode->icon,
        ])->values()->all();
    }

    private function consumeRecipeIngredients($recipe, int $quantity, Request $request, PosOrder $order): void
    {
        $ingredientTotals = [];
        foreach ($recipe->ingredients as $ingredient) {
            $material = $ingredient->material;
            if (! $material || $material->trashed()) {
                throw ValidationException::withMessages(['items' => 'A recipe ingredient is no longer available.']);
            }
            $perServing = (float) $ingredient->quantity / max((float) $recipe->output_quantity, 0.0001);
            $needed = $perServing * $quantity * (1 + (float) $ingredient->waste_percent / 100);
            $ingredientTotals[$material->id] = ($ingredientTotals[$material->id] ?? 0) + $needed;
        }

        $materials = KitchenRawMaterial::query()->whereIn('id', array_keys($ingredientTotals))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($ingredientTotals as $materialId => $needed) {
            $material = $materials->get($materialId);
            if (! $material || (float) $material->quantity < $needed) {
                throw ValidationException::withMessages([
                    'items' => ($material?->name ?? 'Recipe ingredient').' has insufficient stock.',
                ]);
            }
        }

        foreach ($ingredientTotals as $materialId => $needed) {
            $material = $materials->get($materialId);
            $material->decrement('quantity', $needed);
            KitchenRawMaterialMovement::query()->create([
                'raw_material_id' => $material->id,
                'user_id' => $request->user()?->id,
                'type' => 'sale',
                'quantity_delta' => -$needed,
                'unit_cost' => $material->average_cost,
                'reason' => 'POS docket '.$order->docket_number,
                'metadata' => ['order_id' => $order->id, 'recipe_id' => $recipe->id],
            ]);
        }
    }

    private function serializeDocket(PosOrder $order): array
    {
        return [
            'id' => $order->docket_number,
            'orderId' => $order->id,
            'counter' => $order->counter?->name ?? '',
            'table' => $order->table?->name ?? 'Select Table',
            'items' => $order->items->map(fn (PosOrderItem $item) => [
                'name' => $item->product_name,
                'qty' => (int) $item->quantity,
                'price' => (float) $item->unit_price,
            ])->values(),
            'subtotal' => (float) $order->subtotal,
            'total' => (float) $order->total,
            'notes' => $order->notes ?? '',
            'paymentMethod' => $order->payment_method ?? '',
            'paymentRef' => $order->payment_reference ?? '',
            'date' => $order->created_at->toIso8601String(),
            'staff' => $order->user?->name ?? '',
        ];
    }
}
