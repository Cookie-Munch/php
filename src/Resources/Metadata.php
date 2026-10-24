<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Tags on created resources — /v1/metadata/{type}/{id} (type: org, site, webhook, executor, dsar, partner_link). */
final class Metadata
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @return array<string, mixed> { type, id, metadata, externalRef, sandbox } */
    public function get(string $type, string $id): array
    {
        return $this->client->requestJson('GET', '/v1/metadata/' . rawurlencode($type) . '/' . rawurlencode($id));
    }

    /**
     * Merge tags (a null value removes one). forgedeck_sandbox cannot be set here.
     *
     * @param array<string, string|int|float|bool|null> $metadata
     * @return array<string, mixed>
     */
    public function update(string $type, string $id, array $metadata, bool $dryRun = false): array
    {
        return $this->client->requestJson('PATCH', '/v1/metadata/' . rawurlencode($type) . '/' . rawurlencode($id) . ($dryRun ? '?dryRun=1' : ''), ['metadata' => (object) $metadata]);
    }
}
