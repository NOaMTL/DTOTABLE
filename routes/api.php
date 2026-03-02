<?php

use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\TableauController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Routes pour les données du tableau
    Route::post('/tableau/data', [TableauController::class, 'getData']);
    Route::post('/tableau/count', [TableauController::class, 'count']);

    // Routes pour l'export PDF
    Route::post('/export/pdf', [ExportController::class, 'exportPdf'])->name('api.export.pdf');
    Route::get('/export/download/{filename}', [ExportController::class, 'download'])->name('api.export.download');
});
