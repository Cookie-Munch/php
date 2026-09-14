<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Webhook subscriptions — the /v1/webhooks surface. */
final class Webhooks
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List webhook subscriptions — GET /v1/webhooks (the signing secret is omitted).
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/webhooks') ?? [];

        return $out;
    }

    /**
     * Create a webhook subscription — POST /v1/webhooks.
     * The signing `secret` is returned ONCE, in this response.
     *
     * @param array{url: string, events: list<string>, cbid?: string|null} $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        return $this->client->requestJson('POST', '/v1/webhooks', $input);
    }

    /** Delete a webhook subscription — DELETE /v1/webhooks/{id}. */
    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/webhooks/' . rawurlencode($id));
    }
}
