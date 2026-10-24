<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Cards for support, CRM and helpdesk sidebars. Needs dsar:read. */
final class Integrations
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * GET /v1/integrations/{app}/card — a person's open rights requests, by iss and sub (preferred)
     * or email. Never matched across identities on a shared email.
     *
     * @return array<string, mixed>
     */
    public function subjectCard(string $app, ?string $iss = null, ?string $sub = null, ?string $email = null): array
    {
        return $this->client->requestJson('GET', '/v1/integrations/' . rawurlencode($app) . '/card', null, ['iss' => $iss, 'sub' => $sub, 'email' => $email]);
    }
}
