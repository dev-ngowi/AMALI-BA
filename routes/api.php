<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ItemGroupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CountriesController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerTypeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemTypeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentTypeController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionModuleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserRoleController;
use App\Http\Controllers\DamageStockController;
use App\Http\Controllers\CashReconciliationController;

// Protect this route with sanctum
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// User Routes
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::post('/login', [UserController::class, 'login']);

// ITEM GROUP 
Route::get('/item-groups', [ItemGroupController::class, 'index']);
Route::post('/item-groups', [ItemGroupController::class, 'store']);
Route::get('/item-groups/check-name', [ItemGroupController::class, 'checkName']);
Route::put('/item-groups/{id}', [ItemGroupController::class, 'update']);
Route::delete('/item-groups/{id}', [ItemGroupController::class, 'destroy']);

// CATEGORY
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::put('/categories/{id}', [CategoryController::class, 'update']);
Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

// STORE
Route::get('/stores', [StoreController::class, 'index']);
Route::post('/stores', [StoreController::class, 'store']);
Route::get('/stores/{id}', [StoreController::class, 'show']);
Route::put('/stores/{id}', [StoreController::class, 'update']);
Route::delete('/stores/{id}', [StoreController::class, 'destroy']);
Route::get('/stores/users', [StoreController::class, 'users']);

// COUNTRY
Route::get('/countries', [CountriesController::class, 'index']);
Route::post('/countries', [CountriesController::class, 'store']);
Route::delete('/countries/{id}', [CountriesController::class, 'destroy']);


// CITY
Route::get('/cities', [CityController::class, 'index']);
Route::post('/cities', [CityController::class, 'store']);
Route::put('/cities/{id}', [CityController::class, 'update']);
Route::delete('/cities/{id}', [CityController::class, 'destroy']);

// VENDOR
Route::get('/vendors', [VendorController::class, 'index']);
Route::get('/vendors/{id}', [VendorController::class, 'show']);
Route::post('/vendors', [VendorController::class, 'store']);
Route::put('/vendors/{id}', [VendorController::class, 'update']);
Route::delete('/vendors/{id}', [VendorController::class, 'destroy']);

// PURCHASE ORDER
Route::get('/purchase_orders', [PurchaseController::class, 'indexPurchaseOrders']);
Route::post('/purchase_orders', [PurchaseController::class, 'createPurchaseOrder']);

// Purchase Order Routes
Route::get('/purchase_orders', [PurchaseController::class, 'indexPurchaseOrders']);
Route::post('/purchase_orders', [PurchaseController::class, 'createPurchaseOrder']);

// Good Receipt Note Routes
Route::get('/good_receipt_notes', [PurchaseController::class, 'indexGoodReceiptNotes']);
Route::post('/good_receipt_notes', [PurchaseController::class, 'createGoodReceiptNote']);

// Day Status Route
Route::get('/day_status', [PurchaseController::class, 'checkDayStatus']);

// EXPENSE
Route::get('/expenses', [ExpenseController::class, 'index']);
Route::post('/expenses', [ExpenseController::class, 'store']);
Route::put('/expenses/{id}', [ExpenseController::class, 'update']);
Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);

// UNIT
Route::get('/units', [UnitController::class, 'index']);
Route::get('/units/{id}', [UnitController::class, 'show']);
Route::post('/units', [UnitController::class, 'store']);
Route::put('/units/{id}', [UnitController::class, 'update']);
Route::delete('/units/{id}', [UnitController::class, 'destroy']);

// CUSTOMER TYPE
Route::get('/customer-types', [CustomerTypeController::class, 'index']);
Route::get('/customer-types/{id}', [CustomerTypeController::class, 'show']);
Route::post('/customer-types', [CustomerTypeController::class, 'store']);
Route::put('/customer-types/{id}', [CustomerTypeController::class, 'update']);
Route::delete('/customer-types/{id}', [CustomerTypeController::class, 'destroy']);

// CUSTOMER
Route::get('/customers', [CustomerController::class, 'index']);
Route::get('/customers/{id}', [CustomerController::class, 'show']);
Route::post('/customers', [CustomerController::class, 'store']);
Route::put('/customers/{id}', [CustomerController::class, 'update']);
Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

// INVOICE
Route::get('/invoices', [InvoiceController::class, 'index']);
Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
Route::post('/invoices', [InvoiceController::class, 'store']);
Route::put('/invoices/{id}', [InvoiceController::class, 'update']);
Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);

// PAYMENT
Route::get('/payments', [PaymentController::class, 'index']);
Route::get('/payments/{id}', [PaymentController::class, 'show']);
Route::post('/payments', [PaymentController::class, 'store']);
Route::put('/payments/{id}', [PaymentController::class, 'update']);
Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);

// PAYMENT TYPE
Route::get('/payment_types', [PaymentTypeController::class, 'index']);
Route::get('/payment_types/{id}', [PaymentTypeController::class, 'show']);
Route::post('/payment_types', [PaymentTypeController::class, 'store']);
Route::put('/payment_types/{id}', [PaymentTypeController::class, 'update']);
Route::delete('/payment_types/{id}', [PaymentTypeController::class, 'destroy']);

