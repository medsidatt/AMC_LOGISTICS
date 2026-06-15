<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\UserController;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'auth', 'middleware' => ['auth']], function () {
    Route::get('/profile', [UserController::class, 'profile'])->name('auth.profile');
    Route::get('/account', [UserController::class, 'account'])->name('auth.account');
    Route::put('/account/update', [UserController::class, 'updateAccount'])->name('auth.account.update');
    Route::put('/password/update', [UserController::class, 'updatePassword'])->name('auth.password.update');

    // Force password change on first login
    Route::get('/force-password', [UserController::class, 'forcePassword'])->name('force-password');
    Route::put('/force-password/update', [UserController::class, 'forcePasswordUpdate'])->name('force-password.update');
});

Route::group(['prefix' => 'users', 'middleware' => ['auth']], function () {
    Route::get('/', [UserController::class, 'index'])->name('users.index');
    Route::post('store', [UserController::class, 'store'])->name('users.store');
    Route::put('update/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('destroy/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::put('suspend/{id}', [UserController::class, 'suspend'])->name('users.suspend');
});

// Public: rate-limited to blunt invitation-token enumeration/brute force.
Route::get('auth/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware('throttle:10,1')
    ->name('invitation.accept');

Route::group(['prefix' => 'auth/invitations', 'middleware' => [
    'auth'
]], function () {
    Route::get('/', [InvitationController::class, 'index'])->name('invitation.index');
    Route::post('/send', [InvitationController::class, 'sendInvitation'])->name('invitation.send');
    Route::put('/update/{id}', [InvitationController::class, 'update'])->name('invitation.update');
    Route::delete('/destroy/{id}', [InvitationController::class, 'destroy'])->name('invitation.destroy');
});

