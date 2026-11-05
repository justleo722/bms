<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::get('/branding/logo', [SettingsController::class, 'logo'])->name('branding.logo');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/no-access', [PageController::class, 'noAccess'])->name('no-access');

    Route::get('/pos', [PosController::class, 'index'])->middleware('staff.permission:pos')->name('pos');
    Route::get('/pos/stock-levels', [PosController::class, 'stockLevels'])->middleware('staff.permission:pos')->name('pos.stock-levels');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->middleware('staff.permission:pos,sell')->name('pos.checkout');
    Route::get('/pos/dockets/{order}', [PosController::class, 'docket'])->middleware('staff.permission:pos,printDocket')->name('pos.dockets.show');
    Route::post('/settings/tables', [PosController::class, 'storeTable'])->middleware('staff.permission:settings,manage')->name('settings.tables.store');
    Route::delete('/settings/tables/{table}', [PosController::class, 'deleteTable'])->middleware('staff.permission:settings,manage')->name('settings.tables.destroy');
    Route::put('/settings/payment-modes', [PosController::class, 'savePaymentModes'])->middleware('staff.permission:settings,manage')->name('settings.payment-modes.update');

    Route::get('/sales', [SalesController::class, 'index'])->middleware('staff.permission:sales')->name('sales');
    Route::post('/sales/bulk-delete', [SalesController::class, 'destroyMany'])->middleware('staff.permission:sales,delete')->name('sales.bulk-destroy');
    Route::put('/sales/{order}', [SalesController::class, 'update'])->middleware('staff.permission:sales,edit')->name('sales.update');
    Route::patch('/sales/{order}/backdate', [SalesController::class, 'backdate'])->middleware('staff.permission:sales,backdate')->name('sales.backdate');
    Route::delete('/sales/{order}', [SalesController::class, 'destroy'])->middleware('staff.permission:sales,delete')->name('sales.destroy');

    Route::get('/kitchen', [KitchenController::class, 'index'])->middleware('staff.permission:kitchen')->name('kitchen');
    Route::get('/kitchen/raw-materials/template', [KitchenController::class, 'materialTemplate'])->middleware('staff.permission:kitchen,rawAdd')->name('kitchen.materials.template');
    Route::post('/kitchen/raw-materials/import-preview', [KitchenController::class, 'previewMaterials'])->middleware('staff.permission:kitchen,rawAdd')->name('kitchen.materials.preview');
    Route::post('/kitchen/raw-materials', [KitchenController::class, 'storeMaterial'])->middleware('staff.permission:kitchen,rawAdd')->name('kitchen.materials.store');
    Route::put('/kitchen/raw-materials/{material}', [KitchenController::class, 'updateMaterial'])->middleware('staff.permission:kitchen,rawEdit')->name('kitchen.materials.update');
    Route::delete('/kitchen/raw-materials/{material}', [KitchenController::class, 'deleteMaterial'])->middleware('staff.permission:kitchen,rawDelete')->name('kitchen.materials.destroy');
    Route::post('/kitchen/raw-materials/{id}/restore', [KitchenController::class, 'restoreMaterial'])->middleware('staff.permission:kitchen,rawDelete')->name('kitchen.materials.restore');
    Route::delete('/kitchen/raw-materials/{id}/permanent', [KitchenController::class, 'permanentlyDeleteMaterial'])->middleware('staff.permission:kitchen,rawDelete')->name('kitchen.materials.permanent');
    Route::post('/kitchen/raw-materials/restock', [KitchenController::class, 'restockMaterials'])->middleware('staff.permission:kitchen,rawAdd')->name('kitchen.materials.restock');
    Route::post('/kitchen/raw-materials/adjust', [KitchenController::class, 'adjustMaterials'])->middleware('staff.permission:kitchen,rawEdit')->name('kitchen.materials.adjust');
    Route::post('/kitchen/raw-materials/import', [KitchenController::class, 'importMaterials'])->middleware('staff.permission:kitchen,rawAdd')->name('kitchen.materials.import');
    Route::post('/settings/kitchen/raw-material-categories', [KitchenController::class, 'storeCategory'])->middleware('staff.permission:settings,manage')->name('settings.kitchen.categories.store');
    Route::delete('/settings/kitchen/raw-material-categories/{category}', [KitchenController::class, 'deleteCategory'])->middleware('staff.permission:settings,manage')->name('settings.kitchen.categories.destroy');
    Route::post('/kitchen/recipes', [KitchenController::class, 'storeRecipe'])->middleware('staff.permission:kitchen,recipeAdd')->name('kitchen.recipes.store');
    Route::put('/kitchen/recipes/{recipe}', [KitchenController::class, 'updateRecipe'])->middleware('staff.permission:kitchen,recipeEdit')->name('kitchen.recipes.update');
    Route::delete('/kitchen/recipes/{recipe}', [KitchenController::class, 'deleteRecipe'])->middleware('staff.permission:kitchen,recipeDelete')->name('kitchen.recipes.destroy');
    Route::post('/kitchen/recipes/{id}/restore', [KitchenController::class, 'restoreRecipe'])->middleware('staff.permission:kitchen,recipeDelete')->name('kitchen.recipes.restore');
    Route::delete('/kitchen/recipes/{id}/permanent', [KitchenController::class, 'permanentlyDeleteRecipe'])->middleware('staff.permission:kitchen,recipeDelete')->name('kitchen.recipes.permanent');
    Route::post('/kitchen/food-items', [KitchenController::class, 'storeFoodItem'])->middleware('staff.permission:kitchen,foodAdd')->name('kitchen.food-items.store');
    Route::put('/kitchen/food-items/{foodItem}', [KitchenController::class, 'updateFoodItem'])->middleware('staff.permission:kitchen,foodEdit')->name('kitchen.food-items.update');
    Route::delete('/kitchen/food-items/{foodItem}', [KitchenController::class, 'deleteFoodItem'])->middleware('staff.permission:kitchen,foodDelete')->name('kitchen.food-items.destroy');
    Route::post('/kitchen/food-items/{id}/restore', [KitchenController::class, 'restoreFoodItem'])->middleware('staff.permission:kitchen,foodDelete')->name('kitchen.food-items.restore');
    Route::delete('/kitchen/food-items/{id}/permanent', [KitchenController::class, 'permanentlyDeleteFoodItem'])->middleware('staff.permission:kitchen,foodDelete')->name('kitchen.food-items.permanent');

    Route::get('/inventory', [InventoryController::class, 'index'])->middleware('staff.permission:inventory')->name('inventory');
    Route::post('/inventory/products', [InventoryController::class, 'store'])->middleware('staff.permission:inventory,addRestock')->name('inventory.products.store');
    Route::put('/inventory/products/{product}', [InventoryController::class, 'update'])->middleware('staff.permission:inventory,editAdjust')->name('inventory.products.update');
    Route::delete('/inventory/products/{product}', [InventoryController::class, 'destroy'])->middleware('staff.permission:inventory,delete')->name('inventory.products.destroy');
    Route::post('/inventory/products/{product}/restore', [InventoryController::class, 'restoreProduct'])->middleware('staff.permission:inventory,delete')->name('inventory.products.restore');
    Route::delete('/inventory/products/{product}/permanent', [InventoryController::class, 'permanentlyDeleteProduct'])->middleware('staff.permission:inventory,delete')->name('inventory.products.permanent');
    Route::post('/inventory/stocks/{stock}/adjust', [InventoryController::class, 'adjust'])->middleware('staff.permission:inventory,editAdjust')->name('inventory.stocks.adjust');
    Route::put('/inventory/stocks/{stock}', [InventoryController::class, 'updateStock'])->middleware('staff.permission:inventory,editAdjust')->name('inventory.stocks.update');
    Route::post('/inventory/adjustments', [InventoryController::class, 'adjustBulk'])->middleware('staff.permission:inventory,editAdjust')->name('inventory.adjustments.store');
    Route::post('/inventory/restock', [InventoryController::class, 'restock'])->middleware('staff.permission:inventory,addRestock')->name('inventory.restock');
    Route::post('/inventory/transfer', [InventoryController::class, 'transfer'])->middleware('staff.permission:inventory,transfer')->name('inventory.transfer');
    Route::post('/inventory/products/import', [InventoryController::class, 'import'])->middleware('staff.permission:inventory,addRestock')->name('inventory.products.import');
    Route::post('/settings/inventory/categories', [InventoryController::class, 'storeCategory'])->middleware('staff.permission:settings,manage')->name('settings.inventory.categories.store');
    Route::delete('/settings/inventory/categories/{category}', [InventoryController::class, 'destroyCategory'])->middleware('staff.permission:settings,manage')->name('settings.inventory.categories.destroy');
    Route::post('/settings/inventory/counters', [InventoryController::class, 'storeCounter'])->middleware('staff.permission:settings,manage')->name('settings.inventory.counters.store');
    Route::delete('/settings/inventory/counters/{counter}', [InventoryController::class, 'destroyCounter'])->middleware('staff.permission:settings,manage')->name('settings.inventory.counters.destroy');
    Route::post('/settings/inventory/units', [InventoryController::class, 'storeUnit'])->middleware('staff.permission:settings,manage')->name('settings.inventory.units.store');
    Route::delete('/settings/inventory/units', [InventoryController::class, 'destroyUnit'])->middleware('staff.permission:settings,manage')->name('settings.inventory.units.destroy');

    Route::get('/inventory/products/template', [ProductImportController::class, 'download'])->middleware('staff.permission:inventory,addRestock')
        ->name('inventory.products.template');

    Route::post('/inventory/products/import/preview', [ProductImportController::class, 'preview'])->middleware('staff.permission:inventory,addRestock')
        ->name('inventory.products.import.preview');

    Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('staff.permission:expenses')->name('expenses');
    Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('staff.permission:expenses,add')->name('expenses.store');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('staff.permission:expenses,delete')->name('expenses.destroy');
    Route::post('/expenses/{expense}/restore', [ExpenseController::class, 'restore'])->middleware('staff.permission:expenses,delete')->name('expenses.restore');
    Route::delete('/expenses/{expense}/permanent', [ExpenseController::class, 'permanentlyDelete'])->middleware('staff.permission:expenses,delete')->name('expenses.permanent');
    Route::post('/settings/expense-categories', [ExpenseController::class, 'storeCategory'])->middleware('staff.permission:settings,manage')->name('settings.expense-categories.store');
    Route::delete('/settings/expense-categories/{category}', [ExpenseController::class, 'destroyCategory'])->middleware('staff.permission:settings,manage')->name('settings.expense-categories.destroy');
    Route::post('/settings/expense-categories/{category}/restore', [ExpenseController::class, 'restoreCategory'])->middleware('staff.permission:settings,manage')->name('settings.expense-categories.restore');
    Route::delete('/settings/expense-categories/{category}/permanent', [ExpenseController::class, 'permanentlyDeleteCategory'])->middleware('staff.permission:settings,manage')->name('settings.expense-categories.permanent');

    Route::get('/reports', [ReportController::class, 'index'])->middleware('staff.permission:reports,viewExport')->name('reports');
    Route::get('/reports/export/excel', [ReportController::class, 'excel'])->middleware('staff.permission:reports,viewExport')->name('reports.excel');
    Route::get('/reports/export/pdf', [ReportController::class, 'printReport'])->middleware('staff.permission:reports,viewExport')->name('reports.pdf');

    Route::get('/orders', [PageController::class, 'orders'])->name('orders');

    Route::get('/tables', [PageController::class, 'tables'])->name('tables');

    Route::get('/customers', [PageController::class, 'customers'])->name('customers');

    Route::get('/staff', [StaffController::class, 'index'])->middleware('staff.permission:staff')->name('staff');
    Route::get('/staff/data', [StaffController::class, 'data'])->middleware('staff.permission:staff')->name('staff.data');
    Route::post('/staff/authorize', [StaffController::class, 'authorizeAdministrator'])->middleware('staff.permission:staff,add')->name('staff.authorize');
    Route::post('/staff', [StaffController::class, 'store'])->middleware('staff.permission:staff,add')->name('staff.store');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->middleware('staff.permission:staff,add')->name('staff.update');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->middleware('staff.permission:staff,add')->name('staff.destroy');
    Route::put('/staff/{staff}/permissions', [StaffController::class, 'updatePermissions'])->middleware('staff.permission:staff,add')->name('staff.permissions.update');
    Route::get('/staff/{staff}/pin', [StaffController::class, 'revealPin'])->middleware('administrator')->name('staff.pin.show');
    Route::post('/staff/{staff}/pin', [StaffController::class, 'regeneratePin'])->middleware('administrator')->name('staff.pin');
    Route::post('/staff/{staff}/restore', [StaffController::class, 'restoreStaff'])->middleware('staff.permission:settings,manage')->name('staff.restore');
    Route::delete('/staff/{staff}/permanent', [StaffController::class, 'permanentlyDeleteStaff'])->middleware('staff.permission:settings,manage')->name('staff.permanent');
    Route::post('/settings/staff-roles', [StaffController::class, 'storeRole'])->middleware(['administrator'])->name('settings.staff-roles.store');
    Route::delete('/settings/staff-roles/{role}', [StaffController::class, 'destroyRole'])->middleware(['administrator'])->name('settings.staff-roles.destroy');
    Route::post('/settings/staff-roles/{role}/restore', [StaffController::class, 'restoreRole'])->middleware(['administrator'])->name('settings.staff-roles.restore');
    Route::delete('/settings/staff-roles/{role}/permanent', [StaffController::class, 'permanentlyDeleteRole'])->middleware(['administrator'])->name('settings.staff-roles.permanent');

    Route::get('/rooms', [RoomController::class, 'index'])->middleware('staff.permission:rooms,view')->name('rooms');
    Route::get('/rooms/data', [RoomController::class, 'data'])->middleware('staff.permission:rooms,view')->name('rooms.data');
    Route::get('/rooms/board', [RoomController::class, 'board'])->middleware('staff.permission:rooms,view')->name('rooms.board');
    Route::post('/rooms', [RoomController::class, 'storeRoom'])->middleware('staff.permission:rooms,manage')->name('rooms.store');
    Route::put('/rooms/{room}', [RoomController::class, 'updateRoom'])->middleware('staff.permission:rooms,manage')->name('rooms.update');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroyRoom'])->middleware('staff.permission:rooms,manage')->name('rooms.destroy');
    Route::post('/rooms/{room}/check-in', [RoomController::class, 'checkIn'])->middleware('staff.permission:rooms,checkIn')->name('rooms.check-in');
    Route::post('/rooms/{room}/ready', [RoomController::class, 'markReady'])->middleware('staff.permission:rooms,housekeeping')->name('rooms.ready');
    Route::post('/rooms/reservations', [RoomController::class, 'storeReservation'])->middleware('staff.permission:rooms,reservations')->name('rooms.reservations.store');
    Route::post('/rooms/reservations/{reservation}/check-in', [RoomController::class, 'checkInReservation'])->middleware('staff.permission:rooms,checkIn')->name('rooms.reservations.check-in');
    Route::post('/rooms/reservations/{reservation}/cancel', [RoomController::class, 'cancelReservation'])->middleware('staff.permission:rooms,reservations')->name('rooms.reservations.cancel');
    Route::post('/rooms/stays/{stay}/charges', [RoomController::class, 'storeCharge'])->middleware('staff.permission:rooms,checkIn')->name('rooms.charges.store');
    Route::delete('/rooms/stays/{stay}/charges/{charge}', [RoomController::class, 'destroyCharge'])->middleware('staff.permission:rooms,checkIn')->name('rooms.charges.destroy');
    Route::post('/rooms/stays/{stay}/payments', [RoomController::class, 'storePayment'])->middleware('staff.permission:rooms,checkIn')->name('rooms.payments.store');
    Route::post('/rooms/stays/{stay}/checkout', [RoomController::class, 'checkout'])->middleware('staff.permission:rooms,checkOut')->name('rooms.checkout');
    Route::post('/rooms/expenses', [RoomController::class, 'storeExpense'])->middleware('staff.permission:rooms,finance')->name('rooms.expenses.store');
    Route::delete('/rooms/expenses/{expense}', [RoomController::class, 'destroyExpense'])->middleware('staff.permission:rooms,finance')->name('rooms.expenses.destroy');

    Route::get('/settings', [SettingsController::class, 'index'])->middleware('staff.permission:settings,manage')->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->middleware('staff.permission:settings,manage')->name('settings.update');
    Route::post('/settings/logo', [SettingsController::class, 'uploadLogo'])->middleware('staff.permission:settings,manage')->name('settings.logo.upload');
    Route::delete('/settings/logo', [SettingsController::class, 'removeLogo'])->middleware('staff.permission:settings,manage')->name('settings.logo.remove');
    // Administrator profile
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/pin', [ProfileController::class, 'regeneratePin'])->name('profile.pin');
    Route::post('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');

    Route::get('/{page}', [PageController::class, 'dashboardFallback'])->where('page', 'none');
});
