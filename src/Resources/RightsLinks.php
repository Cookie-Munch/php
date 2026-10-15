<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Account-linked rights requests: single-use links to the hosted rights page for a signed-in person. */
final class RightsLinks
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * POST /v1/sites/{cbid}/rights-links. The page locks the person's email and skips the code
     * step; show the returned url only to them. Needs dsar:write.
     *
     * @param list<string>|null $rights limit the link to these rights (default: every right the page offers)
     * @return array<string, mixed> { url, expiresAt }
     */
    public function create(string $cbid, string $email, ?string $userId = null, ?array $rights = null, ?int $ttlSeconds = null): array
    {
        $subject = ['email' => $email];
        if ($userId !== null) {
            $subject['userId'] = $userId;
        }
        $body = ['subject' => $subject];
        if ($rights !== null) {
            $body['rights'] = array_values($rights);
        }
        if ($ttlSeconds !== null) {
            $body['ttlSeconds'] = $ttlSeconds;
        }

        return $this->client->requestJson('POST', '/v1/sites/' . rawurlencode($cbid) . '/rights-links', $body);
    }
}
