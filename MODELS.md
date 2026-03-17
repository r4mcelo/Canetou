# Modelos — Referência de Campos

---

## `Tenant`

Representa um cliente do hub. Cada tenant tem suas próprias credenciais do provedor de assinatura e recebe eventos de webhook de forma isolada.

| Campo              | Tipo      | Descrição |
|--------------------|-----------|-----------|
| `id`               | integer   | Identificador interno do tenant. |
| `name`             | string    | Nome do tenant. Usado apenas para identificação no painel. |
| `provider`         | string    | Qual provedor de assinatura este tenant usa. Atualmente só `autentique`. Preparado para `docusign`, `clicksign`, etc. |
| `provider_api_key` | string    | API key do tenant no provedor (ex: token do Autentique). **Armazenada criptografada** no banco via cast `encrypted` do Laravel — nunca em texto puro. |
| `provider_sandbox` | boolean   | Quando `true`, os documentos criados por este tenant vão para o ambiente de testes do provedor e não têm validade jurídica. Cada tenant pode operar em sandbox independentemente. |
| `webhook_url`      | string    | URL do app do tenant para onde o hub repassa os eventos normalizados (ex: `document.finished`). Se vazio, o hub processa o evento mas não faz forward. |
| `webhook_secret`   | string    | Secret usado para assinar o payload do forward via HMAC-SHA256. O tenant usa esse valor para validar que o evento veio de fato do hub, checando o header `X-Hub-Signature`. |
| `active`           | boolean   | Controla se o tenant pode fazer requisições à API. Se `false`, todas as chamadas com chaves deste tenant retornam `401`. |
| `created_at`       | timestamp | Data de criação do registro. |
| `updated_at`       | timestamp | Data da última atualização do registro. |

---

## `TenantApiKey`

Representa uma chave de API que um tenant usa para se autenticar no hub. Um tenant pode ter múltiplas chaves (ex: uma por ambiente), o que permite rotacionar sem downtime.

| Campo          | Tipo      | Descrição |
|----------------|-----------|-----------|
| `id`           | integer   | Identificador interno da chave. |
| `tenant_id`    | integer   | FK para `tenants`. Indica a qual tenant esta chave pertence. |
| `key`          | string    | **Hash SHA-256** do token original. O token em texto puro nunca é armazenado — apenas o hash. Na autenticação, o hub recalcula `hash('sha256', $tokenRecebido)` e compara com este campo. |
| `name`         | string    | Rótulo para identificar a chave. Exemplos: `produção`, `staging`, `integração ERP`. |
| `last_used_at` | timestamp | Data e hora da última requisição autenticada com esta chave. Atualizado a cada request bem-sucedido. Útil para auditar chaves inativas. |
| `expires_at`   | timestamp | Data de expiração da chave. Se `null`, a chave não expira. Se preenchido, requisições após essa data retornam `401`. |
| `active`       | boolean   | Permite revogar uma chave manualmente sem excluí-la. Chaves com `active = false` retornam `401`. |
| `created_at`   | timestamp | Data de criação do registro. |
| `updated_at`   | timestamp | Data da última atualização do registro. |

---

## `Document`

Representa um documento enviado para assinatura por um tenant. O hub não armazena o PDF — apenas os metadados e o identificador do documento no provedor.

| Campo               | Tipo      | Descrição |
|---------------------|-----------|-----------|
| `id`                | integer   | Identificador interno do documento no hub. É este ID que o tenant usa nos endpoints da API (`/api/documents/1`). |
| `tenant_id`         | integer   | FK para `tenants`. Todo acesso a um documento é filtrado por este campo — um tenant nunca enxerga documentos de outro. |
| `external_id`       | string    | ID do documento no provedor (UUID no Autentique). Usado para todas as chamadas à API do provedor e para correlacionar webhooks recebidos com documentos no banco. |
| `external_provider` | string    | Nome do provedor onde o documento foi criado (`autentique`). Registrado no momento da criação para que, mesmo que o tenant mude de provedor futuramente, o documento saiba onde foi enviado. |
| `name`              | string    | Nome do documento informado pelo tenant no momento da criação. |
| `status`            | string    | Status atual do documento no hub. Valores possíveis: `pending` (aguardando assinaturas), `signed` (todos assinaram), `refused` (algum signatário recusou), `cancelled` (documento removido). |
| `signers`           | JSON      | Snapshot dos signatários retornados pelo provedor no momento da criação. Cada entrada contém `external_id`, `name`, `email` e `sign_link` (quando `delivery_method` é `LINK`). |
| `provider_response` | JSON      | Resposta bruta completa do provedor ao criar o documento. Útil para debug e para acessar campos não mapeados pelo hub. |
| `signed_at`         | timestamp | Data e hora em que o documento foi totalmente assinado. Preenchido quando o webhook `document.finished` é recebido e processado. |
| `deleted_at`        | timestamp | Soft delete — quando preenchido, o documento foi cancelado/removido mas permanece no banco para histórico. |
| `created_at`        | timestamp | Data de criação do registro no hub. |
| `updated_at`        | timestamp | Data da última atualização do registro. |

---

## `WebhookLog`

Registra todos os eventos de webhook que passam pelo hub, tanto os recebidos do provedor (`inbound`) quanto os repassados para o tenant (`outbound`). Serve para auditoria, debug e deduplicação.

| Campo               | Tipo      | Descrição |
|---------------------|-----------|-----------|
| `id`                | integer   | Identificador interno do log. |
| `tenant_id`         | integer   | FK para `tenants` (nullable). Pode ser `null` se o evento chegou mas o documento não foi encontrado no banco para correlacionar com um tenant. |
| `document_id`       | integer   | FK para `documents` (nullable). Preenchido após o documento ser encontrado pelo `external_id`. Pode ser `null` se o documento não existir no hub. |
| `direction`         | string    | `inbound` — evento recebido do provedor (Autentique → hub). `outbound` — evento repassado para o tenant (hub → app do tenant). |
| `event_type`        | string    | Tipo do evento. Exemplos: `document.finished`, `signature.rejected`, `document.deleted`. |
| `external_event_id` | string    | ID único do evento fornecido pelo provedor. Usado para **deduplicação**: se um mesmo evento for entregue duas vezes pelo Autentique, o segundo é ignorado por já existir um `inbound` com este ID. |
| `payload`           | JSON      | Conteúdo completo do evento. Para `inbound`, é o payload bruto do provedor. Para `outbound`, é o payload normalizado enviado ao tenant. |
| `response_status`   | integer   | Código HTTP da resposta. Para `outbound`, é o status retornado pelo app do tenant (ex: `200`, `404`, `500`). Nulo para `inbound`. |
| `response_body`     | text      | Corpo da resposta. Para `outbound`, é o body retornado pelo app do tenant. Útil para diagnosticar falhas na entrega do forward. Nulo para `inbound`. |
| `attempt`           | integer   | Número da tentativa de entrega. Começa em `1`. O job tem `$tries = 3`, então em caso de falha o evento pode ter até 3 logs `outbound`. |
| `created_at`        | timestamp | Data de criação do registro. |
| `updated_at`        | timestamp | Data da última atualização do registro. |
