<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Cards for support, CRM and helpdesk sidebars (dsar:read), and identity-provider connect (dsar:write). */
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

    /**
     * POST /v1/integrations/{provider}/connect — connect an identity provider's privacy orchestrator:
     * mint a dsar:write key locked to $cbid and hand it to the provider, authorised with $providerKey.
     * The minted key is never returned, only its prefix. $connector (url, method, body) only for a
     * provider with no built-in connector. Needs dsar:write (no keys:write); $dryRun mints and sends nothing.
     *
     * @param array<string, mixed>|null $connector
     * @return array<string, mixed>
     */
    public function connect(string $provider, string $cbid, string $providerKey, ?array $connector = null, bool $dryRun = false): array
    {
        $body = ['cbid' => $cbid, 'providerKey' => $providerKey];
        if ($connector !== null) {
            $body['connector'] = $connector;
        }

        return $this->client->requestJson('POST', '/v1/integrations/' . rawurlencode($provider) . '/connect' . ($dryRun ? '?dryRun=1' : ''), $body);
    }
}
