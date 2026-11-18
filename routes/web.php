<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InternshipRequestController as AdminInternshipRequestController;
use App\Http\Controllers\Admin\ProcessInternshipRequestController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Operator\DashboardController as OperatorDashboardController;
use App\Http\Controllers\Operator\InternshipRequestController;
use App\Http\Controllers\ReplyLetterDownloadController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    /** @var User $user */
    $user = Auth::user();

    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->isOperator()) {
        return redirect()->route('operator.dashboard');
    }

    // Role hanya boleh admin atau operator.
    abort(403, 'Role pengguna tidak dikenali.');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');

    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.store');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', AdminDashboardController::class)->name('admin.dashboard');
    Route::get('/admin/internship-requests', [AdminInternshipRequestController::class, 'index'])
        ->name('admin.internship-requests.index');
    Route::get('/admin/internship-requests/{internshipRequestId}', [AdminInternshipRequestController::class, 'show'])
        ->whereNumber('internshipRequestId')
        ->name('admin.internship-requests.show');
    Route::post('/admin/internship-requests/{internshipRequestId}/decision', ProcessInternshipRequestController::class)
        ->whereNumber('internshipRequestId')
        ->name('admin.internship-requests.decision');
    Route::get('/admin/internship-requests/{internshipRequestId}/reply-letter', [ReplyLetterDownloadController::class, 'admin'])
        ->whereNumber('internshipRequestId')
        ->name('admin.internship-requests.reply-letter');
});

Route::middleware(['auth', 'role:operator'])->group(function () {
    Route::get('/operator/internship-requests', [InternshipRequestController::class, 'index'])
        ->name('operator.internship-requests.index');
    Route::get('/operator/internship-requests/create', [InternshipRequestController::class, 'create'])
        ->name('operator.internship-requests.create');
    Route::post('/operator/internship-requests', [InternshipRequestController::class, 'store'])
        ->name('operator.internship-requests.store');
    Route::get('/operator/internship-requests/{internshipRequestId}', [InternshipRequestController::class, 'show'])
        ->whereNumber('internshipRequestId')
        ->name('operator.internship-requests.show');
    Route::get('/operator/internship-requests/{internshipRequestId}/reply-letter', [ReplyLetterDownloadController::class, 'operator'])
        ->whereNumber('internshipRequestId')
        ->name('operator.internship-requests.reply-letter');

    Route::get('/operator/dashboard', OperatorDashboardController::class)->name('operator.dashboard');
});
