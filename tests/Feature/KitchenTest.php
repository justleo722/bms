<?php

use App\Models\KitchenFoodItem;
use App\Models\KitchenRawMaterial;
use App\Models\KitchenRawMaterialCategory;
use App\Models\KitchenRawMaterialMovement;
use App\Models\KitchenRecipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('requires authentication for Kitchen changes', function () {
    auth()->logout();

    $this->postJson('/kitchen/raw-materials', [])->assertUnauthorized();
    $this->postJson('/kitchen/raw-materials/import-preview', [])->assertUnauthorized();
    $this->postJson('/settings/kitchen/raw-material-categories', [])->assertUnauthorized();
    $this->postJson('/kitchen/recipes', [])->assertUnauthorized();
    $this->postJson('/kitchen/food-items', [])->assertUnauthorized();
});

it('shows Kitchen controls and modal templates only for their matching permissions', function () {
    $permissionControls = [
        'rawAdd' => ['@click="openAddRawMaterial()"', '@click="openRestockModal()"', '@click="addRawMaterial()"', '@click="saveRestock()"', '@click="confirmImportRawMaterials()"', 'x-show="showAddRawMaterial"', 'x-show="showImportMaterials"'],
        'rawEdit' => ['@click="openAdjustModal()"', '@click.stop="openEditRawMaterial(m)"', '@click="saveEditRawMaterial()"', '@click="saveAdjust()"', 'x-show="showEditRawMaterial"', 'x-show="showAdjustModal"'],
        'rawDelete' => ['@click.stop="openDeleteRawMaterial(m)"', '@click="confirmDeleteRawMaterial()"', 'x-show="showDeleteRawMaterial"'],
        'rawValue' => ['Stock Value', 'Cost/Unit', 'Average Cost'],
        'recipeAdd' => ['@click="openAddRecipe()"'],
        'recipeEdit' => ['@click="openEditRecipe(r)"'],
        'recipeDelete' => ['@click="openDeleteRecipe(r)"', '@click="showDeleteRecipe = false"', '@click="confirmDeleteRecipe()"', 'x-show="showDeleteRecipe"'],
        'foodAdd' => ['@click="openAddFoodItem()"', '@click="addFoodItem()"', 'x-show="showAddFoodItem"'],
        'foodEdit' => ['@click="toggleFoodAvailability(item)"', '@click="openEditFoodItem(item)"', '@click="saveEditFoodItem()"', 'x-show="showEditFoodItem"'],
        'foodDelete' => ['@click="openDeleteFoodItem(item)"', '@click="closeDeleteFoodItem()"', '@click="confirmDeleteFoodItem()"', 'x-show="showDeleteFoodItem"'],
    ];
    $allComponents = array_fill_keys(array_keys($permissionControls), false);
    $staff = User::factory()->create([
        'role' => 'cashier',
        'permissions' => ['kitchen' => ['enabled' => true, 'components' => $allComponents]],
    ]);
    $noRecipePermissions = $allComponents;
    $noRecipePermissions['rawAdd'] = true;
    $staff->update([
        'permissions' => ['kitchen' => ['enabled' => true, 'components' => $noRecipePermissions]],
    ]);
    $noRecipePermissionsResponse = $this->actingAs($staff)->get('/kitchen')->assertOk();
    $noRecipePermissionsResponse->assertDontSee('@click="recipeFormMode === \'edit\' ? saveEditRecipe() : addRecipe()"', false);

    foreach ($permissionControls as $permission => $visibleControls) {
        $components = $allComponents;
        $components[$permission] = true;
        $staff->update([
            'permissions' => ['kitchen' => ['enabled' => true, 'components' => $components]],
        ]);
        $response = $this->actingAs($staff)->get('/kitchen')->assertOk();

        foreach ($permissionControls as $controlPermission => $controls) {
            foreach ($controls as $control) {
                if ($controlPermission === $permission) {
                    $response->assertSee($control, false);
                } else {
                    $response->assertDontSee($control, false);
                }
            }
        }
    }
});

