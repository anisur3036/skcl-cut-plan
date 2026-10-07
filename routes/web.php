<?php

use App\Http\Controllers\PoSheetController;
use App\Http\Controllers\SizeQuantityController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('auth')->prefix('po-sheets')->name('po-sheets.')->group(function () {
        Route::get('/', [PoSheetController::class, 'index'])->name('index');
        Route::get('/template', [PoSheetController::class, 'template'])->name('template');
        Route::post('/import', [PoSheetController::class, 'import'])->name('import');
    });

    Route::middleware('auth')->prefix('size-quantities')->name('size-quantities.')->group(function () {
        Route::get('/', [SizeQuantityController::class, 'index'])->name('index');
        Route::get('/create', [SizeQuantityController::class, 'create'])->name('create');
        Route::post('/', [SizeQuantityController::class, 'store'])->name('store');
        Route::get('/{ref}/edit', [SizeQuantityController::class, 'edit'])->name('edit');
        Route::put('/{ref}', [SizeQuantityController::class, 'update'])->name('update');
        Route::delete('/{ref}', [SizeQuantityController::class, 'destroy'])->name('destroy');
        Route::get('/{ref}/pdf', [SizeQuantityController::class, 'pdf'])->name('pdf');
    });
});



require __DIR__ . '/settings.php';
