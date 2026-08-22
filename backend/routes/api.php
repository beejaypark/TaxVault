<?php

use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FinancialYearController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\PropertyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/financial-years', [FinancialYearController::class, 'index']);
    Route::post('/financial-years', [FinancialYearController::class, 'store']);

    Route::get('/properties', [PropertyController::class, 'index']);
    Route::post('/properties', [PropertyController::class, 'store']);
    Route::get('/properties/{property}', [PropertyController::class, 'show']);
    Route::post(
        '/properties/{property}/periods',
        [PropertyController::class, 'storePeriod']
    );

    Route::post('/exports', [ExportController::class, 'store']);
    Route::get('/exports/{id}', [ExportController::class, 'show']);

    Route::get('/investments', [InvestmentController::class, 'index']);
    Route::post('/investments', [InvestmentController::class, 'store']);
    Route::get('/investments/{id}', [InvestmentController::class, 'show']);

    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{id}', [DocumentController::class, 'show']);

});
