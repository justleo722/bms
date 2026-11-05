<?php

namespace App\Http\Controllers;

use App\Kitchen\RawMaterialImportTemplate;
use App\Models\KitchenFoodItem;
use App\Models\KitchenRawMaterial;
use App\Models\KitchenRawMaterialCategory;
use App\Models\KitchenRawMaterialMovement;
use App\Models\KitchenRecipe;
use App\Models\KitchenRecipeIngredient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class KitchenController extends Controller
{
    public function index()
    {
        $topUsedIngredients = KitchenRecipeIngredient::query()
            ->join('kitchen_recipes', 'kitchen_recipes.id', '=', 'kitchen_recipe_ingredients.recipe_id')
            ->join('kitchen_raw_materials', 'kitchen_raw_materials.id', '=', 'kitchen_recipe_ingredients.raw_material_id')
            ->whereNull('kitchen_recipes.deleted_at')
            ->whereNull('kitchen_raw_materials.deleted_at')
            ->select([
                'kitchen_raw_materials.name',
                'kitchen_recipe_ingredients.unit',
                DB::raw('SUM(kitchen_recipe_ingredients.quantity) as used'),
            ])
            ->groupBy('kitchen_raw_materials.id', 'kitchen_raw_materials.name', 'kitchen_recipe_ingredients.unit')
            ->orderByDesc('used')
            ->limit(5)
            ->get()
            ->map(fn ($ingredient) => [
                'name' => $ingredient->name,
                'used' => (float) $ingredient->used,
                'unit' => $ingredient->unit,
            ]);

        return view('pages.kitchen', [
            'activeNav' => 'kitchen',
            'kitchenRawMaterialCategories' => KitchenRawMaterialCategory::query()->orderBy('name')->get(),
            'kitchenRawMaterials' => KitchenRawMaterial::query()->with('category')->orderBy('name')->get()->map(fn ($material) => $this->serializeMaterial($material)),
            'kitchenRecipes' => KitchenRecipe::query()->with(['ingredients.material'])->orderBy('name')->get()->map(fn ($recipe) => $this->serializeRecipe($recipe)),
            'kitchenFoodItems' => KitchenFoodItem::query()->with('recipes')->orderBy('name')->get()->map(fn ($item) => $this->serializeFoodItem($item)),
            'topUsedIngredients' => $topUsedIngredients,
            'recentActivity' => $this->recentKitchenActivity(),
        ]);
    }

    public function storeMaterial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80', 'unique:kitchen_raw_materials,sku'],
            'category_id' => ['required', 'exists:kitchen_raw_material_categories,id'],
            'unit' => ['required', 'string', 'max:40'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999.9999'],
            'alert_level' => ['nullable', 'numeric', 'min:0', 'max:99999999999.9999'],
            'cost_per_unit' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $material = DB::transaction(function () use ($data, $request) {
            $material = KitchenRawMaterial::query()->create([
                'category_id' => $data['category_id'],
                'name' => trim($data['name']),
                'sku' => trim($data['sku'] ?? '') ?: 'RM-'.Str::upper(Str::random(12)),
                'unit' => trim($data['unit']),
                'quantity' => $data['quantity'] ?? 0,
                'alert_level' => $data['alert_level'] ?? 0,
                'cost_per_unit' => $data['cost_per_unit'],
                'average_cost' => $data['cost_per_unit'],
                'last_cost' => $data['cost_per_unit'],
                'supplier' => $data['supplier'] ?? null,
                'batch_no' => null,
                'expiry_date' => $data['expiry_date'] ?? null,
            ]);
            if ((float) $material->quantity > 0) {
                $this->recordMaterialMovement($material, 'opening', (float) $material->quantity, (float) $material->cost_per_unit, 'Opening balance', $request);
            }

            return $material->load('category');
        }, 3);

        return response()->json(['ok' => true, 'material' => $this->serializeMaterial($material)], 201);
    }

    public function updateMaterial(Request $request, KitchenRawMaterial $material): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('kitchen_raw_materials', 'sku')->ignore($material->id)],
            'category_id' => ['required', 'exists:kitchen_raw_material_categories,id'],
            'unit' => ['required', 'string', 'max:40'],
            'alert_level' => ['required', 'numeric', 'min:0', 'max:99999999999.9999'],
            'cost_per_unit' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $material = DB::transaction(function () use ($data, $material) {
            $material = KitchenRawMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
            $material->fill([
                'category_id' => $data['category_id'],
                'name' => trim($data['name']),
                'sku' => trim($data['sku'] ?? '') ?: $material->sku,
                'unit' => trim($data['unit']),
                'alert_level' => $data['alert_level'],
                'cost_per_unit' => $data['cost_per_unit'],
                'average_cost' => $data['cost_per_unit'],
                'supplier' => $data['supplier'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
            ])->save();

            return $material->load('category');
        }, 3);

        return response()->json(['ok' => true, 'material' => $this->serializeMaterial($material)]);
    }

    public function deleteMaterial(Request $request, KitchenRawMaterial $material): JsonResponse
    {
        if ($material->recipeIngredients()->whereHas('recipe', fn ($query) => $query->whereNull('deleted_at'))->exists()) {
            return response()->json(['ok' => false, 'message' => 'This raw material is used in an active recipe. Delete the linked food item and recipe before deleting it.'], 422);
        }

        $material->delete();

        return response()->json(['ok' => true, 'message' => 'Raw material deleted.']);
    }

    public function restoreMaterial(int $id): JsonResponse
    {
        $material = KitchenRawMaterial::withTrashed()->findOrFail($id);
        $material->restore();

        return response()->json(['ok' => true]);
    }

    public function permanentlyDeleteMaterial(int $id): JsonResponse
    {
        $material = KitchenRawMaterial::withTrashed()->findOrFail($id);
        if ($material->movements()->exists()) {
            return response()->json(['ok' => false, 'message' => 'This raw material has stock movement history and cannot be permanently deleted without losing the audit trail.'], 422);
        }
        if ($material->recipeIngredients()->exists()) {
            return response()->json(['ok' => false, 'message' => 'Remove this material from all recipes before permanently deleting it.'], 422);
        }
        $material->forceDelete();

        return response()->json(['ok' => true]);
    }

    public function restockMaterials(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.material_id' => ['required', 'integer', 'distinct', 'exists:kitchen_raw_materials,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999.9999'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.batch_no' => ['nullable', 'string', 'max:80'],
        ]);

        $materials = DB::transaction(function () use ($data, $request) {
            $ids = [];
            foreach ($data['items'] as $line) {
                $material = KitchenRawMaterial::query()->whereKey($line['material_id'])->lockForUpdate()->firstOrFail();
                $previousQuantity = (float) $material->quantity;
                $quantity = (float) $line['quantity'];
                $newQuantity = $previousQuantity + $quantity;
                $oldCost = (float) $material->average_cost;
                $newCost = (float) $line['unit_cost'];
                $material->quantity = $newQuantity;
                $material->average_cost = $newQuantity > 0
                    ? round((($previousQuantity * $oldCost) + ($quantity * $newCost)) / $newQuantity, 2)
                    : $newCost;
                $material->cost_per_unit = $newCost;
                $material->last_cost = $newCost;
                if (! empty($line['expiry_date'])) {
                    $material->expiry_date = $line['expiry_date'];
                }
                if (! empty($line['batch_no'])) {
                    $material->batch_no = $line['batch_no'];
                }
                $material->save();
                $this->recordMaterialMovement($material, 'restock', $quantity, $newCost, 'Restock', $request);
                $ids[] = $material->id;
            }

            return KitchenRawMaterial::query()->with('category')->whereIn('id', $ids)->get();
        }, 3);

        return response()->json(['ok' => true, 'materials' => $materials->map(fn ($material) => $this->serializeMaterial($material))]);
    }

    public function adjustMaterials(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.material_id' => ['required', 'integer', 'distinct', 'exists:kitchen_raw_materials,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999.9999'],
            'items.*.reason' => ['required', Rule::in(['balancing', 'bad', 'expired'])],
        ]);

        $materials = DB::transaction(function () use ($data, $request) {
            $ids = [];
            foreach ($data['items'] as $line) {
                $material = KitchenRawMaterial::query()->whereKey($line['material_id'])->lockForUpdate()->firstOrFail();
                $quantity = (float) $line['quantity'];
                $before = (float) $material->quantity;
                $after = $line['reason'] === 'balancing' ? $quantity : $before - $quantity;
                if ($after < 0) {
                    abort(422, "Adjustment for {$material->name} cannot exceed the available quantity.");
                }
                $material->quantity = $after;
                $material->save();
                $reason = match ($line['reason']) {
                    'bad' => 'Damaged stock',
                    'expired' => 'Expired stock',
                    default => 'Stock count balancing',
                };
                $this->recordMaterialMovement($material, 'adjustment', $after - $before, (float) $material->average_cost, $reason, $request);
                $ids[] = $material->id;
            }

            return KitchenRawMaterial::query()->with('category')->whereIn('id', $ids)->get();
        }, 3);

        return response()->json(['ok' => true, 'materials' => $materials->map(fn ($material) => $this->serializeMaterial($material))]);
    }

    public function previewMaterials(Request $request): JsonResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx,xls']]);
        try {
            $rows = $this->parseRawMaterials($data['file']);
        } catch (Throwable $exception) {
            if ($exception instanceof \InvalidArgumentException) {
                return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }
            report($exception);

            return response()->json(['ok' => false, 'message' => 'Could not read the spreadsheet. Upload a valid CSV or Excel file.'], 422);
        }
        if ($rows === []) {
            return response()->json(['ok' => false, 'message' => 'The spreadsheet has no material rows to preview.'], 422);
        }
        $validCount = count(array_filter($rows, fn (array $row) => $row['valid']));

        return response()->json([
            'ok' => true,
            'rows' => $rows,
            'summary' => ['total' => count($rows), 'valid' => $validCount, 'invalid' => count($rows) - $validCount],
        ]);
    }

    public function importMaterials(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx,xls'],
            'category_id' => ['required', 'exists:kitchen_raw_material_categories,id'],
            'unit' => ['required', 'string', 'max:40'],
            'exclude_rows' => ['sometimes', 'array'],
            'exclude_rows.*' => ['integer', 'min:2'],
        ]);
        try {
            $rows = $this->parseRawMaterials($data['file']);
        } catch (Throwable $exception) {
            if ($exception instanceof \InvalidArgumentException) {
                return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }
            report($exception);

            return response()->json(['ok' => false, 'message' => 'Could not read the spreadsheet. Upload a valid CSV or Excel file.'], 422);
        }
        $excludedRows = array_map('intval', $data['exclude_rows'] ?? []);
        $rows = array_values(array_filter($rows, fn (array $row) => ! in_array($row['row'], $excludedRows, true)));
        if ($rows === []) {
            return response()->json(['ok' => false, 'message' => 'The spreadsheet has no material rows to import.'], 422);
        }
        foreach ($rows as $row) {
            if (! $row['valid']) {
                return response()->json(['ok' => false, 'message' => 'Row '.$row['row'].': '.implode(' ', $row['errors'])], 422);
            }
        }
        $category = KitchenRawMaterialCategory::query()->findOrFail($data['category_id']);

        $materials = DB::transaction(function () use ($rows, $request, $data, $category) {
            $created = [];
            foreach ($rows as $row) {
                $cost = (float) $row['cost_per_unit'];
                $material = KitchenRawMaterial::query()->create([
                    'category_id' => $category->id,
                    'name' => $row['name'],
                    'sku' => 'RM-'.Str::upper(Str::random(12)),
                    'unit' => $data['unit'],
                    'quantity' => (float) $row['current_quantity'],
                    'alert_level' => (float) ($row['alert_level'] ?: 0),
                    'cost_per_unit' => $cost,
                    'average_cost' => $cost,
                    'last_cost' => $cost,
                    'supplier' => $row['supplier'] ?: null,
                    'batch_no' => null,
                    'expiry_date' => $row['expiry_date'] ?: null,
                ]);
                if ((float) $material->quantity > 0) {
                    $this->recordMaterialMovement($material, 'opening', (float) $material->quantity, $cost, 'Spreadsheet import', $request);
                }
                $created[] = $material->id;
            }

            return KitchenRawMaterial::query()->with('category')->whereIn('id', $created)->get();
        }, 3);

        return response()->json(['ok' => true, 'message' => count($materials).' raw materials imported.', 'materials' => $materials->map(fn ($material) => $this->serializeMaterial($material))]);
    }

    public function materialTemplate()
    {
        return app(RawMaterialImportTemplate::class)->download();
    }

    /**
     * @return array<int, array{row: int, name: string, current_quantity: string, alert_level: string, cost_per_unit: string, supplier: string, expiry_date: string, errors: array<int, string>, valid: bool}>
     */
    private function parseRawMaterials(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $headers = array_map(fn ($value) => $this->normalizeMaterialHeader((string) $value), $sheet->rangeToArray('A1:F1', null, true, false)[0] ?? []);
        $aliases = [
            'name' => 'name',
            'current_quantity' => 'current_quantity',
            'quantity' => 'current_quantity',
            'alert_level' => 'alert_level',
            'cost_per_unit' => 'cost_per_unit',
            'supplier' => 'supplier',
            'expiry_date' => 'expiry_date',
        ];
        $mapping = [];
        foreach ($headers as $index => $header) {
            if (isset($aliases[$header]) && ! isset($mapping[$aliases[$header]])) {
                $mapping[$aliases[$header]] = $index;
            }
        }
        $missing = array_values(array_diff(['name', 'current_quantity', 'cost_per_unit'], array_keys($mapping)));
        if ($missing !== []) {
            $spreadsheet->disconnectWorksheets();
            throw new \InvalidArgumentException('Missing required spreadsheet columns: '.implode(', ', $missing).'. Download the raw-material template and keep its header row.');
        }

        $rows = [];
        $lastRow = min($sheet->getHighestDataRow(), 2001);
        for ($rowNumber = 2; $rowNumber <= $lastRow; $rowNumber++) {
            $values = $sheet->rangeToArray("A{$rowNumber}:F{$rowNumber}", null, true, false)[0] ?? [];
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $record = [];
            foreach (['name', 'current_quantity', 'alert_level', 'cost_per_unit', 'supplier', 'expiry_date'] as $key) {
                $index = $mapping[$key] ?? null;
                $value = $index === null ? '' : ($values[$index] ?? '');
                if ($key === 'expiry_date' && is_numeric($value) && (float) $value > 0) {
                    $value = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                }
                $record[$key] = trim((string) $value);
            }
            $validator = Validator::make($record, [
                'name' => ['required', 'string', 'max:160'],
                'current_quantity' => ['required', 'numeric', 'min:0', 'max:99999999999.9999'],
                'alert_level' => ['nullable', 'numeric', 'min:0', 'max:99999999999.9999'],
                'cost_per_unit' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
                'supplier' => ['nullable', 'string', 'max:160'],
                'expiry_date' => ['nullable', 'date'],
            ]);
            $rows[] = [
                'row' => $rowNumber,
                ...$record,
                'errors' => array_values($validator->errors()->all()),
                'valid' => ! $validator->fails(),
                'is_sample' => $rowNumber === 2
                    && $record['name'] === 'Chicken Breast'
                    && $record['current_quantity'] === '25.5'
                    && $record['alert_level'] === '10'
                    && $record['cost_per_unit'] === '8000'
                    && $record['supplier'] === 'Sample Supplier',
            ];
        }
        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    private function normalizeMaterialHeader(string $header): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower(trim($header))), '_');
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:kitchen_raw_material_categories,name']]);
        $category = KitchenRawMaterialCategory::query()->create([
            'name' => trim($data['name']),
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
        ]);

        return response()->json(['ok' => true, 'category' => ['id' => $category->id, 'name' => $category->name]], 201);
    }

    public function deleteCategory(KitchenRawMaterialCategory $category): JsonResponse
    {
        if (KitchenRawMaterial::withTrashed()->where('category_id', $category->id)->exists()) {
            return response()->json(['ok' => false, 'message' => 'This category is used by raw materials and cannot be deleted.'], 422);
        }
        $category->delete();

        return response()->json(['ok' => true]);
    }

    public function storeRecipe(Request $request): JsonResponse
    {
        $data = $this->validateRecipe($request);
        $recipe = DB::transaction(function () use ($data) {
            $recipe = KitchenRecipe::query()->create($this->recipeAttributes($data));
            $this->syncIngredients($recipe, $data['ingredients']);

            return $recipe->load(['ingredients.material']);
        }, 3);

        return response()->json(['ok' => true, 'recipe' => $this->serializeRecipe($recipe)], 201);
    }

    public function updateRecipe(Request $request, KitchenRecipe $recipe): JsonResponse
    {
        $data = $this->validateRecipe($request, $recipe);
        $recipe = DB::transaction(function () use ($data, $recipe) {
            $recipe->update($this->recipeAttributes($data));
            $recipe->increment('version');
            $this->syncIngredients($recipe, $data['ingredients']);

            return $recipe->load(['ingredients.material']);
        }, 3);

        return response()->json(['ok' => true, 'recipe' => $this->serializeRecipe($recipe)]);
    }

    public function deleteRecipe(KitchenRecipe $recipe): JsonResponse
    {
        if ($this->recipeHasActiveFoodItems($recipe)) {
            return response()->json(['ok' => false, 'message' => 'This recipe is linked to a food item. Delete the linked food item before deleting the recipe.'], 422);
        }

        $recipe->delete();

        return response()->json(['ok' => true, 'message' => 'Recipe moved to Trash.']);
    }

    public function restoreRecipe(int $id): JsonResponse
    {
        KitchenRecipe::withTrashed()->findOrFail($id)->restore();

        return response()->json(['ok' => true]);
    }

    public function permanentlyDeleteRecipe(int $id): JsonResponse
    {
        $recipe = KitchenRecipe::withTrashed()->findOrFail($id);
        if ($this->recipeHasActiveFoodItems($recipe)) {
            return response()->json(['ok' => false, 'message' => 'This recipe is linked to a food item. Delete the linked food item before permanently deleting the recipe.'], 422);
        }
        $recipe->forceDelete();

        return response()->json(['ok' => true]);
    }

    private function recipeHasActiveFoodItems(KitchenRecipe $recipe): bool
    {
        return DB::table('kitchen_food_item_recipes')
            ->join('kitchen_food_items', 'kitchen_food_items.id', '=', 'kitchen_food_item_recipes.food_item_id')
            ->where('kitchen_food_item_recipes.recipe_id', $recipe->id)
            ->whereNull('kitchen_food_items.deleted_at')
            ->exists();
    }

    public function storeFoodItem(Request $request): JsonResponse
    {
        $data = $this->validateFoodItem($request);
        $item = DB::transaction(function () use ($data) {
            $item = KitchenFoodItem::query()->create($this->foodItemAttributes($data));
            $item->recipes()->sync($data['recipe_ids'] ?? []);

            return $item->load('recipes');
        }, 3);

        return response()->json(['ok' => true, 'foodItem' => $this->serializeFoodItem($item)], 201);
    }

    public function updateFoodItem(Request $request, KitchenFoodItem $foodItem): JsonResponse
    {
        $data = $this->validateFoodItem($request);
        $item = DB::transaction(function () use ($data, $foodItem) {
            $foodItem->update($this->foodItemAttributes($data));
            $foodItem->recipes()->sync($data['recipe_ids'] ?? []);

            return $foodItem->load('recipes');
        }, 3);

        return response()->json(['ok' => true, 'foodItem' => $this->serializeFoodItem($item)]);
    }

    public function deleteFoodItem(KitchenFoodItem $foodItem): JsonResponse
    {
        $foodItem->delete();

        return response()->json(['ok' => true, 'message' => 'Food item moved to Trash.']);
    }

    public function restoreFoodItem(int $id): JsonResponse
    {
        KitchenFoodItem::withTrashed()->findOrFail($id)->restore();

        return response()->json(['ok' => true]);
    }

    public function permanentlyDeleteFoodItem(int $id): JsonResponse
    {
        KitchenFoodItem::withTrashed()->findOrFail($id)->forceDelete();

        return response()->json(['ok' => true]);
    }

    private function validateRecipe(Request $request, ?KitchenRecipe $recipe = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:80', Rule::unique('kitchen_recipes', 'code')->ignore($recipe?->id)],
            'category' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:40'],
            'output_quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999.9999'],
            'output_unit' => ['required', 'string', 'max:40'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'ingredients' => ['present', 'array', 'max:200'],
            'ingredients.*.material_id' => ['required', 'integer', 'distinct', Rule::exists('kitchen_raw_materials', 'id')->whereNull('deleted_at')],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999.9999'],
            'ingredients.*.unit' => ['required', 'string', 'max:40'],
            'ingredients.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function recipeAttributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'code' => trim($data['code'] ?? '') ?: 'REC-'.Str::upper(Str::random(10)),
            'category' => $data['category'],
            'type' => $data['type'] ?? 'Menu Item',
            'output_quantity' => $data['output_quantity'],
            'output_unit' => $data['output_unit'],
            'instructions' => $data['instructions'] ?? null,
        ];
    }

    private function syncIngredients(KitchenRecipe $recipe, array $ingredients): void
    {
        $recipe->ingredients()->delete();
        foreach ($ingredients as $ingredient) {
            $recipe->ingredients()->create([
                'raw_material_id' => $ingredient['material_id'],
                'quantity' => $ingredient['quantity'],
                'unit' => $ingredient['unit'],
                'waste_percent' => $ingredient['waste_percent'] ?? 0,
            ]);
        }
    }

    private function validateFoodItem(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'image' => ['nullable', 'string', 'max:3000000'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'expiry_date' => ['nullable', 'date'],
            'available' => ['required', 'boolean'],
            'recipe_ids' => ['present', 'array', 'max:100'],
            'recipe_ids.*' => ['integer', 'distinct', Rule::exists('kitchen_recipes', 'id')->whereNull('deleted_at')],
        ]);
    }

    private function foodItemAttributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'category' => $data['category'],
            'price' => $data['price'],
            'image' => $data['image'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'available' => $data['available'],
        ];
    }

    /**
     * Real stock activity for the Kitchen overview.
     *
     * @return array<int, array{id:int,color:string,text:string,time:string}>
     */
    private function recentKitchenActivity(): array
    {
        return KitchenRawMaterialMovement::query()
            ->with('material')
            ->latest('created_at')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(function (KitchenRawMaterialMovement $movement) {
                $material = $movement->material?->name ?? 'Deleted material';
                $unit = $movement->material?->unit ?: '';
                $quantity = rtrim(rtrim(number_format(abs((float) $movement->quantity_delta), 4, '.', ''), '0'), '.');
                $amount = trim($quantity.' '.$unit);

                [$color, $text] = match ($movement->type) {
                    'opening' => ['text-purple-500', "New raw material: {$material} added"],
                    'restock' => ['text-green-500', "{$material} restocked (+{$amount})"],
                    'adjustment' => ['text-amber-500', "{$material} adjusted: ".($movement->reason ?: 'manual adjustment')],
                    'sale' => ['text-blue-500', "{$material} used for a sale"],
                    'sale_return' => ['text-emerald-500', "{$material} returned to stock"],
                    default => ['text-gray-400', "{$material} stock updated"],
                };

                return [
                    'id' => $movement->id,
                    'color' => $color,
                    'text' => $text,
                    'time' => $movement->created_at?->diffForHumans() ?? '',
                ];
            })
            ->values()
            ->all();
    }

    private function serializeMaterial(KitchenRawMaterial $material): array
    {
        return [
            'id' => $material->id,
            'name' => $material->name,
            'sku' => $material->sku,
            'category' => $material->category->name,
            'categoryId' => $material->category_id,
            'unit' => $material->unit,
            'baseUnit' => $material->unit,
            'conversion' => 1,
            'currentQty' => (float) $material->quantity,
            'alertLevel' => (float) $material->alert_level,
            'costPerUnit' => (float) $material->cost_per_unit,
            'avgCost' => (float) $material->average_cost,
            'lastCost' => (float) $material->last_cost,
            'supplier' => $material->supplier ?? '',
            'batchNo' => $material->batch_no ?? '',
            'expiryDate' => $material->expiry_date?->format('Y-m-d') ?? '',
            'active' => (bool) $material->active,
            'image' => '📦',
        ];
    }

    private function serializeRecipe(KitchenRecipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'name' => $recipe->name,
            'code' => $recipe->code,
            'category' => $recipe->category,
            'type' => $recipe->type,
            'outputQty' => (float) $recipe->output_quantity,
            'outputUnit' => $recipe->output_unit,
            'prepTime' => $recipe->prep_time,
            'cookTime' => $recipe->cook_time,
            'version' => $recipe->version,
            'active' => (bool) $recipe->active,
            'instructions' => $recipe->instructions ?? '',
            'ingredients' => $recipe->ingredients->map(fn (KitchenRecipeIngredient $ingredient) => [
                'materialId' => $ingredient->raw_material_id,
                'name' => $ingredient->material?->name ?? 'Deleted material',
                'qty' => (float) $ingredient->quantity,
                'unit' => $ingredient->unit,
                'consumptionQty' => (float) $ingredient->quantity,
                'consumptionUnit' => $ingredient->unit,
                'unitCost' => (float) ($ingredient->material?->average_cost ?? 0),
                'wastePct' => (float) $ingredient->waste_percent,
                'totalCost' => (float) $ingredient->quantity * (float) ($ingredient->material?->average_cost ?? 0) * (1 + (float) $ingredient->waste_percent / 100),
            ])->values(),
        ];
    }

    private function serializeFoodItem(KitchenFoodItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category,
            'price' => (float) $item->price,
            'image' => $item->image ?? '',
            'recipeIds' => $item->recipes->pluck('id')->values(),
            'available' => (bool) $item->available,
            'instructions' => $item->instructions ?? '',
            'expiryDate' => $item->expiry_date?->format('Y-m-d') ?? '',
        ];
    }

    private function recordMaterialMovement(
        KitchenRawMaterial $material,
        string $type,
        float $quantityDelta,
        ?float $unitCost,
        ?string $reason,
        Request $request,
    ): void {
        KitchenRawMaterialMovement::query()->create([
            'raw_material_id' => $material->id,
            'user_id' => $request->user()?->id,
            'type' => $type,
            'quantity_delta' => $quantityDelta,
            'unit_cost' => $unitCost,
            'reason' => $reason,
        ]);
    }
}
