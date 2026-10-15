<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/**
 * A site's sign-in provider (Clerk, Atlas or any JWKS issuer): a visitor signed in to your site is
 * verified by their session, with no code mail. A site without its own setting uses the organisation
 * default ($client->org->rightsIdentity()). Needs sites:read / sites:write.
 */
final class RightsIdentity
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * GET /v1/sites/{cbid}/rights-identity — the setting in force (`source`: site, org or default).
     *
     * @return array<string, mixed>
     */
    public function get(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . rawurlencode($cbid) . '/rights-identity');
    }

    /**
     * PUT /v1/sites/{cbid}/rights-identity. `type` is clerk, atlas or oidc_jwt; `issuer` exactly the token's
     * iss. `jwksUrl` is required for oidc_jwt (clerk/atlas: <issuer>/.well-known/jwks.json); `authorizedParties`
     * (your own origins) is required for clerk and atlas. The JWKS is fetched and checked before anything is
     * saved. The token must carry the email and a verified-email claim set to true.
     *
     * @param array{type: string, issuer: string, jwksUrl?: string, audience?: string, authorizedParties?: list<string>, emailClaim?: string, emailVerifiedClaim?: string, sessionCookieName?: string, trustEmailClaim?: bool} $input
     * @return array<string, mixed>
     */
    public function set(string $cbid, array $input): array
    {
        return $this->client->requestJson('PUT', '/v1/sites/' . rawurlencode($cbid) . '/rights-identity', $input);
    }

    /**
     * DELETE /v1/sites/{cbid}/rights-identity — the site inherits the organisation default again.
     *
     * @return array<string, mixed> the setting now in force
     */
    public function remove(string $cbid): array
    {
        return $this->client->requestJson('DELETE', '/v1/sites/' . rawurlencode($cbid) . '/rights-identity');
    }
}
