<?php

use App\Http\Controllers\ImportController;
use App\Http\Controllers\QueryBuilderController;
use App\Http\Controllers\TableauController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('tableau.index');
});

// Query Builder POC (accès public pour la démo)
Route::get('/query-builder', [QueryBuilderController::class, 'index'])->name('query-builder.index');
Route::post('/query-builder/execute', [QueryBuilderController::class, 'execute'])->name('query-builder.execute');
Route::post('/query-builder/count', [QueryBuilderController::class, 'count'])->name('query-builder.count');
Route::post('/query-builder/sql', [QueryBuilderController::class, 'sql'])->name('query-builder.sql');
Route::post('/query-builder/parse', [QueryBuilderController::class, 'parseSearch'])->name('query-builder.parse');
Route::post('/query-builder/export', [QueryBuilderController::class, 'export'])->name('query-builder.export');
Route::post('/query-builder/favorite', [QueryBuilderController::class, 'saveFavorite'])->name('query-builder.favorite');

Route::middleware(['auth'])->group(function () {
    // Page principale du tableau avec AGGrid
    Route::get('/tableau', [TableauController::class, 'index'])->name('tableau.index');

    // Import de données
    Route::get('/import', [ImportController::class, 'create'])->name('import.create');
    Route::post('/import', [ImportController::class, 'store'])->name('import.store');
});