// CART
Route::get('/carts', [CartController::class, 'index']);
Route::get('/carts/{id}', [CartController::class, 'show']);
Route::post('/carts', [CartController::class, 'store']);
Route::put('/carts/{id}', [CartController::class, 'update']);
Route::delete('/carts/{id}', [CartController::class, 'destroy']);

// CHART OF ACCOUNTS
Route::get('/chart_of_accounts', [ChartOfAccountController::class, 'index']);
Route::get('/chart_of_accounts/{id}', [ChartOfAccountController::class, 'show']);
Route::post('/chart_of_accounts', [ChartOfAccountController::class, 'store']);
Route::put('/chart_of_accounts/{id}', [ChartOfAccountController::class, 'update']);
Route::delete('/chart_of_accounts/{id}', [ChartOfAccountController::class, 'destroy']);

// GENERAL LEDGER
Route::get('/general_ledger', [GeneralLedgerController::class, 'index']);
Route::get('/general_ledger/{id}', [GeneralLedgerController::class, 'show']);
Route::post('/general_ledger', [GeneralLedgerController::class, 'store']);
Route::put('/general_ledger/{id}', [GeneralLedgerController::class, 'update']);
Route::delete('/general_ledger/{id}', [GeneralLedgerController::class, 'destroy']);

// TAX
Route::get('/taxes', [TaxController::class, 'index']);
Route::get('/taxes/{id}', [TaxController::class, 'show']);
Route::post('/taxes', [TaxController::class, 'store']);
Route::put('/taxes/{id}', [TaxController::class, 'update']);
Route::delete('/taxes/{id}', [TaxController::class, 'destroy']);

// ITEM
Route::get('/items', [ItemController::class, 'index']);
Route::get('/items/{id}', [ItemController::class, 'show']);
Route::post('/items', [ItemController::class, 'store']);
Route::post('items/batch', [ItemController::class, 'storeBatch']);
Route::put('/items/{id}', [ItemController::class, 'update']);
Route::delete('/items/{id}', [ItemController::class, 'destroy']);

// ITEM TYPE
Route::get('/item_types', [ItemTypeController::class, 'index']);
Route::get('/item_types/{id}', [ItemTypeController::class, 'show']);
Route::post('/item_types', [ItemTypeController::class, 'store']);
Route::put('/item_types/{id}', [ItemTypeController::class, 'update']);
Route::delete('/item_types/{id}', [ItemTypeController::class, 'destroy']);

// ORDER
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders', [OrderController::class, 'getOrders']);
Route::post('/orders/batch', [OrderController::class, 'storeBatch']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::put('/orders/{id}', [OrderController::class, 'update']);

// ITEM BRAND
Route::get('/item_brands', [BrandController::class, 'index']);
Route::get('/item_brands/{id}', [BrandController::class, 'show']);
Route::post('/item_brands', [BrandController::class, 'store']);
Route::put('/item_brands/{id}', [BrandController::class, 'update']);
Route::delete('/item_brands/{id}', [BrandController::class, 'destroy']);

//ROLES 
Route::get('/roles', [RoleController::class, 'index']);
Route::get('/roles/{id}', [RoleController::class, 'show']);
Route::post('/roles', [RoleController::class, 'store']);
Route::put('/roles/{id}', [RoleController::class, 'update']);
Route::delete('/roles/{id}', [RoleController::class, 'destroy']);


//PERMISSION MODULES 
Route::get('/permission_modules', [PermissionModuleController::class, 'index']);
Route::get('/permission_modules/{id}', [PermissionModuleController::class, 'show']);
Route::post('/permission_modules', [PermissionModuleController::class, 'store']);
Route::put('/permission_modules/{id}', [PermissionModuleController::class, 'update']);
Route::delete('/permission_modules/{id}', [PermissionModuleController::class, 'destroy']);

//PERMISSIONs
Route::get('/permissions', [PermissionController::class, 'index']);
Route::get('/permissions/{id}', [PermissionController::class, 'show']);
Route::post('/permissions', [PermissionController::class, 'store']);
Route::put('/permissions/{id}', [PermissionController::class, 'update']);
Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);

//USER ROLES    
Route::get('/user_roles', [UserRoleController::class, 'index']);
Route::get('/user_roles/{id}', [UserRoleController::class, 'show']);
Route::post('/user_roles', [UserRoleController::class, 'store']);
Route::put('/user_roles/{id}', [UserRoleController::class, 'update']);
Route::delete('/user_roles/{id}', [UserRoleController::class, 'destroy']);

//DAMAGE STOCKS 
Route::apiResource('damage_stocks', DamageStockController::class);
Route::post('damage_stocks/{id}/restore', [DamageStockController::class, 'restore']);

//CASH RECONCILIATION 
Route::apiResource('cash-reconciliations', CashReconciliationController::class);
Route::post('cash-reconciliations/{id}/restore', [CashReconciliationController::class, 'restore']);