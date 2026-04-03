<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MicrosoftAuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/auth/microsoft', [MicrosoftAuthController::class, 'redirect']);
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback']);

Route::get('/', function () {
    return redirect()->route('home');
});

Auth::routes();

Route::group(['middleware' => ['auth'], 'prefix' => 'dashboard'], function () {
    Route::get('', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
});

Route::get('/language/switch/{locale}', [LanguageController::class, 'switchLanguage'])->name('language.switch');
Route::get('/translations', [LanguageController::class, 'getTranslations'])->name('translations');

Route::get('/auth/redirect', [LoginController::class, 'redirectToAzure']);
Route::get('/auth/callback', [LoginController::class, 'handleAzureCallback']);
