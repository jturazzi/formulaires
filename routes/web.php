<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormOwnershipController;
use App\Http\Controllers\FormResponseController;
use App\Http\Controllers\FormShareController;
use App\Http\Controllers\FormStructureController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\RespondentVerificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

/*
|--------------------------------------------------------------------------
| Public routes — respondents (no authentication)
|--------------------------------------------------------------------------
*/

Route::get('terms', [LegalPageController::class, 'terms'])->name('terms');
Route::get('privacy', [LegalPageController::class, 'privacy'])->name('privacy');

Route::prefix('f/{slug}')->group(function () {
    Route::get('/', [PublicFormController::class, 'show'])->name('public.forms.show');
    Route::post('/', [PublicFormController::class, 'submit'])
        ->middleware('throttle:20,1')
        ->name('public.forms.submit');
    Route::get('merci', [PublicFormController::class, 'thanks'])->name('public.forms.thanks');
    Route::post('email-code', [RespondentVerificationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('public.forms.email-code');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes — form managers
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('forms', FormController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);

    Route::put('forms/{form}/structure', [FormStructureController::class, 'update'])->name('forms.structure.update');
    Route::post('forms/{form}/status', [FormController::class, 'updateStatus'])->name('forms.status.update');
    Route::post('forms/{form}/duplicate', [FormController::class, 'duplicate'])->name('forms.duplicate');
    Route::post('forms/{form}/logo', [FormController::class, 'uploadLogo'])->middleware('throttle:15,1')->name('forms.logo.upload');
    Route::delete('forms/{form}/logo', [FormController::class, 'deleteLogo'])->name('forms.logo.delete');

    Route::post('forms/{form}/shares', [FormShareController::class, 'store'])->middleware('throttle:15,1')->name('forms.shares.store');
    Route::delete('forms/{form}/shares/{share}', [FormShareController::class, 'destroy'])->name('forms.shares.destroy');
    Route::post('forms/{form}/owner', [FormOwnershipController::class, 'update'])->middleware('throttle:10,1')->name('forms.owner.update');

    Route::get('forms/{form}/responses', [FormResponseController::class, 'index'])->name('forms.responses.index');
    Route::get('forms/{form}/responses/export', [FormResponseController::class, 'export'])->middleware('throttle:10,1')->name('forms.responses.export');
    Route::delete('forms/{form}/responses/{response}', [FormResponseController::class, 'destroy'])->name('forms.responses.destroy');

    Route::get('answers/{answer}/file', [FormResponseController::class, 'downloadFile'])->name('answers.file');
});

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::get('settings', [SettingsController::class, 'edit'])->name('admin.settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('admin.settings.update');

    Route::get('users', [UserController::class, 'index'])->name('admin.users.index');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
