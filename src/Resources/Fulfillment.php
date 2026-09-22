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
 * Connect one. The secret is stored encrypted and never returned; the response carries the
 * webhook URL to configure in that system.
 *
 * @return array<string, mixed>
 */
public function connectExecutor(
    string $kind,
    string $baseUrl,
    string $secretKey,
    ?string $webhookSecret = null,
    ?string $system = null,
    ?bool $auto = null,
): array {
    $body = ['kind' => $kind, 'baseUrl' => $baseUrl, 'secretKey' => $secretKey];
    if ($webhookSecret !== null) {
        $body['webhookSecret'] = $webhookSecret;
    }
    if ($system !== null) {
        $body['system'] = $system;
    }
    if ($auto !== null) {
        $body['auto'] = $auto;
    }

    return $this->client->requestJson('POST', '/v1/dsar/executors', $body);
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
