<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\PriceController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\DeliveryChargeController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\EmailController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public category routes (GET only)
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

// Public item routes (GET only)
Route::get('/items', [ItemController::class, 'index']);
Route::get('/items/by-category', [ItemController::class, 'getItemsByCategory']);

// Public discount routes (GET only)
Route::get('/discounts', [DiscountController::class, 'index']);
Route::get('/discounts/{id}', [DiscountController::class, 'show']);

// Public city routes (GET only)
Route::get('/cities', [CityController::class, 'index']);
Route::get('/cities/{id}', [CityController::class, 'show']);

// Public delivery charge routes (GET only)
Route::get('/delivery-charges', [DeliveryChargeController::class, 'index']);
Route::get('/delivery-charges/{id}', [DeliveryChargeController::class, 'show']);

// Public restaurant route (GET only)
Route::get('/my-restaurant', [RestaurantController::class, 'show']);

// Public cart routes (no authentication required)
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart', [CartController::class, 'store']);
Route::put('/cart/update-quantity', [CartController::class, 'updateQuantity']);
Route::delete('/cart/item', [CartController::class, 'deleteItem']);
Route::delete('/cart/{id}', [CartController::class, 'deleteCart']);
Route::post('/cart/apply-discount', [CartController::class, 'applyDiscount']);
Route::delete('/cart/remove-discount', [CartController::class, 'removeDiscount']);

// Public contact route (POST only)
Route::post('/contact', [ContactController::class, 'store']);

// Public order routes
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{id}', [OrderController::class, 'show']);

// Public email routes
Route::post('/v1/emails/send', [EmailController::class, 'send']);
Route::get('/v1/emails/types', [EmailController::class, 'getTypes']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Address route (requires authentication)
    Route::post('/addresses', [AddressController::class, 'store']);
    // User routes (authenticated users)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/update', [AuthController::class, 'update']);
    Route::delete('/user/delete', [AuthController::class, 'delete']);

    // Admin routes (require admin role)
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/users', [AdminController::class, 'index']);
        Route::get('/users/{id}', [AdminController::class, 'show']);
        Route::post('/users', [AdminController::class, 'store']);
        Route::put('/users/{id}', [AdminController::class, 'update']);
        Route::delete('/users/{id}', [AdminController::class, 'destroy']);
    });
    
    // Category management routes (Admin only - require authentication + admin role)
    Route::middleware('role:admin')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    });

    // City management routes (Admin only - require authentication + admin role)
    Route::middleware('role:admin')->group(function () {
        Route::post('/cities', [CityController::class, 'store']);
        Route::put('/cities/{id}', [CityController::class, 'update']);
        Route::delete('/cities/{id}', [CityController::class, 'destroy']);
    });

    // Discount management routes (Admin only - require authentication + admin role)
    Route::middleware('role:admin')->group(function () {
        Route::post('/discounts', [DiscountController::class, 'store']);
        Route::put('/discounts/{id}', [DiscountController::class, 'update']);
        Route::delete('/discounts/{id}', [DiscountController::class, 'destroy']);
    });

    // Delivery charge management routes (Admin only - require authentication + admin role)
    Route::middleware('role:admin')->group(function () {
        Route::post('/delivery-charges', [DeliveryChargeController::class, 'store']);
        Route::put('/delivery-charges/{id}', [DeliveryChargeController::class, 'update']);
        Route::delete('/delivery-charges/{id}', [DeliveryChargeController::class, 'destroy']);
    });

    // MyRestaurant (singleton) - Admin only
    Route::middleware('role:admin')->group(function () {
        Route::post('/my-restaurant', [RestaurantController::class, 'store']);
        Route::match(['put', 'post'], '/my-restaurant/{id}', [RestaurantController::class, 'update']);
    });

    // Cart management routes (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/carts', [CartController::class, 'getAllCarts']);
    });
    
    // Order management routes (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::put('/orders/{id}', [OrderController::class, 'updateStatus']);
    });
    
    // Item management routes (Admin only - require authentication + admin role)
    Route::middleware('role:admin')->group(function () {
        Route::post('/items', [ItemController::class, 'store']);
        Route::match(['put', 'post'], '/items/{id}', [ItemController::class, 'update']);
        Route::delete('/items/{id}', [ItemController::class, 'destroy']);
        
        // Price management routes for items (Admin only)
        Route::post('/items/{itemId}/prices', [PriceController::class, 'store']);
        Route::put('/items/{itemId}/prices/{priceId}', [PriceController::class, 'update']);
        Route::delete('/items/{itemId}/prices/{priceId}', [PriceController::class, 'destroy']);
    });

    // Contact management routes (Admin only - require authentication + admin role)
    Route::middleware('role:admin')->group(function () {
        Route::get('/contact', [ContactController::class, 'index']);
        Route::get('/contact/{id}', [ContactController::class, 'show']);
        Route::put('/contact/{id}', [ContactController::class, 'update']);
        Route::delete('/contact/{id}', [ContactController::class, 'destroy']);
    });

    // Address management routes (Admin only - require authentication + admin role)
    Route::middleware('role:admin')->group(function () {
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::get('/addresses/{id}', [AddressController::class, 'show']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
    });
});

