<?php

use App\Http\Controllers\PoSheetController;
use App\Http\Controllers\SizeQuantityController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('po-sheets', PoSheetController::class);

    //Route::get('/size-quantities', [SizeQuantityController::class, 'index'])->name('size-quantities.index');
    //Route::post('/size-quantities', [SizeQuantityController::class, 'store'])->name('size-quantities.store');
    //Route::put('/size-quantities/{ref}', [SizeQuantityController::class, 'update'])->name('size-quantities.update');
    Route::middleware('auth')->prefix('size-quantities')->name('size-quantities.')->group(function () {
        Route::get('/', [SizeQuantityController::class, 'index'])->name('index');
        Route::get('/create', [SizeQuantityController::class, 'create'])->name('create');
        Route::post('/', [SizeQuantityController::class, 'store'])->name('store');
        Route::get('/{ref}/edit', [SizeQuantityController::class, 'edit'])->name('edit');
        Route::put('/{ref}', [SizeQuantityController::class, 'update'])->name('update');
    });
});



require __DIR__ . '/settings.php';
