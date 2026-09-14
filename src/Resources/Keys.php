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
     * @param array{name?: string} $input
     *
     * @return array<string, mixed>
     */
    public function issue(array $input = []): array
    {
        return $this->client->requestJson('POST', '/v1/keys', $input);
    }
}
