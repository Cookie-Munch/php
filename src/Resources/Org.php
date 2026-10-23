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

    /**
     * Your organisation's own data-controller identity — GET /v1/org/controller. Every rights
     * notice names it, before any site config `controller` block. `set: false` when there is none.
     *
     * @return array<string, mixed>
     */
    public function controller(): array
    {
        return $this->client->requestJson('GET', '/v1/org/controller');
    }

    /**
     * Set the organisation's data-controller identity — PUT /v1/org/controller. Replaces it: a
     * field left out is removed. Needs an unscoped key or `rights:write`.
     *
     * @param array{legalName: string, contactEmail?: string, address?: string, dpoContact?: string} $input
     *
     * @return array<string, mixed>
     */
    public function setController(array $input): array
    {
        return $this->client->requestJson('PUT', '/v1/org/controller', $input);
    }

    /** Remove the identity — DELETE /v1/org/controller. Notices then name the site config `controller` block. */
    public function removeController(): void
    {
        $this->client->request('DELETE', '/v1/org/controller');
    }

    /**
     * Your own mail provider for rights-request mail — GET /v1/org/mail-provider. Never returns
     * the credential, only `secretLast4`; `sender` says whether verification codes go through it now
     * (`own`) or are not mailed at all (`none`) — there is no shared sender.
     *
     * @return array<string, mixed>
     */
    public function mailProvider(): array
    {
        return $this->client->requestJson('GET', '/v1/org/mail-provider');
    }

    /**
     * Connect your own mail provider (recommended) — PUT /v1/org/mail-provider. Replaces the
     * stored one; it carries rights mail only after testMailProvider() succeeds. `secret` is
     * write-only; omit it to keep the stored one (same provider only).
     *
     * @param array{provider: string, fromEmail: string, fromName?: ?string, replyTo?: ?string, secret?: string,
     *   ses?: array{region: string, accessKeyId: string},
     *   smtp?: array{host: string, port: int, security: string, username: string}} $input
     *
     * @return array<string, mixed>
     */
    public function setMailProvider(array $input): array
    {
        return $this->client->requestJson('PUT', '/v1/org/mail-provider', $input);
    }

    /**
     * Send a test through your provider — POST /v1/org/mail-provider/test. $to defaults to the
     * reply-to, else the from address. A refusal comes back as `ok: false` with the reason.
     *
     * @return array<string, mixed>
     */
    public function testMailProvider(?string $to = null): array
    {
        return $this->client->requestJson('POST', '/v1/org/mail-provider/test', $to === null ? [] : ['to' => $to]);
    }

    /** Remove your provider — DELETE /v1/org/mail-provider. No verification codes are mailed after this. */
    public function removeMailProvider(): void
    {
        $this->client->request('DELETE', '/v1/org/mail-provider');
    }

    /**
     * The default captcha for your rights pages (sites without their own) — GET /v1/org/rights-captcha.
     * Never the secret.
     *
     * @return array<string, mixed>
     */
    public function rightsCaptcha(): array
    {
        return $this->client->requestJson('GET', '/v1/org/rights-captcha');
    }

    /**
     * Set the default — PUT /v1/org/rights-captcha: own keys (recommended) or off. Same fields as
     * RightsCaptcha::set().
     *
     * @param array{mode: string, provider?: string, siteKey?: string, secret?: string, minScore?: float} $input
     * @return array<string, mixed>
     */
    public function setRightsCaptcha(array $input): array
    {
        return $this->client->requestJson('PUT', '/v1/org/rights-captcha', $input);
    }

    /**
     * Remove the default — DELETE /v1/org/rights-captcha: sites without their own setting have no captcha.
     *
     * @return array<string, mixed> the setting now in force
     */
    public function removeRightsCaptcha(): array
    {
        return $this->client->requestJson('DELETE', '/v1/org/rights-captcha');
    }

    /**
     * The default sign-in provider rights requests are verified with (sites without their own) —
     * GET /v1/org/rights-identity.
     *
     * @return array<string, mixed>
     */
    public function rightsIdentity(): array
    {
        return $this->client->requestJson('GET', '/v1/org/rights-identity');
    }

    /**
     * Set the default — PUT /v1/org/rights-identity. Same fields as RightsIdentity::set().
     *
     * @param array{type: string, issuer: string, jwksUrl?: string, audience?: string, authorizedParties?: list<string>, emailClaim?: string, emailVerifiedClaim?: string, sessionCookieName?: string, trustEmailClaim?: bool} $input
     * @return array<string, mixed>
     */
    public function setRightsIdentity(array $input): array
    {
        return $this->client->requestJson('PUT', '/v1/org/rights-identity', $input);
    }

    /**
     * Remove the default — DELETE /v1/org/rights-identity.
     *
     * @return array<string, mixed> the setting now in force
     */
    public function removeRightsIdentity(): array
    {
        return $this->client->requestJson('DELETE', '/v1/org/rights-identity');
    }
}