it('creates and edits raw materials while requiring restock or adjustment to change quantity', function () {
    $category = KitchenRawMaterialCategory::query()->where('name', 'Meats & Poultry')->firstOrFail();
    $response = $this->postJson('/kitchen/raw-materials', [
        'name' => 'Chicken Breast Test',
        'category_id' => $category->id,
        'unit' => 'kg',
        'quantity' => 10,
        'alert_level' => 3,
        'cost_per_unit' => 8000,
    ])->assertCreated();

    $materialId = $response->json('material.id');
    $material = KitchenRawMaterial::query()->findOrFail($materialId);
    expect((float) $response->json('material.currentQty'))->toBe(10.0)
        ->and(KitchenRawMaterialMovement::query()->where('raw_material_id', $materialId)->count())->toBe(1);

    $this->putJson('/kitchen/raw-materials/'.$materialId, [
        'name' => 'Chicken Breast Updated',
        'sku' => $material->sku,
        'category_id' => $category->id,
        'unit' => 'kg',
        'quantity' => 8,
        'alert_level' => 4,
        'cost_per_unit' => 8200,
    ])->assertOk()->assertJsonPath('material.currentQty', 10);

    $this->postJson('/kitchen/raw-materials/restock', [
        'items' => [[
            'material_id' => $materialId,
            'quantity' => 2,
            'unit_cost' => 10000,
            'expiry_date' => '2027-01-01',
            'batch_no' => 'BATCH-2',
        ]],
    ])->assertOk()->assertJsonPath('materials.0.currentQty', 12)
        ->assertJsonPath('materials.0.avgCost', 8500);

    $this->postJson('/kitchen/raw-materials/adjust', [
        'items' => [['material_id' => $materialId, 'quantity' => 2, 'reason' => 'expired']],
    ])->assertOk()->assertJsonPath('materials.0.currentQty', 10);

    $this->deleteJson('/kitchen/raw-materials/'.$materialId)->assertOk();
    expect(KitchenRawMaterial::query()->find($materialId))->toBeNull()
        ->and(KitchenRawMaterial::withTrashed()->findOrFail($materialId)->trashed())->toBeTrue()
        ->and(KitchenRawMaterialMovement::query()->where('raw_material_id', $materialId)->count())->toBe(3);
    $this->deleteJson('/kitchen/raw-materials/'.$materialId.'/permanent')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This raw material has stock movement history and cannot be permanently deleted without losing the audit trail.');
    $this->postJson('/kitchen/raw-materials/'.$materialId.'/restore')->assertOk();
    expect(KitchenRawMaterial::query()->find($materialId))->not->toBeNull();
});

