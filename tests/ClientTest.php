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

    public function testUserAgentDefaultsToSdkAndVersionAndCanBeOverridden(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{}']);
        $client->me();
        $this->assertSame('cookiemunch-php/' . Client::VERSION, $this->requests[0]['headers']['User-Agent']);

        $client->setUserAgent('acme/2');
        $client->me();
        $this->assertSame('acme/2', $this->requests[1]['headers']['User-Agent']);
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

    public function testOrgGetHitsExpectedUrl(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"id":"org_1","name":"Acme","plan":"pro","logoUrl":null}']);

        $org = $client->org->get();

        $this->assertSame('GET', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/org', $this->requests[0]['url']);
        $this->assertSame('Acme', $org['name']);
    }

    public function testOrgUpdateClearingLogoSendsExplicitNull(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"id":"org_1","name":"Acme","plan":"pro","logoUrl":null}']);

        $client->org->update(['logoUrl' => null]);

        $decoded = json_decode((string) $this->requests[0]['body'], true);
        $this->assertArrayHasKey('logoUrl', $decoded);
        $this->assertNull($decoded['logoUrl']);
    }

    public function testOrgUpdateWithoutLogoUrlOmitsTheKey(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"id":"org_1","name":"Acme","plan":"pro","logoUrl":null}']);

        $client->org->update(['name' => 'New Name']);

        $decoded = json_decode((string) $this->requests[0]['body'], true);
        $this->assertArrayNotHasKey('logoUrl', $decoded);
        $this->assertSame('New Name', $decoded['name']);
    }

    public function testAuditPassesLimitAsQueryParam(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"entries":[]}']);

        $client->audit(50);

        $this->assertSame('GET', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/audit?limit=50', $this->requests[0]['url']);
    }

    public function testAuditOmitsLimitWhenNotGiven(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"entries":[]}']);

        $client->audit();

        $this->assertSame('https://api.example.test/v1/audit', $this->requests[0]['url']);
    }

    public function testAssetsUploadPostsDataAndContentType(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"url":"https://cdn.example.test/a.png"}']);

        $result = $client->assets->upload(['data' => 'QUJD', 'contentType' => 'image/png']);

        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/assets', $this->requests[0]['url']);
        $decoded = json_decode((string) $this->requests[0]['body'], true);
        $this->assertSame('QUJD', $decoded['data']);
        $this->assertSame('image/png', $decoded['contentType']);
        $this->assertSame('https://cdn.example.test/a.png', $result['url']);
    }

    public function testKeysRollHitsExpectedUrl(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"key":"fck_new","prefix":"fck_new_pre"}']);

        $result = $client->keys->roll('fck_old_pre');

        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/keys/fck_old_pre/roll', $this->requests[0]['url']);
        $this->assertSame('fck_new', $result['key']);
    }

    public function testKeysUpdateSendsOnlyGivenFields(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"ok":true}']);

        $client->keys->update('fck_pre', ['name' => 'CI key']);

        $this->assertSame('PATCH', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/keys/fck_pre', $this->requests[0]['url']);
        $decoded = json_decode((string) $this->requests[0]['body'], true);
        $this->assertSame(['name' => 'CI key'], $decoded);
    }

    public function testWebhooksRollSecretHitsExpectedUrl(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"secret":"whsec_new"}']);

        $result = $client->webhooks->rollSecret('wh_1');

        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/webhooks/wh_1/roll', $this->requests[0]['url']);
        $this->assertSame('whsec_new', $result['secret']);
    }

    public function testWebhooksTestHitsExpectedUrl(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"ok":true,"status":200}']);

        $result = $client->webhooks->test('wh_1');

        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/webhooks/wh_1/test', $this->requests[0]['url']);
        $this->assertTrue($result['ok']);
    }

    public function testWebhooksDeadLettersHitsExpectedUrl(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"deadLetters":[]}']);

        $client->webhooks->deadLetters();

        $this->assertSame('GET', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/webhooks/dead-letters', $this->requests[0]['url']);
    }

    public function testWebhooksReplayDeadLetterHitsExpectedUrl(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{}']);

        $client->webhooks->replayDeadLetter('dl_1');

        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/webhooks/dead-letters/dl_1/replay', $this->requests[0]['url']);
    }

    public function testPreferencesGetHitsExpectedUrl(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"subjectId":"sub_1","purposes":{}}']);

        $result = $client->preferences->get('sub_1');

        $this->assertSame('GET', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/preferences/sub_1', $this->requests[0]['url']);
        $this->assertSame('sub_1', $result['subjectId']);
    }

    public function testDsarEraseSendsCbidAndStamp(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"erased":1,"encryptionEnabled":true,"request":{}}']);

        $client->dsar->erase('dsar_1', 'cb_1', 'stamp_1');

        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/dsar/dsar_1/erase', $this->requests[0]['url']);
        $decoded = json_decode((string) $this->requests[0]['body'], true);
        $this->assertSame(['cbid' => 'cb_1', 'stamp' => 'stamp_1'], $decoded);
    }

    public function testDsarExportSendsCbidAndStamp(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"records":[],"count":0,"request":{}}']);

        $client->dsar->export('dsar_1', 'cb_1', 'stamp_1');

        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/dsar/dsar_1/export', $this->requests[0]['url']);
        $decoded = json_decode((string) $this->requests[0]['body'], true);
        $this->assertSame(['cbid' => 'cb_1', 'stamp' => 'stamp_1'], $decoded);
    }

    public function testRightsLinksCreateSendsTheSubjectAndOptions(): void
    {
        $client = $this->client(fn () => ['status' => 201, 'body' => '{"url":"https://cookiemunch.net/r/cb_1?t=a.b","expiresAt":1}']);

        $out = $client->rightsLinks->create('cb_1', 'p@x.com', 'u-1', ['access'], 600);

        $this->assertSame('https://cookiemunch.net/r/cb_1?t=a.b', $out['url']);
        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/sites/cb_1/rights-links', $this->requests[0]['url']);
        $this->assertSame(['subject' => ['email' => 'p@x.com', 'userId' => 'u-1'], 'rights' => ['access'], 'ttlSeconds' => 600], json_decode((string) $this->requests[0]['body'], true));

        $client->rightsLinks->create('cb_1', 'p@x.com');
        $this->assertSame(['subject' => ['email' => 'p@x.com']], json_decode((string) $this->requests[1]['body'], true));
    }

    private const DEFAULT_DRAFT = '{"cbid":"cb_1","draft":{"v":1,"theme":{"preset":"brand","width":"normal","density":"comfortable","colorScheme":"light"},"sections":[{"id":"request","type":"form","props":{}}],"form":{"requestTypes":[{"id":"access","enabled":true,"maps":"access","verification":"email_code"}],"fields":[{"name":"details","type":"textarea","label":"","required":false,"max":5000}],"jurisdiction":"ask","consentIdField":"optional","success":{}},"i18n":{"defaultLocale":"en","autoDetect":true,"overrides":{}},"seo":{"index":false}},"version":0,"publishedAt":null,"updatedAt":null,"warnings":[]}';

    public function testRightsPageDefaultDraftRoundTripsWithItsEmptyObjects(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => self::DEFAULT_DRAFT]);
        $d = $client->rightsPage->get('cb_1');
        $client->rightsPage->put('cb_1', $d['draft'] + ['baseUpdatedAt' => $d['updatedAt']]);
        $sent = (string) $this->requests[1]['body'];
        $expected = json_decode(self::DEFAULT_DRAFT, false)->draft;
        $expected->baseUpdatedAt = null;
        $this->assertSame(json_encode($expected), $sent);
        $this->assertStringContainsString('"props":{}', $sent);
        $this->assertStringContainsString('"overrides":{}', $sent);
        $this->assertStringContainsString('"success":{}', $sent);
        // lists stay lists, even empty ones
        $client->rightsPage->patch('cb_1', ['sections' => [], 'seo' => [], 'i18n' => ['overrides' => ['de' => ['sections' => [], 'form' => ['fields' => []]]]]]);
        $this->assertSame('{"sections":[],"seo":{},"i18n":{"overrides":{"de":{"sections":{},"form":{"fields":{}}}}}}', (string) $this->requests[2]['body']);
        $client->rightsPage->dryRun('cb_1', ['sections' => [['id' => 'h', 'type' => 'hero', 'hidden' => [], 'style' => [], 'props' => ['title' => 'T', 'showLogo' => true]]]]);
        $this->assertSame('{"sections":[{"id":"h","type":"hero","hidden":{},"style":{},"props":{"title":"T","showLogo":true}}]}', (string) $this->requests[3]['body']);
        $this->assertSame('https://api.example.test/v1/sites/cb_1/rights-page?dryRun=1', $this->requests[3]['url']);
    }

    public function testRightsPageMapsKeyedByDigitOnlyIdsStayObjects(): void
    {
        // Section ids of the caller's choosing may be digits; json_decode makes them int keys 0, 1, ...
        // which json_encode would turn into a list (and the API answers 422).
        $client = $this->client(fn () => ['status' => 200, 'body' => '{}']);
        $patch = json_decode('{"i18n":{"overrides":{"de":{"sections":{"0":{"title":"A"},"1":{"title":"B"}},"form":{"fields":{"0":{"label":"L"}}}}}}}', true);
        $client->rightsPage->patch('cb_1', $patch);
        $this->assertSame('{"i18n":{"overrides":{"de":{"sections":{"0":{"title":"A"},"1":{"title":"B"}},"form":{"fields":{"0":{"label":"L"}}}}}}}', (string) $this->requests[0]['body']);
    }

    public function testRightsPagePublishAndRollbackSendBaseUpdatedAtIncludingNull(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{}']);
        $client->rightsPage->publish('cb_1');
        $client->rightsPage->publish('cb_1', 7);
        $client->rightsPage->publish('cb_1', null);
        $client->rightsPage->rollback('cb_1', 2);
        $client->rightsPage->rollback('cb_1', 2, '2026-10-14T00:00:00.000Z');
        $client->rightsPage->rollback('cb_1', 2, null);
        $bodies = array_map(fn ($r) => (string) $r['body'], $this->requests);
        $this->assertSame([
            '{}',
            '{"baseUpdatedAt":7}',
            '{"baseUpdatedAt":null}',
            '{"version":2}',
            '{"version":2,"baseUpdatedAt":"2026-10-14T00:00:00.000Z"}',
            '{"version":2,"baseUpdatedAt":null}',
        ], $bodies);
        $this->assertSame('https://api.example.test/v1/sites/cb_1/rights-page/rollback', $this->requests[3]['url']);
    }

    public function testLintConfigPostsToLintAndReplaceAddsTheFlag(): void
    {
        $client = $this->client(fn () => ['status' => 200, 'body' => '{"mode":"merged","designWarnings":[],"score":100,"suggestedReject":[]}']);
        $r = $client->sites->lintConfig('cb_1', ['banner' => ['type' => 'accept-only']]);
        $this->assertSame(100, $r['score']);
        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://api.example.test/v1/sites/cb_1/config/lint', $this->requests[0]['url']);
        $this->assertSame('{"banner":{"type":"accept-only"}}', (string) $this->requests[0]['body']);
        $client->sites->lintConfig('cb_1', ['v' => 2], true);
        $this->assertSame('https://api.example.test/v1/sites/cb_1/config/lint?replace=1', $this->requests[1]['url']);
    }
}
