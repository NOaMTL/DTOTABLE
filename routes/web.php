<?php

use App\Http\Controllers\ImportController;
use App\Http\Controllers\TableauController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('tableau.index');
});

Route::middleware(['auth'])->group(function () {
    // Page principale du tableau avec AGGrid
    Route::get('/tableau', [TableauController::class, 'index'])->name('tableau.index');

    // Import de données
    Route::get('/import', [ImportController::class, 'create'])->name('import.create');
    Route::post('/import', [ImportController::class, 'store'])->name('import.store');
});
