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
use CookieMunch\Resources\RightsPage;
use CookieMunch\Resources\Subjects;
use CookieMunch\Resources\RightsLinks;
use CookieMunch\Resources\RightsCaptcha;
use CookieMunch\Resources\ConsentSyncSecret;
use CookieMunch\Resources\PreferenceCenter;
use CookieMunch\Resources\Metadata;
use CookieMunch\Resources\PartnerLinks;
use CookieMunch\Resources\RightsIdentity;
use CookieMunch\Resources\DsarSettings;
use CookieMunch\Resources\Integrations;
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
    public readonly RightsPage $rightsPage;
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
    public readonly RightsLinks $rightsLinks;
    public readonly RightsCaptcha $rightsCaptcha;
    public readonly ConsentSyncSecret $consentSyncSecret;
    public readonly PreferenceCenter $preferenceCenter;
    public readonly RightsIdentity $rightsIdentity;
    public readonly DsarSettings $dsarSettings;
    public readonly Integrations $integrations;
    public readonly Org $org;
    public readonly Assets $assets;
    public readonly Metadata $metadata;
    public readonly PartnerLinks $partnerLinks;

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
        /** Send `Prefer: strict-unknown-fields` on writes: an undeclared body field is refused (400 unknown_field). */
        private readonly bool $strictUnknownFields = false,
        /** `x25519:<base64 public key>`: once-shown secrets come back sealed (`sealed_secret`). */
        private readonly ?string $secretRecipient = null,
        /** @var array<string, string> Extra headers on every request, e.g. ['Idempotency-Key' => '<ref>:<run>']. */
        private readonly array $extraHeaders = [],
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
        $this->rightsPage = new RightsPage($this);
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
        $this->rightsLinks = new RightsLinks($this);
        $this->rightsCaptcha = new RightsCaptcha($this);
        $this->consentSyncSecret = new ConsentSyncSecret($this);
        $this->preferenceCenter = new PreferenceCenter($this);
        $this->rightsIdentity = new RightsIdentity($this);
        $this->dsarSettings = new DsarSettings($this);
        $this->integrations = new Integrations($this);
        $this->org = new Org($this);
        $this->assets = new Assets($this);
        $this->metadata = new Metadata($this);
        $this->partnerLinks = new PartnerLinks($this);
    }

    /**
     * `?metadata[k]=v` tag filters for list endpoints.
     *
     * @param array<string, string> $metadata
     */
    public static function metadataQuery(array $metadata): string
    {
        if ($metadata === []) {
            return '';
        }
        $parts = [];
        foreach ($metadata as $k => $v) {
            $parts[] = 'metadata%5B' . rawurlencode((string) $k) . '%5D=' . rawurlencode((string) $v);
        }

        return '?' . implode('&', $parts);
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
     * GET /v1/orgs — every organisation on this key's account, each with its effective
     * plan, planSource (org = its own subscription, account = covered by a higher plan
     * another org on the account pays for) and subscribedPlan, plus orgLimit and upgradeUrl.
     * Needs an unscoped key that is not locked to properties.
     *
     * @return array<string, mixed> { orgs, orgLimit: { max, plan, used }, upgradeUrl }
     */
    /** @param array<string, string> $metadata keep only organisations with these tags */
    public function listOrgs(array $metadata = []): array
    {
        return $this->requestJson('GET', '/v1/orgs' . self::metadataQuery($metadata));
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
    /**
     * @param array{mintKey?: bool, keyScopes?: list<string>, keyExpiresInDays?: int, keyOwner?: string, externalRef?: string, metadata?: array<string, mixed>} $opts
     */
    public function createOrg(string $name, array $opts = []): array
    {
        return $this->requestJson('POST', '/v1/orgs', ['name' => $name] + $opts);
    }

    /**
     * Remove a SANDBOX organisation of this account — DELETE /v1/orgs/{id}?hard=1. Only one created
     * with metadata.forgedeck_sandbox; anything else is 403 not_a_sandbox.
     *
     * @return array<string, mixed>
     */
    public function deleteSandboxOrg(string $id): array
    {
        return $this->requestJson('DELETE', '/v1/orgs/' . rawurlencode($id) . '?hard=1');
    }

    /**
     * What `forgedeck env` writes for the product provisioned with this ref — GET /v1/provision/env:
     * names, non-secret values, and references for secrets.
     *
     * @return array<string, mixed>
     */
    public function provisionEnv(string $ref): array
    {
        return $this->requestJson('GET', '/v1/provision/env?ref=' . rawurlencode($ref));
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
     * What the org may use, from its plan — GET /v1/entitlements: every feature as
     * { key, enabled } and every limit as limits.<name> with limit (null = unlimited) and used
     * where metered. Any key may read it.
     *
     * @return array<string, mixed> { orgId, plan, planSource, subscribedPlan, entitlements, generatedAt }
     */
    public function entitlements(): array
    {
        return $this->requestJson('GET', '/v1/entitlements');
    }

    /**
     * Every link a site's privacy wiring depends on, one row each with `ok` (null = could not
     * check) and a `fixHint` — GET /v1/integration-health. $cbid may be omitted with one site.
     *
     * @return array<string, mixed> { cbid, checkedAt, ok, counts, checks }
     */
    public function integrationHealth(?string $cbid = null): array
    {
        return $this->requestJson('GET', '/v1/integration-health' . ($cbid !== null ? '?cbid=' . rawurlencode($cbid) : ''));
    }

    /**
     * The site's embed manifest — GET /v1/embed?cbid=, the tenant variant of
     * /.well-known/forgedeck-embed.json: every origin by CSP directive and every browser key the
     * embed writes for this site's config. Public: no key is needed.
     *
     * @return array<string, mixed> { schema, product, updated, checkers, surfaces, changes, tenant }
     */
    public function embedManifest(string $cbid): array
    {
        return $this->requestJson('GET', '/v1/embed?cbid=' . rawurlencode($cbid));
    }

    /**
     * Wire a product's privacy in one call — POST /v1/provision. Idempotent; $dryRun writes
     * nothing anywhere. Secrets in $input are used for this request only; none is returned.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed> { dryRun, product, cbid, steps, summary }
     */
    public function provision(array $input, bool $dryRun = false, ?string $fromStep = null): array
    {
        $q = array_filter(['dryRun' => $dryRun ? '1' : null, 'fromStep' => $fromStep], static fn ($v) => $v !== null);
        return $this->requestJson('POST', '/v1/provision' . ($q ? '?' . http_build_query($q) : ''), $input);
    }

    /**
     * The organisation's audit log, newest first — GET /v1/audit. Sign-ins aside, every
     * administrative change made in the dashboard or through the API, with who made it;
     * API actions are attributed to `apikey:<prefix>`. Requires an unscoped key that is not
     * property-locked.
     *
     * @return array<string, mixed>
     */
    public function audit(?int $limit = null, ?string $actor = null): array
    {
        return $this->requestJson('GET', '/v1/audit', null, ['limit' => $limit, 'actor' => $actor]);
    }

    /** GET /v1/audit/sink — where the audit log is streamed as CloudEvents (never the secret). */
    public function getAuditSink(): array
    {
        return $this->requestJson('GET', '/v1/audit/sink');
    }

    /**
     * PUT /v1/audit/sink {type: "webhook", url, secret?}. A generated secret is returned once.
     *
     * @param array<string, mixed> $input
     */
    public function setAuditSink(array $input): array
    {
        return $this->requestJson('PUT', '/v1/audit/sink', $input);
    }

    /** DELETE /v1/audit/sink — stop the audit-log export. */
    public function removeAuditSink(): void
    {
        $this->request('DELETE', '/v1/audit/sink');
    }

    /** GET /v1/integration-health/waivers — checks the org chose not to meet. */
    public function healthWaivers(?string $cbid = null): array
    {
        return $this->requestJson('GET', '/v1/integration-health/waivers', null, ['cbid' => $cbid]);
    }

    /**
     * PUT /v1/integration-health/waivers {cbid?, check, subject?, reason, until?}. Audited.
     *
     * @param array<string, mixed> $input
     */
    public function setHealthWaiver(array $input): array
    {
        return $this->requestJson('PUT', '/v1/integration-health/waivers', $input);
    }

    /** DELETE /v1/integration-health/waivers — the check reports its real state again. */
    public function removeHealthWaiver(string $check, ?string $cbid = null, ?string $subject = null): void
    {
        $this->request('DELETE', '/v1/integration-health/waivers', null, ['check' => $check, 'cbid' => $cbid, 'subject' => $subject]);
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
     * @param array<string, mixed>|\stdClass|null             $body  A \stdClass is sent as a JSON object even when empty.
     * @param array<string, int|string|bool|null>|null        $query
     * @param array<string, string>                           $extraHeaders e.g. Idempotency-Key.
     *
     * @return array<string, mixed>
     */
    public function requestJson(string $method, string $path, array|\stdClass|null $body = null, ?array $query = null, array $extraHeaders = []): array
    {
        $result = $this->request($method, $path, $body, $query, false, $extraHeaders);

        return is_array($result) ? $result : [];
    }

    /**
     * Perform a request against the API.
     *
     * @param array<string, mixed>|\stdClass|null      $body  JSON request body, or null for none (a \stdClass is always an object).
     * @param array<string, int|string|bool|null>|null $query Query parameters (null values dropped).
     * @param bool                                     $raw   When true, return the raw response body as a string.
     * @param array<string, string>                    $extraHeaders Extra request headers, e.g. Idempotency-Key.
     *
     * @return array<mixed>|string|null Decoded JSON (array), the raw string when $raw, or null for 204/empty.
     *
     * @throws ApiException     On any non-2xx response.
     * @throws TransportException On a transport-level failure.
     */
    public function request(string $method, string $path, array|\stdClass|null $body = null, ?array $query = null, bool $raw = false, array $extraHeaders = []): array|string|null
    {
        $url = $this->baseUrl . $path . $this->buildQuery($query);

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ] + $extraHeaders;
        if ($this->strictUnknownFields && strtoupper($method) !== 'GET') {
            $headers['Prefer'] = 'strict-unknown-fields';
        }
        if ($this->secretRecipient !== null && strtoupper($method) !== 'GET') {
            $headers['X-Secret-Recipient'] = $this->secretRecipient;
        }
        foreach ($this->extraHeaders as $k => $v) {
            $headers[$k] = $v;
        }

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
