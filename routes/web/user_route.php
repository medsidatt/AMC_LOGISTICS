<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\UserController;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'auth', 'middleware' => ['auth']], function () {
    Route::get('/profile', [UserController::class, 'profile'])->name('auth.profile');
    Route::get('/account', [UserController::class, 'account'])->name('auth.account');
    Route::put('/account/update', [UserController::class, 'updateAccount'])->name('auth.account.update');
    Route::get('/password', [UserController::class, 'password'])->name('auth.password');
    Route::put('/password/update', [UserController::class, 'updatePassword'])->name('auth.password.update');

    // Force password change on first login
    Route::get('/force-password', [UserController::class, 'forcePassword'])->name('force-password');
    Route::put('/force-password/update', [UserController::class, 'forcePasswordUpdate'])->name('force-password.update');
});

Route::group(['prefix' => 'users', 'middleware' => ['auth']], function () {
    Route::get('/', [UserController::class, 'index'])->name('users.index');
    Route::get('create', [UserController::class, 'create'])->name('users.create');
    Route::post('store', [UserController::class, 'store'])->name('users.store');
    Route::get('edit/{id}', [UserController::class, 'edit'])->name('users.edit');
    Route::put('update/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('destroy/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('show/{id}', [UserController::class, 'show'])->name('users.show');
    Route::get('suspend/{id}', [UserController::class, 'suspend'])->name('users.suspend');
    Route::get('change-password/{id}', [UserController::class, 'changePassword'])->name('users.change-password');
    Route::put('update-password', [UserController::class, 'updatePassword'])->name('users.update-password');
    // users.change-user-password
    Route::put('change-user-password/{id}', [UserController::class, 'changeUserPassword'])->name('users.change-user-password');
});

Route::get('auth/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->name('invitation.accept');
Route::post('auth/invitations/register', [InvitationController::class, 'register'])->name('invitation.register');

Route::group(['prefix' => 'auth/invitations', 'middleware' => [
    'auth'
]], function () {
    Route::get('/', [InvitationController::class, 'index'])->name('invitation.index');
    Route::post('/send', [InvitationController::class, 'sendInvitation'])->name('invitation.send');
    Route::get('/create', [InvitationController::class, 'create'])->name('invitation.create');
    Route::get('/edit/{id}', [InvitationController::class, 'edit'])->name('invitation.edit');
    Route::put('/update/{id}', [InvitationController::class, 'update'])->name('invitation.update');
    Route::delete('/destroy/{id}', [InvitationController::class, 'destroy'])->name('invitation.destroy');
});

