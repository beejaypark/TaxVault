<?php

use App\Http\Controllers\Api\AccountantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FinancialYearController;
use App\Http\Controllers\Api\InboundEmailController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\MortgageController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\RentalSummaryController;
use App\Http\Controllers\Api\ReturnSummaryController;
use App\Http\Controllers\Api\TransactionBulkController;
use App\Http\Controllers\Api\WorkFromHomeController;
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
    Route::put('/auth/preferences', [AuthController::class, 'updatePreferences'])
        ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)
        ->middleware('auth');
});
Route::post('/inbound-email/webhook', [InboundEmailController::class, 'webhook']);

Route::middleware(['auth', 'acting'])->group(function (): void {
    Route::get('/accountants', [AccountantController::class, 'index']);
    Route::post('/accountants', [AccountantController::class, 'store'])->middleware('audit:accountant.invite,accountant');
    Route::patch('/accountants/{id}', [AccountantController::class, 'update'])->middleware('audit:accountant.update,accountant');
    Route::delete('/accountants/{id}', [AccountantController::class, 'destroy'])->middleware('audit:accountant.revoke,accountant');
    Route::post('/accountants/{id}/reset-password', [AccountantController::class, 'resetPassword'])->middleware('audit:accountant.reset_password,accountant');
    Route::post('/accountants/{id}/send-pack', [AccountantController::class, 'sendPack'])->middleware('audit:accountant.send_pack,accountant');
    Route::get('/inbound-email', [InboundEmailController::class, 'show']);
    Route::get('/financial-years', [FinancialYearController::class, 'index']);
    Route::post('/financial-years', [FinancialYearController::class, 'store']);
    Route::patch('/financial-years/{id}', [FinancialYearController::class, 'update'])->middleware('audit:financial_year.update,financial_year');
    Route::delete('/financial-years/{id}', [FinancialYearController::class, 'destroy'])->middleware('audit:financial_year.delete,financial_year');

    Route::get('/properties', [PropertyController::class, 'index']);
    Route::post('/properties', [PropertyController::class, 'store']);
    Route::get('/properties/{property}', [PropertyController::class, 'show']);
    Route::get('/properties/{property}/rental-summary', [RentalSummaryController::class, 'show']);
    Route::put('/properties/{property}', [PropertyController::class, 'update'])->middleware('audit:property.update,property');
    Route::delete('/properties/{property}', [PropertyController::class, 'destroy'])->middleware('audit:property.delete,property');
    Route::post('/properties/{property}/mortgages', [MortgageController::class, 'store'])->middleware('audit:mortgage.create,property');
    Route::put('/mortgages/{id}', [MortgageController::class, 'update'])->middleware('audit:mortgage.update,mortgage');
    Route::delete('/mortgages/{id}', [MortgageController::class, 'destroy'])->middleware('audit:mortgage.delete,mortgage');
    Route::put('/mortgages/{id}/interest', [MortgageController::class, 'saveInterest'])->middleware('audit:mortgage.interest,mortgage');
    Route::delete('/mortgages/{id}/interest/{entry}', [MortgageController::class, 'destroyInterest'])->middleware('audit:mortgage.interest_delete,mortgage');
    Route::post(
        '/properties/{property}/periods',
        [PropertyController::class, 'storePeriod']
    );

    Route::post('/exports', [ExportController::class, 'store'])->middleware('audit:export.create,financial_year_export');
    Route::get('/exports/transactions.csv', [ExportController::class, 'transactionsCsv'])->middleware('audit:export.transactions_csv,financial_year');
    Route::get('/exports/{id}', [ExportController::class, 'show']);

    Route::get('/investments', [InvestmentController::class, 'index']);
    Route::post('/investments', [InvestmentController::class, 'store']);
    Route::get('/investments/{id}', [InvestmentController::class, 'show']);
    Route::put('/investments/{id}', [InvestmentController::class, 'update'])->middleware('audit:investment.update,investment');
    Route::delete('/investments/{id}', [InvestmentController::class, 'destroy'])->middleware('audit:investment.delete,investment');

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::put('/transactions/{id}', [TransactionController::class, 'update'])->middleware('audit:transaction.update,transaction');
    Route::post('/transactions/bulk-categorise', [TransactionBulkController::class, 'categorise'])->middleware('audit:transaction.bulk_categorise,transaction');
    Route::post('/transactions/import', [TransactionBulkController::class, 'import'])->middleware('audit:transaction.import,transaction');
    Route::get('/financial-years/{id}/return-summary', [ReturnSummaryController::class, 'show']);
    Route::put('/financial-years/{id}/wfh-rate', [WorkFromHomeController::class, 'updateRate'])->middleware('audit:work_from_home.rate,financial_year');
    Route::get('/work-from-home', [WorkFromHomeController::class, 'index']);
    Route::post('/work-from-home', [WorkFromHomeController::class, 'store'])->middleware('audit:work_from_home.save,work_from_home');
    Route::delete('/work-from-home/{id}', [WorkFromHomeController::class, 'destroy'])->middleware('audit:work_from_home.delete,work_from_home');
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy'])->middleware('audit:transaction.delete,transaction');

    Route::get('/document-types', [DocumentTypeController::class, 'index']);

    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{id}', [DocumentController::class, 'show']);
    Route::post('/documents/{id}/payment', [DocumentController::class, 'confirmPayment'])->middleware('audit:document.payment_confirmed,document');
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->middleware('audit:document.delete,document');
    Route::post(
        '/documents/{id}/financial-year',
        [DocumentController::class, 'assignFinancialYear']
    )->middleware('audit:document.assign_financial_year,document');
    Route::get('/documents/{id}/content', [DocumentController::class, 'content'])->middleware('audit:document.download,document');
    Route::post('/documents/{id}/retry', [DocumentController::class, 'retry'])->middleware('audit:document.retry,document');

    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/{id}', [ReviewController::class, 'show']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy'])->middleware('audit:review.delete,review');
    Route::post(
        '/reviews/{id}/decision',
        [ReviewController::class, 'decision']
    )->middleware('audit:review.decision,review');
    Route::post(
        '/reviews/{id}/correction',
        [ReviewController::class, 'correction']
    )->middleware('audit:review.correction,review');
});
