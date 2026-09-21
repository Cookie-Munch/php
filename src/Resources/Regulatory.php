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
}
