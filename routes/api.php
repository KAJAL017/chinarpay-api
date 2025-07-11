<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\admin\ProductBrandController;
use App\Http\Controllers\admin\CustomersController;
use App\Http\Controllers\admin\ProductCategoryController;
use App\Http\Controllers\admin\AdminAuthController;
use App\Http\Controllers\admin\EmandateController;
use App\Http\Controllers\admin\RazorpayController;
use App\Http\Controllers\admin\ProductController;
use App\Http\Controllers\user\UserAuthController;
use App\Http\Controllers\user\UserController;


// USER ROUTES HERE
Route::post('/user/login', [UserAuthController::class, 'login']);
Route::get('/user/subscriptions/{customerId}', [UserController::class, 'getUserSubscriptions']);
Route::get('/user/get-razorpay-key', [UserController::class, 'getRazorpayKey']);
Route::post('/user/pay-subscription-invoice/{subscription_id}', [UserController::class, 'createSubscriptionPayment']);
Route::get('/user/subscription-details/{subscriptionId}', [UserController::class, 'getSingleSubscriptionDetails']);




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


//  PRODUCTS ROUTES HERE

// Route::get('admin/customers', [CustomersController::class, 'index']);
// Route::get('admin/customers/count', [CustomersController::class, 'count']);
Route::post('admin/products', [ProductController::class, 'store']);
// Route::put('admin/customers/{id}', [CustomersController::class, 'update']);
// Route::delete('admin/customers/{id}', [CustomersController::class, 'destroy']);
// Route::post('admin/customers/delete-multiple', [CustomersController::class, 'deleteMultiple']);
// Route::get('admin/users', [CustomersController::class, 'users']);


//  CUSTOMERS ROUTES HERE

Route::get('admin/customers', [CustomersController::class, 'index']);
Route::get('admin/customers/count', [CustomersController::class, 'count']);
Route::post('admin/customers', [CustomersController::class, 'store']);
Route::put('admin/customers/{id}', [CustomersController::class, 'update']);
Route::delete('admin/customers/{id}', [CustomersController::class, 'destroy']);
Route::post('admin/customers/delete-multiple', [CustomersController::class, 'deleteMultiple']);
Route::get('admin/users', [CustomersController::class, 'users']);

// PRODUCT E-MANDATRE ROUTES HERE

// Route::get('admin/customers', [CustomersController::class, 'index']);
Route::post('admin/emandate', [EmandateController::class, 'create']);
// Route::put('admin/customers/{id}', [CustomersController::class, 'update']);
// Route::delete('admin/customers/{id}', [CustomersController::class, 'destroy']);
// Route::post('admin/customers/delete-multiple', [CustomersController::class, 'deleteMultiple']);



// RAZORPAY ROUTES HERE
Route::get('admin/razorpay/subscriptions', [RazorpayController::class, 'getAllSubscriptions']);
Route::get('admin/razorpay/subscription-details/{subscriptionId}', [RazorpayController::class, 'getSubscriptionDetails']);
Route::get('admin/razorpay/active-mandates-count', [RazorpayController::class, 'getActiveMandatesCount']);
Route::get('admin/razorpay/pending-mandates-count', [RazorpayController::class, 'getPendingMandatesCount']);
Route::get('admin/razorpay/monthly-collection', [RazorpayController::class, 'getMonthlyCollection']);
