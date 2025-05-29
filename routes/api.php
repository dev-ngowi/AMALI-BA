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

// Protect this route with sanctum
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// User Routes
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::post('/login', [UserController::class, 'login']);

// ITEM GROUP 
Route::get('/item_groups', [ItemGroupController::class, 'index']);
Route::post('/item_groups', [ItemGroupController::class, 'store']);
Route::get('/item_groups/check-name', [ItemGroupController::class, 'checkName']);

// CATEGORY
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);

// STORE
Route::get('/stores', [StoreController::class, 'index']);
Route::post('/stores', [StoreController::class, 'store']);

// COUNTRY
Route::get('/countries', [CountriesController::class, 'index']);
Route::post('/countries', [CountriesController::class, 'store']);

// CITY
Route::get('/cities', [CityController::class, 'index']);
Route::post('/cities', [CityController::class, 'store']);

// VENDOR
Route::get('/vendors', [VendorController::class, 'index']);
Route::post('/vendors', [VendorController::class, 'store']);

// PURCHASE ORDER
Route::get('/purchase_orders', [PurchaseController::class, 'indexPurchaseOrders']);
Route::post('/purchase_orders', [PurchaseController::class, 'createPurchaseOrder']);

// GOOD RECEIPT NOTE
Route::get('/good_receipt_notes', [PurchaseController::class, 'indexGoodReceiptNotes']);
Route::post('/good_receipt_notes', [PurchaseController::class, 'createGoodReceiptNote']);

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
Route::get('/orders', [OrderController::class, 'get']);
Route::get('/orders/{id}', [OrderController::class, 'show']);

// ITEM BRAND
Route::get('/item_brands', [BrandController::class, 'index']);
Route::get('/item_brands/{id}', [BrandController::class, 'show']);
Route::post('/item_brands', [BrandController::class, 'store']);
Route::put('/item_brands/{id}', [BrandController::class, 'update']);
Route::delete('/item_brands/{id}', [BrandController::class, 'destroy']);