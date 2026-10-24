<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/**
 * A site's rights-request settings: graceDays (0–30; 7 for a new site, 0 until set for an existing one), how long an erasure or account
 * closure waits before any system acts. Needs dsar:read / dsar:write.
 */
final class DsarSettings
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * GET /v1/sites/{cbid}/dsar-settings.
     *
     * @return array<string, mixed>
     */
    public function get(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . rawurlencode($cbid) . '/dsar-settings');
    }

    /**
     * PUT /v1/sites/{cbid}/dsar-settings. Applies to cases opened afterwards.
     *
     * @return array<string, mixed>
     */
    public function set(string $cbid, int $graceDays): array
    {
        return $this->client->requestJson('PUT', '/v1/sites/' . rawurlencode($cbid) . '/dsar-settings', ['graceDays' => $graceDays]);
    }
}
