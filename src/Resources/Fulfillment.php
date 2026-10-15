<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** DSAR fulfilment: plan work per system, and the in-environment agent's protocol. */
final class Fulfillment
{
    public function __construct(private readonly Client $client)
    {
    }

/** @return array<string, mixed> */
public function sla(): array
{
    return $this->client->requestJson('GET', '/v1/dsar/sla');
}

/**
 * @param list<array{system: string, operation: 'locate'|'export'|'erase'|'optOut'}> $systems
 *
 * @return array<string, mixed>
 */
public function plan(string $requestId, array $systems, bool $includeHistorical = false): array
{
    $body = ['systems' => $systems];
    if ($includeHistorical) {
        $body['includeHistorical'] = true;
    }

    return $this->client->requestJson('POST', '/v1/dsar/' . rawurlencode($requestId) . '/plan', $body);
}

/** @return array<string, mixed> */
public function status(string $requestId): array
{
    return $this->client->requestJson('GET', '/v1/dsar/' . rawurlencode($requestId) . '/fulfillment');
}

/** @return list<array<string, mixed>> Systems connected to run part of a request themselves. */
public function executors(): array
{
    return $this->client->requestJson('GET', '/v1/dsar/executors');
}

/**
 * Connect one. $profile describes that system's API — paths, the words it uses for export
 * and erase, its status vocabulary, how it signs webhooks — so connecting a new platform
 * needs no code. The secret is stored encrypted and never returned; the response carries the
 * webhook URL to configure in that system. `$dryRun` (?dryRun=1) validates everything and
 * returns the would-be connection and a diff, storing nothing. A `whsec_<base64>` webhook
 * secret verifies deliveries signed with its base64-decoded bytes or with the literal string;
 * any other only with its UTF-8 bytes.
 *
 * @param array<string, mixed> $profile
 *
 * @return array<string, mixed>
 */
public function connectExecutor(
    string $system,
    string $baseUrl,
    string $secretKey,
    array $profile,
    ?string $webhookSecret = null,
    ?bool $auto = null,
    bool $dryRun = false,
): array {
    $body = [
        'system' => $system,
        'baseUrl' => $baseUrl,
        'secretKey' => $secretKey,
        'profile' => $profile,
    ];
    if ($webhookSecret !== null) {
        $body['webhookSecret'] = $webhookSecret;
    }
    if ($auto !== null) {
        $body['auto'] = $auto;
    }

    return $this->client->requestJson('POST', '/v1/dsar/executors' . ($dryRun ? '?dryRun=1' : ''), $body);
}

/**
 * Change a connection in place — PATCH /v1/dsar/executors/{id}. Only the keys you pass change;
 * the credential is kept unless `secretKey` is given, and `webhookSecret => null` removes it.
 * `$dryRun` (?dryRun=1) returns the would-be connection and a diff, and changes nothing.
 *
 * @param array{profile?: array<string, mixed>, baseUrl?: string, secretKey?: string, webhookSecret?: ?string, auto?: bool} $patch
 *
 * @return array<string, mixed>
 */
public function updateExecutor(string $id, array $patch, bool $dryRun = false): array
{
    return $this->client->requestJson('PATCH', '/v1/dsar/executors/' . rawurlencode($id) . ($dryRun ? '?dryRun=1' : ''), $patch);
}

/**
 * Would this webhook delivery verify? — POST /v1/dsar/executors/{id}/webhook/test. Runs the
 * receiver's exact check on the headers and the raw body string (the bytes that were signed)
 * and acts on nothing. Returns `verified`, `stage` and `reason`.
 *
 * @param array<string, string> $headers
 *
 * @return array<string, mixed>
 */
public function testExecutorWebhook(string $id, array $headers, string $body): array
{
    return $this->client->requestJson(
        'POST',
        '/v1/dsar/executors/' . rawurlencode($id) . '/webhook/test',
        ['headers' => (object) $headers, 'body' => $body],
    );
}

/**
 * The last 50 deliveries to a connection's webhook, newest first, each with its reason —
 * GET /v1/dsar/executors/{id}/webhook/deliveries.
 *
 * @return array<int, array<string, mixed>>
 */
public function executorWebhookDeliveries(string $id): array
{
    return $this->client->requestJson('GET', '/v1/dsar/executors/' . rawurlencode($id) . '/webhook/deliveries');
}

/**
 * Try a connection against one email without filing a request — POST /v1/dsar/executors/{id}/test.
 * Runs the lookup and returns the raw (redacted) upstream answers. `open: true` on an export
 * opens and polls a real export once; an erasure is never sent.
 *
 * @return array<string, mixed>
 */
public function testExecutor(string $id, string $email, ?string $operation = null, ?bool $open = null): array
{
    $body = ['email' => $email];
    if ($operation !== null) {
        $body['operation'] = $operation;
    }
    if ($open !== null) {
        $body['open'] = $open;
    }

    return $this->client->requestJson('POST', '/v1/dsar/executors/' . rawurlencode($id) . '/test', $body);
}

/** @return array<string, mixed> One task: status history (events), upstream log, versions. */
public function task(string $requestId, string $taskId): array
{
    return $this->client->requestJson(
        'GET',
        '/v1/dsar/' . rawurlencode($requestId) . '/tasks/' . rawurlencode($taskId),
    );
}

public function disconnectExecutor(string $id): void
{
    $this->client->requestJson('DELETE', '/v1/dsar/executors/' . rawurlencode($id));
}

/** @return array<string, mixed> The export bundle a connected system produced. */
public function taskExport(string $requestId, string $taskId): array
{
    return $this->client->requestJson(
        'GET',
        '/v1/dsar/' . rawurlencode($requestId) . '/tasks/' . rawurlencode($taskId) . '/export',
    );
}

/** @return array<string, mixed> For the in-environment agent: tasks to execute inside your network. */
public function pendingTasks(?int $limit = null): array
{
    return $this->client->requestJson('GET', '/v1/dsar/agent/tasks', null, ['limit' => $limit]);
}

/** @return array<string, mixed> For the in-environment agent: only the outcome crosses the boundary. */
public function reportTask(string $taskId, bool $ok, ?string $error = null): array
{
    $body = ['ok' => $ok];
    if ($error !== null) {
        $body['error'] = $error;
    }

    return $this->client->requestJson('POST', '/v1/dsar/agent/tasks/' . rawurlencode($taskId) . '/result', $body);
}
}
