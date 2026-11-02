<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/**
 * Audit anchoring — the /v1/anchors surface: post the head of your own hash-chained log and keep the
 * signed receipt. `seq` must strictly increase per stream; the same seq and head again returns the
 * original receipt, a different head for an anchored seq is a 409 (`anchor_head_mismatch`).
 * create() needs `anchors:write`; list() and get() need `anchors:read`.
 */
final class Anchors
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Anchor `head` at `seq` on `stream` — POST /v1/anchors. Signed with the key in /v1/partner/jwks.json.
     *
     * @return array<string, mixed>
     */
    public function create(string $stream, string $head, int $seq): array
    {
        return $this->client->requestJson('POST', '/v1/anchors', ['stream' => $stream, 'head' => $head, 'seq' => $seq]);
    }

    /**
     * A stream's receipts, newest first — GET /v1/anchors/{stream}: `{stream, anchors, nextBefore}`.
     * Pass `nextBefore` as `$before` until it is null.
     *
     * @return array<string, mixed>
     */
    public function list(string $stream, ?int $limit = null, ?int $before = null): array
    {
        return $this->client->requestJson('GET', '/v1/anchors/' . rawurlencode($stream), null, ['limit' => $limit, 'before' => $before]);
    }

    /**
     * One receipt — GET /v1/anchors/{stream}/{id}.
     *
     * @return array<string, mixed>
     */
    public function get(string $stream, string $id): array
    {
        return $this->client->requestJson('GET', '/v1/anchors/' . rawurlencode($stream) . '/' . rawurlencode($id));
    }
}
