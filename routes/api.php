<?php

use App\Http\Controllers\Api\DocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.tenant')->group(function () {
    Route::post('documents', [DocumentController::class, 'store']);
    Route::get('documents/{id}', [DocumentController::class, 'show']);
    Route::get('documents/{id}/download', [DocumentController::class, 'download']);
    Route::delete('documents/{id}', [DocumentController::class, 'destroy']);
    Route::post('documents/{id}/resend', [DocumentController::class, 'resend']);
});
