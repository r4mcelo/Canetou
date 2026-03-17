<?php

namespace App\Jobs;

use App\Actions\ForwardWebhookAction;
use App\Models\Document;
use App\Models\WebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAutentiqueWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(private readonly array $payload) {}

    public function handle(ForwardWebhookAction $forwardAction): void
    {
        $event = $this->payload['event'] ?? [];
        $eventId = $event['id'] ?? null;
        $eventType = $event['type'] ?? null;

        if (! $eventId || ! $eventType) {
            Log::warning('[ProcessAutentiqueWebhook]: payload sem event.id ou event.type', $this->payload);

            return;
        }

        $duplicate = WebhookLog::where('external_event_id', $eventId)
            ->where('direction', 'inbound')
            ->exists();

        if ($duplicate) {
            return;
        }

        $log = WebhookLog::create([
            'tenant_id' => null,
            'document_id' => null,
            'direction' => 'inbound',
            'event_type' => $eventType,
            'external_event_id' => $eventId,
            'payload' => $this->payload,
            'attempt' => 1,
        ]);

        $document = $this->resolveDocument($event, $eventType);

        if (! $document) {
            Log::warning("[ProcessAutentiqueWebhook]: documento não encontrado para evento {$eventType}", $event);

            return;
        }

        $log->update([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
        ]);

        match ($eventType) {
            'document.finished' => $this->handleFinished($document),
            'signature.rejected' => $this->handleRejected($document),
            'document.deleted' => $this->handleDeleted($document),
            default => Log::info("[ProcessAutentiqueWebhook]: evento desconhecido '{$eventType}'"),
        };

        if (in_array($eventType, ['document.finished', 'signature.rejected', 'document.deleted'])) {
            $forwardAction->execute($document, $eventType);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[ProcessAutentiqueWebhook]: falhou', [
            'message' => $e->getMessage(),
            'payload' => $this->payload,
        ]);
    }

    private function resolveDocument(array $event, string $eventType): ?Document
    {
        $externalId = match ($eventType) {
            'signature.rejected' => $event['data']['document'] ?? null,
            default => $event['data']['id'] ?? null,
        };

        if (! $externalId) {
            return null;
        }

        return Document::where('external_id', $externalId)->first();
    }

    private function handleFinished(Document $document): void
    {
        $document->update([
            'status' => 'signed',
            'signed_at' => now(),
        ]);
    }

    private function handleRejected(Document $document): void
    {
        $document->update(['status' => 'refused']);
    }

    private function handleDeleted(Document $document): void
    {
        $document->update(['status' => 'cancelled']);
    }
}
