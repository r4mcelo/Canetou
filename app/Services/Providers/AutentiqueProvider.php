<?php

namespace App\Services\Autentique;

use App\Services\Contracts\SignatureProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AutentiqueProvider implements SignatureProviderInterface
{
    private const ENDPOINT = 'https://api.autentique.com.br/v2/graphql';

    public function __construct(
        private readonly string $apiKey,
        private readonly bool $sandbox = false
    ) {}

    public function createDocument(string $name, array $signers, string $fileContent, string $fileName, array $options = []): array
    {
        $sandbox = $this->sandbox ? 'true' : 'false';

        $mutation = <<<GQL
            mutation CreateDocumentMutation(
                \$document: DocumentInput!,
                \$signers: [SignerInput!]!,
                \$file: Upload!
            ) {
                createDocument(
                    sandbox: {$sandbox},
                    document: \$document,
                    signers: \$signers,
                    file: \$file
                ) {
                    id
                    name
                    created_at
                    signatures {
                        public_id
                        name
                        email
                        action { name }
                        link { short_link }
                    }
                }
            }
        GQL;

        $documentParams = array_merge([
            'name' => $name,
            'new_signature_style' => true,
            'show_audit_page' => false,
        ], $options);

        $operations = json_encode([
            'query' => $mutation,
            'variables' => [
                'document' => $documentParams,
                'signers' => $signers,
                'file' => null,
            ],
        ]);

        $response = $this->http()
            ->attach('file', $fileContent, $fileName)
            ->post(self::ENDPOINT, [
                'operations' => $operations,
                'map' => '{"file": ["variables.file"]}',
            ]);

        $data = $this->parse($response, 'createDocument');

        return [
            'external_id' => $data['id'],
            'status' => 'pending',
            'signers' => collect($data['signatures'])->map(fn ($s) => [
                'external_id' => $s['public_id'],
                'name' => $s['name'],
                'email' => $s['email'],
                'sign_link' => $s['link']['short_link'] ?? null,
            ])->toArray(),
            'raw' => $data,
        ];
    }

    public function getDocument(string $externalId): array
    {
        $query = <<<'GQL'
            query GetDocument($id: UUID!) {
                document(id: $id) {
                    id
                    files { original signed }
                    signatures {
                        public_id
                        name
                        email
                        signed   { created_at }
                        rejected { created_at }
                    }
                }
            }
        GQL;

        $data = $this->parse(
            $this->http()->post(self::ENDPOINT, ['query' => $query, 'variables' => ['id' => $externalId]]),
            'document'
        );

        $signers = $data['signatures'];
        $total = count($signers);
        $signedCount = collect($signers)->filter(fn ($s) => $s['signed'])->count();
        $refusedCount = collect($signers)->filter(fn ($s) => $s['rejected'])->count();

        $status = match (true) {
            $refusedCount > 0 => 'refused',
            $signedCount === $total => 'signed',
            default => 'pending',
        };

        return [
            'external_id' => $data['id'],
            'status' => $status,
            'signed_pdf_url' => $data['files']['signed'] ?? null,
            'signers' => collect($signers)->map(fn ($s) => [
                'external_id' => $s['public_id'],
                'name' => $s['name'],
                'email' => $s['email'],
                'signed_at' => $s['signed']['created_at'] ?? null,
                'refused_at' => $s['rejected']['created_at'] ?? null,
            ])->toArray(),
        ];
    }

    public function getSignedFileUrl(string $externalId): ?string
    {
        return $this->getDocument($externalId)['signed_pdf_url'];
    }

    public function deleteDocument(string $externalId): bool
    {
        $mutation = <<<'GQL'
            mutation DeleteDocument($id: UUID!) {
                deleteDocument(id: $id)
            }
        GQL;

        return (bool) $this->parse(
            $this->http()->post(self::ENDPOINT, ['query' => $mutation, 'variables' => ['id' => $externalId]]),
            'deleteDocument'
        );
    }

    public function resendToSigner(string $signerExternalId): bool
    {
        $mutation = <<<'GQL'
            mutation ResendSignature($publicId: UUID!) {
                resendSignature(public_id: $publicId)
            }
        GQL;

        return (bool) $this->parse(
            $this->http()->post(self::ENDPOINT, ['query' => $mutation, 'variables' => ['publicId' => $signerExternalId]]),
            'resendSignature'
        );
    }

    private function http()
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->timeout(30);
    }

    private function parse($response, string $key): mixed
    {
        if ($response->failed()) {
            throw new RuntimeException("Autentique HTTP {$response->status()}: {$response->body()}");
        }

        $json = $response->json();

        if (! empty($json['errors'])) {
            throw new RuntimeException($json['errors'][0]['message']);
        }

        if (! array_key_exists($key, $json['data'] ?? [])) {
            throw new RuntimeException("Chave '{$key}' ausente na resposta do Autentique.");
        }

        return $json['data'][$key];
    }
}
