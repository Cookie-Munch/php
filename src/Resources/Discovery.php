<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** The data map from an in-environment scan (metadata only) — the /v1/discovery surface. */
final class Discovery
{
    public function __construct(private readonly Client $client)
    {
    }

/**
 * @param array<string, mixed> $map
 *
 * @return array<string, mixed>
 */
public function ingestMap(array $map): array
{
    return $this->client->requestJson('POST', '/v1/discovery/map', ['map' => $map]);
}

/** @return array<string, mixed> */
public function getMap(): array
{
    return $this->client->requestJson('GET', '/v1/discovery/map');
}

/** @return array<string, mixed> */
public function ropaDrafts(): array
{
    return $this->client->requestJson('GET', '/v1/discovery/ropa-drafts');
}

/** @return array<string, mixed> */
public function evidence(): array
{
    return $this->client->requestJson('GET', '/v1/discovery/evidence');
}

/** @return array<string, mixed> What changed since the last scan, and where the RoPA disagrees with reality. */
public function drift(): array
{
    return $this->client->requestJson('GET', '/v1/discovery/drift');
}

/**
 * Plan masking / row-access policy for a warehouse. Applies nothing.
 *
 * @param 'postgres'|'mysql'|'snowflake' $dialect
 * @param list<array<string, mixed>>    $rules
 * @param array{permitsTable?: string, policyPrefix?: string} $opts
 *
 * @return array<string, mixed>
 */
public function planEnforcement(string $dialect, array $rules, array $opts = []): array
{
    return $this->client->requestJson('POST', '/v1/discovery/enforcement', ['dialect' => $dialect, 'rules' => $rules] + $opts);
}
}
