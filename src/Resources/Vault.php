<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** A resolved person's consent — the /v1/vault surface. */
final class Vault
{
    public function __construct(private readonly Client $client)
    {
    }

/**
 * @param list<array{space: string, value: string}> $identifiers
 * @param list<array<string, mixed>> $decisions
 *
 * @return array<string, mixed>
 */
public function record(array $identifiers, array $decisions): array
{
    return $this->client->requestJson('POST', '/v1/vault/record', ['identifiers' => $identifiers, 'decisions' => $decisions]);
}

/**
 * Allow/deny per purpose, across all of the person's identifiers — POST /v1/vault/current.
 *
 * @param list<array{space: string, value: string}> $identifiers
 *
 * @return array<string, mixed>
 */
public function current(array $identifiers): array
{
    return $this->client->requestJson('POST', '/v1/vault/current', ['identifiers' => $identifiers]);
}

/**
 * The same decisions in full: legal basis, jurisdiction, provenance, time — POST /v1/vault/permits.
 *
 * @param list<array{space: string, value: string}> $identifiers
 *
 * @return array<string, mixed>
 */
public function permits(array $identifiers): array
{
    return $this->client->requestJson('POST', '/v1/vault/permits', ['identifiers' => $identifiers]);
}
}
