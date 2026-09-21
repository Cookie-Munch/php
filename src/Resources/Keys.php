<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** API-key management — the /v1/keys surface. */
final class Keys
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List API-key prefixes for the org — GET /v1/keys.
     * Each row is { prefix, createdAt } (display metadata; never the secret).
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/keys') ?? [];

        return $out;
    }

    /**
     * Issue a new API key — POST /v1/keys. The `key` is returned ONCE.
     * Returns { key, prefix } (authoritative OpenAPI ApiKeyIssued shape).
     *
     * Pass `scopes` and/or `cbids` for a least-privilege key — omit both for full access to
     * the whole org. A key locked with `cbids` works only on those sites and on no org-wide
     * endpoint.
     *
     * @param array{name?: string, scopes?: list<string>, cbids?: list<string>, expiresInDays?: int} $input
     *
     * @return array<string, mixed>
     */
    public function issue(array $input = []): array
    {
        return $this->client->requestJson('POST', '/v1/keys', $input);
    }

    /** Revoke a key by its prefix — DELETE /v1/keys/{prefix}. Immediate. */
    public function revoke(string $prefix): void
    {
        $this->client->request('DELETE', '/v1/keys/' . rawurlencode($prefix));
    }

    /**
     * Rotate a key: a new secret, returned once, with the same name, scopes, property lock
     * and expiry — POST /v1/keys/{prefix}/roll. The old secret stops working immediately.
     *
     * @return array<string, mixed>
     */
    public function roll(string $prefix): array
    {
        return $this->client->requestJson('POST', '/v1/keys/' . rawurlencode($prefix) . '/roll');
    }

    /**
     * Rename a key, or replace its scopes or the properties it is locked to — PATCH
     * /v1/keys/{prefix}. Only the fields sent change; the secret is unchanged.
     *
     * @param array{name?: string, scopes?: list<string>, cbids?: list<string>} $patch
     *
     * @return array<string, mixed>
     */
    public function update(string $prefix, array $patch): array
    {
        return $this->client->requestJson('PATCH', '/v1/keys/' . rawurlencode($prefix), $patch);
    }
}
