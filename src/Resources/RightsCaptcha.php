<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/**
 * A site's rights-page captcha: your own Turnstile, hCaptcha or reCAPTCHA keys (recommended), or off.
 * A site without its own setting uses the organisation default ($client->org->rightsCaptcha()), else off.
 * Cookie Munch's own captcha is never used on rights pages. Needs sites:read / sites:write.
 */
final class RightsCaptcha
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * GET /v1/sites/{cbid}/rights-captcha — the setting in force (`source`: site, org or default). Never the secret.
     *
     * @return array<string, mixed>
     */
    public function get(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . rawurlencode($cbid) . '/rights-captcha');
    }

    /**
     * PUT /v1/sites/{cbid}/rights-captcha. `mode: own` with provider (turnstile, hcaptcha, recaptcha_v2,
     * recaptcha_v3), siteKey and secret (write-only; omit to keep the stored one) — the provider must accept
     * the secret before it is saved; minScore is for recaptcha_v3 (0.1-0.9). `mode: off` removes spam
     * protection beyond the spam-trap field, the rate limits and the email code.
     *
     * @param array{mode: string, provider?: string, siteKey?: string, secret?: string, minScore?: float} $input
     * @return array<string, mixed>
     */
    public function set(string $cbid, array $input): array
    {
        return $this->client->requestJson('PUT', '/v1/sites/' . rawurlencode($cbid) . '/rights-captcha', $input);
    }

    /**
     * DELETE /v1/sites/{cbid}/rights-captcha — the site inherits the organisation default again.
     *
     * @return array<string, mixed> the setting now in force
     */
    public function remove(string $cbid): array
    {
        return $this->client->requestJson('DELETE', '/v1/sites/' . rawurlencode($cbid) . '/rights-captcha');
    }
}
