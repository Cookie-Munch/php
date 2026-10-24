<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Suite-standard partner links — /v1/partner_links. */
final class PartnerLinks
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array<string, string> $metadata keep only links with these tags
     * @return list<array<string, mixed>>
     */
    public function list(array $metadata = []): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/partner_links' . Client::metadataQuery($metadata)) ?? [];

        return $out;
    }

    /**
     * @param list<string> $scopes
     * @param array{expires_at?: string, externalId?: string, label?: string} $opts
     * @return array<string, mixed>
     */
    public function create(string $partner, string $resource, array $scopes, array $opts = []): array
    {
        return $this->client->requestJson('POST', '/v1/partner_links', ['partner' => $partner, 'resource' => $resource, 'scopes' => $scopes] + $opts);
    }

    /** @return array<string, mixed> */
    public function revoke(string $id): array
    {
        return $this->client->requestJson('DELETE', '/v1/partner_links/' . rawurlencode($id));
    }
}
