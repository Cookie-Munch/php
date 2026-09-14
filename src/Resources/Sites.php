<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/**
 * Site management: CRUD, config, cookie scans, snippet, domain verification,
 * brand extraction, and the v2 banner flow — the /v1/sites/* surface.
 */
final class Sites
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List sites for the key's org — GET /v1/sites.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/sites') ?? [];

        return $out;
    }

    /**
     * Create a site (cbid auto-generated when omitted) — POST /v1/sites.
     *
     * @param array{domain: string, cbid?: string} $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        return $this->client->requestJson('POST', '/v1/sites', $input);
    }

    /**
     * Get a single site — GET /v1/sites/{cbid}.
     *
     * @return array<string, mixed>
     */
    public function get(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . self::enc($cbid));
    }

    /** Delete a site — DELETE /v1/sites/{cbid}. */
    public function delete(string $cbid): void
    {
        $this->client->request('DELETE', '/v1/sites/' . self::enc($cbid));
    }

    /**
     * Get site config — GET /v1/sites/{cbid}/config.
     *
     * @return array<string, mixed>
     */
    public function getConfig(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . self::enc($cbid) . '/config');
    }

    /**
     * Upsert (merge) site config — PUT /v1/sites/{cbid}/config.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function putConfig(string $cbid, array $config): array
    {
        return $this->client->requestJson('PUT', '/v1/sites/' . self::enc($cbid) . '/config', $config);
    }

    /**
     * Latest categorized cookie declaration — GET /v1/sites/{cbid}/cookies.
     * Returns { updatedAt, cookies: CategorizedCookie[] } (authoritative OpenAPI shape).
     *
     * @return array<string, mixed>
     */
    public function cookies(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . self::enc($cbid) . '/cookies');
    }

    /**
     * Kick off an async cookie crawl — POST /v1/sites/{cbid}/scan.
     * Returns a ScanStatus { status: "idle"|"scanning", lastScannedAt }.
     *
     * @return array<string, mixed>
     */
    public function scan(string $cbid): array
    {
        return $this->client->requestJson('POST', '/v1/sites/' . self::enc($cbid) . '/scan');
    }

    /**
     * Current cookie-scan status — GET /v1/sites/{cbid}/scan.
     *
     * @return array<string, mixed>
     */
    public function scanStatus(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . self::enc($cbid) . '/scan');
    }

    /**
     * A/B experiment results — GET /v1/sites/{cbid}/ab.
     * Each row is { variant, impressions, optIns, optInRate } (authoritative OpenAPI shape).
     *
     * @return list<array<string, mixed>>
     */
    public function ab(string $cbid): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = $this->client->request('GET', '/v1/sites/' . self::enc($cbid) . '/ab') ?? [];

        return $out;
    }

    /**
     * The install snippet — GET /v1/sites/{cbid}/snippet.
     *
     * @param array{blockingMode?: string, culture?: string} $opts
     *
     * @return array<string, mixed>
     */
    public function snippet(string $cbid, array $opts = []): array
    {
        $query = [
            'blockingmode' => $opts['blockingMode'] ?? null,
            'culture' => $opts['culture'] ?? null,
        ];

        return $this->client->requestJson('GET', '/v1/sites/' . self::enc($cbid) . '/snippet', null, $query);
    }

    /**
     * Verify the site's domain — POST /v1/sites/{cbid}/verify.
     *
     * @param 'dns'|'meta'|'file' $method
     *
     * @return array<string, mixed> { verified, method?, reason? }
     */
    public function verify(string $cbid, string $method): array
    {
        return $this->client->requestJson('POST', '/v1/sites/' . self::enc($cbid) . '/verify', ['method' => $method]);
    }

    /**
     * Suggest theme tokens from the site homepage ("Match my site") —
     * POST /v1/sites/{cbid}/brand. Returns { suggestion: BrandSuggestion }.
     *
     * @return array<string, mixed>
     */
    public function brand(string $cbid): array
    {
        return $this->client->requestJson('POST', '/v1/sites/' . self::enc($cbid) . '/brand', []);
    }

    /**
     * Read the v2 banner flow (design) plus lint findings —
     * GET /v1/sites/{cbid}/flow.
     *
     * @return array<string, mixed> A FlowConfig.
     */
    public function getFlow(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . self::enc($cbid) . '/flow');
    }

    /**
     * Apply an ordered batch of structured edit ops —
     * POST /v1/sites/{cbid}/flow/ops. Returns a FlowWriteResult; check `ok`
     * (a lint/validation failure is HTTP 200 with { ok: false, issues }).
     *
     * @param list<array<string, mixed>> $operations
     *
     * @return array<string, mixed>
     */
    public function editFlow(string $cbid, array $operations): array
    {
        return $this->client->requestJson('POST', '/v1/sites/' . self::enc($cbid) . '/flow/ops', ['operations' => $operations]);
    }

    /**
     * Replace the flow wholesale with a full v2 config —
     * PUT /v1/sites/{cbid}/flow. Returns a FlowWriteResult; check `ok`.
     *
     * @param array<string, mixed> $config A complete FlowConfig ({ v:2, flow, categories, customCss? }).
     *
     * @return array<string, mixed>
     */
    public function setFlow(string $cbid, array $config): array
    {
        return $this->client->requestJson('PUT', '/v1/sites/' . self::enc($cbid) . '/flow', $config);
    }

    /**
     * Enable the personalized-ads split on the site's banner —
     * POST /v1/sites/{cbid}/elements/ad-personalization. Idempotent.
     *
     * @param array{enabled?: bool, default?: bool, label?: string} $opts
     *
     * @return array<string, mixed> An AdPersonalizationResult.
     */
    public function enableAdPersonalization(string $cbid, array $opts = []): array
    {
        return $this->client->requestJson('POST', '/v1/sites/' . self::enc($cbid) . '/elements/ad-personalization', $opts);
    }

    private static function enc(string $segment): string
    {
        return rawurlencode($segment);
    }
}
