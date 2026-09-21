<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** One person's consent across every site in the org, by the subject id your apps attach. */
final class Subjects
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * GET /v1/subjects/{subjectId}/consent. Needs consent:read; not available to
     * property-locked keys.
     *
     * @return array<string, mixed>
     */
    public function consent(string $subjectId): array
    {
        return $this->client->requestJson('GET', '/v1/subjects/' . rawurlencode($subjectId) . '/consent');
    }
}
