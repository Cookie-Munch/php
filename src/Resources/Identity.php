<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** A person as a cluster of identifiers — the /v1/identity surface. Reads are POSTs so identifiers never appear in a URL. */
final class Identity
{
    public function __construct(private readonly Client $client)
    {
    }

/**
 * The subject id for these identifiers, or null if unknown — POST /v1/identity/resolve.
 *
 * @param list<array{space: string, value: string}> $identifiers
 *
 * @return array<string, mixed>
 */
public function resolve(array $identifiers): array
{
    return $this->client->requestJson('POST', '/v1/identity/resolve', ['identifiers' => $identifiers]);
}

/**
 * Stitch identifiers into one subject. A durable merge — POST /v1/identity/link.
 *
 * @param list<array{space: string, value: string}> $identifiers
 *
 * @return array<string, mixed>
 */
public function link(array $identifiers): array
{
    return $this->client->requestJson('POST', '/v1/identity/link', ['identifiers' => $identifiers]);
}

/** @return array<string, mixed> Every identifier stitched to a subject — GET /v1/identity/{subjectId}. */
public function cluster(string $subjectId): array
{
    return $this->client->requestJson('GET', '/v1/identity/' . rawurlencode($subjectId));
}
}
