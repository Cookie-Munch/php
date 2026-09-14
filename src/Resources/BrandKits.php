<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Reusable, org-level banner themes (brand kits) — the /v1/brand-kits surface. */
final class BrandKits
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List brand kits — GET /v1/brand-kits.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/brand-kits') ?? [];

        return $out;
    }

    /**
     * Create a brand kit — POST /v1/brand-kits. Returns { kit: BrandKit }.
     *
     * @param array{name: string, theme: mixed, content?: mixed, logoUrl?: string, customCss?: string} $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        return $this->client->requestJson('POST', '/v1/brand-kits', $input);
    }

    /** Delete a brand kit — DELETE /v1/brand-kits/{id}. */
    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/brand-kits/' . rawurlencode($id));
    }
}
