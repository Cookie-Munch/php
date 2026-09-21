<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Preference-center records — the /v1/preferences surface. */
final class Preferences
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List the org's preference-center records — GET /v1/preferences.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/preferences') ?? [];

        return $out;
    }

    /**
     * Save a subject's purpose choices — POST /v1/preferences.
     *
     * @param array<string, bool> $purposes purpose id => granted
     *
     * @return array<string, mixed>|null
     */
    public function save(string $subjectId, array $purposes): ?array
    {
        $result = $this->client->request('POST', '/v1/preferences', ['subjectId' => $subjectId, 'purposes' => $purposes]);

        return is_array($result) ? $result : null;
    }

    /**
     * One subject's preference record. A subject with none has empty `purposes` —
     * GET /v1/preferences/{subjectId}. Requires consent:read.
     *
     * @return array<string, mixed>
     */
    public function get(string $subjectId): array
    {
        return $this->client->requestJson('GET', '/v1/preferences/' . rawurlencode($subjectId));
    }
}
