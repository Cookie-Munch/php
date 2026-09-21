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

    /**
     * Change or pause a subscription — PATCH /v1/webhooks/{id}. Only the keys present are
     * sent: `'active' => false` pauses it, and `'cbid' => null` widens it to the whole org.
     *
     * @param array{url?: string, events?: list<string>, cbid?: ?string, active?: bool} $patch
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $patch): array
    {
        return $this->client->requestJson('PATCH', '/v1/webhooks/' . rawurlencode($id), $patch);
    }

    /**
     * Rotate a subscription's signing secret — POST /v1/webhooks/{id}/roll. The new secret
     * is returned once; deliveries are signed with it from now on.
     *
     * @return array<string, mixed>
     */
    public function rollSecret(string $id): array
    {
        return $this->client->requestJson('POST', '/v1/webhooks/' . rawurlencode($id) . '/roll');
    }

    /**
     * Send a signed test event to the subscription now, and report what the endpoint
     * answered — POST /v1/webhooks/{id}/test.
     *
     * @return array<string, mixed>
     */
    public function test(string $id): array
    {
        return $this->client->requestJson('POST', '/v1/webhooks/' . rawurlencode($id) . '/test');
    }

    /**
     * Deliveries that failed every retry, newest first — GET /v1/webhooks/dead-letters. A
     * property-locked key sees only its own properties'.
     *
     * @return array<string, mixed>
     */
    public function deadLetters(): array
    {
        return $this->client->requestJson('GET', '/v1/webhooks/dead-letters');
    }

    /**
     * Deliver a dead-lettered event again, to the subscription as it is now, with a fresh
     * set of retries — POST /v1/webhooks/dead-letters/{id}/replay.
     *
     * @return array<string, mixed>
     */
    public function replayDeadLetter(string $id): array
    {
        return $this->client->requestJson('POST', '/v1/webhooks/dead-letters/' . rawurlencode($id) . '/replay');
    }
}
