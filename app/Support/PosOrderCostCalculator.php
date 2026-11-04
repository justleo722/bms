<?php

namespace App\Support;

use App\Models\InventoryProduct;
use App\Models\KitchenRecipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PosOrderCostCalculator
{
    /**
     * Estimate each order's cost using the current inventory and recipe costs.
     *
     * @return array<int, float>
     */
    public function forOrders(Collection $orders): array
    {
        $items = $orders->flatMap->items;
        $inventoryProducts = InventoryProduct::withTrashed()
            ->select('id', 'buy_price')
            ->whereIn('id', $items->where('product_type', 'inventory')->pluck('product_id')->unique())
            ->get()->keyBy('id');
        $foodIds = $items->where('product_type', 'food')->pluck('product_id')->unique();
        $links = DB::table('kitchen_food_item_recipes')
            ->whereIn('food_item_id', $foodIds)
            ->get(['food_item_id', 'recipe_id'])
            ->keyBy('food_item_id');
        $recipes = KitchenRecipe::withTrashed()
            ->select('id', 'output_quantity')
            ->with(['ingredients' => fn ($query) => $query
                ->select('id', 'recipe_id', 'raw_material_id', 'quantity', 'waste_percent')
                ->with(['material' => fn ($material) => $material->withTrashed()->select('id', 'average_cost')])])
            ->whereIn('id', $links->pluck('recipe_id')->unique())
            ->get()
            ->keyBy('id');

        $costs = [];
        foreach ($orders as $order) {
            $cost = 0.0;
            foreach ($order->items as $item) {
                if ($item->product_type === 'inventory') {
                    $cost += (float) ($inventoryProducts->get($item->product_id)?->buy_price ?? 0) * $item->quantity;
                    continue;
                }

                $recipeId = $links->get($item->product_id)?->recipe_id;
                $recipe = $recipeId ? $recipes->get($recipeId) : null;
                if ($recipe) {
                    $recipeCost = $recipe->ingredients->sum(fn ($ingredient) =>
                        (float) $ingredient->quantity
                        * (float) ($ingredient->material?->average_cost ?? 0)
                        * (1 + (float) $ingredient->waste_percent / 100)
                    );
                    $cost += $recipeCost / max((float) $recipe->output_quantity, 0.0001) * $item->quantity;
                }
            }
            $costs[$order->id] = $cost;
        }

        return $costs;
    }
}
