<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** The key's organisation — the /v1/org surface. Requires an unscoped key that is not property-locked. */
final class Org
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * The key's organisation: id, name, plan and logo — GET /v1/org.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->client->requestJson('GET', '/v1/org');
    }

    /**
     * Rename the org or set its logo — PATCH /v1/org. Include `'logoUrl' => null` in $patch
     * to remove the logo; omit the key entirely to leave it unchanged. Deleting the org is
     * not available through the API.
     *
     * @param array{name?: string, logoUrl?: ?string} $patch
     *
     * @return array<string, mixed>
     */
    public function update(array $patch): array
    {
        return $this->client->requestJson('PATCH', '/v1/org', $patch);
    }
}