it('previews six-column raw material sheets and imports selected rows with shared category and unit', function () {
    $category = KitchenRawMaterialCategory::query()->where('name', 'Dry Goods')->firstOrFail();
    $file = UploadedFile::fake()->createWithContent(
        'materials.csv',
        "Name,Current Quantity,Alert Level,Cost Per Unit,Supplier,Expiry Date\nChicken Breast,25.5,10,8000,Sample Supplier,2027-12-31\nRice,20,5,2000,Test supplier,2027-12-31\n"
    );

    $this->post('/kitchen/raw-materials/import-preview', ['file' => $file])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('summary.total', 2)
        ->assertJsonPath('rows.0.is_sample', true)
        ->assertJsonPath('rows.1.name', 'Rice');

    expect(KitchenRawMaterial::query()->count())->toBe(0);

    $this->post('/kitchen/raw-materials/import', [
        'file' => $file,
        'category_id' => $category->id,
        'unit' => 'kg',
        'exclude_rows' => [2],
    ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('materials.0.name', 'Rice')
        ->assertJsonPath('materials.0.category', 'Dry Goods')
        ->assertJsonPath('materials.0.unit', 'kg');

    $material = KitchenRawMaterial::query()->where('name', 'Rice')->firstOrFail();
    expect((float) $material->quantity)->toBe(20.0)
        ->and($material->sku)->toStartWith('RM-');
});

it('reports invalid raw material rows in preview and does not import them', function () {
    $category = KitchenRawMaterialCategory::query()->where('name', 'Dry Goods')->firstOrFail();
    $file = UploadedFile::fake()->createWithContent(
        'materials.csv',
        "Name,Current Quantity,Alert Level,Cost Per Unit,Supplier,Expiry Date\nRice,invalid,5,2000,Test supplier,\n"
    );

    $this->post('/kitchen/raw-materials/import-preview', ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.invalid', 1)
        ->assertJsonPath('rows.0.valid', false);
    $this->post('/kitchen/raw-materials/import', [
        'file' => $file,
        'category_id' => $category->id,
        'unit' => 'kg',
    ])->assertUnprocessable();
    expect(KitchenRawMaterial::query()->count())->toBe(0);
});

it('downloads a protected raw material template with the requested columns and sample row', function () {
    $response = $this->get('/kitchen/raw-materials/template')->assertOk();
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();
    $path = tempnam(sys_get_temp_dir(), 'kitchen-template-test-');
    file_put_contents($path, $content);
    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        expect($sheet->rangeToArray('A1:F1', null, true, false)[0])->toBe([
            'Name', 'Current Quantity', 'Alert Level', 'Cost Per Unit', 'Supplier', 'Expiry Date',
        ])->and($sheet->getCell('A2')->getValue())->toBe('Chicken Breast')
            ->and($sheet->getStyle('A1')->getProtection()->getLocked())->toBe('protected')
            ->and($sheet->getStyle('A2')->getProtection()->getLocked())->toBe('unprotected');
    } finally {
        @unlink($path);
    }
});

it('persists raw material categories and prevents deleting categories in use', function () {
    $this->postJson('/settings/kitchen/raw-material-categories', ['name' => 'Seafood'])
        ->assertCreated()->assertJsonPath('category.name', 'Seafood');
    $category = KitchenRawMaterialCategory::query()->where('name', 'Seafood')->firstOrFail();
    $material = KitchenRawMaterial::query()->create([
        'category_id' => $category->id,
        'name' => 'Category usage test',
        'sku' => 'RM-CATEGORY-1',
        'unit' => 'kg',
    ]);
    $this->deleteJson('/settings/kitchen/raw-material-categories/'.$category->id)->assertUnprocessable();
    $material->forceDelete();
    $this->deleteJson('/settings/kitchen/raw-material-categories/'.$category->id)->assertOk();
    expect(KitchenRawMaterialCategory::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('stores recipe ingredients and links recipes to food items', function () {
    $category = KitchenRawMaterialCategory::query()->where('name', 'Meats & Poultry')->firstOrFail();
    $material = KitchenRawMaterial::query()->create([
        'category_id' => $category->id,
        'name' => 'Recipe Chicken',
        'sku' => 'RM-RECIPE-1',
        'unit' => 'kg',
        'quantity' => 5,
        'cost_per_unit' => 9000,
        'average_cost' => 9000,
        'last_cost' => 9000,
    ]);

    $recipeResponse = $this->postJson('/kitchen/recipes', [
        'name' => 'Grilled Chicken Recipe',
        'category' => 'Main Course',
        'output_quantity' => 1,
        'output_unit' => 'serving',
        'ingredients' => [[
            'material_id' => $material->id,
            'quantity' => 0.25,
            'unit' => 'kg',
            'waste_percent' => 5,
        ]],
    ])->assertCreated()->assertJsonPath('recipe.ingredients.0.name', 'Recipe Chicken');
    $recipeId = $recipeResponse->json('recipe.id');

    $foodResponse = $this->postJson('/kitchen/food-items', [
        'name' => 'Grilled Chicken',
        'category' => 'Mains',
        'price' => 12000,
        'available' => true,
        'recipe_ids' => [$recipeId],
    ])->assertCreated()->assertJsonPath('foodItem.recipeIds.0', $recipeId);
    $foodId = $foodResponse->json('foodItem.id');

    $this->putJson('/kitchen/food-items/'.$foodId, [
        'name' => 'Grilled Chicken Special',
        'category' => 'Mains',
        'price' => 13000,
        'available' => false,
        'recipe_ids' => [$recipeId],
    ])->assertOk()->assertJsonPath('foodItem.available', false);

    $this->deleteJson('/kitchen/raw-materials/'.$material->id)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This raw material is used in an active recipe. Delete the linked food item and recipe before deleting it.');
    $this->deleteJson('/kitchen/recipes/'.$recipeId)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This recipe is linked to a food item. Delete the linked food item before deleting the recipe.');
    $this->deleteJson('/kitchen/recipes/'.$recipeId.'/permanent')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This recipe is linked to a food item. Delete the linked food item before permanently deleting the recipe.');
    $this->deleteJson('/kitchen/food-items/'.$foodId)->assertOk();
    $this->deleteJson('/kitchen/recipes/'.$recipeId)->assertOk();
    expect(KitchenRecipe::query()->find($recipeId))->toBeNull()
        ->and(KitchenFoodItem::query()->withTrashed()->findOrFail($foodId)->recipes()->count())->toBe(0);
    $this->deleteJson('/kitchen/raw-materials/'.$material->id)->assertOk();
    expect(KitchenRawMaterial::withTrashed()->findOrFail($material->id)->trashed())->toBeTrue();
    $this->postJson('/kitchen/recipes/'.$recipeId.'/restore')->assertOk();
    $this->postJson('/kitchen/raw-materials/'.$material->id.'/restore')->assertOk();
    $this->postJson('/kitchen/food-items/'.$foodId.'/restore')->assertOk();
    expect(KitchenFoodItem::query()->findOrFail($foodId)->recipes()->count())->toBe(1);
});

it('shows the most-used recipe ingredients from database recipe quantities', function () {
    $category = KitchenRawMaterialCategory::query()->where('name', 'Meats & Poultry')->firstOrFail();
    $chicken = KitchenRawMaterial::query()->create([
        'category_id' => $category->id,
        'name' => 'Usage Test Chicken',
        'sku' => 'RM-USAGE-CHICKEN',
        'unit' => 'kg',
    ]);
    $rice = KitchenRawMaterial::query()->create([
        'category_id' => $category->id,
        'name' => 'Usage Test Rice',
        'sku' => 'RM-USAGE-RICE',
        'unit' => 'kg',
    ]);

    foreach ([['Usage Recipe A', $chicken, 0.5], ['Usage Recipe B', $chicken, 0.75], ['Usage Recipe C', $rice, 0.25]] as [$name, $material, $quantity]) {
        $this->postJson('/kitchen/recipes', [
            'name' => $name,
            'category' => 'Test',
            'output_quantity' => 1,
            'output_unit' => 'serving',
            'ingredients' => [[
                'material_id' => $material->id,
                'quantity' => $quantity,
                'unit' => 'kg',
            ]],
        ])->assertCreated();
    }

    $this->get('/kitchen')
        ->assertOk()
        ->assertSee('Usage Test Chicken')
        ->assertSee('1.25')
        ->assertSee('Usage Test Rice');
});

it('renders settings with server-backed Kitchen categories and Trash entries', function () {
    $this->get('/settings')->assertOk();
    $this->get('/kitchen')->assertOk();
});
