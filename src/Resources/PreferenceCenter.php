<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** The hosted preference centre, opened for a visitor your server has signed in. */
final class PreferenceCenter
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * POST /v1/preference-center/tokens — mint a preference-centre token for the page to pass to
     * `CookieMunch.setSubjectToken`. Give exactly one of `$email` (resolved as the emailed preference
     * link resolves it) or `$subjectId`. `$ttlSeconds` defaults to 3600, at most 86400. The token is a
     * credential: never put it in a shared URL or a log. Needs subscriptions:write and profile:write,
     * plus identity:read by email.
     *
     * @return array<string, mixed> `{ token, expiresAt }`
     */
    public function mintToken(string $cbid, ?string $email = null, ?string $subjectId = null, ?int $ttlSeconds = null): array
    {
        $body = ['cbid' => $cbid];
        if ($email !== null) {
            $body['email'] = $email;
        }
        if ($subjectId !== null) {
            $body['subjectId'] = $subjectId;
        }
        if ($ttlSeconds !== null) {
            $body['ttlSeconds'] = $ttlSeconds;
        }
        return $this->client->requestJson('POST', '/v1/preference-center/tokens', $body);
    }
}
