<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingStatusController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\CustomerBookingController;
use App\Http\Controllers\Api\VietQrController;
use App\Http\Controllers\Api\FriendlyMatchController;
use App\Http\Controllers\Api\Admin\RecurringBookingController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\BookingAdminController;
use App\Http\Controllers\Api\Admin\BookingSettlementController;
use App\Http\Controllers\Api\Admin\InvoiceAdminController;
use App\Http\Controllers\Api\Admin\CustomerAdminController;
use App\Http\Controllers\Api\Admin\PitchController;
use App\Http\Controllers\Api\Admin\PitchPriceRuleController;
use App\Http\Controllers\Api\Admin\ServiceAdminController;
use App\Http\Controllers\Api\BookingQuoteController;

Route::get('/pitches', fn () => response()->json(DB::table('pitches')->where('status', 'active')->orderBy('name')->get()));
Route::post('/bookings', [BookingController::class, 'store'])->middleware('throttle:20,1');
Route::post('/bookings/status', [BookingStatusController::class, 'show'])->middleware('throttle:20,1');
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::get('/my-bookings', [CustomerBookingController::class, 'index'])->middleware(\App\Http\Middleware\SyncBookingLifecycle::class);
    Route::get('/my-bookings/{id}', [CustomerBookingController::class, 'show'])->middleware(\App\Http\Middleware\SyncBookingLifecycle::class)->whereNumber('id');
    Route::patch('/my-bookings/{id}/cancel', [CustomerBookingController::class, 'cancel']);
    Route::get('/bookings/{bookingId}/vietqr', [VietQrController::class, 'show']);
});
Route::get('/pricing/quote', [BookingQuoteController::class, 'show'])
    ->middleware('throttle:120,1');
Route::get('/availability', [AvailabilityController::class, 'index'])->middleware('throttle:120,1');
Route::get('/services', [AvailabilityController::class, 'services'])->middleware('throttle:120,1');
Route::middleware(['auth:sanctum', 'role:admin,staff'])->prefix('admin')->group(function () {
    Route::get('/recurring-bookings', [RecurringBookingController::class, 'index']);
    Route::post('/recurring-bookings', [RecurringBookingController::class, 'store']);
    Route::get('/recurring-bookings/{id}', [RecurringBookingController::class, 'show']);
    Route::patch('/recurring-bookings/{id}/cancel-future', [RecurringBookingController::class, 'cancelFuture']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/reports/revenue', [DashboardController::class, 'revenue']);
    Route::get('/reports/monthly', [\App\Http\Controllers\Api\Admin\MonthlyReportController::class, 'show']);
    Route::get('/reports/cashflow', [BookingSettlementController::class, 'cashflow']);
    Route::get('/bookings', [BookingAdminController::class, 'index'])->middleware(\App\Http\Middleware\SyncBookingLifecycle::class);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::patch('/bookings/{id}/confirm', [BookingAdminController::class, 'confirm']);
    Route::patch('/bookings/{id}/cancel', [BookingSettlementController::class, 'cancel']);
    Route::get('/customers', [CustomerAdminController::class, 'index']);
    Route::get('/customers/bookings', [CustomerAdminController::class, 'show']);
    Route::get('/invoices', [InvoiceAdminController::class, 'index']);
    Route::get('/invoices/{id}', [InvoiceAdminController::class, 'show']);
    Route::post('/invoices/{invoiceId}/payments', [BookingSettlementController::class, 'recordPayment']);
    Route::post('/invoices/{invoiceId}/settle-after-match', [BookingSettlementController::class, 'settleAfterMatch'])->middleware('throttle:20,1');
    Route::get('/pitches', [PitchController::class, 'index']);
    Route::post('/pitches', [PitchController::class, 'store']);
    Route::put('/pitches/{id}', [PitchController::class, 'update']);
    // Danh mục dịch vụ và tồn kho; chỉ admin/staff được truy cập.
    Route::get('/services', [ServiceAdminController::class, 'index']);
    Route::post('/services', [ServiceAdminController::class, 'store']);
    Route::put('/services/{id}', [ServiceAdminController::class, 'update']);
    Route::post('/services/{id}/stock', [ServiceAdminController::class, 'adjustStock']);
    Route::get('/services/{id}/stock-movements', [ServiceAdminController::class, 'movements']);
    // Quan ly bang gia san
    Route::get('/price-rules', [PitchPriceRuleController::class, 'index']);
    Route::post('/price-rules', [PitchPriceRuleController::class, 'store']);
    Route::put('/price-rules/{id}', [PitchPriceRuleController::class, 'update']);
    Route::delete('/price-rules/{id}', [PitchPriceRuleController::class, 'destroy']);
});

// Ai cũng có thể xem tin tìm đối, nhưng cần đăng nhập để gửi lời mời.
// Mỗi lời mời gắn với tài khoản gửi để chặn tự mời và quản lý riêng tư.
Route::get('/friendly-matches', [FriendlyMatchController::class, 'index'])->middleware('throttle:120,1');
Route::middleware(['auth:sanctum', 'throttle:20,1'])->group(function () {
    Route::get('/friendly-matches/mine', [FriendlyMatchController::class, 'myMatches']);
    Route::get('/friendly-match-requests/sent', [FriendlyMatchController::class, 'sentRequests']);
    Route::patch('/friendly-match-requests/{requestId}/withdraw', [FriendlyMatchController::class, 'withdrawRequest']);
    Route::post('/friendly-matches', [FriendlyMatchController::class, 'store']);
    Route::post('/friendly-matches/{id}/owner', [FriendlyMatchController::class, 'ownerView']);
    Route::patch('/friendly-matches/{id}/requests/{requestId}', [FriendlyMatchController::class, 'decide']);
    Route::patch('/friendly-matches/{id}/cancel', [FriendlyMatchController::class, 'cancel']);
});
Route::post('/friendly-matches/{id}/requests', [FriendlyMatchController::class, 'sendRequest'])
    ->middleware(['auth:sanctum', 'throttle:5,1']);
