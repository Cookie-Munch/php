<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Org membership — the /v1/members surface. */
final class Members
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List the org's members — GET /v1/members.
     * Each member is { userId, email, role } (authoritative OpenAPI shape).
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/members') ?? [];

        return $out;
    }

    /**
     * Invite a member — POST /v1/members.
     * Returns { member: { userId, email, role } }.
     *
     * @param 'owner'|'admin'|'member'|'viewer' $role
     *
     * @return array<string, mixed>
     */
    public function invite(string $email, string $role): array
    {
        return $this->client->requestJson('POST', '/v1/members', ['email' => $email, 'role' => $role]);
    }

    /**
     * Change a member's role — PATCH /v1/members/{userId}.
     * Returns { member: { userId, email, role } }.
     *
     * @param 'owner'|'admin'|'member'|'viewer' $role
     *
     * @return array<string, mixed>
     */
    public function setRole(string $userId, string $role): array
    {
        return $this->client->requestJson('PATCH', '/v1/members/' . rawurlencode($userId), ['role' => $role]);
    }

    /**
     * Remove a member — DELETE /v1/members/{userId}.
     *
     * @return array<string, mixed>|null
     */
    public function remove(string $userId): ?array
    {
        $result = $this->client->request('DELETE', '/v1/members/' . rawurlencode($userId));

        return is_array($result) ? $result : null;
    }
}
