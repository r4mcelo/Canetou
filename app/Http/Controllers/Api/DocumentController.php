<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Contracts\SignatureProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class DocumentController extends Controller
{
    public function __construct(private readonly SignatureProviderInterface $provider) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf',
            'signers' => 'required|string',
            'document_options' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'code' => 'VALIDATION_ERROR',
                'errors' => $validator->errors(),
            ], 422);
        }

        $signers = json_decode($request->input('signers'), true);

        if (! is_array($signers)) {
            return response()->json([
                'message' => 'O campo signers deve ser um JSON válido.',
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }

        $options = [];
        if ($request->filled('document_options')) {
            $options = json_decode($request->input('document_options'), true) ?? [];
        }

        $file = $request->file('file');
        $fileContent = file_get_contents($file->getRealPath());
        $fileName = $file->getClientOriginalName();

        try {
            $result = $this->provider->createDocument(
                $request->input('name'),
                $signers,
                $fileContent,
                $fileName,
                $options
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Erro ao comunicar com o provedor.',
                'code' => 'PROVIDER_ERROR',
            ], 502);
        }

        $document = Document::create([
            'tenant_id' => $request->tenant->id,
            'external_id' => $result['external_id'],
            'external_provider' => $request->tenant->provider,
            'name' => $request->input('name'),
            'status' => 'pending',
            'signers' => $result['signers'],
            'provider_response' => $result['raw'],
        ]);

        return response()->json([
            'id' => (string) $document->id,
            'name' => $document->name,
            'status' => $document->status,
            'signers' => $result['signers'],
            'created_at' => $document->created_at->toIso8601String(),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $document = Document::where('id', $id)
            ->where('tenant_id', $request->tenant->id)
            ->first();

        if (! $document) {
            return $this->notFound();
        }

        try {
            $data = $this->provider->getDocument($document->external_id);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Erro ao comunicar com o provedor.',
                'code' => 'PROVIDER_ERROR',
            ], 502);
        }

        $signedPdfUrl = $data['status'] === 'signed'
            ? url("/api/documents/{$document->id}/download")
            : null;

        return response()->json([
            'id' => (string) $document->id,
            'name' => $document->name,
            'status' => $data['status'],
            'signed_at' => $document->signed_at?->toIso8601String(),
            'signed_pdf_url' => $signedPdfUrl,
            'signers' => $data['signers'],
        ]);
    }

    public function download(Request $request, int $id)
    {
        $document = Document::where('id', $id)
            ->where('tenant_id', $request->tenant->id)
            ->first();

        if (! $document) {
            return $this->notFound();
        }

        if ($document->status !== 'signed') {
            return response()->json([
                'message' => 'O PDF assinado ainda não está disponível.',
                'code' => 'SIGNED_FILE_NOT_READY',
            ], 404);
        }

        try {
            $url = $this->provider->getSignedFileUrl($document->external_id);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Erro ao comunicar com o provedor.',
                'code' => 'PROVIDER_ERROR',
            ], 502);
        }

        if (! $url) {
            return response()->json([
                'message' => 'O PDF assinado ainda não está disponível.',
                'code' => 'SIGNED_FILE_NOT_READY',
            ], 404);
        }

        return redirect($url, 302);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $document = Document::where('id', $id)
            ->where('tenant_id', $request->tenant->id)
            ->first();

        if (! $document) {
            return $this->notFound();
        }

        try {
            $this->provider->deleteDocument($document->external_id);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Erro ao comunicar com o provedor.',
                'code' => 'PROVIDER_ERROR',
            ], 502);
        }

        $document->update(['status' => 'cancelled']);
        $document->delete();

        return response()->json(['message' => 'Documento removido com sucesso.']);
    }

    public function resend(Request $request, int $id): JsonResponse
    {
        $document = Document::where('id', $id)
            ->where('tenant_id', $request->tenant->id)
            ->first();

        if (! $document) {
            return $this->notFound();
        }

        $validator = Validator::make($request->all(), [
            'signer_external_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'code' => 'VALIDATION_ERROR',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->provider->resendToSigner($request->input('signer_external_id'));
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Erro ao comunicar com o provedor.',
                'code' => 'PROVIDER_ERROR',
            ], 502);
        }

        return response()->json(['message' => 'Notificação reenviada com sucesso.']);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'message' => 'Documento não encontrado.',
            'code' => 'DOCUMENT_NOT_FOUND',
        ], 404);
    }
}
