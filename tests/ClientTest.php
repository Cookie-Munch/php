<?php

declare(strict_types=1);

namespace CookieMunch\Tests;

use CookieMunch\ApiException;
use CookieMunch\Client;
use CookieMunch\Http\CallableTransport;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /** @var list<array{method: string, url: string, headers: array<string,string>, body: ?string}> */
    private array $requests = [];

    /**
     * Build a client whose transport records every request and returns a canned
     * response, so no network is ever touched.
     *
     * @param callable(array{method: string, url: string, headers: array<string,string>, body: ?string}): array<string,mixed> $responder
     */
    private function client(callable $responder, string $apiKey = 'fck_test_123'): Client
    {
        $this->requests = [];
        $transport = new CallableTransport(function (string $method, string $url, array $headers, ?string $body) use ($responder): array {
            $record = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
            $this->requests[] = $record;

            return $responder($record);
        });

        return new Client($apiKey, 'https://api.example.test', $transport);
    }

    public function testSendsBearerAndApiKeyAuthHeaders(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"orgId":"org_1","plan":"pro","keyPrefix":"fck_test"}']);

        $identity = $client->me();

        $this->assertSame('org_1', $identity['orgId']);
        $this->assertSame('pro', $identity['plan']);

        $headers = $this->requests[0]['headers'];
        $this->assertSame('Bearer fck_test_123', $headers['Authorization']);
        $this->assertSame('fck_test_123', $headers['X-API-Key']);
        $this->assertSame('application/json', $headers['Accept']);
        $this->assertStringStartsWith('cookiemunch-php/', $headers['User-Agent']);
    }

    public function testGetRequestHitsExpectedUrlAndParsesJsonArray(): void
    {
        $client = $this->client(fn () => [
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '[{"cbid":"cb_1","orgId":"org_1","domain":"example.com","verified":true,"verifyToken":"t"}]',
        ]);

        $sites = $client->sites->list();

        $this->assertSame('GET', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/sites', $this->requests[0]['url']);
        $this->assertCount(1, $sites);
        $this->assertSame('cb_1', $sites[0]['cbid']);
        $this->assertTrue($sites[0]['verified']);
    }

    public function testGetWithQueryParamsEncodesWindowBounds(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '[]']);

        $client->consent->log('cb 1', ['from' => 100, 'to' => 200, 'limit' => 5]);

        $url = $this->requests[0]['url'];
        $this->assertStringContainsString('/v1/sites/cb%201/consent/log', $url);
        $this->assertStringContainsString('from=100', $url);
        $this->assertStringContainsString('to=200', $url);
        $this->assertStringContainsString('limit=5', $url);
    }

    public function testLogConsentPostsToPublicIngestEndpointAndReturnsVoidOn204(): void
    {
        $client = $this->client(function (array $req): array {
            $this->assertNotNull($req['body']);
            $decoded = json_decode((string) $req['body'], true);
            $this->assertSame('cb_1', $decoded['cbid']);
            $this->assertTrue($decoded['choices']['statistics']);

            return ['status' => 204, 'body' => ''];
        });

        $client->logConsent([
            'cbid' => 'cb_1',
            'stamp' => 'stamp_1',
            'choices' => ['preferences' => false, 'statistics' => true, 'marketing' => false],
            'method' => 'explicit',
            'ver' => 1,
            'utc' => 1700000000000,
            'url' => 'https://example.com/',
        ]);

        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/api/v1/consent', $this->requests[0]['url']);
        $this->assertSame('application/json', $this->requests[0]['headers']['Content-Type']);
    }

    public function testConsentResourceIngestAliasWorks(): void
    {
        $client = $this->client(fn () => ['status' => 204, 'body' => '']);

        $client->consent->ingest(['cbid' => 'cb_1', 'choices' => ['preferences' => true]]);

        $this->assertSame('https://api.example.test/api/v1/consent', $this->requests[0]['url']);
    }

    public function testLogConsentOmitsSubjectIdWhenAbsent(): void
    {
        $client = $this->client(fn () => ['status' => 204, 'body' => '']);

        $client->logConsent(['cbid' => 'cb_1', 'choices' => ['preferences' => true]]);

        $decoded = json_decode((string) $this->requests[0]['body'], true);
        $this->assertArrayNotHasKey('subjectId', $decoded);
    }

    public function testLogConsentIncludesSubjectIdWhenSet(): void
    {
        $client = $this->client(fn () => ['status' => 204, 'body' => '']);

        $client->logConsent([
            'cbid' => 'cb_1',
            'choices' => ['preferences' => true],
            'subjectId' => 'user-42',
        ]);

        $decoded = json_decode((string) $this->requests[0]['body'], true);
        $this->assertSame('user-42', $decoded['subjectId']);
    }

    public function testErrorMappingParsesErrorAndCodeFields(): void
    {
        $client = $this->client(fn () => [
            'status' => 409,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{"error":"Still assigned to one or more sites","code":"banner_in_use","cbids":["cb_1"]}',
        ]);

        try {
            $client->banners->delete('bn_1');
            $this->fail('expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame(409, $e->getStatus());
            $this->assertSame('Still assigned to one or more sites', $e->getMessage());
            $this->assertSame('banner_in_use', $e->errorCode);
            $this->assertSame('banner_in_use', $e->getErrorCode());
            $this->assertStringContainsString('banner_in_use', $e->getBody());
        }
    }

    public function testErrorMappingFallsBackWhenBodyIsNotJson(): void
    {
        $client = $this->client(fn () => ['status' => 500, 'body' => 'Internal Server Error']);

        try {
            $client->sites->get('cb_1');
            $this->fail('expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(500, $e->status);
            $this->assertSame('request failed with status 500', $e->getMessage());
            $this->assertNull($e->errorCode);
            $this->assertSame('Internal Server Error', $e->getBody());
        }
    }

    public function testCsvExportReturnsRawStringBody(): void
    {
        $csv = "stamp,region\nabc,eu\n";
        $client = $this->client(fn () => [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/csv; charset=utf-8'],
            'body' => $csv,
        ]);

        $out = $client->consent->export('cb_1', ['from' => 1, 'to' => 2]);

        $this->assertSame($csv, $out);
        $this->assertStringContainsString('/v1/sites/cb_1/consent/export', $this->requests[0]['url']);
    }

    public function testDeleteReturnsVoidAndUsesDeleteVerb(): void
    {
        $client = $this->client(fn () => ['status' => 204, 'body' => '']);

        $client->sites->delete('cb_1');

        $this->assertSame('DELETE', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/sites/cb_1', $this->requests[0]['url']);
    }

    public function testBaseUrlTrailingSlashesAreTrimmed(): void
    {
        $transport = new CallableTransport(function (string $m, string $url) : array {
            $this->requests[] = ['method' => $m, 'url' => $url, 'headers' => [], 'body' => null];

            return ['status' => 200, 'body' => '{}'];
        });
        $client = new Client('fck_x', 'https://api.example.test///', $transport);

        $client->me();

        $this->assertSame('https://api.example.test/v1/me', $this->requests[0]['url']);
    }
}
