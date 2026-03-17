# Testando a API — Signature Hub

Guia para testar os endpoints usando Bruno, Insomnia, Postman ou curl.

---

## Pré-requisitos

### 1. Servidor rodando

```bash
php artisan serve
# http://localhost:8000
```

### 2. Worker de filas (para processar webhooks)

```bash
php artisan queue:work database --queue=webhooks
```

### 3. Criar um tenant e gerar uma API key

Acesse o painel em **http://localhost:8000**, faça login e:

1. Crie um **Tenant** com sua API key do Autentique
2. Na aba **Chaves de API** → **Gerar Nova Chave**
3. Copie o token exibido — ele **não será mostrado novamente**

---

## Configuração no cliente

### Base URL

```
http://localhost:8000
```

### Header obrigatório em todas as requisições

```
Authorization: Bearer SEU_TOKEN
```

---

## Endpoints

### POST `/api/documents` — Criar documento

Envia um PDF para assinatura.

**Tipo de body:** `multipart/form-data`

| Campo              | Tipo   | Obrigatório | Exemplo                          |
|--------------------|--------|-------------|----------------------------------|
| `name`             | text   | sim         | `Contrato de Prestação`          |
| `file`             | file   | sim         | arquivo `.pdf`                   |
| `signers`          | text   | sim         | JSON (ver abaixo)                |
| `document_options` | text   | não         | JSON (ver abaixo)                |

**Valor do campo `signers` (string JSON):**

```json
[
  {
    "name": "João Silva",
    "email": "joao@exemplo.com",
    "action": "SIGN"
  },
  {
    "name": "Empresa XYZ",
    "action": "SIGN",
    "delivery_method": "DELIVERY_METHOD_LINK"
  }
]
```

**Valor do campo `document_options` (string JSON, opcional):**

```json
{
  "message": "Por favor, assine o contrato.",
  "refusable": true,
  "deadline_at": "2025-12-31T23:59:59Z"
}
```

**Resposta 201:**

```json
{
  "id": "1",
  "name": "Contrato de Prestação",
  "status": "pending",
  "signers": [
    {
      "external_id": "uuid",
      "name": "João Silva",
      "email": "joao@exemplo.com",
      "sign_link": null
    },
    {
      "external_id": "uuid",
      "name": "Empresa XYZ",
      "email": null,
      "sign_link": "https://assina.ae/XXXXX"
    }
  ],
  "created_at": "2025-01-01T00:00:00+00:00"
}
```

---

### GET `/api/documents/{id}` — Consultar status

Retorna o status atual do documento consultando o provedor em tempo real.

**Resposta 200:**

```json
{
  "id": "1",
  "name": "Contrato de Prestação",
  "status": "signed",
  "signed_at": "2025-01-02T14:30:00+00:00",
  "signed_pdf_url": "http://localhost:8000/api/documents/1/download",
  "signers": [
    {
      "external_id": "uuid",
      "name": "João Silva",
      "email": "joao@exemplo.com",
      "signed_at": "2025-01-02T14:30:00+00:00",
      "refused_at": null
    }
  ]
}
```

**Status possíveis:** `pending` · `signed` · `refused` · `cancelled`

---

### GET `/api/documents/{id}/download` — Download do PDF assinado

Redireciona `302` para a URL do PDF assinado no Autentique.

- Retorna `404` se o documento ainda não está com status `signed`
- No Bruno/Insomnia/Postman, ative **"Follow redirects"** para baixar o arquivo automaticamente

---

### DELETE `/api/documents/{id}` — Cancelar documento

Remove o documento no Autentique e marca como `cancelled` no hub.

**Resposta 200:**

```json
{
  "message": "Documento removido com sucesso."
}
```

---

### POST `/api/documents/{id}/resend` — Reenviar notificação

Reenvia o link de assinatura para um signatário específico.

**Body JSON:**

```json
{
  "signer_external_id": "uuid-do-signatario"
}
```

O `signer_external_id` vem no campo `signers[].external_id` da resposta do `POST /api/documents`.

**Resposta 200:**

```json
{
  "message": "Notificação reenviada com sucesso."
}
```

---

## Webhook (rota pública, sem autenticação)

### POST `/webhooks/autentique` — Simular evento do Autentique

Use para testar o processamento de webhooks sem precisar aguardar o Autentique disparar.

O `external_id` do documento está na tabela `documents` — você pode consultar via tinker:

```bash
php artisan tinker
App\Models\Document::select('id', 'external_id', 'status')->get();
```

#### Documento totalmente assinado

```json
{
  "event": {
    "id": "evt-001",
    "type": "document.finished",
    "data": {
      "id": "EXTERNAL_ID_DO_DOCUMENTO"
    }
  }
}
```

#### Signatário recusou

```json
{
  "event": {
    "id": "evt-002",
    "type": "signature.rejected",
    "data": {
      "document": "EXTERNAL_ID_DO_DOCUMENTO"
    }
  }
}
```

#### Documento deletado no provedor

```json
{
  "event": {
    "id": "evt-003",
    "type": "document.deleted",
    "data": {
      "id": "EXTERNAL_ID_DO_DOCUMENTO"
    }
  }
}
```

> O webhook retorna `200` imediatamente e processa em background via queue.
> O worker precisa estar rodando para o processamento acontecer.

---

## Erros

Todas as respostas de erro seguem o formato:

```json
{
  "message": "Descrição do erro.",
  "code": "CODIGO_DO_ERRO"
}
```

| HTTP | Code                    | Situação                            |
|------|-------------------------|-------------------------------------|
| 401  | `UNAUTHORIZED`          | Token ausente ou inválido           |
| 403  | `FORBIDDEN`             | Documento pertence a outro tenant   |
| 404  | `DOCUMENT_NOT_FOUND`    | Documento não existe                |
| 404  | `SIGNED_FILE_NOT_READY` | PDF ainda não assinado              |
| 422  | `VALIDATION_ERROR`      | Campos obrigatórios ausentes        |
| 502  | `PROVIDER_ERROR`        | Falha na comunicação com o Autentique |

---

## Verificando o webhook de retorno (forward)

Se o tenant tiver um `webhook_url` configurado, o hub repassa o evento normalizado com o header:

```
X-Hub-Signature: sha256=HMAC_DO_PAYLOAD
```

Para validar a assinatura no seu app:

```php
$payload   = file_get_contents('php://input');
$secret    = 'SEU_WEBHOOK_SECRET';
$expected  = 'sha256=' . hash_hmac('sha256', $payload, $secret);
$received  = $_SERVER['HTTP_X_HUB_SIGNATURE'];

if (!hash_equals($expected, $received)) {
    http_response_code(401);
    exit;
}
```
