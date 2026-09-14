# Cookie Munch PHP SDK

A small, dependency-free PHP client for the [Cookie Munch](https://cookiemunch.net) Developer API (the `/v1` surface) plus the public consent-ingest endpoint.

- Modern PHP (**8.1+**), PSR-4, no third-party runtime dependencies (uses `ext-curl`).
- The organisation is derived server-side from the API key — you never pass an `orgId`.
- Both `Authorization: Bearer <key>` and `X-API-Key: <key>` are sent on every request.
- Every non-2xx response throws `CookieMunch\ApiException` (carrying the status, raw body, and any `code`).
- The HTTP transport is injectable, so it is trivial to unit-test with no network.

## Install

```bash
composer require cookiemunch/cookiemunch
```

Or drop the package in and use Composer's autoloader:

```php
require 'vendor/autoload.php';
```

## Quick start

```php
use CookieMunch\Client;

$cm = new CookieMunch\Client('fck_live_...');            // default base: https://api.cookiemunch.net
// $cm = new CookieMunch\Client('fck_live_...', 'https://cmp.example.com');

$me = $cm->me();                                          // GET /v1/me → { orgId, plan, keyPrefix }

$sites = $cm->sites->list();                              // GET /v1/sites
$site  = $cm->sites->create(['domain' => 'example.com']); // POST /v1/sites
$cbid  = $site['cbid'];

$stats = $cm->consent->stats($cbid, ['from' => 0, 'to' => time() * 1000]);
$csv   = $cm->consent->export($cbid);                     // raw CSV string
```

Responses are returned as associative arrays (decoded JSON). Raw responses (the CSV export) are returned as strings.

## Logging consent server-side

`logConsent()` posts to the public `POST /api/v1/consent` endpoint — the same high-volume write the browser embed makes. It returns nothing (HTTP 204) and throws `ApiException` on failure.

```php
$cm->logConsent([
    'cbid'    => $cbid,
    'stamp'   => bin2hex(random_bytes(16)),
    'choices' => ['preferences' => true, 'statistics' => true, 'marketing' => false],
    'method'  => 'explicit',           // "explicit" | "implied"
    'ver'     => 1,
    'utc'     => (int) (microtime(true) * 1000),
    'url'     => 'https://example.com/checkout',
    // optional: tcString, gppString, purposes, subjectPolicyHash, purposeRopa, variant
]);

// Same call, via the resource group:
$cm->consent->ingest([...]);
```

## Error handling

```php
use CookieMunch\ApiException;

try {
    $cm->banners->delete($id);
} catch (ApiException $e) {
    $e->getStatus();     // e.g. 409
    $e->getMessage();    // server "error" field, when present
    $e->getErrorCode();  // server "code" field, e.g. "banner_in_use"
    $e->getBody();       // raw response body
}
```

`CookieMunch\TransportException` is thrown for connection-level failures (DNS, timeout, TLS) — i.e. when no HTTP status was received.

## Resource groups

| Group | Methods |
|---|---|
| `$cm->me()` / `$cm->usage()` | identity; org usage |
| `$cm->sites` | `list`, `create`, `get`, `delete`, `getConfig`, `putConfig`, `cookies`, `scan`, `scanStatus`, `ab`, `snippet`, `verify`, `brand`, `getFlow`, `editFlow`, `setFlow`, `enableAdPersonalization` |
| `$cm->consent` | `ingest`, `stats`, `log`, `export`, `receipt`, `eraseSubject`, `exportSubject` |
| `$cm->dsar` | `list`, `create`, `advance` |
| `$cm->vendors` | `list`, `create` |
| `$cm->ropa` | `list`, `create` |
| `$cm->brandKits` | `list`, `create`, `delete` |
| `$cm->preferences` | `list`, `save` |
| `$cm->members` | `list`, `invite`, `setRole`, `remove` |
| `$cm->keys` | `list`, `issue` |
| `$cm->webhooks` | `list`, `create`, `delete` |
| `$cm->banners` | `list`, `create`, `get`, `update`, `delete`, `assignments`, `setAssignments`, `publish` |

> Field-shape note: where the TypeScript SDK and the server's OpenAPI schema disagree, this SDK follows the **OpenAPI** schema (the authoritative wire contract). Notably `usage()` returns `{ domains, seats, monthlyEvents }`; A/B rows are `{ variant, impressions, optIns, optInRate }`; members are `{ userId, email, role }`; `keys->list()` returns `{ prefix, createdAt }` rows and `keys->issue()` returns `{ key, prefix }`.

## Custom / mock transport

Inject any `CookieMunch\Http\Transport`. `CallableTransport` wraps a plain closure — ideal for tests:

```php
use CookieMunch\Client;
use CookieMunch\Http\CallableTransport;

$transport = new CallableTransport(function (string $method, string $url, array $headers, ?string $body): array {
    return ['status' => 200, 'headers' => ['Content-Type' => 'application/json'], 'body' => '{"orgId":"org_1"}'];
});

$cm = new Client('fck_test', 'https://api.example.test', $transport);
```

Tune the default curl transport (timeouts) by constructing it yourself:

```php
use CookieMunch\Http\CurlTransport;

$cm = new Client('fck_live_...', 'https://api.cookiemunch.net', new CurlTransport(timeout: 15, connectTimeout: 5));
```

## Development

```bash
composer install
composer test          # or: vendor/bin/phpunit

# No Composer? Zero-dependency fallback runner:
php tests/run.php
```

## License

MIT

## Publishing (maintainers)

This package is distributed via [Packagist](https://packagist.org). Packagist does
not host uploaded archives — it pulls tags directly from this repository. To keep it
in sync, submit the package once at https://packagist.org/packages/submit and enable
the **GitHub webhook** (Packagist → your profile → "Show API token", then add the
Packagist service hook to this repo, or install the Packagist GitHub app). After that,
every pushed git tag (e.g. `v0.1.0`) publishes a new version automatically. The
release-triggered `.github/workflows/publish.yml` pings the Packagist update API as a
backstop using the `PACKAGIST_USERNAME` and `PACKAGIST_API_TOKEN` repo secrets.
