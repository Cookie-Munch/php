<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Data-subject access requests — the /v1/dsar surface. */
final class Dsar
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List DSARs — GET /v1/dsar.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/dsar') ?? [];

        return $out;
    }

    /**
     * Create a DSAR — POST /v1/dsar. Returns { request: DsarRequest }.
     *
     * @param array{type: string, subjectEmail: string, regulation: string, note?: string} $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        return $this->client->requestJson('POST', '/v1/dsar', $input);
    }

    /**
     * Advance a DSAR to a new status — POST /v1/dsar/{id}/advance.
     * Returns { request: DsarRequest }.
     *
     * @param 'received'|'verifying'|'in_progress'|'completed'|'rejected' $toStatus
     *
     * @return array<string, mixed>
     */
    public function advance(string $id, string $toStatus): array
    {
        return $this->client->requestJson('POST', '/v1/dsar/' . rawurlencode($id) . '/advance', ['toStatus' => $toStatus]);
    }
}
