<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InternParticipantController;
use App\Http\Controllers\Admin\InternPasswordResetRequestController;
use App\Http\Controllers\Admin\InternshipRequestController as AdminInternshipRequestController;
use App\Http\Controllers\Admin\ProcessInternshipRequestController;
use App\Http\Controllers\Admin\RegistrationTokenController;
use App\Http\Controllers\Admin\SharedRegistrationTokenController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InternNewPasswordController;
use App\Http\Controllers\Auth\InternPasswordResetLinkController;
use App\Http\Controllers\Auth\InternRegistrationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OperatorPasswordResetController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Intern\DashboardController;
use App\Http\Controllers\Intern\InternProfileController;
use App\Http\Controllers\Operator\DashboardController as OperatorDashboardController;
use App\Http\Controllers\Operator\InternshipRequestController;
use App\Http\Controllers\ProposalDownloadController;
use App\Http\Controllers\ReplyLetterDownloadController;
use App\Http\Middleware\EnsureInternAuthenticationVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $admin = Auth::guard('admin')->user();
    $operator = Auth::guard('operator')->user();

    if ($admin !== null && ! $admin->isAdmin()) {
        abort(403);
    }

    if ($operator !== null && ! $operator->isOperator()) {
        abort(403);
    }

    return view('welcome');
})->name('home');

