<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryCategory;
use App\Models\InventoryCounter;
use App\Models\InventoryProduct;
use App\Models\InventoryUnit;
use App\Models\KitchenFoodItem;
use App\Models\KitchenRawMaterial;
use App\Models\KitchenRawMaterialCategory;
use App\Models\KitchenRecipe;
use App\Models\PosPaymentMode;
use App\Models\PosTable;
use App\Models\StaffRole;
use App\Models\User;
use App\Settings\BarSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingsController extends Controller
{
    /**
     * Maximum accepted logo size (2 MB).
     */
    private const MAX_LOGO_KB = 2048;

    /**
     * Bar profile, receipt settings and the currency catalogue.
     */
    public function index(Request $request)
    {
        $settings = BarSettings::all();
        $user = $request->user();
        if ($user && filled($user->business_name)) {
            $settings['name'] = $user->business_name;
            $settings['receipt_header'] = $user->business_name;
        }
        if ($user && filled($user->phone)) {
            $settings['phone'] = $user->phone;
        }
        if ($user && filled($user->business_address)) {
            $settings['address'] = $user->business_address;
        }

        $profile = [
            'name' => $settings['name'],
            'phone' => $settings['phone'],
            'address' => $settings['address'],
            'receipt_header' => $settings['receipt_header'],
        ];
        if (array_intersect_key(BarSettings::all(), $profile) !== $profile) {
            BarSettings::save($profile);
        }

        $counterTotals = DB::table('inventory_stocks')
            ->join('inventory_products', 'inventory_products.id', '=', 'inventory_stocks.product_id')
            ->whereNull('inventory_products.deleted_at')
            ->selectRaw('inventory_stocks.counter_id, COUNT(DISTINCT inventory_products.id) as products, COALESCE(SUM(inventory_stocks.quantity * inventory_products.buy_price), 0) as stock_value')
            ->groupBy('inventory_stocks.counter_id')
            ->get()->keyBy('counter_id');

        return view('pages.settings', [
            'activeNav' => 'settings',
            'settings' => $settings,
            'currency' => BarSettings::currency(),
            'currencyGroups' => BarSettings::currencyGroups(),
            'inventoryCategories' => InventoryCategory::query()->orderBy('name')->get(),
            'counters' => InventoryCounter::query()->orderBy('name')->get()
                ->map(function (InventoryCounter $counter) use ($counterTotals) {
                    $totals = $counterTotals->get($counter->id);

                    return [
                        'id' => $counter->id,
                        'name' => $counter->name,
                        'products' => (int) ($totals->products ?? 0),
                        'stockValue' => (float) ($totals->stock_value ?? 0),
                    ];
                }),
            'inventoryUnits' => InventoryUnit::query()->orderBy('name')->pluck('name'),
            'posTables' => PosTable::query()->orderBy('name')->get(['id', 'name']),
            'posPaymentModes' => PosPaymentMode::query()->orderBy('id')->get()->map(fn (PosPaymentMode $mode) => [
                'id' => $mode->id,
                'name' => $mode->name,
                'enabled' => (bool) $mode->enabled,
                'requiresRef' => (bool) $mode->requires_reference,
                'refLabel' => $mode->reference_label ?? '',
                'icon' => $mode->icon,
            ]),
            'staffRoles' => StaffRole::query()->orderBy('name')->get()
                ->map(fn (StaffRole $role) => [
                    'id' => $role->slug,
                    'recordId' => $role->id,
                    'label' => $role->name,
                ]),
            'canManageStaffRoles' => $user?->isAdministrator() ?? false,
            'kitchenRawMaterialCategories' => KitchenRawMaterialCategory::query()->orderBy('name')->get(),
            'inventoryTrash' => InventoryProduct::query()->onlyTrashed()->select('id', 'name', 'deleted_at')->orderByDesc('deleted_at')->get()
                ->map(fn (InventoryProduct $product) => [
                    'id' => 'inventory-product-'.$product->id,
                    'type' => 'Inventory product',
                    'label' => $product->name,
                    'item' => ['productId' => $product->id],
                    'sourceKey' => 'server.inventory.products',
                    'deletedAt' => $product->deleted_at->toIso8601String(),
                ])
                ->concat(KitchenRawMaterial::query()->onlyTrashed()->select('id', 'name', 'deleted_at')->orderByDesc('deleted_at')->get()->map(fn (KitchenRawMaterial $material) => [
                    'id' => 'kitchen-raw-material-'.$material->id,
                    'type' => 'Raw material',
                    'label' => $material->name,
                    'item' => ['recordId' => $material->id],
                    'sourceKey' => 'server.kitchen.raw-materials',
                    'deletedAt' => $material->deleted_at->toIso8601String(),
                ]))
                ->concat(KitchenRecipe::query()->onlyTrashed()->select('id', 'name', 'deleted_at')->orderByDesc('deleted_at')->get()->map(fn (KitchenRecipe $recipe) => [
                    'id' => 'kitchen-recipe-'.$recipe->id,
                    'type' => 'Recipe',
                    'label' => $recipe->name,
                    'item' => ['recordId' => $recipe->id],
                    'sourceKey' => 'server.kitchen.recipes',
                    'deletedAt' => $recipe->deleted_at->toIso8601String(),
                ]))
                ->concat(KitchenFoodItem::query()->onlyTrashed()->select('id', 'name', 'deleted_at')->orderByDesc('deleted_at')->get()->map(fn (KitchenFoodItem $item) => [
                    'id' => 'kitchen-food-item-'.$item->id,
                    'type' => 'Food item',
                    'label' => $item->name,
                    'item' => ['recordId' => $item->id],
                    'sourceKey' => 'server.kitchen.food-items',
                    'deletedAt' => $item->deleted_at->toIso8601String(),
                ]))
                ->concat(Expense::query()->onlyTrashed()->select('id', 'description', 'deleted_at')->orderByDesc('deleted_at')->get()->map(fn (Expense $expense) => [
                    'id' => 'expense-'.$expense->id,
                    'type' => 'Expense',
                    'label' => $expense->description,
                    'item' => ['recordId' => $expense->id],
                    'sourceKey' => 'server.expenses',
                    'deletedAt' => $expense->deleted_at->toIso8601String(),
                ]))
                ->concat(ExpenseCategory::query()->onlyTrashed()->select('id', 'name', 'slug', 'deleted_at')->orderByDesc('deleted_at')->get()->map(fn (ExpenseCategory $category) => [
                    'id' => 'expense-category-'.$category->id,
                    'type' => 'Expense category',
                    'label' => $category->name,
                    'item' => ['recordId' => $category->id, 'slug' => $category->slug],
                    'sourceKey' => 'server.expense-categories',
                    'deletedAt' => $category->deleted_at->toIso8601String(),
                ]))
                ->concat(StaffRole::onlyTrashed()->select('id', 'name', 'slug', 'deleted_at')->orderByDesc('deleted_at')->get()->map(fn (StaffRole $role) => [
                    'id' => 'staff-role-'.$role->id,
                    'type' => 'Staff role',
                    'label' => $role->name,
                    'item' => ['recordId' => $role->id, 'slug' => $role->slug],
                    'sourceKey' => 'server.staff-roles',
                    'deletedAt' => $role->deleted_at->toIso8601String(),
                ]))
                ->concat(User::onlyTrashed()->where('role', '!=', 'administrator')->select('id', 'name', 'deleted_at')->orderByDesc('deleted_at')->get()->map(fn (User $staff) => [
                    'id' => 'staff-member-'.$staff->id,
                    'type' => 'Staff member',
                    'label' => $staff->name,
                    'item' => ['recordId' => $staff->id],
                    'sourceKey' => 'server.staff',
                    'deletedAt' => $staff->deleted_at->toIso8601String(),
                ]))->values(),
            'expenseCategories' => ExpenseCategory::query()->orderBy('name')->get()
                ->map(fn (ExpenseCategory $category) => [
                    'id' => $category->slug,
                    'recordId' => $category->id,
                    'label' => $category->name,
                ]),
        ]);
    }

    /**
     * Save the bar profile and the receipt settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:255'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', Rule::in(BarSettings::currencyCodes())],
        ], [
            'name' => 'bar name',
            'phone' => 'phone number',
            'address' => 'address',
            'receipt_footer' => 'receipt footer',
            'currency' => 'currency',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $data['receipt_header'] = $data['name'];
        $settings = BarSettings::save($data);

        $request->user()->update([
            'business_name' => $settings['name'],
            'phone' => $settings['phone'],
            'business_address' => $settings['address'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Settings saved successfully.',
            'currency' => BarSettings::currency($settings['currency']),
        ]);
    }

    /**
     * Upload the bar logo / branding image.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => [
                'required',
                'file',
                'image',
                'max:'.self::MAX_LOGO_KB,
                'mimes:png,jpg,jpeg,webp,svg',
            ],
        ], [], ['logo' => 'logo']);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $file = $request->file('logo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension());
        $size = $file->getSize();
        $originalName = $file->getClientOriginalName();

        if ($extension === 'svg') {
            $contents = (string) file_get_contents($file->getRealPath());
            $sanitized = $this->sanitizeSvg($contents);

            if ($sanitized === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'That SVG file could not be read. Please upload a valid image.',
                ], 422);
            }

            $directory = BarSettings::brandingDirectory();
            $this->ensureDirectory($directory);

            $filename = 'logo-'.time().'.svg';
            file_put_contents($directory.DIRECTORY_SEPARATOR.$filename, $sanitized);
        } else {
            $directory = BarSettings::brandingDirectory();
            $this->ensureDirectory($directory);

            $filename = 'logo-'.time().'.'.$extension;
            $file->move($directory, $filename);

            if (! @getimagesize($directory.DIRECTORY_SEPARATOR.$filename)) {
                @unlink($directory.DIRECTORY_SEPARATOR.$filename);

                return response()->json([
                    'ok' => false,
                    'message' => 'That file is not a readable image. Please upload a PNG, JPG, WEBP or SVG.',
                ], 422);
            }
        }

        // Drop the previous logo so only one branding file is kept.
        $previous = BarSettings::get('logo');
        if ($previous && $previous !== $filename) {
            $old = $directory.DIRECTORY_SEPARATOR.basename($previous);
            if (is_file($old)) {
                @unlink($old);
            }
        }

        BarSettings::setLogo($filename);

        return response()->json([
            'ok' => true,
            'message' => 'Logo uploaded successfully.',
            'url' => BarSettings::logoUrl(),
            'name' => $originalName,
            'size' => $size,
        ]);
    }

    /**
     * Remove the uploaded logo.
     */
    public function removeLogo(): JsonResponse
    {
        BarSettings::deleteLogo();

        return response()->json([
            'ok' => true,
            'message' => 'Logo removed.',
        ]);
    }

    /**
     * Stream the uploaded logo.
     */
    public function logo(): BinaryFileResponse|Response
    {
        $path = BarSettings::logoPath();

        if (! $path) {
            return response('Logo not found.', 404);
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    /**
     * Render a validation failure as a JSON response.
     */
    private function validationError(\Illuminate\Contracts\Validation\Validator $validator): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);
    }

    /**
     * Strip scripts and event handlers from an uploaded SVG.
     */
    private function sanitizeSvg(string $contents): ?string
    {
        if (! str_contains($contents, '<svg')) {
            return null;
        }

        $contents = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $contents) ?? $contents;
        $contents = preg_replace('#<script\b[^>]*/>#is', '', $contents) ?? $contents;
        $contents = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $contents) ?? $contents;
        $contents = preg_replace('#(href|xlink:href)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '', $contents) ?? $contents;

        return $contents;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }
}
