<?php

declare(strict_types=1);

namespace CookieMunch;

use CookieMunch\Http\CurlTransport;
use CookieMunch\Http\Transport;
use CookieMunch\Resources\Banners;
use CookieMunch\Resources\BrandKits;
use CookieMunch\Resources\Consent;
use CookieMunch\Resources\Dsar;
use CookieMunch\Resources\Keys;
use CookieMunch\Resources\Members;
use CookieMunch\Resources\Org;
use CookieMunch\Resources\Preferences;
use CookieMunch\Resources\Ropa;
use CookieMunch\Resources\Sites;
use CookieMunch\Resources\Vendors;
use CookieMunch\Resources\Webhooks;
use CookieMunch\Resources\Identity;
use CookieMunch\Resources\Vault;
use CookieMunch\Resources\Profile;
use CookieMunch\Resources\Subscriptions;
use CookieMunch\Resources\Assessments;
use CookieMunch\Resources\Discovery;
use CookieMunch\Resources\Ai;
use CookieMunch\Resources\Fulfillment;
use CookieMunch\Resources\Regulatory;
use CookieMunch\Resources\Reseller;
use CookieMunch\Resources\Subjects;
use CookieMunch\Resources\Assets;

/**
 * A small, dependency-free PHP client for the Cookie Munch Developer API.
 *
 * The organisation is derived server-side from the API key, so callers never
 * pass an orgId. Construct with an API key (an "fck_..." developer key); the
 * default base URL is https://api.cookiemunch.net.
 *
 *   $cm = new CookieMunch\Client('fck_live_...');
 *   $sites = $cm->sites->list();
 *   $me = $cm->me();
 *
 * Both `Authorization: Bearer <key>` and `X-API-Key: <key>` are sent on every
 * request (the server accepts either). The transport is injectable for tests
 * and custom runtimes; it defaults to {@see CurlTransport}.
 */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://api.cookiemunch.net';
    public const VERSION = '0.1.0';

    private readonly string $baseUrl;
    private readonly Transport $transport;
    private string $userAgent;

    public readonly Sites $sites;
    public readonly Consent $consent;
    public readonly Dsar $dsar;
    public readonly Vendors $vendors;
    public readonly Ropa $ropa;
    public readonly BrandKits $brandKits;
    public readonly Preferences $preferences;
    public readonly Members $members;
    public readonly Keys $keys;
    public readonly Webhooks $webhooks;
    public readonly Banners $banners;
    public readonly Identity $identity;
    public readonly Vault $vault;
    public readonly Profile $profile;
    public readonly Subscriptions $subscriptions;
    public readonly Assessments $assessments;
    public readonly Discovery $discovery;
    public readonly Ai $ai;
    public readonly Fulfillment $fulfillment;
    public readonly Regulatory $regulatory;
    public readonly Reseller $reseller;
    public readonly Subjects $subjects;
    public readonly Org $org;
    public readonly Assets $assets;

    /**
     * @param string         $apiKey    An "fck_..." developer API key.
     * @param string         $baseUrl   API origin (no trailing "/v1"); trailing slashes are trimmed.
     * @param Transport|null $transport HTTP transport; defaults to a curl-based one. Inject a
     *                                  {@see \CookieMunch\Http\CallableTransport} in tests.
     */
    public function __construct(
        private readonly string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        ?Transport $transport = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = $transport ?? new CurlTransport();
        $this->userAgent = 'cookiemunch-php/' . self::VERSION;

        $this->sites = new Sites($this);
        $this->consent = new Consent($this);
        $this->dsar = new Dsar($this);
        $this->vendors = new Vendors($this);
        $this->ropa = new Ropa($this);
        $this->brandKits = new BrandKits($this);
        $this->preferences = new Preferences($this);
        $this->members = new Members($this);
        $this->keys = new Keys($this);
        $this->webhooks = new Webhooks($this);
        $this->banners = new Banners($this);
        $this->identity = new Identity($this);
        $this->vault = new Vault($this);
        $this->profile = new Profile($this);
        $this->subscriptions = new Subscriptions($this);
        $this->assessments = new Assessments($this);
        $this->discovery = new Discovery($this);
        $this->ai = new Ai($this);
        $this->fulfillment = new Fulfillment($this);
        $this->regulatory = new Regulatory($this);
        $this->reseller = new Reseller($this);
        $this->subjects = new Subjects($this);
        $this->org = new Org($this);
        $this->assets = new Assets($this);
    }

    /** Override the User-Agent header sent on every request. */
    public function setUserAgent(string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    // ---- top-level endpoints -------------------------------------------------

    /**
     * Identify the caller — GET /v1/me. Returns { orgId, plan, keyPrefix }.
     *
     * @return array<string, mixed>
     */
    public function me(): array
    {
        return $this->requestJson('GET', '/v1/me');
    }

    /**
     * Create a sibling organisation owned by the same account — POST /v1/orgs.
     *
     * For starting a separate business of your own. Needs an unscoped key and counts against
     * the account's plan org allowance (403 org_limit names the plan). Not reseller
     * provisioning, which is for organisations you run on behalf of YOUR customers.
     *
     * @return array<string, mixed>
     */
    public function createOrg(string $name): array
    {
        return $this->requestJson('POST', '/v1/orgs', ['name' => $name]);
    }

    /**
     * GET /v1/languages — the languages the banner already has copy for. Diff it
     * against your visitors' locales to find the ones you still have to write.
     *
     * @return array<int, array<string, mixed>> each { code, name, endonym, rtl, source }
     */
    public function languages(): array
    {
        return $this->requestJson('GET', '/v1/languages');
    }

    /**
     * Current resource usage for the org — GET /v1/usage.
     * Returns { domains, seats, monthlyEvents } (per the authoritative OpenAPI schema).
     *
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        return $this->requestJson('GET', '/v1/usage');
    }

    /**
     * The organisation's audit log, newest first — GET /v1/audit. Sign-ins aside, every
     * administrative change made in the dashboard or through the API, with who made it;
     * API actions are attributed to `apikey:<prefix>`. Requires an unscoped key that is not
     * property-locked.
     *
     * @return array<string, mixed>
     */
    public function audit(?int $limit = null): array
    {
        return $this->requestJson('GET', '/v1/audit', null, ['limit' => $limit]);
    }

    /**
     * Record a consent decision via the PUBLIC POST /api/v1/consent endpoint —
     * the high-volume write the browser embed makes. No auth is required by the
     * server (the cbid must be a registered site), but this client still sends
     * its auth headers harmlessly. A success returns HTTP 204 (no body).
     *
     * Note this endpoint lives under /api/v1, not the /v1 dev-API prefix.
     *
     * Expected payload fields (see server ConsentIngest):
     *   - cbid (string, required)      the registered site id
     *   - stamp (string)               unique consent-receipt id
     *   - choices (array)              { preferences: bool, statistics: bool, marketing: bool }
     *   - method (string)             "explicit" | "implied"
     *   - ver (int)                   consent/policy version
     *   - utc (int)                   epoch-ms of the decision
     *   - url (string)                page URL
     *   - tcString, gppString (string, optional)
     *   - purposes (array<string,bool>, optional)
     *   - subjectPolicyHash (string, optional)
     *   - purposeRopa (array<string,string>, optional)
     *   - variant (string, optional)  A/B variant
     *   - subjectId (string, optional) stable cross-surface subject id (e.g. a logged-in
     *                                  account id); correlates one subject's consent across
     *                                  all the org's sites/surfaces. Opaque; hashed server-side.
     *
     * @param array<string, mixed> $payload
     */
    public function logConsent(array $payload): void
    {
        $this->request('POST', '/api/v1/consent', $payload);
    }

    // ---- internal request plumbing ------------------------------------------

    /**
     * Perform a request and return the decoded JSON body as an associative array.
     *
     * @param array<string, mixed>|null                       $body
     * @param array<string, int|string|bool|null>|null        $query
     *
     * @return array<string, mixed>
     */
    public function requestJson(string $method, string $path, ?array $body = null, ?array $query = null): array
    {
        $result = $this->request($method, $path, $body, $query);

        return is_array($result) ? $result : [];
    }

    /**
     * Perform a request against the API.
     *
     * @param array<string, mixed>|null                $body  JSON request body, or null for none.
     * @param array<string, int|string|bool|null>|null $query Query parameters (null values dropped).
     * @param bool                                     $raw   When true, return the raw response body as a string.
     *
     * @return array<mixed>|string|null Decoded JSON (array), the raw string when $raw, or null for 204/empty.
     *
     * @throws ApiException     On any non-2xx response.
     * @throws TransportException On a transport-level failure.
     */
    public function request(string $method, string $path, ?array $body = null, ?array $query = null, bool $raw = false): array|string|null
    {
        $url = $this->baseUrl . $path . $this->buildQuery($query);

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ];

        $encodedBody = null;
        if ($body !== null) {
            $encodedBody = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->transport->send($method, $url, $headers, $encodedBody);

        if ($response->status < 200 || $response->status >= 300) {
            throw $this->toApiException($response->status, $response->body);
        }

        if ($response->status === 204 || $response->body === '') {
            return $raw ? '' : null;
        }

        if ($raw) {
            return $response->body;
        }

        $contentType = $response->header('content-type') ?? '';
        if ($contentType !== '' && !str_contains($contentType, 'application/json')) {
            return $response->body;
        }

        $decoded = json_decode($response->body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            // Not JSON despite the absence of a content-type hint — hand back the raw text.
            return $response->body;
        }

        /** @var array<mixed>|null $decoded */
        return $decoded;
    }

    private function toApiException(int $status, string $body): ApiException
    {
        $message = 'request failed with status ' . $status;
        $code = null;

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (isset($decoded['error']) && is_string($decoded['error'])) {
                $message = $decoded['error'];
            }
            if (isset($decoded['code']) && is_string($decoded['code'])) {
                $code = $decoded['code'];
            }
        }

        return new ApiException($status, $message, $body, $code);
    }

    /**
     * @param array<string, int|string|bool|null>|null $query
     */
    private function buildQuery(?array $query): string
    {
        if ($query === null) {
            return '';
        }

        $parts = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $parts[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
        }

        return $parts === [] ? '' : '?' . implode('&', $parts);
    }
}
