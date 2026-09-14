<?php

declare(strict_types=1);

/**
 * Zero-dependency test runner — a fallback for environments without Composer /
 * PHPUnit. It exercises the same core behaviours as ClientTest.php using an
 * injected mock transport, so no network is hit.
 *
 *   php tests/run.php
 *
 * Exits 0 when all assertions pass, 1 otherwise.
 */

require __DIR__ . '/bootstrap.php';

use CookieMunch\ApiException;
use CookieMunch\Client;
use CookieMunch\Http\CallableTransport;

$passed = 0;
$failed = 0;

function check(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  ok   $label\n";
    } else {
        $failed++;
        echo "  FAIL $label\n";
    }
}

/**
 * @param callable(array{method:string,url:string,headers:array<string,string>,body:?string}): array<string,mixed> $responder
 * @param array<int, array<string,mixed>> $captured
 */
function makeClient(callable $responder, array &$captured, string $apiKey = 'fck_test_123'): Client
{
    $transport = new CallableTransport(function (string $method, string $url, array $headers, ?string $body) use ($responder, &$captured): array {
        $record = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $captured[] = $record;

        return $responder($record);
    });

    return new Client($apiKey, 'https://api.example.test', $transport);
}

// 1. Auth headers (Bearer + X-API-Key) + a GET that parses JSON.
echo "auth headers + GET /v1/me\n";
$captured = [];
$client = makeClient(fn () => ['status' => 200, 'body' => '{"orgId":"org_1","plan":"pro","keyPrefix":"fck_test"}'], $captured);
$me = $client->me();
check(($me['orgId'] ?? null) === 'org_1', 'me() parses orgId');
check(($captured[0]['headers']['Authorization'] ?? null) === 'Bearer fck_test_123', 'sends Authorization: Bearer');
check(($captured[0]['headers']['X-API-Key'] ?? null) === 'fck_test_123', 'sends X-API-Key');
check(($captured[0]['url'] ?? null) === 'https://api.example.test/v1/me', 'hits /v1/me');

// 2. A list GET returning a JSON array + query encoding.
echo "GET /v1/sites + query params\n";
$captured = [];
$client = makeClient(fn () => ['status' => 200, 'headers' => ['Content-Type' => 'application/json'], 'body' => '[{"cbid":"cb_1","domain":"example.com"}]'], $captured);
$sites = $client->sites->list();
check(count($sites) === 1 && ($sites[0]['cbid'] ?? null) === 'cb_1', 'sites->list parses array');

$captured = [];
$client = makeClient(fn () => ['status' => 200, 'body' => '[]'], $captured);
$client->consent->log('cb 1', ['from' => 100, 'to' => 200, 'limit' => 5]);
$url = (string) $captured[0]['url'];
check(str_contains($url, '/v1/sites/cb%201/consent/log'), 'encodes cbid path segment');
check(str_contains($url, 'from=100') && str_contains($url, 'to=200') && str_contains($url, 'limit=5'), 'encodes query params');

// 3. POST consent-log to the public ingest endpoint (204 -> void).
echo "POST /api/v1/consent (logConsent)\n";
$captured = [];
$client = makeClient(function (array $req): array {
    $decoded = json_decode((string) $req['body'], true);
    check(($decoded['cbid'] ?? null) === 'cb_1', 'consent body carries cbid');
    check(($decoded['choices']['statistics'] ?? null) === true, 'consent body carries choices.statistics');

    return ['status' => 204, 'body' => ''];
}, $captured);
$client->logConsent([
    'cbid' => 'cb_1',
    'stamp' => 'stamp_1',
    'choices' => ['preferences' => false, 'statistics' => true, 'marketing' => false],
    'method' => 'explicit',
    'ver' => 1,
    'utc' => 1700000000000,
    'url' => 'https://example.com/',
]);
check(($captured[0]['method'] ?? null) === 'POST', 'logConsent uses POST');
check(($captured[0]['url'] ?? null) === 'https://api.example.test/api/v1/consent', 'logConsent hits /api/v1/consent');
check(($captured[0]['headers']['Content-Type'] ?? null) === 'application/json', 'logConsent sets Content-Type');

// 4. Error mapping — JSON error + code.
echo "error mapping\n";
$captured = [];
$client = makeClient(fn () => ['status' => 409, 'headers' => ['Content-Type' => 'application/json'], 'body' => '{"error":"Still assigned","code":"banner_in_use"}'], $captured);
$threw = false;
try {
    $client->banners->delete('bn_1');
} catch (ApiException $e) {
    $threw = true;
    check($e->status === 409, 'ApiException status is 409');
    check($e->getMessage() === 'Still assigned', 'ApiException message from error field');
    check($e->errorCode === 'banner_in_use', 'ApiException code from code field');
    check(str_contains($e->getBody(), 'banner_in_use'), 'ApiException retains raw body');
}
check($threw, 'non-2xx throws ApiException');

// 5. Error mapping — non-JSON body falls back gracefully.
$captured = [];
$client = makeClient(fn () => ['status' => 500, 'body' => 'Internal Server Error'], $captured);
$threw = false;
try {
    $client->sites->get('cb_1');
} catch (ApiException $e) {
    $threw = true;
    check($e->status === 500, 'non-JSON error keeps status 500');
    check($e->getMessage() === 'request failed with status 500', 'non-JSON error uses fallback message');
    check($e->errorCode === null, 'non-JSON error has null code');
}
check($threw, 'non-JSON non-2xx throws ApiException');

// 6. Raw CSV export returns the body string verbatim.
echo "CSV export (raw string)\n";
$captured = [];
$csv = "stamp,region\nabc,eu\n";
$client = makeClient(fn () => ['status' => 200, 'headers' => ['Content-Type' => 'text/csv; charset=utf-8'], 'body' => $csv], $captured);
$out = $client->consent->export('cb_1');
check($out === $csv, 'export returns raw CSV string');

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
