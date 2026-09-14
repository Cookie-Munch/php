<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Third-party vendors and their risk scores — the /v1/vendors surface. */
final class Vendors
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List vendors with risk scores — GET /v1/vendors.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/vendors') ?? [];

        return $out;
    }

    /**
     * Create a vendor — POST /v1/vendors.
     * Returns { vendor, risk: { score, band } }.
     *
     * @param array{name: string, category: string, dataShared: list<string>, dpaSigned: bool, subprocessors: int, certifications: list<string>, region: string} $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        return $this->client->requestJson('POST', '/v1/vendors', $input);
    }
}
