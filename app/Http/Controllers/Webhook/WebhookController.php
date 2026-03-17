<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAutentiqueWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function receive(Request $request, string $provider): Response
    {
        if (! isset($provider)) {
            return response('Necessário definir um provedor para o webhook', 403);
        }

        if ($provider == 'autentique') {
            ProcessAutentiqueWebhook::dispatch($request->all())->onQueue('webhooks');

            return response('', 200);
        }

        return response('Provedor desconhecido ou não implementado', 404);
    }
}
