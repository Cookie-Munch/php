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
public function executors(array $metadata = []): array
{
    return $this->client->requestJson('GET', '/v1/dsar/executors' . Client::metadataQuery($metadata));
}

/**
 * @return array<string, mixed> One connection with its full profile, never its credentials. Read it before
 * changing the profile: updateExecutor replaces the whole profile.
 */
public function executor(string $id): array
{
    return $this->client->requestJson('GET', '/v1/dsar/executors/' . rawurlencode($id));
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
 * Connect a system from a versioned preset — POST /v1/dsar/executors {preset}. `atlas`
 * (optional `atlasIdAppId`), `forumulate` (`orgId`), `cm-standard-v1` (`baseUrl` and `system`)
 * or `chatly`. The response carries presetVersion and presetDiff. A preset waiting on another
 * platform's API is refused (409 preset_waiting_on_platform); see executorPresets().
 *
 * @param array{system?: string, baseUrl?: string, orgId?: string, atlasIdAppId?: string, presetVersion?: int, webhookSecret?: string, auto?: bool, cbid?: string} $options
 *
 * @return array<string, mixed>
 */
public function connectExecutorPreset(string $preset, string $secretKey, array $options = [], bool $dryRun = false): array
{
    $body = ['preset' => $preset, 'secretKey' => $secretKey] + $options;

    return $this->client->requestJson('POST', '/v1/dsar/executors' . ($dryRun ? '?dryRun=1' : ''), $body);
}

/**
 * The versioned presets, whether each is usable now or waits on another platform, and the
 * organisation's canary address — GET /v1/dsar/executors/presets.
 *
 * @return array<string, mixed>
 */
public function executorPresets(): array
{
    return $this->client->requestJson('GET', '/v1/dsar/executors/presets');
}

/**
 * Rebuild a connection from its preset's latest version (or `presetVersion`), keeping its id
 * and credential; `preset` adopts one on a hand-written connection — POST
 * /v1/dsar/executors/{id}/upgrade-preset. Answers with a diff; `$dryRun` changes nothing.
 *
 * Your hand edits (`profileOverrides`) are kept and listed as `kept` or `conflict`;
 * `acceptPreset` names the paths that take the preset's value again. A pinned connection is
 * refused (409 `preset_pinned`).
 *
 * @param array{preset?: string, presetVersion?: int, orgId?: string, atlasIdAppId?: string, revokeAtlasIdGrant?: bool, acceptPreset?: string|list<string>} $input
 *
 * @return array<string, mixed>
 */
public function upgradeExecutorPreset(string $id, array $input = [], bool $dryRun = false): array
{
    return $this->client->requestJson(
        'POST',
        '/v1/dsar/executors/' . rawurlencode($id) . '/upgrade-preset' . ($dryRun ? '?dryRun=1' : ''),
        (object) $input,
    );
}

/**
 * Change a connection in place — PATCH /v1/dsar/executors/{id}. Only the keys you pass change;
 * the credential is kept unless `secretKey` is given, and `webhookSecret => null` removes it.
 * `$dryRun` (?dryRun=1) returns the would-be connection and a diff, and changes nothing.
 *
 * @param array{profile?: array<string, mixed>, baseUrl?: string, secretKey?: string, webhookSecret?: ?string, auto?: bool, orchestrator?: int} $patch
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
 * Re-check a connection now: a canary test that records its health, for a deploy pipeline —
 * POST /v1/dsar/executors/{id}/recheck.
 *
 * @return array<string, mixed>
 */
public function recheckExecutor(string $id): array
{
    return $this->client->requestJson('POST', '/v1/dsar/executors/' . rawurlencode($id) . '/recheck', (object) []);
}

/**
 * A connection's last health checks, newest first (`$limit` 1 to 50) —
 * GET /v1/dsar/executors/{id}/health/history.
 *
 * @return array<int, array<string, mixed>>
 */
public function executorHealthHistory(string $id, ?int $limit = null): array
{
    return $this->client->requestJson('GET', '/v1/dsar/executors/' . rawurlencode($id) . '/health/history', null, ['limit' => $limit]);
}

/**
 * Try a connection against one email without filing a request — POST /v1/dsar/executors/{id}/test.
 * Runs the lookup and returns the raw (redacted) upstream answers. `open: true` on an export
 * opens and polls a real export once; an erasure is never sent. `canary: true` (instead of an
 * email) uses the org canary address — no real person — and a canary locate updates health.
 *
 * @return array<string, mixed>
 */
public function testExecutor(string $id, ?string $email = null, ?string $operation = null, ?bool $open = null, ?bool $canary = null): array
{
    $body = [];
    if ($email !== null) {
        $body['email'] = $email;
    }
    if ($canary !== null) {
        $body['canary'] = $canary;
    }
    if ($operation !== null) {
        $body['operation'] = $operation;
    }
    if ($open !== null) {
        $body['open'] = $open;
    }

    return $this->client->requestJson('POST', '/v1/dsar/executors/' . rawurlencode($id) . '/test', (object) $body);
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

/**
 * Run the cm-standard-v2 conformance kit against a connection, on the canary subject only —
 * POST /v1/dsar/executors/{id}/test {"conformance": true}.
 *
 * @return array<string, mixed>
 */
public function executorConformance(string $id): array
{
    return $this->client->requestJson('POST', '/v1/dsar/executors/' . rawurlencode($id) . '/test', ['conformance' => true]);
}

/**
 * A new completion secret, shown once; the old one keeps verifying for 24 hours —
 * POST /v1/dsar/executors/{id}/completion-secret/roll.
 *
 * @return array<string, mixed>
 */
public function rollExecutorCompletionSecret(string $id): array
{
    return $this->client->requestJson('POST', '/v1/dsar/executors/' . rawurlencode($id) . '/completion-secret/roll');
}
}
