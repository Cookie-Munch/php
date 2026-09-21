<?php

declare(strict_types=1);

namespace CookieMunch\Tests;

use CookieMunch\Client;
use CookieMunch\Http\CallableTransport;
use PHPUnit\Framework\TestCase;

/** The shapes that matter on the platform surface: what is sent, and what comes back. */
final class PlatformTest extends TestCase
{
    /** @var list<array{method: string, url: string, body: mixed}> */
    private array $calls = [];

    private function client(int $status = 200, string $body = '{}', string $contentType = 'application/json'): Client
    {
        $this->calls = [];
        $transport = new CallableTransport(function (string $method, string $url, array $headers, ?string $b) use ($status, $body, $contentType): array {
            $this->calls[] = ['method' => $method, 'url' => $url, 'body' => $b === null ? null : json_decode($b, true)];
            return ['status' => $status, 'headers' => ['Content-Type' => $contentType], 'body' => $body];
        });

        return new Client('fck_test', 'https://api.example.test', $transport);
    }

    public function testDeprovisionSuspendsByDefaultAndPurgesOnlyWhenAsked(): void
    {
        $c = $this->client(204, '');
        $c->reseller->deprovision('c1');
        $c->reseller->deprovision('c1', purge: true);
        $this->assertSame('https://api.example.test/v1/reseller/customers/c1', $this->calls[0]['url']);
        $this->assertSame('https://api.example.test/v1/reseller/customers/c1?purge=true', $this->calls[1]['url']);
    }

    public function testClearingDsarRoutingSendsAnExplicitNull(): void
    {
        $c = $this->client();
        $c->reseller->update('c1', ['dsarRouting' => null]);
        $this->assertSame(['dsarRouting' => null], $this->calls[0]['body']);
    }

    public function testIssueSendsLeastPrivilegeFields(): void
    {
        $c = $this->client();
        $c->keys->issue(['name' => 'agency', 'scopes' => ['consent:read'], 'cbids' => ['cb_shop'], 'expiresInDays' => 30]);
        $this->assertSame(['name' => 'agency', 'scopes' => ['consent:read'], 'cbids' => ['cb_shop'], 'expiresInDays' => 30], $this->calls[0]['body']);
    }

    public function testPolicyIsMarkdownWithOptionsInTheQuery(): void
    {
        $c = $this->client(200, '# Privacy policy', 'text/markdown');
        $md = $c->sites->policy('s1', ['contactEmail' => 'dpo@x.com', 'jurisdictions' => ['gdpr', 'ccpa']]);
        $this->assertSame('# Privacy policy', $md);
        $this->assertSame('https://api.example.test/v1/sites/s1/policy?contactEmail=dpo%40x.com&jurisdictions=gdpr%2Cccpa', $this->calls[0]['url']);
    }

    public function testRopaExportAndDsarNoticeAreText(): void
    {
        $this->assertSame("a,b\n", $this->client(200, "a,b\n", 'text/csv')->ropa->exportCsv());
        $this->assertSame('Dear subject', $this->client(200, 'Dear subject', 'text/plain')->dsar->response('d1'));
    }

    public function testIdentifiersTravelInTheBodyNeverTheUrl(): void
    {
        $c = $this->client();
        $ids = [['space' => 'email_sha256', 'value' => 'abc']];
        $c->identity->resolve($ids);
        $c->vault->current($ids);
        $c->profile->activate($ids, 'marketing');
        foreach ($this->calls as $call) {
            $this->assertSame('POST', $call['method']);
            $this->assertStringNotContainsString('abc', $call['url']);
            $this->assertSame($ids, $call['body']['identifiers']);
        }
    }
}
