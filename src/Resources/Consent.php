<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/**
 * Consent reads and subject operations: per-site stats/log/CSV export, signed
 * receipts, and subject erase/export — plus the public consent-log ingest.
 */
final class Consent
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Record a consent decision via the PUBLIC POST /api/v1/consent endpoint.
     * Mirrors {@see Client::logConsent()} on the resource group. Returns nothing
     * (HTTP 204).
     *
     * @param array<string, mixed> $payload See {@see Client::logConsent()} for fields.
     */
    public function ingest(array $payload): void
    {
        $this->client->logConsent($payload);
    }

    /**
     * Verify the consent log's tamper-evident hash chain —
     * GET /v1/sites/{cbid}/consent/verify.
     *
     * Each record carries the hash of the one before it, so an edited, reordered or removed
     * record answers false. This is the evidence behind the log.
     */
    public function verify(string $cbid): bool
    {
        /** @var array{valid?: bool} $out */
        $out = $this->client->request('GET', '/v1/sites/' . self::enc($cbid) . '/consent/verify') ?? [];

        return (bool) ($out['valid'] ?? false);
    }

    /**
     * Aggregated per-day consent stats — GET /v1/sites/{cbid}/consent/stats.
     *
     * @param array{from?: int, to?: int} $query Epoch-ms window bounds.
     *
     * @return list<array<string, mixed>> ConsentDay rows.
     */
    public function stats(string $cbid, array $query = []): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/sites/' . self::enc($cbid) . '/consent/stats', null, $this->range($query)) ?? [];

        return $out;
    }

    /**
     * Recent anonymised consent records — GET /v1/sites/{cbid}/consent/log.
     *
     * @param array{from?: int, to?: int, limit?: int} $query
     *
     * @return list<array<string, mixed>> ConsentLogRow rows.
     */
    public function log(string $cbid, array $query = []): array
    {
        $params = $this->range($query);
        if (isset($query['limit'])) {
            $params['limit'] = $query['limit'];
        }
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/sites/' . self::enc($cbid) . '/consent/log', null, $params) ?? [];

        return $out;
    }

    /**
     * CSV audit export as a raw string — GET /v1/sites/{cbid}/consent/export.
     *
     * @param array{from?: int, to?: int} $query
     */
    public function export(string $cbid, array $query = []): string
    {
        /** @var string $csv */
        $csv = $this->client->request('GET', '/v1/sites/' . self::enc($cbid) . '/consent/export', null, $this->range($query), true);

        return $csv;
    }

    /**
     * Signed ISO-27560 consent receipt (JSON) —
     * GET /v1/sites/{cbid}/receipt/{stamp}.
     *
     * @return array<string, mixed>
     */
    public function receipt(string $cbid, string $stamp): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . self::enc($cbid) . '/receipt/' . self::enc($stamp));
    }

    /**
     * Crypto-erase a subject's consent records by receipt stamp — irreversible.
     * POST /v1/sites/{cbid}/erase-consent. Returns { erased: int }.
     *
     * @return array<string, mixed>
     */
    public function eraseSubject(string $cbid, string $stamp): array
    {
        return $this->client->requestJson('POST', '/v1/sites/' . self::enc($cbid) . '/erase-consent', ['stamp' => $stamp]);
    }

    /**
     * Export a subject's consent records by receipt stamp (GDPR access/portability) —
     * GET /v1/sites/{cbid}/subject-export?stamp=... Returns { cbid, stamp, records, count }.
     *
     * @return array<string, mixed>
     */
    public function exportSubject(string $cbid, string $stamp): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . self::enc($cbid) . '/subject-export', null, ['stamp' => $stamp]);
    }

    /**
     * @param array{from?: int, to?: int} $query
     *
     * @return array<string, int|string|bool|null>
     */
    private function range(array $query): array
    {
        return [
            'from' => $query['from'] ?? null,
            'to' => $query['to'] ?? null,
        ];
    }

    private static function enc(string $segment): string
    {
        return rawurlencode($segment);
    }
}
