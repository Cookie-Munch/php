<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Records of Processing Activities (RoPA) — the /v1/ropa surface. */
final class Ropa
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List RoPA entries — GET /v1/ropa.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/ropa') ?? [];

        return $out;
    }

    /**
     * Create a RoPA entry — POST /v1/ropa. Returns { entry: RopaEntry }.
     *
     * @param array{name: string, purpose: string, legalBasis: string, dataCategories: list<string>, recipients: list<string>, retentionDays: int, crossBorderTransfer: bool} $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        return $this->client->requestJson('POST', '/v1/ropa', $input);
    }

    /** The org's RoPA (GDPR Art. 30), as CSV — GET /v1/ropa/export.csv. */
    public function exportCsv(): string
    {
        return (string) $this->client->request('GET', '/v1/ropa/export.csv', null, null, true);
    }
}
