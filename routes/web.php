<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/salons');
Route::post('/payments/momo/ipn', [\App\Http\Controllers\MomoController::class, 'ipn'])->name('momo.ipn');
Route::get('/salons', [\App\Http\Controllers\BookingController::class, 'salons'])->name('salons.index');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'create'])->middleware('throttle:registration')->name('register.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'active'])->group(function (): void {
    Route::controller(\App\Http\Controllers\PaymentController::class)->group(function (): void {
        Route::get('/customer/bookings/{booking}/payments', 'show')->name('payments.show');
        Route::post('/customer/bookings/{booking}/payments/{payment}/refunds', 'requestRefund')->middleware('throttle:20,1')->name('refunds.request');
        Route::get('/salon/branches/{branch}/bookings/{booking}/payments', 'salon')->name('salon.payments.show');
        Route::post('/salon/branches/{branch}/bookings/{booking}/payments', 'collect')->middleware('throttle:20,1')->name('salon.payments.collect');
        Route::post('/salon/branches/{branch}/bookings/{booking}/payments/{payment}/refunds', 'salonRefund')->middleware('throttle:20,1')->name('salon.refunds.request');
        Route::patch('/salon/branches/{branch}/bookings/{booking}/refunds/{refund}', 'review')->middleware('throttle:20,1')->name('salon.refunds.review');
    });
    Route::get('/salon/branches/{branch}/vouchers', [\App\Http\Controllers\VoucherController::class, 'index'])->name('vouchers.index');
    Route::post('/salon/branches/{branch}/vouchers', [\App\Http\Controllers\VoucherController::class, 'store'])->name('vouchers.store');
    Route::patch('/salon/branches/{branch}/vouchers/{voucher}', [\App\Http\Controllers\VoucherController::class, 'toggle'])->name('vouchers.toggle');
    Route::post('/customer/bookings/{booking}/momo', [\App\Http\Controllers\MomoController::class, 'checkout'])->middleware('throttle:5,1')->name('momo.checkout');
    Route::post('/bookings/{booking}/payments/{payment}/momo/reconcile', [\App\Http\Controllers\MomoController::class, 'reconcile'])->middleware('throttle:10,1')->name('momo.reconcile');
    Route::patch('/customer/bookings/{booking}/reschedule', [\App\Http\Controllers\RescheduleController::class, 'customer'])->middleware('throttle:20,1')->name('bookings.reschedule');
    Route::patch('/salon/branches/{branch}/bookings/{booking}/reschedule', [\App\Http\Controllers\RescheduleController::class, 'salon'])->middleware('throttle:20,1')->name('salon.bookings.reschedule');
    Route::get('/salons/{branch}/slots', \App\Http\Controllers\SlotController::class)->middleware('throttle:20,1')->name('bookings.slots');
    Route::controller(\App\Http\Controllers\BookingController::class)->group(function (): void {
        Route::get('/salons/{branch}/book', 'create')->name('bookings.create');
        Route::get('/salons/{branch}/availability', 'availability')->middleware('throttle:60,1')->name('bookings.availability');
        Route::post('/salons/{branch}/book', 'store')->middleware('throttle:10,1')->name('bookings.store');
        Route::get('/customer/bookings', 'index')->name('bookings.index');
        Route::get('/customer/bookings/{booking}', 'show')->name('bookings.show');
        Route::patch('/customer/bookings/{booking}/cancel', 'cancel')->name('bookings.cancel');
        Route::get('/salon/branches/{branch}/bookings', 'salon')->name('salon.bookings');
        Route::patch('/salon/branches/{branch}/bookings/{booking}', 'transition')->name('salon.bookings.status');
    });
    Route::scopeBindings()->prefix('/salon/branches/{branch}')->group(function (): void {
        Route::controller(\App\Http\Controllers\StaffController::class)->prefix('staff')->name('staff.')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{staff}/edit', 'edit')->name('edit');
            Route::put('/{staff}', 'update')->name('update');
            Route::put('/{staff}/hours', 'hours')->name('hours');
            Route::post('/{staff}/leaves', 'leave')->name('leave');
            Route::patch('/{staff}/leaves/{leave}', 'cancelLeave')->whereNumber('leave')->name('leave.cancel');
        });
        Route::controller(\App\Http\Controllers\ScheduleController::class)->prefix('schedule')->name('schedule.')->group(function (): void {
            Route::get('/', 'edit')->name('edit');
            Route::put('/', 'update')->name('update');
            Route::post('/holidays', 'holiday')->name('holiday');
            Route::patch('/holidays/{holiday}', 'reopen')->whereNumber('holiday')->name('reopen');
        });
    });
    Route::get('/salon/branches', [\App\Http\Controllers\CatalogController::class, 'branches'])->name('salon.branches');
    Route::scopeBindings()->prefix('/salon/branches/{branch}/services')->name('catalog.')->controller(\App\Http\Controllers\CatalogController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{service}/edit', 'edit')->name('edit');
        Route::put('/{service}', 'update')->name('update');
    });
    Route::get('/dashboard', \App\Http\Controllers\CustomerDashboardController::class)->name('dashboard');
    Route::get('/admin/dashboard', \App\Http\Controllers\PlatformDashboardController::class)->middleware('can:view-platform')->name('admin.dashboard');
});
// Public salon routes will use /salons/{business:slug}; management stays under /salon.
// No legacy endpoint is advertised until its module has been migrated and tested.
