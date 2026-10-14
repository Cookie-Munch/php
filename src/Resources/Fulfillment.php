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
 * webhook URL to configure in that system.
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

    return $this->client->requestJson('POST', '/v1/dsar/executors', $body);
}

/**
 * Change a connection in place — PATCH /v1/dsar/executors/{id}. Only the keys you pass change;
 * the credential is kept unless `secretKey` is given, and `webhookSecret => null` removes it.
 *
 * @param array{profile?: array<string, mixed>, baseUrl?: string, secretKey?: string, webhookSecret?: ?string, auto?: bool} $patch
 *
 * @return array<string, mixed>
 */
public function updateExecutor(string $id, array $patch): array
{
    return $this->client->requestJson('PATCH', '/v1/dsar/executors/' . rawurlencode($id), $patch);
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
