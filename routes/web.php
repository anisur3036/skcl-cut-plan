<?php

use App\Http\Controllers\MarkerPlanController;
use App\Http\Controllers\OrderImportController;
use App\Http\Controllers\FabricImportController;
use App\Http\Controllers\MarkerPlanOptionController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('auth')->prefix('order-import')->name('order-import.')->group(function () {
        Route::get('/', [OrderImportController::class, 'index'])->name('index');
        Route::get('/template', [OrderImportController::class, 'template'])->name('template');
        Route::post('/', [OrderImportController::class, 'import'])->name('import');
    });


    Route::middleware('auth')->prefix('fabric-import')->name('fabric-import.')->group(function () {
        Route::get('/', [FabricImportController::class, 'index'])->name('index');
        Route::get('/template', [FabricImportController::class, 'template'])->name('template');
        Route::post('/', [FabricImportController::class, 'import'])->name('import');
    });


    Route::middleware('auth')->prefix('marker-plans')->name('marker-plans.')->group(function () {
        Route::get('/', [MarkerPlanController::class, 'index'])->name('index');
        Route::get('/create', [MarkerPlanController::class, 'create'])->name('create');
        Route::post('/', [MarkerPlanController::class, 'store'])->name('store');
        Route::get('/summary', [MarkerPlanController::class, 'summary'])->name('summary');
        Route::get('/{markerPlan}/edit', [MarkerPlanController::class, 'edit'])->name('edit');
        Route::put('/{markerPlan}', [MarkerPlanController::class, 'update'])->name('update');
        Route::get('/{markerPlan}/pdf', [MarkerPlanController::class, 'pdf'])->name('pdf');
        Route::delete('/{markerPlan}', [MarkerPlanController::class, 'destroy'])->name('destroy');
    });

    Route::get('/api/order/search', [SearchController::class, 'skcl']);


    Route::middleware('auth')->prefix('marker-plan-options')->name('marker-plan-options.')->group(function () {
        Route::get('/skcl', [MarkerPlanOptionController::class, 'skcl'])->name('skcl');
        Route::get('/skcl/{skcl}', [MarkerPlanOptionController::class, 'skclShow'])
            ->where('skcl', '.+')
            ->name('skcl.show');
        Route::get('/colors/{skcl}', [MarkerPlanOptionController::class, 'colors'])
            ->where('skcl', '.+')
            ->name('colors');
    });

});



require __DIR__ . '/settings.php';
