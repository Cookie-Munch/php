<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Marketing preferences as topics x channels — the /v1/subscriptions surface. */
final class Subscriptions
{
    public function __construct(private readonly Client $client)
    {
    }

/** @return array<string, mixed> */
public function topics(): array
{
    return $this->client->requestJson('GET', '/v1/subscriptions/topics');
}

/**
 * Replace the topic catalog. It is authored whole; anything omitted is removed.
 *
 * @param list<array{code: string, name?: string, channels: list<string>, downstream?: array<string, string>}> $topics
 *
 * @return array<string, mixed>
 */
public function setTopics(array $topics): array
{
    return $this->client->requestJson('PUT', '/v1/subscriptions/topics', ['topics' => $topics]);
}

/** @return array<string, mixed> */
public function get(string $subjectId): array
{
    return $this->client->requestJson('GET', '/v1/subscriptions/' . rawurlencode($subjectId));
}

/** @return array<string, mixed> */
public function set(string $subjectId, string $topic, string $channel, bool $optedIn): array
{
    return $this->client->requestJson('PUT', '/v1/subscriptions/' . rawurlencode($subjectId), ['topic' => $topic, 'channel' => $channel, 'optedIn' => $optedIn]);
}

/** @return array<string, mixed> */
public function unsubscribeAll(string $subjectId): array
{
    return $this->client->requestJson('POST', '/v1/subscriptions/' . rawurlencode($subjectId) . '/unsubscribe-all');
}

/** @return array<string, mixed> Lift a global unsubscribe, restoring the choices from before it. */
public function resubscribe(string $subjectId): array
{
    return $this->client->requestJson('POST', '/v1/subscriptions/' . rawurlencode($subjectId) . '/resubscribe');
}

/**
 * @param list<array<string, mixed>> $topics
 *
 * @return array<string, mixed>
 */
public function activation(string $subjectId, array $topics): array
{
    return $this->client->requestJson('POST', '/v1/subscriptions/' . rawurlencode($subjectId) . '/activation', ['topics' => $topics]);
}
}
