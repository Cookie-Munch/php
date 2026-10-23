<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/**
 * A site's consent-sync signing secret. Your server signs consent-sync tokens with it so a signed-in
 * visitor's consent decision follows them across browsers (hosted consent sync). Reading needs
 * sites:read; rotate and remove need sites:write and consent:write.
 */
final class ConsentSyncSecret
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * GET /v1/sites/{cbid}/consent-sync-secret — whether it is set up: `configured`, `secretLast4`, `createdAt`. Never the secret.
     *
     * @return array<string, mixed>
     */
    public function get(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . rawurlencode($cbid) . '/consent-sync-secret');
    }

    /**
     * POST /v1/sites/{cbid}/consent-sync-secret — mint the secret, or replace it (the old one stops at once).
     * The answer's `secret` is shown this once: keep it on your server, never in a page.
     *
     * @return array<string, mixed>
     */
    public function rotate(string $cbid): array
    {
        return $this->client->requestJson('POST', '/v1/sites/' . rawurlencode($cbid) . '/consent-sync-secret');
    }

    /**
     * DELETE /v1/sites/{cbid}/consent-sync-secret — hosted consent sync stops for the site.
     *
     * @return array<string, mixed>
     */
    public function remove(string $cbid): array
    {
        return $this->client->requestJson('DELETE', '/v1/sites/' . rawurlencode($cbid) . '/consent-sync-secret');
    }
}
