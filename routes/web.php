<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FilmController;
use App\Http\Controllers\FinanceVisibilityController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

// Link fixo de cada filme (público): abre o player direto, sem opção de baixar.
Route::prefix('filme/{slug}')->where(['slug' => '[a-z0-9-]{1,120}'])->group(function () {
    Route::get('/', [FilmController::class, 'show'])->middleware('throttle:120,1')->name('film.show');
    Route::get('video', [FilmController::class, 'stream'])->name('film.stream');
    Route::get('capa', [FilmController::class, 'cover'])->name('film.cover');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('videos/{video}/stream', [MediaController::class, 'stream'])->name('videos.stream');
    Route::get('videos/{video}/cover', [MediaController::class, 'cover'])->name('videos.cover');

    Route::middleware('master')->group(function () {
        Route::resource('videos', VideoController::class)->except('show');
        Route::post('uploads/chunk', [UploadController::class, 'chunk'])->name('uploads.chunk');

        Route::post('valores/mostrar', [FinanceVisibilityController::class, 'show'])->middleware('throttle:20,1')->name('finance.show');
        Route::post('valores/ocultar', [FinanceVisibilityController::class, 'hide'])->name('finance.hide');

        Route::resource('usuarios', MemberController::class)->except('show')
            ->parameters(['usuarios' => 'member'])->names('members');
        Route::get('atividades', ActivityController::class)->name('activities.index');
        Route::get('configuracoes', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('configuracoes', [SettingsController::class, 'update'])->name('settings.update');
    });
});
