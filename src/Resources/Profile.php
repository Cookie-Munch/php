<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Attributes, with consent enforced when they are used — the /v1/profile surface. */
final class Profile
{
    public function __construct(private readonly Client $client)
    {
    }

/**
 * @param list<array{space: string, value: string}> $identifiers
 *
 * @return array<string, mixed>
 */
public function get(array $identifiers): array
{
    return $this->client->requestJson('POST', '/v1/profile/get', ['identifiers' => $identifiers]);
}

/**
 * @param list<array{space: string, value: string}> $identifiers
 * @param array<string, array{value: string, purpose?: string, collectedAt?: int}> $attributes
 *
 * @return array<string, mixed>
 */
public function setAttributes(array $identifiers, array $attributes): array
{
    return $this->client->requestJson('POST', '/v1/profile/attributes', ['identifiers' => $identifiers, 'attributes' => $attributes]);
}

/**
 * Attribute values usable for $purpose — empty when the person has not consented to it.
 *
 * @param list<array{space: string, value: string}> $identifiers
 *
 * @return array<string, mixed>
 */
public function activate(array $identifiers, string $purpose): array
{
    return $this->client->requestJson('POST', '/v1/profile/activate', ['identifiers' => $identifiers, 'purpose' => $purpose]);
}
}
