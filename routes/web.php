<?php

use App\Http\Controllers\Webhook\AutentiqueWebhookController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::post('webhooks/autentique', [AutentiqueWebhookController::class, 'receive']);
