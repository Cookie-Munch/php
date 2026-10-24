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
     * @param array{name?: string, scopes?: list<string>, cbids?: list<string>, expiresInDays?: int, expiresAt?: int|string} $input
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
     * `expiresAt` sets the expiry (epoch ms or ISO date-time); `'expiresAt' => null` removes it.
     *
     * @param array{name?: string, scopes?: list<string>, cbids?: list<string>, expiresAt?: int|string|null} $patch
     *
     * @return array<string, mixed>
     */
    public function update(string $prefix, array $patch): array
    {
        return $this->client->requestJson('PATCH', '/v1/keys/' . rawurlencode($prefix), $patch);
    }

    /**
     * Key hygiene — GET /v1/keys/report: keys unused for $unusedDays (default 90), keys with
     * broad access, and keys expiring within $expiringDays (default 14), each with a reason.
     *
     * @return array<string, mixed> { generatedAt, unused, broad, expiringSoon, noExpiry, total, ... }
     */
    public function report(?int $unusedDays = null, ?int $expiringDays = null): array
    {
        $q = array_filter(['unusedDays' => $unusedDays, 'expiringDays' => $expiringDays], static fn ($v) => $v !== null);
        return $this->client->requestJson('GET', '/v1/keys/report' . ($q ? '?' . http_build_query($q) : ''));
    }

    /**
     * The suite-standard listing — GET /v1/keys?shape=standard.
     *
     * @return array<string, mixed>
     */
    public function listStandard(): array
    {
        return $this->client->requestJson('GET', '/v1/keys?shape=standard');
    }

    /**
     * The calling key itself (any key): scopes, properties, expiry, owner — GET /v1/keys/self.
     *
     * @return array<string, mixed>
     */
    public function self(): array
    {
        return $this->client->requestJson('GET', '/v1/keys/self');
    }

    /**
     * The calling key rotates itself — POST /v1/keys/self/roll. The old secret stops at once; pass an
     * Idempotency-Key header (the Client's extra headers) so a lost answer replays.
     *
     * @return array<string, mixed>
     */
    public function rollSelf(): array
    {
        return $this->client->requestJson('POST', '/v1/keys/self/roll');
    }

    /**
     * A short-lived key (at most 12 hours) within this key's own scopes and properties —
     * POST /v1/keys/vend. Needs keys:write or a full-access key.
     *
     * @param list<string> $scopes
     * @param array{cbids?: list<string>, name?: string} $opts
     * @return array<string, mixed>
     */
    public function vend(array $scopes, int $ttlSeconds, array $opts = []): array
    {
        return $this->client->requestJson('POST', '/v1/keys/vend', ['scopes' => $scopes, 'ttlSeconds' => $ttlSeconds] + $opts);
    }

    /**
     * Revoke a published secret — POST /v1/keys/revoke_leaked. Needs no valid key; always answers
     * { received }.
     *
     * @return array<string, mixed>
     */
    public function revokeLeaked(string $key, ?string $source = null): array
    {
        return $this->client->requestJson('POST', '/v1/keys/revoke_leaked', ['key' => $key] + ($source !== null ? ['source' => $source] : []));
    }
}
