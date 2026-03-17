<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAutentiqueWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AutentiqueWebhookController extends Controller
{
    public function receive(Request $request): Response
    {
        ProcessAutentiqueWebhook::dispatch($request->all())->onQueue('webhooks');

        return response('', 200);
    }
}
