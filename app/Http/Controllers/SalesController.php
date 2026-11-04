<?php

namespace App\Http\Controllers;

use App\Models\InventoryCounter;
use App\Models\InventoryMovement;
use App\Models\InventoryProduct;
use App\Models\InventoryStock;
use App\Models\KitchenRawMaterial;
use App\Models\KitchenRawMaterialMovement;
use App\Models\PosOrder;
use App\Models\PosPaymentMode;
use App\Models\PosTable;
use App\Support\PosOrderCostCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();
        $ordersQuery = PosOrder::query();
        if (! $request->user()->hasPermission('sales', 'viewOthers')) {
            $ordersQuery->where('user_id', $request->user()->id);
        }
        $orders = $ordersQuery->with([
            'items:id,order_id,product_name,product_id,product_type,quantity,unit_price',
            'counter:id,name',
            'table:id,name',
            'user:id,name',
        ])
            ->latest('created_at')->get();
        $costs = $this->costsByOrder($orders);
        $todayOrders = $orders->filter(
            fn (PosOrder $order) => $order->status === 'paid' && $order->created_at->toDateString() === $today
        );

        return view('pages.sales', [
            'activeNav' => 'sales',
            'sales' => $orders->map(fn (PosOrder $order) => [
                'id' => $order->docket_number,
                'orderId' => $order->id,
                'date' => $order->created_at->toIso8601String(),
                'counter' => $order->counter?->name ?? '',
                'staff' => $order->user?->name ?? '',
                'table' => $order->table?->name ?? 'Takeaway',
                'tableId' => $order->table_id,
                'status' => $order->status,
                'subtotal' => (float) $order->subtotal,
                'total' => (float) $order->total,
                'cost' => $costs[$order->id] ?? 0,
                'tableId' => $order->table_id,
                'paymentMethod' => $order->payment_method ?? '',
                'paymentRef' => $order->payment_reference ?? '',
                'notes' => $order->notes ?? '',
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->product_name,
                    'productId' => $item->product_id,
                    'type' => $item->product_type,
                    'qty' => (int) $item->quantity,
                    'price' => (float) $item->unit_price,
                ])->values(),
            ])->values(),
            'salesToday' => (float) $todayOrders->sum('total'),
            'salesTodayCount' => $todayOrders->count(),
            'salesTodayProfit' => $todayOrders->sum('total')
                - $todayOrders->sum(fn (PosOrder $order) => $costs[$order->id] ?? 0),
            'salesTodayDate' => $today,
            'salesCounters' => InventoryCounter::query()->orderBy('name')->pluck('name'),
            'salesAttendants' => $orders->pluck('user.name')->filter()->unique()->sort()->values(),
            'salesTables' => PosTable::query()->orderBy('name')->get(['id', 'name']),
            'salesProducts' => $this->editableProducts(),
            'salesPaymentModes' => PosPaymentMode::query()->where('enabled', true)->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, PosOrder $order): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['nullable', 'integer', 'exists:pos_tables,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_mode_id' => ['nullable', 'integer', 'exists:pos_payment_modes,id'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.type' => ['required', 'in:food,inventory'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $mode = ! empty($data['payment_mode_id'])
            ? PosPaymentMode::query()->where('enabled', true)->findOrFail($data['payment_mode_id'])
            : null;

        $updated = DB::transaction(function () use ($data, $request, $order, $mode) {
            $lockedOrder = PosOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            $this->reverseStockMovements($lockedOrder, $request);
            $lockedOrder->items()->delete();
            $subtotal = $this->applyEditedItems($lockedOrder, $data['items'], $request);
            $lockedOrder->update([
                'table_id' => $data['table_id'] ?? null,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'payment_mode_id' => $mode?->id,
                'payment_method' => $mode?->name,
                'payment_reference' => filled($data['payment_reference'] ?? null) ? trim($data['payment_reference']) : null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            return $lockedOrder->fresh(['items', 'counter', 'table', 'user']);
        }, 3);

        return response()->json([
            'ok' => true,
            'message' => 'Sale updated successfully.',
            'sale' => $this->serializeSale($updated),
        ]);
    }

    public function backdate(Request $request, PosOrder $order): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        DB::transaction(function () use ($data, $order) {
            $locked = PosOrder::query()->lockForUpdate()->findOrFail($order->id);
            $locked->created_at = $locked->created_at->setDate(
                (int) substr($data['date'], 0, 4),
                (int) substr($data['date'], 5, 2),
                (int) substr($data['date'], 8, 2),
            );
            $locked->save();
        });

        return response()->json(['ok' => true, 'message' => 'Sale date updated.']);
    }

    public function destroy(Request $request, PosOrder $order): JsonResponse
    {
        DB::transaction(function () use ($request, $order) {
            $lockedOrder = PosOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            $this->reverseStockMovements($lockedOrder, $request);
            $lockedOrder->delete();
        }, 3);

        return response()->json(['ok' => true, 'message' => 'Sale deleted and stock returned.']);
    }

    public function destroyMany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['required', 'integer', 'distinct', 'exists:pos_orders,id'],
        ]);

        DB::transaction(function () use ($request, $data) {
            $orders = PosOrder::query()
                ->whereIn('id', $data['order_ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->with('items')
                ->get();
            foreach ($orders as $order) {
                $this->reverseStockMovements($order, $request);
                $order->delete();
            }
        }, 3);

        return response()->json(['ok' => true, 'message' => 'Selected sales deleted and stock returned.']);
    }

    private function reverseStockMovements(PosOrder $order, Request $request): void
    {
        $inventoryMovements = InventoryMovement::query()
            ->where('type', 'sale')
            ->where('metadata->order_id', $order->id)
            ->whereNull('metadata->reversed_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        foreach ($inventoryMovements as $movement) {
            $stock = InventoryStock::query()
                ->where('product_id', $movement->product_id)
                ->where('counter_id', $movement->counter_id)
                ->lockForUpdate()
                ->first();
            if (! $stock) {
                throw ValidationException::withMessages(['sale' => 'Stock record for '.$movement->product?->name.' is missing; sale cannot be safely reversed.']);
            }
            $returned = abs((float) $movement->quantity_delta);
            $stock->increment('quantity', $returned);
            InventoryMovement::query()->create([
                'product_id' => $movement->product_id,
                'counter_id' => $movement->counter_id,
                'user_id' => $request->user()?->id,
                'type' => 'sale_return',
                'quantity_delta' => $returned,
                'reason' => 'Reversal of sale '.$order->docket_number,
                'metadata' => ['order_id' => $order->id, 'reversal_of_movement_id' => $movement->id],
            ]);
            $movement->update(['metadata' => array_merge($movement->metadata ?? [], ['reversed_at' => now()->toIso8601String()])]);
        }

        $materialMovements = KitchenRawMaterialMovement::query()
            ->where('type', 'sale')
            ->where('metadata->order_id', $order->id)
            ->whereNull('metadata->reversed_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        foreach ($materialMovements as $movement) {
            $material = KitchenRawMaterial::withTrashed()->lockForUpdate()->find($movement->raw_material_id);
            if (! $material) {
                throw ValidationException::withMessages(['sale' => 'A recipe material for this sale is missing; sale cannot be safely reversed.']);
            }
            $returned = abs((float) $movement->quantity_delta);
            $material->increment('quantity', $returned);
            KitchenRawMaterialMovement::query()->create([
                'raw_material_id' => $material->id,
                'user_id' => $request->user()?->id,
                'type' => 'sale_return',
                'quantity_delta' => $returned,
                'unit_cost' => $movement->unit_cost,
                'reason' => 'Reversal of sale '.$order->docket_number,
                'metadata' => ['order_id' => $order->id, 'reversal_of_movement_id' => $movement->id],
            ]);
            $movement->update(['metadata' => array_merge($movement->metadata ?? [], ['reversed_at' => now()->toIso8601String()])]);
        }
    }

    private function applyEditedItems(PosOrder $order, array $items, Request $request): float
    {
        $subtotal = 0.0;
        foreach ($items as $line) {
            $quantity = (int) $line['quantity'];
            if ($line['type'] === 'inventory') {
                $product = InventoryProduct::query()->findOrFail($line['id']);
                $stock = InventoryStock::query()
                    ->where('product_id', $product->id)
                    ->where('counter_id', $order->counter_id)
                    ->lockForUpdate()
                    ->first();
                if (! $stock || (float) $stock->quantity < $quantity) {
                    throw ValidationException::withMessages(['items' => ($product->name).' has insufficient stock at this counter.']);
                }
                $stock->decrement('quantity', $quantity);
                InventoryMovement::query()->create([
                    'product_id' => $product->id,
                    'counter_id' => $order->counter_id,
                    'user_id' => $request->user()?->id,
                    'type' => 'sale',
                    'quantity_delta' => -$quantity,
                    'reason' => 'POS docket '.$order->docket_number,
                    'metadata' => ['order_id' => $order->id],
                ]);
                $name = $product->name;
                $price = (float) $product->sell_price;
            } else {
                $foodItem = \App\Models\KitchenFoodItem::query()
                    ->where('available', true)
                    ->with(['recipes' => fn ($query) => $query->where('active', true)->with('ingredients.material')])
                    ->findOrFail($line['id']);
                $recipe = $foodItem->recipes->first();
                if ($recipe) {
                    $this->consumeRecipe($recipe, $quantity, $order, $request);
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

        return $subtotal;
    }

    private function consumeRecipe($recipe, int $quantity, PosOrder $order, Request $request): void
    {
        $neededByMaterial = [];
        foreach ($recipe->ingredients as $ingredient) {
            $material = $ingredient->material;
            if (! $material || $material->trashed()) {
                throw ValidationException::withMessages(['items' => 'A recipe ingredient is no longer available.']);
            }
            $neededByMaterial[$material->id] = ($neededByMaterial[$material->id] ?? 0)
                + ((float) $ingredient->quantity / max((float) $recipe->output_quantity, 0.0001))
                * $quantity * (1 + (float) $ingredient->waste_percent / 100);
        }
        $materials = KitchenRawMaterial::query()
            ->whereIn('id', array_keys($neededByMaterial))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($neededByMaterial as $id => $needed) {
            if (! $materials->has($id) || (float) $materials->get($id)->quantity < $needed) {
                throw ValidationException::withMessages(['items' => ($materials->get($id)?->name ?? 'Recipe ingredient').' has insufficient stock.']);
            }
        }
        foreach ($neededByMaterial as $id => $needed) {
            $material = $materials->get($id);
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

    private function editableProducts(): array
    {
        return InventoryProduct::query()->select('id', 'name', 'sell_price')->orderBy('name')->get()->map(fn ($product) => [
            'id' => $product->id,
            'type' => 'inventory',
            'name' => $product->name,
            'price' => (float) $product->sell_price,
        ])->concat(\App\Models\KitchenFoodItem::query()->select('id', 'name', 'price')->where('available', true)->orderBy('name')->get()->map(fn ($item) => [
            'id' => $item->id,
            'type' => 'food',
            'name' => $item->name,
            'price' => (float) $item->price,
        ]))->values()->all();
    }

    private function serializeSale(PosOrder $order): array
    {
        return [
            'id' => $order->docket_number,
            'orderId' => $order->id,
            'date' => $order->created_at->toIso8601String(),
            'counter' => $order->counter?->name ?? '',
            'staff' => $order->user?->name ?? '',
            'table' => $order->table?->name ?? 'Takeaway',
            'tableId' => $order->table_id,
            'status' => $order->status,
            'subtotal' => (float) $order->subtotal,
            'total' => (float) $order->total,
            'paymentMethod' => $order->payment_method ?? '',
            'paymentRef' => $order->payment_reference ?? '',
            'notes' => $order->notes ?? '',
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'productId' => $item->product_id,
                'type' => $item->product_type,
                'qty' => (int) $item->quantity,
                'price' => (float) $item->unit_price,
            ])->values(),
        ];
    }

    private function costsByOrder($orders): array
    {
        return (new PosOrderCostCalculator())->forOrders($orders);
    }
}
