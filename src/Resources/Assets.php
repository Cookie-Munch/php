<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Image uploads — the /v1/assets surface. */
final class Assets
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Upload an image — a banner logo or the org logo — up to 1,000,000 bytes. PNG, JPEG,
     * WebP, GIF or SVG. Returns { url }. Requires sites:write — POST /v1/assets.
     *
     * @param array{data: string, contentType: 'image/png'|'image/jpeg'|'image/webp'|'image/gif'|'image/svg+xml'} $input
     *
     * @return array<string, mixed>
     */
    public function upload(array $input): array
    {
        return $this->client->requestJson('POST', '/v1/assets', $input);
    }
}
