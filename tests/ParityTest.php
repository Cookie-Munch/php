<?php

declare(strict_types=1);

namespace CookieMunch\Tests;

use CookieMunch\Client;
use CookieMunch\Http\CallableTransport;
use PHPUnit\Framework\TestCase;

/**
 * Every operation the Developer API documents is reachable from this SDK.
 *
 * The list lives in sdks/operations.json, generated from the server's OpenAPI document
 * and shared by all six server-side SDKs. This calls every public method of every
 * resource through a recording transport — arguments are placeholders derived from each
 * parameter's declared type — and checks what reached the wire, in both directions.
 */
final class ParityTest extends TestCase
{
    private const PLACEHOLDER = 'x1';

    /** Parameters whose array holds objects rather than strings. */
    private const OBJECT_LISTS = ['identifiers', 'decisions', 'topics', 'rules', 'systems', 'operations', 'requests', 'sites'];

    private static function placeholder(\ReflectionParameter $p): mixed
    {
        $type = $p->getType();
        $name = $type instanceof \ReflectionNamedType ? $type->getName() : 'string';
        return match ($name) {
            'bool' => true,
            'int' => 1,
            'array' => in_array($p->getName(), self::OBJECT_LISTS, true) ? [['a' => self::PLACEHOLDER]] : ['a' => self::PLACEHOLDER],
            default => self::PLACEHOLDER,
        };
    }

    public function testEveryDocumentedOperationIsReachable(): void
    {
        // ../../operations.json in the product repo; ../operations.json in the published SDK repo.
        $path = is_file(__DIR__ . '/../../operations.json')
            ? __DIR__ . '/../../operations.json'
            : __DIR__ . '/../operations.json';
        $operations = json_decode((string) file_get_contents($path), true)['operations'];
        $seen = [];
        $transport = new CallableTransport(function (string $method, string $url) use (&$seen): array {
            // The public consent ingest (/api/v1/consent) is not part of the Developer API.
            if (str_contains($url, '/api/v1/')) {
                return ['status' => 204, 'body' => ''];
            }
            $path = explode('/v1', explode('?', $url, 2)[0], 2)[1] ?? '';
            $path = implode('/', array_map(fn ($s) => $s === self::PLACEHOLDER ? '{}' : $s, explode('/', $path)));
            $seen["$method /v1$path"] = true;
            return ['status' => 200, 'headers' => ['Content-Type' => 'application/json'], 'body' => '{}'];
        });
        $client = new Client('fck_test', 'https://api.example.test', $transport);

        $targets = [$client];
        foreach ((new \ReflectionClass($client))->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $targets[] = $prop->getValue($client);
        }
        foreach ($targets as $target) {
            foreach ((new \ReflectionClass($target))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->isStatic() || $m->isConstructor() || str_starts_with($m->getName(), '__')) {
                    continue;
                }
                // The client's transport primitives take a raw path, not an operation.
                if ($target instanceof Client && in_array($m->getName(), ['request', 'requestJson', 'setUserAgent'], true)) {
                    continue;
                }
                $args = [];
                foreach ($m->getParameters() as $p) {
                    if ($p->isOptional()) {
                        break;
                    }
                    $args[] = self::placeholder($p);
                }
                try {
                    $m->invokeArgs($target, $args);
                } catch (\Throwable) {
                    // only what reached the wire matters here
                }
            }
        }

        $missing = array_values(array_diff($operations, array_keys($seen)));
        $this->assertSame([], $missing, count($missing) . " documented operations are unreachable");
        $this->assertSame([], array_values(array_diff(array_keys($seen), $operations)), 'calls the API does not document');
    }
}
