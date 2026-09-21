<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** AI governance: policy, the inline gateway, inventory and lineage — the /v1/ai surface. */
final class Ai
{
    public function __construct(private readonly Client $client)
    {
    }

/** @return array<string, mixed> */
public function getPolicy(): array
{
    return $this->client->requestJson('GET', '/v1/ai/policy');
}

/**
 * Replace the AI gateway policy.
 *
 * @param array<string, mixed> $policy
 *
 * @return array<string, mixed>
 */
public function setPolicy(array $policy): array
{
    return $this->client->requestJson('PUT', '/v1/ai/policy', ['policy' => $policy]);
}

/**
 * Enforce consent and policy on a prompt or response. Needs the ai:inspect scope.
 *
 * @param array{prompt: string, purpose: string, model?: string, actor?: string, direction?: string} $input
 *
 * @return array<string, mixed>
 */
public function inspect(array $input): array
{
    return $this->client->requestJson('POST', '/v1/ai/inspect', $input);
}

/** @return array<string, mixed> */
public function inventory(): array
{
    return $this->client->requestJson('GET', '/v1/ai/inventory');
}

/** @return array<string, mixed> */
public function lineage(): array
{
    return $this->client->requestJson('GET', '/v1/ai/lineage');
}

/**
 * @param array{id: string, name: string, provider?: string, purpose?: string} $input
 *
 * @return array<string, mixed>
 */
public function registerSystem(array $input): array
{
    return $this->client->requestJson('POST', '/v1/ai/systems', $input);
}

/** @return array<string, mixed> */
public function systems(): array
{
    return $this->client->requestJson('GET', '/v1/ai/systems');
}

/** @return array<string, mixed> */
public function audit(?int $limit = null): array
{
    return $this->client->requestJson('GET', '/v1/ai/audit', null, ['limit' => $limit]);
}
}
