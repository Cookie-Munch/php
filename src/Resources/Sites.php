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
     * Replace site config — PUT /v1/sites/{cbid}/config. A top-level field $config omits
     * reverts to its default — unless $keepUnspecified (?keep=unspecified), which keeps its
     * stored value. A top-level field sent as null reverts to its default.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function putConfig(string $cbid, array $config, bool $keepUnspecified = false): array
    {
        $query = $keepUnspecified ? '?keep=unspecified' : '';
        return $this->client->requestJson('PUT', '/v1/sites/' . self::enc($cbid) . '/config' . $query, $config);
    }

    /**
     * Change part of a site's config — PATCH /v1/sites/{cbid}/config.
     * A JSON Merge Patch (RFC 7386): omitted fields keep their stored value, and a field set
     * to null is removed (it takes its default). putConfig() replaces the whole document.
     *
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public function patchConfig(string $cbid, array $config): array
    {
        return $this->client->requestJson('PATCH', '/v1/sites/' . self::enc($cbid) . '/config', $config);
    }

    /**
     * Preview a config write without saving it — PATCH (or, with $replace, PUT)
     * /v1/sites/{cbid}/config?dryRun=1. Returns `config` (as it would be saved),
     * `diff` (every leaf that would change) and any `warnings`.
     *
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public function dryRunConfig(string $cbid, array $config, bool $replace = false, bool $keepUnspecified = false): array
    {
        $keep = $replace && $keepUnspecified ? '&keep=unspecified' : '';
        return $this->client->requestJson($replace ? 'PUT' : 'PATCH', '/v1/sites/' . self::enc($cbid) . '/config?dryRun=1' . $keep, $config);
    }

    /**
     * Check a proposed config for consent-button parity without saving it —
     * POST /v1/sites/{cbid}/config/lint: is "Accept all" more prominent than "Reject all"
     * anywhere (colour, size, placement, copy, custom CSS)? Returns `designWarnings`,
     * `score` (0-100) and `suggestedReject`. It checks exactly what the write would store:
     * merged as patchConfig would, or with $replace replaced as putConfig would. Needs only
     * sites:read.
     *
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public function lintConfig(string $cbid, array $config, bool $replace = false): array
    {
        return $this->client->requestJson('POST', '/v1/sites/' . self::enc($cbid) . '/config/lint' . ($replace ? '?replace=1' : ''), $config);
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
     * Mint a short-lived signed token to preview the banner as a visitor in a given region
     * and language sees it (headless browsers included; nothing is recorded) —
     * POST /v1/sites/{cbid}/preview-token. Returns { cbid, token, expiresAt, previewUrl?, region?, lang? }.
     *
     * @return array<string, mixed>
     */
    public function previewToken(string $cbid, ?string $region = null, ?string $lang = null, ?string $url = null, ?int $ttlSeconds = null): array
    {
        $body = array_filter(
            ['region' => $region, 'lang' => $lang, 'url' => $url, 'ttlSeconds' => $ttlSeconds],
            static fn ($v) => $v !== null,
        );
        return $this->client->requestJson('POST', '/v1/sites/' . self::enc($cbid) . '/preview-token', $body);
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

    /**
     * Which banner design the site uses — GET /v1/sites/{cbid}/banner. Returns { bannerId: ?string }.
     *
     * @return array<string, mixed>
     */
    public function banner(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . self::enc($cbid) . '/banner');
    }

    /**
     * Pages where the embed could not load its banner renderer — the host page's CSP or
     * Trusted Types policy refused it, so nobody there can be asked. Empty is healthy —
     * GET /v1/sites/{cbid}/blocked.
     *
     * @return array<string, mixed>
     */
    public function blocked(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . rawurlencode($cbid) . '/blocked');
    }

    /**
     * Read a cookie declaration exported from another CMP and translate its categories
     * into ours. Nothing is applied — the result comes back for review —
     * POST /v1/sites/{cbid}/import.
     *
     * @return array<string, mixed>
     */
    public function importDeclaration(string $cbid, string $data): array
    {
        return $this->client->requestJson('POST', '/v1/sites/' . rawurlencode($cbid) . '/import', ['data' => $data]);
    }

    /**
     * Import a declaration exported from another CMP AND declare it: every row whose
     * category mapped to ours and that has a purpose is declared; the rest come back in
     * `skipped`. Cookies already declared are kept unless $overwrite —
     * POST /v1/sites/{cbid}/import?apply=1.
     *
     * @return array<string, mixed>
     */
    public function applyImport(string $cbid, string $data, bool $overwrite = false): array
    {
        $query = '?apply=1' . ($overwrite ? '&overwrite=1' : '');
        return $this->client->requestJson('POST', '/v1/sites/' . rawurlencode($cbid) . '/import' . $query, ['data' => $data]);
    }

    /**
     * Declare what one cookie is. Overrides scanned, observed and database classification
     * everywhere (API, preference modal, data-cc="declaration"). A name ending in `*`
     * declares a prefix wildcard — PUT /v1/sites/{cbid}/cookies/{name}. `purposes` is the
     * purpose in other languages (['es' => '…', 'pt-BR' => '…']); `purpose` is the fallback.
     * $dryRun stores nothing and answers with `declared` (the would-be list) and a `diff`.
     *
     * @param array{category: string, purpose: string, purposes?: array<string, string>|null, provider?: string, expiry?: string, domain?: string, pattern?: bool} $declaration
     * @return array<string, mixed> { cookie, warnings?, dryRun?, declared?, diff? }
     */
    public function declareCookie(string $cbid, string $name, array $declaration, bool $dryRun = false): array
    {
        return $this->client->requestJson('PUT', '/v1/sites/' . rawurlencode($cbid) . '/cookies/' . rawurlencode($name) . ($dryRun ? '?dryRun=1' : ''), $declaration);
    }

    /**
     * Declare many cookies at once (each with `name`). All-or-nothing; $replace also
     * removes declarations the list does not name; $dryRun stores nothing and answers with
     * what would be, plus a `diff` — PUT /v1/sites/{cbid}/cookies.
     *
     * @param list<array<string, mixed>> $cookies
     * @return array<string, mixed> { declared, saved, removed, warnings?, dryRun?, diff? }
     */
    public function declareCookies(string $cbid, array $cookies, bool $replace = false, bool $dryRun = false): array
    {
        $body = ['cookies' => $cookies];
        if ($replace) {
            $body['replace'] = true;
        }
        return $this->client->requestJson('PUT', '/v1/sites/' . rawurlencode($cbid) . '/cookies' . ($dryRun ? '?dryRun=1' : ''), $body);
    }

    /**
     * Remove a declaration; the scanned classification applies again —
     * DELETE /v1/sites/{cbid}/cookies/{name}. With $dryRun nothing is removed and the
     * answer is the would-be list (`declared`) and a `diff`; otherwise null.
     *
     * @return array<string, mixed>|null
     */
    public function undeclareCookie(string $cbid, string $name, bool $dryRun = false): ?array
    {
        $path = '/v1/sites/' . rawurlencode($cbid) . '/cookies/' . rawurlencode($name);
        if ($dryRun) {
            return $this->client->requestJson('DELETE', $path . '?dryRun=1');
        }
        $this->client->request('DELETE', $path);
        return null;
    }

    /**
     * The site's privacy and cookie policy, as Markdown — GET /v1/sites/{cbid}/policy.
     *
     * @param array{contactEmail?: string, effectiveDate?: string, jurisdictions?: list<string>} $opts
     */
    public function policy(string $cbid, array $opts = []): string
    {
        $query = [
            'contactEmail' => $opts['contactEmail'] ?? null,
            'effectiveDate' => $opts['effectiveDate'] ?? null,
            'jurisdictions' => isset($opts['jurisdictions']) ? implode(',', $opts['jurisdictions']) : null,
        ];

        return (string) $this->client->request('GET', '/v1/sites/' . self::enc($cbid) . '/policy', null, $query, true);
    }

    /**
     * Which trackers fired after opt-out in a captured session, and what personal data left
     * the page — POST /v1/sites/{cbid}/sentry.
     *
     * @param array{har?: mixed, requests?: list<mixed>, consent?: array<string, bool>, gpc?: bool} $input
     *
     * @return array<string, mixed>
     */
    public function analyzeSession(string $cbid, array $input): array
    {
        return $this->client->requestJson('POST', '/v1/sites/' . self::enc($cbid) . '/sentry', $input);
    }

    /**
     * Exactly what to publish to prove control of the domain, for each method —
     * GET /v1/sites/{cbid}/verify/challenge.
     *
     * @return array<string, mixed>
     */
    public function verifyChallenge(string $cbid): array
    {
        return $this->client->requestJson('GET', '/v1/sites/' . self::enc($cbid) . '/verify/challenge');
    }

    /**
     * Create up to 100 sites — POST /v1/sites/bulk. Partial success: each item reports `ok`
     * or its own error.
     *
     * @param list<array{domain: string, cbid?: string, platform?: string}> $sites
     *
     * @return array<string, mixed>
     */
    public function createBulk(array $sites): array
    {
        return $this->client->requestJson('POST', '/v1/sites/bulk', ['sites' => $sites]);
    }

    private static function enc(string $segment): string
    {
        return rawurlencode($segment);
    }
}
