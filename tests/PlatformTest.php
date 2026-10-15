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

    /** Numestri F-01: per-language purposes, and dryRun on every declaration write. */
    public function testDeclaredCookiesPurposesAndDryRun(): void
    {
        $c = $this->client();
        $c->sites->declareCookie('cb', 'sid', ['category' => 'necessary', 'purpose' => 'Signed in.', 'purposes' => ['es' => 'Sesión.']]);
        $c->sites->declareCookie('cb', 'sid', ['category' => 'necessary', 'purpose' => 'Signed in.'], true);
        $c->sites->declareCookies('cb', [['name' => 'a', 'category' => 'necessary', 'purpose' => 'A']], true, true);
        $out = $c->sites->undeclareCookie('cb', 'sid', true);
        $this->assertSame(
            [
                ['PUT', 'https://api.example.test/v1/sites/cb/cookies/sid'],
                ['PUT', 'https://api.example.test/v1/sites/cb/cookies/sid?dryRun=1'],
                ['PUT', 'https://api.example.test/v1/sites/cb/cookies?dryRun=1'],
                ['DELETE', 'https://api.example.test/v1/sites/cb/cookies/sid?dryRun=1'],
            ],
            array_map(fn ($x) => [$x['method'], $x['url']], $this->calls),
        );
        $this->assertSame(['es' => 'Sesión.'], $this->calls[0]['body']['purposes']);
        $this->assertTrue($this->calls[2]['body']['replace']);
        $this->assertSame([], $out);

        $none = $this->client(204, '');
        $this->assertNull($none->sites->undeclareCookie('cb', 'sid'));
        $this->assertSame('https://api.example.test/v1/sites/cb/cookies/sid', $this->calls[0]['url']);
    }

    public function testOrgMailProvider(): void
    {
        $c = $this->client();
        $c->org->mailProvider();
        $c->org->setMailProvider(['provider' => 'resend', 'fromEmail' => 'p@acme.com', 'secret' => 're_x']);
        $c->org->testMailProvider('me@acme.com');
        $c->org->testMailProvider();
        $c->org->removeMailProvider();
        $this->assertSame(
            [
                ['GET', 'https://api.example.test/v1/org/mail-provider'],
                ['PUT', 'https://api.example.test/v1/org/mail-provider'],
                ['POST', 'https://api.example.test/v1/org/mail-provider/test'],
                ['POST', 'https://api.example.test/v1/org/mail-provider/test'],
                ['DELETE', 'https://api.example.test/v1/org/mail-provider'],
            ],
            array_map(fn ($x) => [$x['method'], $x['url']], $this->calls),
        );
        $this->assertSame(['provider' => 'resend', 'fromEmail' => 'p@acme.com', 'secret' => 're_x'], $this->calls[1]['body']);
        $this->assertSame(['to' => 'me@acme.com'], $this->calls[2]['body']);
        $this->assertSame([], $this->calls[3]['body']);
    }

    public function testRightsCaptcha(): void
    {
        $c = $this->client();
        $c->rightsCaptcha->get('s 1');
        $c->rightsCaptcha->set('s1', ['mode' => 'own', 'provider' => 'hcaptcha', 'siteKey' => 'k-12345678', 'secret' => 'sec']);
        $c->rightsCaptcha->remove('s1');
        $c->org->rightsCaptcha();
        $c->org->setRightsCaptcha(['mode' => 'off']);
        $c->org->removeRightsCaptcha();
        $this->assertSame(
            [
                ['GET', 'https://api.example.test/v1/sites/s%201/rights-captcha'],
                ['PUT', 'https://api.example.test/v1/sites/s1/rights-captcha'],
                ['DELETE', 'https://api.example.test/v1/sites/s1/rights-captcha'],
                ['GET', 'https://api.example.test/v1/org/rights-captcha'],
                ['PUT', 'https://api.example.test/v1/org/rights-captcha'],
                ['DELETE', 'https://api.example.test/v1/org/rights-captcha'],
            ],
            array_map(fn ($x) => [$x['method'], $x['url']], $this->calls),
        );
        $this->assertSame(['mode' => 'own', 'provider' => 'hcaptcha', 'siteKey' => 'k-12345678', 'secret' => 'sec'], $this->calls[1]['body']);
        $this->assertSame(['mode' => 'off'], $this->calls[4]['body']);
    }

    public function testRightsIdentity(): void
    {
        $c = $this->client();
        $c->rightsIdentity->get('s 1');
        $c->rightsIdentity->set('s1', ['type' => 'clerk', 'issuer' => 'https://clerk.acme.example', 'authorizedParties' => ['https://www.acme.example']]);
        $c->rightsIdentity->remove('s1');
        $c->org->rightsIdentity();
        $c->org->setRightsIdentity(['type' => 'oidc_jwt', 'issuer' => 'https://id.acme.example', 'jwksUrl' => 'https://id.acme.example/keys']);
        $c->org->removeRightsIdentity();
        $this->assertSame(
            [
                ['GET', 'https://api.example.test/v1/sites/s%201/rights-identity'],
                ['PUT', 'https://api.example.test/v1/sites/s1/rights-identity'],
                ['DELETE', 'https://api.example.test/v1/sites/s1/rights-identity'],
                ['GET', 'https://api.example.test/v1/org/rights-identity'],
                ['PUT', 'https://api.example.test/v1/org/rights-identity'],
                ['DELETE', 'https://api.example.test/v1/org/rights-identity'],
            ],
            array_map(fn ($x) => [$x['method'], $x['url']], $this->calls),
        );
        $this->assertSame(['type' => 'clerk', 'issuer' => 'https://clerk.acme.example', 'authorizedParties' => ['https://www.acme.example']], $this->calls[1]['body']);
        $this->assertSame(['type' => 'oidc_jwt', 'issuer' => 'https://id.acme.example', 'jwksUrl' => 'https://id.acme.example/keys'], $this->calls[4]['body']);
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

    public function testRightsPageDomains(): void
    {
        $c = $this->client();
        $c->rightsPage->listDomains('s 1');
        $c->rightsPage->addDomain('s', 'privacy.acme.com');
        $c->rightsPage->verifyDomain('s', 'privacy.acme.com');
        $c->rightsPage->removeDomain('s', 'privacy.acme.com');
        $base = 'https://api.example.test/v1/sites';
        $this->assertSame(['GET', 'POST', 'POST', 'DELETE'], array_column($this->calls, 'method'));
        $this->assertSame($base . '/s%201/rights-page/domains', $this->calls[0]['url']);
        $this->assertSame(['hostname' => 'privacy.acme.com'], $this->calls[1]['body']);
        $this->assertSame($base . '/s/rights-page/domains/privacy.acme.com/verify', $this->calls[2]['url']);
        $this->assertSame($base . '/s/rights-page/domains/privacy.acme.com', $this->calls[3]['url']);
    }

    public function testExecutorWebhookCheckDeliveriesAndDryRuns(): void
    {
        $c = $this->client();
        $c->fulfillment->testExecutorWebhook('ex 1', ['x-sig' => 'v1,abc'], '{"a":1}');
        $c->fulfillment->executorWebhookDeliveries('ex 1');
        $c->fulfillment->connectExecutor('crm', 'https://crm.example', 'sk_12345678', ['open' => []], dryRun: true);
        $c->fulfillment->updateExecutor('ex 1', ['auto' => false], true);
        $c->fulfillment->updateExecutor('ex 1', ['auto' => false]);
        $this->assertSame(
            [
                ['POST', 'https://api.example.test/v1/dsar/executors/ex%201/webhook/test'],
                ['GET', 'https://api.example.test/v1/dsar/executors/ex%201/webhook/deliveries'],
                ['POST', 'https://api.example.test/v1/dsar/executors?dryRun=1'],
                ['PATCH', 'https://api.example.test/v1/dsar/executors/ex%201?dryRun=1'],
                ['PATCH', 'https://api.example.test/v1/dsar/executors/ex%201'],
            ],
            array_map(fn ($x) => [$x['method'], $x['url']], $this->calls),
        );
        $this->assertSame(['headers' => ['x-sig' => 'v1,abc'], 'body' => '{"a":1}'], $this->calls[0]['body']);
    }
}
