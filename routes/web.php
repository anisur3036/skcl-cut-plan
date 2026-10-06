<?php

use App\Http\Controllers\PoSheetController;
use App\Http\Controllers\SizeQuantityController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('po-sheets', PoSheetController::class);

    Route::get('/size-quantities', [SizeQuantityController::class, 'index'])->name('size-quantities.index');
    Route::post('/size-quantities', [SizeQuantityController::class, 'store'])->name('size-quantities.store');
    Route::put('/size-quantities/{ref}', [SizeQuantityController::class, 'update'])->name('size-quantities.update');
});



require __DIR__ . '/settings.php';
