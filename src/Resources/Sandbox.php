<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/**
 * The organisation's sandbox — the /v1/sandbox surface: a separate test organisation with the same
 * people and plan features, never billed. Its keys are test keys and its rights requests are all
 * tests. create() and delete() need a full-access key or `orgs:create`.
 */
final class Sandbox
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * The sandbox, or null — GET /v1/sandbox. From a sandbox key, the sandbox itself.
     *
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        /** @var array<string, mixed>|null $out */
        $out = $this->client->requestJson('GET', '/v1/sandbox')['sandbox'] ?? null;

        return $out;
    }

    /**
     * Make the sandbox (or reuse it, `created: false`) and a new test key for it, shown once — POST /v1/sandbox.
     *
     * @return array<string, mixed>
     */
    public function create(?string $keyName = null): array
    {
        return $this->client->requestJson('POST', '/v1/sandbox', $keyName === null ? (object) [] : ['keyName' => $keyName]);
    }

    /**
     * Erase the sandbox and everything in it — DELETE /v1/sandbox. Cannot be undone; the live
     * organisation is not touched.
     *
     * @return array<string, mixed>
     */
    public function delete(): array
    {
        return $this->client->requestJson('DELETE', '/v1/sandbox');
    }
}
