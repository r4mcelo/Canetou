<?php

namespace App\Actions;

use App\Models\Document;
use App\Models\WebhookLog;
use App\Services\Providers\Autentique\AutentiqueProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ForwardWebhookAction
{
    public function execute(Document $document, string $event): void
    {
        $tenant = $document->tenant;

        if (empty($tenant->webhook_url)) {
            return;
        }

        $providerData = $this->fetchProviderData($document, $tenant);

        $signedPdfUrl = $document->status === 'signed'
            ? url("/api/documents/{$document->id}/download")
            : null;

        $payload = [
            'event'          => $event,
            'document_id'    => (string) $document->id,
            'status'         => $document->status,
            'signed_pdf_url' => $signedPdfUrl,
            'signed_at'      => $document->signed_at?->toIso8601String(),
            'signers'        => $providerData['signers'],
        ];

        $hmac = hash_hmac('sha256', json_encode($payload), $tenant->webhook_secret ?? '');

        try {
            $response = Http::withHeaders([
                'X-Hub-Signature' => "sha256={$hmac}",
            ])->post($tenant->webhook_url, $payload);

            WebhookLog::create([
                'tenant_id'       => $tenant->id,
                'document_id'     => $document->id,
                'direction'       => 'outbound',
                'event_type'      => $event,
                'payload'         => $payload,
                'response_status' => $response->status(),
                'response_body'   => $response->body(),
                'attempt'         => 1,
            ]);

            if ($response->failed()) {
                throw new RuntimeException("Webhook delivery failed with status {$response->status()}.");
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            WebhookLog::create([
                'tenant_id'       => $tenant->id,
                'document_id'     => $document->id,
                'direction'       => 'outbound',
                'event_type'      => $event,
                'payload'         => $payload,
                'response_status' => null,
                'response_body'   => $e->getMessage(),
                'attempt'         => 1,
            ]);

            throw new RuntimeException("Falha ao entregar webhook: {$e->getMessage()}", 0, $e);
        }
    }

    private function fetchProviderData(Document $document, $tenant): array
    {
        $provider = match ($tenant->provider) {
            'autentique' => new AutentiqueProvider($tenant->provider_api_key, $tenant->provider_sandbox),
            default      => throw new RuntimeException("Provedor '{$tenant->provider}' não suportado."),
        };

        return $provider->getDocument($document->external_id);
    }
}
