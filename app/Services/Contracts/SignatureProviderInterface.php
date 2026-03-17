<?php

namespace App\Services\Contracts;

interface SignatureProviderInterface
{
    /**
     * Cria um documento no provedor.
     *
     * Retorno normalizado:
     * [
     *   'external_id' => string,
     *   'status'      => 'pending',
     *   'signers'     => [
     *     [
     *       'external_id' => string,
     *       'name'        => string,
     *       'email'       => string|null,
     *       'sign_link'   => string|null,
     *     ]
     *   ],
     *   'raw' => array,
     * ]
     */
    public function createDocument(
        string $name,
        array $signers,
        string $fileContent,
        string $fileName,
        array $options = []
    ): array;

    /**
     * Retorna status atual do documento.
     *
     * Retorno normalizado:
     * [
     *   'external_id'    => string,
     *   'status'         => 'pending'|'signed'|'refused'|'expired'|'cancelled',
     *   'signed_pdf_url' => string|null,
     *   'signers'        => [
     *     [
     *       'external_id' => string,
     *       'name'        => string,
     *       'email'       => string|null,
     *       'signed_at'   => string|null,
     *       'refused_at'  => string|null,
     *     ]
     *   ],
     * ]
     */
    public function getDocument(string $externalId): array;

    /**
     * Retorna a URL do PDF assinado ou null se ainda não disponível.
     */
    public function getSignedFileUrl(string $externalId): ?string;

    /**
     * Remove/cancela o documento no provedor.
     */
    public function deleteDocument(string $externalId): bool;

    /**
     * Reenvia notificação para um signatário.
     */
    public function resendToSigner(string $signerExternalId): bool;
}
