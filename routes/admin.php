<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FlashSaleController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SellerApprovalController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/**
 * Admin area (spec §17): Inertia pages behind `auth` + `can:admin`.
 * Pages are thin shells; lists and forms fetch from `/api/v1/admin/*`.
 */
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('', DashboardController::class)->name('dashboard');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');

    Route::get('sellers', [SellerController::class, 'index'])->name('sellers.index');
    Route::get('seller-approvals', [SellerApprovalController::class, 'index'])->name('seller-approvals.index');
    Route::post('sellers/{seller}/approve', [SellerApprovalController::class, 'approve'])->name('sellers.approve');
    Route::post('sellers/{seller}/reject', [SellerApprovalController::class, 'reject'])->name('sellers.reject');
    Route::get('sellers/{seller}/edit', [SellerController::class, 'edit'])->name('sellers.edit');
    Route::get('sellers/{seller}', [SellerController::class, 'show'])->name('sellers.show');

    Route::get('flash-sales', [FlashSaleController::class, 'index'])->name('flash-sales.index');
    Route::get('flash-sales/create', [FlashSaleController::class, 'create'])->name('flash-sales.create');
    Route::get('flash-sales/{flashSale:id}/edit', [FlashSaleController::class, 'edit'])->name('flash-sales.edit');
    Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
    Route::get('coupons/create', [CouponController::class, 'create'])->name('coupons.create');
    Route::get('coupons/{coupon}/edit', [CouponController::class, 'edit'])->name('coupons.edit');

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product:id}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::get('products/{product:id}', [ProductController::class, 'show'])->name('products.show');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{orderNumber}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

    Route::get('settings/{group?}', [SettingController::class, 'show'])->name('settings.show');
});
