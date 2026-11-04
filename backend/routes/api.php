<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FinancialYearController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::middleware('web')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class);
    Route::get('/auth/oidc/redirect', [AuthController::class, 'oidcRedirect'])
        ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class);
    Route::get('/auth/oidc/callback', [AuthController::class, 'oidcCallback'])
        ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)
        ->name('auth.oidc.callback');
    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)
        ->middleware('auth');
    Route::get('/auth/user', [AuthController::class, 'user'])
        ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)
        ->middleware('auth');
});
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
    Route::get('/exports/transactions.csv', [ExportController::class, 'transactionsCsv']);
    Route::get('/exports/{id}', [ExportController::class, 'show']);

    Route::get('/investments', [InvestmentController::class, 'index']);
    Route::post('/investments', [InvestmentController::class, 'store']);
    Route::get('/investments/{id}', [InvestmentController::class, 'show']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);

    Route::get('/document-types', [DocumentTypeController::class, 'index']);

    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{id}', [DocumentController::class, 'show']);
    Route::post('/documents/{id}/payment', [DocumentController::class, 'confirmPayment']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
    Route::post(
        '/documents/{id}/financial-year',
        [DocumentController::class, 'assignFinancialYear']
    );
    Route::get('/documents/{id}/content', [DocumentController::class, 'content']);
    Route::post('/documents/{id}/retry', [DocumentController::class, 'retry']);

    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/{id}', [ReviewController::class, 'show']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    Route::post(
        '/reviews/{id}/decision',
        [ReviewController::class, 'decision']
    );
    Route::post(
        '/reviews/{id}/correction',
        [ReviewController::class, 'correction']
    );
});
