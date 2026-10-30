<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** The curated privacy-law dataset — the /v1/regulatory surface. */
final class Regulatory
{
    public function __construct(private readonly Client $client)
    {
    }

/**
 * @param list<string>|null $jurisdictions
 *
 * @return array<string, mixed>
 */
public function feed(?array $jurisdictions = null): array
{
    return $this->client->requestJson('GET', '/v1/regulatory/feed', null, ['jurisdictions' => $jurisdictions !== null ? implode(',', $jurisdictions) : null]);
}

/** @return array<string, mixed> */
public function upcoming(?int $days = null): array
{
    return $this->client->requestJson('GET', '/v1/regulatory/upcoming', null, ['days' => $days]);
}

/**
 * GET /v1/sites/{cbid}/regulatory/upcoming — what takes effect within $days (default 90) where at
 * least $minShare (default 0.01) of the site's last 30 days of decisions came from. Needs sites:read.
 *
 * @return array<string, mixed>
 */
public function upcomingForSite(string $cbid, ?int $days = null, ?float $minShare = null): array
{
    return $this->client->requestJson('GET', '/v1/sites/' . rawurlencode($cbid) . '/regulatory/upcoming', null, ['days' => $days, 'minShare' => $minShare]);
}
}