Route::middleware('guest:operator')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');

    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.store');

});

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::get('/admin/login', [AuthenticatedSessionController::class, 'createAdmin'])->middleware('guest:admin')->name('admin.login');
Route::post('/admin/login', [AuthenticatedSessionController::class, 'storeAdmin'])->middleware(['guest:admin', 'throttle:login'])->name('admin.login.store');
Route::get('/operator/login', [AuthenticatedSessionController::class, 'createOperator'])->middleware('guest:operator')->name('operator.login');
Route::post('/operator/login', [AuthenticatedSessionController::class, 'storeOperator'])->middleware(['guest:operator', 'throttle:login'])->name('operator.login.store');
Route::get('/intern/login', [AuthenticatedSessionController::class, 'createIntern'])->middleware('guest:intern')->name('intern.login');
Route::post('/intern/login', [AuthenticatedSessionController::class, 'storeIntern'])->middleware(['guest:intern', 'throttle:login'])->name('intern.login.store');

Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.update');
Route::get('/intern/forgot-password', [InternPasswordResetLinkController::class, 'create'])->name('intern.password.request');
Route::post('/intern/forgot-password', [InternPasswordResetLinkController::class, 'store'])->middleware('throttle:intern-password-reset')->name('intern.password.email');
Route::get('/intern/reset-password', [InternNewPasswordController::class, 'create'])->name('intern.password.reset');
Route::post('/intern/reset-password', [InternNewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('intern.password.update');

Route::middleware('guest:operator')->group(function () {
    Route::get('/operator/forgot-password', [OperatorPasswordResetController::class, 'create'])
        ->name('operator.password.request');
    Route::post('/operator/forgot-password', [OperatorPasswordResetController::class, 'start'])
        ->name('operator.password.email');
    Route::get('/operator/reset-password', [OperatorPasswordResetController::class, 'edit'])
        ->name('operator.password.reset');
    Route::post('/operator/reset-password', [OperatorPasswordResetController::class, 'update'])
        ->name('operator.password.update');
});

Route::post('/admin/logout', [AuthenticatedSessionController::class, 'destroyAdmin'])->middleware('auth:admin')->name('admin.logout');
Route::post('/operator/logout', [AuthenticatedSessionController::class, 'destroyOperator'])->middleware('auth:operator')->name('operator.logout');
Route::post('/intern/logout', [AuthenticatedSessionController::class, 'destroyIntern'])
    ->middleware(['auth:intern', EnsureInternAuthenticationVersion::class])
    ->name('intern.logout');

Route::get('/intern/register', [InternRegistrationController::class, 'create'])
    ->name('intern.register');
Route::post('/intern/register', [InternRegistrationController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('intern.register.store');

Route::middleware(['auth:admin', 'role:admin,admin'])->group(function () {
    Route::get('/admin/dashboard', AdminDashboardController::class)->name('admin.dashboard');
    Route::prefix('/admin/intern-participants')->name('admin.intern-participants.')->group(function () {
        Route::get('/', [InternParticipantController::class, 'index'])->name('index');
        Route::get('/archived', [InternParticipantController::class, 'archivedIndex'])->name('archived.index');
        Route::post('/archived/bulk-restore', [InternParticipantController::class, 'bulkRestore'])->name('archived.bulk-restore');
        Route::get('/archived/{userInternId}', [InternParticipantController::class, 'showArchived'])
            ->whereNumber('userInternId')
            ->name('archived.show');
        Route::post('/archived/{userInternId}/restore', [InternParticipantController::class, 'restore'])
            ->whereNumber('userInternId')
            ->name('restore');
        Route::post('/bulk-archive', [InternParticipantController::class, 'bulkArchive'])->name('bulk-archive');
        Route::post('/bulk-lifecycle', [InternParticipantController::class, 'bulkUpdateLifecycle'])
            ->name('bulk-lifecycle.update');
        Route::get('/{userInternId}', [InternParticipantController::class, 'show'])
            ->whereNumber('userInternId')
            ->name('show');
        Route::get('/{userInternId}/edit', [InternParticipantController::class, 'edit'])
            ->whereNumber('userInternId')
            ->name('edit');
        Route::put('/{userInternId}', [InternParticipantController::class, 'update'])
            ->whereNumber('userInternId')
            ->name('update');
        Route::delete('/{userInternId}/archive', [InternParticipantController::class, 'archive'])
            ->whereNumber('userInternId')
            ->name('archive');
        Route::patch('/{userInternId}/lifecycle', [InternParticipantController::class, 'updateLifecycle'])
            ->whereNumber('userInternId')
            ->name('lifecycle.update');
    });
    Route::get('/admin/shared-registration-tokens', [SharedRegistrationTokenController::class, 'index'])
        ->name('admin.shared-registration-tokens.index');
    Route::get('/admin/internship-requests', [AdminInternshipRequestController::class, 'index'])
        ->name('admin.internship-requests.index');
    Route::get('/admin/internship-requests/{internshipRequestId}', [AdminInternshipRequestController::class, 'show'])
        ->whereNumber('internshipRequestId')
        ->name('admin.internship-requests.show');
    Route::post('/admin/internship-requests/{internshipRequestId}/decision', ProcessInternshipRequestController::class)
        ->whereNumber('internshipRequestId')
        ->name('admin.internship-requests.decision');
    Route::post('/admin/internship-requests/{internshipRequestId}/applicants/{applicantId}/registration-token/replace', [RegistrationTokenController::class, 'replace'])
        ->whereNumber(['internshipRequestId', 'applicantId'])
        ->name('admin.internship-requests.registration-tokens.replace');
    Route::put('/admin/internship-requests/{internshipRequestId}/shared-registration-token/quota', [SharedRegistrationTokenController::class, 'updateQuota'])
        ->whereNumber('internshipRequestId')
        ->name('admin.internship-requests.shared-registration-token.quota');
    Route::get('/admin/internship-requests/{internshipRequestId}/reply-letter', [ReplyLetterDownloadController::class, 'admin'])
        ->whereNumber('internshipRequestId')
        ->name('admin.internship-requests.reply-letter');
    Route::get('/admin/internship-requests/{internshipRequestId}/proposal', [ProposalDownloadController::class, 'admin'])
        ->whereNumber('internshipRequestId')
        ->name('admin.internship-requests.proposal');
});

Route::middleware(['auth:admin', 'role:admin,admin'])->prefix('/admin/intern-password-reset-requests')->name('admin.intern-password-reset-requests.')->group(function () {
    Route::get('/', [InternPasswordResetRequestController::class, 'index'])->name('index');
    Route::get('/{requestId}', [InternPasswordResetRequestController::class, 'show'])->whereNumber('requestId')->name('show');
    Route::post('/{requestId}/approve', [InternPasswordResetRequestController::class, 'approve'])->whereNumber('requestId')->name('approve');
    Route::post('/{requestId}/reject', [InternPasswordResetRequestController::class, 'reject'])->whereNumber('requestId')->name('reject');
});

Route::middleware(['auth:operator', 'role:operator,operator'])->group(function () {
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
    Route::get('/operator/internship-requests/{internshipRequestId}/proposal', [ProposalDownloadController::class, 'operator'])
        ->whereNumber('internshipRequestId')
        ->name('operator.internship-requests.proposal');

    Route::get('/operator/dashboard', OperatorDashboardController::class)->name('operator.dashboard');
});

Route::middleware(['auth:intern', EnsureInternAuthenticationVersion::class])->group(function () {
    Route::get('/intern/profile', [InternProfileController::class, 'show'])
        ->name('intern.profile.show');
    Route::get('/intern/profile/edit', [InternProfileController::class, 'edit'])
        ->name('intern.profile.edit');
    Route::put('/intern/profile/edit', [InternProfileController::class, 'updateDetails'])
        ->middleware('throttle:6,1')
        ->name('intern.profile.update-details');
    Route::put('/intern/profile', [InternProfileController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('intern.profile.update');
    Route::get('/intern/dashboard', [DashboardController::class, '__invoke'])
        ->name('intern.dashboard');
});
