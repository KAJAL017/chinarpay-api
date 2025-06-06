<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\admin\ProductBrandController;
use App\Http\Controllers\admin\CustomersController;
use App\Http\Controllers\admin\ProductCategoryController;
use App\Http\Controllers\admin\AdminAuthController;
use App\Http\Controllers\admin\EmandateController;
use App\Http\Controllers\admin\RazorpayController;




Route::post('/admin/login', [AdminAuthController::class, 'login']);

// PRODUCT CATEGORIES ROUTES HERE

Route::get('admin/categories', [ProductCategoryController::class, 'index']);
Route::post('admin/categories', [ProductCategoryController::class, 'store']);
Route::put('admin/categories/{id}', [ProductCategoryController::class, 'update']);
Route::delete('admin/categories/{id}', [ProductCategoryController::class, 'destroy']);
Route::post('admin/categories/delete-multiple', [ProductCategoryController::class, 'deleteMultiple']);


// PRODUCT BRAND ROUTES HERE

Route::get('admin/brand', [ProductBrandController::class, 'index']);
Route::post('admin/brand', [ProductBrandController::class, 'store']);
Route::put('admin/brand/{id}', [ProductBrandController::class, 'update']);
Route::delete('admin/brand/{id}', [ProductBrandController::class, 'destroy']);
Route::post('admin/brand/delete-multiple', [ProductBrandController::class, 'deleteMultiple']);


// PRODUCT CUSTOMERS ROUTES HERE

Route::get('admin/customers', [CustomersController::class, 'index']);
Route::post('admin/customers', [CustomersController::class, 'store']);
Route::put('admin/customers/{id}', [CustomersController::class, 'update']);
Route::delete('admin/customers/{id}', [CustomersController::class, 'destroy']);
Route::post('admin/customers/delete-multiple', [CustomersController::class, 'deleteMultiple']);


// PRODUCT E-MANDATRE ROUTES HERE

// Route::get('admin/customers', [CustomersController::class, 'index']);
Route::post('admin/emandate', [EmandateController::class, 'create']);
// Route::put('admin/customers/{id}', [CustomersController::class, 'update']);
// Route::delete('admin/customers/{id}', [CustomersController::class, 'destroy']);
// Route::post('admin/customers/delete-multiple', [CustomersController::class, 'deleteMultiple']);



// RAZORPAY ROUTES HERE
Route::get('/razorpay/plans', [RazorpayController::class, 'getPlans']);