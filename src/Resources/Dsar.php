<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** Data-subject access requests — the /v1/dsar surface. */
final class Dsar
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List DSARs — GET /v1/dsar.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/dsar') ?? [];

        return $out;
    }

    /**
     * Create a DSAR — POST /v1/dsar. Returns { request: DsarRequest }.
     *
     * `test: true` files a test request: off the SLA board, labelled, and deletable.
     *
     * @param array{type: string, subjectEmail: string, regulation: string, note?: string, cbid?: string, stamp?: string, jurisdiction?: string, test?: bool} $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        return $this->client->requestJson('POST', '/v1/dsar', $input);
    }

    /**
     * One request, in the shape list() returns — GET /v1/dsar/{id}.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->requestJson('GET', '/v1/dsar/' . rawurlencode($id));
    }

    /**
     * Delete a TEST request with its tasks and log — DELETE /v1/dsar/{id}. Real requests are
     * refused with 409 not_a_test_request; the deletion is audited.
     */
    public function delete(string $id): void
    {
        $this->client->requestJson('DELETE', '/v1/dsar/' . rawurlencode($id));
    }

    /**
     * Mark a CLOSED request (completed or rejected) as a test, after the fact —
     * POST /v1/dsar/{id}/mark-test. An open one is refused with 409 request_not_closed.
     * Audited; delete() then works on it.
     *
     * @return array{request: array<string, mixed>, changed: bool}
     */
    public function markTest(string $id): array
    {
        /** @var array{request: array<string, mixed>, changed: bool} */
        return $this->client->requestJson('POST', '/v1/dsar/' . rawurlencode($id) . '/mark-test');
    }

    /**
     * Advance a DSAR to a new status — POST /v1/dsar/{id}/advance.
     * Returns { request: DsarRequest }.
     *
     * @param 'received'|'verifying'|'in_progress'|'completed'|'rejected' $toStatus
     *
     * @return array<string, mixed>
     */
    public function advance(string $id, string $toStatus, ?string $messageToSubject = null): array
    {
        $body = ['toStatus' => $toStatus];
        if ($messageToSubject !== null) {
            $body['messageToSubject'] = $messageToSubject;
        }
        return $this->client->requestJson('POST', '/v1/dsar/' . rawurlencode($id) . '/advance', $body);
    }

    /** The subject-facing response notice for a request, as plain text — GET /v1/dsar/{id}/response. */
    public function response(string $id): string
    {
        return (string) $this->client->request('GET', '/v1/dsar/' . rawurlencode($id) . '/response', null, null, true);
    }

    /**
     * Erase a subject's consent records on one site, for a deletion request past identity
     * verification. Noted on the request. Requires dsar:write and consent:write —
     * POST /v1/dsar/{id}/erase.
     *
     * @return array<string, mixed>
     */
    public function erase(string $id, string $cbid, string $stamp): array
    {
        return $this->client->requestJson('POST', '/v1/dsar/' . rawurlencode($id) . '/erase', ['cbid' => $cbid, 'stamp' => $stamp]);
    }

    /**
     * A subject's consent records on one site, for an access or portability request past
     * identity verification. Noted on the request. Requires dsar:write and consent:read —
     * POST /v1/dsar/{id}/export.
     *
     * @return array<string, mixed>
     */
    public function export(string $id, string $cbid, string $stamp): array
    {
        return $this->client->requestJson('POST', '/v1/dsar/' . rawurlencode($id) . '/export', ['cbid' => $cbid, 'stamp' => $stamp]);
    }

    /**
     * The request carrying one X-ForgeDeck-Trace — GET /v1/dsar?trace=.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByTrace(string $trace): array
    {
        return $this->client->requestJson('GET', '/v1/dsar', null, ['trace' => $trace]);
    }

    /**
     * Open a case fanned out to every connected system — POST /v1/dsar/requests. `type` takes the
     * canonical words (access, deletion, closure, restriction, rectification) or an alias (erasure,
     * erase, export). `subject` is ['emails' => [...], 'identity' => ['iss' =>, 'sub' =>, 'ext' =>, 'principal' =>]]
     * from any issuer the site trusts. `source` product/atlas attests the person (dsar:write); admin
     * files it received. `$idempotencyKey` (16–128 chars) makes a retry answer the same case.
     *
     * @param array{cbid: string, type: string, subject?: array<string, mixed>, subjectToken?: string, source?: string, reason?: string, skipSystems?: list<string>, fulfill?: bool, test?: bool} $input
     *
     * @return array<string, mixed>
     */
    public function openCase(array $input, ?string $idempotencyKey = null, ?string $trace = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        if ($trace !== null) {
            $headers['X-ForgeDeck-Trace'] = $trace;
        }

        return $this->client->requestJson('POST', '/v1/dsar/requests', $input, null, $headers);
    }

    /**
     * One case across every system — GET /v1/dsar/requests/{id}.
     *
     * @return array<string, mixed>
     */
    public function getCase(string $id): array
    {
        return $this->client->requestJson('GET', '/v1/dsar/requests/' . rawurlencode($id));
    }

    /**
     * Cancel a case before executeAfter — POST /v1/dsar/requests/{id}/cancel. Every system keeps its data.
     *
     * @return array<string, mixed>
     */
    public function cancelCase(string $id): array
    {
        return $this->client->requestJson('POST', '/v1/dsar/requests/' . rawurlencode($id) . '/cancel');
    }

    /**
     * The signed (Ed25519) receipt for a case — GET /v1/dsar/requests/{id}/receipt.
     *
     * @return array<string, mixed>
     */
    public function caseReceipt(string $id): array
    {
        return $this->client->requestJson('GET', '/v1/dsar/requests/' . rawurlencode($id) . '/receipt');
    }
}
