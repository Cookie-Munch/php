<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Provision and manage child orgs — the /v1/reseller surface. Needs the reseller:* scopes. */
final class Reseller
{
    public function __construct(private readonly Client $client)
    {
    }

/** @return array<string, mixed> */
public function list(): array
{
    return $this->client->requestJson('GET', '/v1/reseller/customers');
}

/**
 * Provision a child org. With mintKey, its first API key is returned once as `apiKey`.
 *
 * @param array{name: string, ownerEmail?: string, controller?: array<string, mixed>, whiteLabel?: array<string, mixed>, delegatedAccess?: bool, mintKey?: bool, keyScopes?: list<string>} $input
 *
 * @return array<string, mixed>
 */
public function create(array $input): array
{
    return $this->client->requestJson('POST', '/v1/reseller/customers', $input);
}

/** @return array<string, mixed> */
public function get(string $id): array
{
    return $this->client->requestJson('GET', '/v1/reseller/customers/' . rawurlencode($id));
}

/**
 * Update a child. Include `'dsarRouting' => null` to clear its override.
 *
 * @param array{status?: 'active'|'suspended', delegatedAccess?: bool, dsarRouting?: 'reseller'|'child'|null, controller?: array<string, mixed>} $patch
 *
 * @return array<string, mixed>
 */
public function update(string $id, array $patch): array
{
    return $this->client->requestJson('PATCH', '/v1/reseller/customers/' . rawurlencode($id), $patch);
}

/** Suspend a child (reversible). $purge = true deletes it and its data — irreversibly. */
public function deprovision(string $id, bool $purge = false): void
{
    $this->client->request('DELETE', '/v1/reseller/customers/' . rawurlencode($id), null, ['purge' => $purge ? 'true' : null]);
}

/** @return list<array<string, mixed>> */
public function listKeys(string $id): array
{
    /** @var list<array<string, mixed>> $out */
    $out = $this->client->request('GET', '/v1/reseller/customers/' . rawurlencode($id) . '/keys') ?? [];

    return $out;
}

/**
 * Mint an API key for a child org; the secret is returned once.
 *
 * @param array{name?: string, scopes?: list<string>, cbids?: list<string>} $input
 *
 * @return array<string, mixed>
 */
public function mintKey(string $id, array $input = []): array
{
    return $this->client->requestJson('POST', '/v1/reseller/customers/' . rawurlencode($id) . '/keys', $input);
}

public function revokeKey(string $id, string $prefix): void
{
    $this->client->request('DELETE', '/v1/reseller/customers/' . rawurlencode($id) . '/keys/' . rawurlencode($prefix));
}
}
