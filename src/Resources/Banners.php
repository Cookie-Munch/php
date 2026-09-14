<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Reusable, account-level banner designs — the /v1/banners surface. */
final class Banners
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List the org's banner designs — GET /v1/banners.
     * Each row is a BannerSummary { id, name, updatedAt, assignedCbids }.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/banners') ?? [];

        return $out;
    }

    /**
     * Create a banner design — POST /v1/banners. Returns a Banner.
     *
     * @param array{name: string, json: array<string, mixed>} $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        return $this->client->requestJson('POST', '/v1/banners', $input);
    }

    /**
     * Get a banner design — GET /v1/banners/{id}.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->requestJson('GET', '/v1/banners/' . rawurlencode($id));
    }

    /**
     * Update a banner design's name and/or json — PUT /v1/banners/{id}.
     *
     * @param array{name?: string, json?: array<string, mixed>} $patch
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $patch): array
    {
        return $this->client->requestJson('PUT', '/v1/banners/' . rawurlencode($id), $patch);
    }

    /**
     * Delete a banner design — DELETE /v1/banners/{id}.
     * Fails (409, code "banner_in_use") while assigned to any site.
     */
    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/banners/' . rawurlencode($id));
    }

    /**
     * List the site cbids a design is assigned to —
     * GET /v1/banners/{id}/assignments. Returns { cbids }.
     *
     * @return array<string, mixed>
     */
    public function assignments(string $id): array
    {
        return $this->client->requestJson('GET', '/v1/banners/' . rawurlencode($id) . '/assignments');
    }

    /**
     * Set the sites a design is assigned to —
     * PUT /v1/banners/{id}/assignments. Returns { cbids }.
     *
     * @param list<string> $cbids
     *
     * @return array<string, mixed>
     */
    public function setAssignments(string $id, array $cbids): array
    {
        return $this->client->requestJson('PUT', '/v1/banners/' . rawurlencode($id) . '/assignments', ['cbids' => $cbids]);
    }

    /**
     * Compile the design into every assigned site's config —
     * POST /v1/banners/{id}/publish. Returns { publishedCbids }.
     *
     * @return array<string, mixed>
     */
    public function publish(string $id): array
    {
        return $this->client->requestJson('POST', '/v1/banners/' . rawurlencode($id) . '/publish');
    }
}
