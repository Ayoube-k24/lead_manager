<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\SmtpProfileController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware([AuthenticateApiToken::class])->group(function () {
    // API Tokens management
    Route::apiResource('tokens', ApiTokenController::class)->only(['index', 'store', 'destroy']);

    // Forms management
    Route::apiResource('forms', FormController::class);

    // SMTP Profiles management
    Route::apiResource('smtp-profiles', SmtpProfileController::class);

    // Email Templates management
    Route::apiResource('email-templates', EmailTemplateController::class);
});
