<?php

declare(strict_types=1);

namespace CookieMunch\Http;

/**
 * Wraps a plain callable as a {@see Transport} — the simplest way to inject a
 * mock HTTP layer in tests so no network is hit.
 *
 * The callable receives ($method, $url, $headers, $body) and must return either
 * a {@see Response} or an array shaped
 * `['status' => int, 'headers' => array<string,string>, 'body' => string]`.
 *
 *   $transport = new CallableTransport(function (string $m, string $url, array $h, ?string $b): array {
 *       return ['status' => 200, 'body' => '{"ok":true}'];
 *   });
 *   $client = new Client('fck_test', 'https://api.example.com', $transport);
 */
final class CallableTransport implements Transport
{
    /** @var callable(string, string, array<string,string>, ?string): (Response|array<string,mixed>) */
    private $handler;

    /**
     * @param callable(string, string, array<string,string>, ?string): (Response|array<string,mixed>) $handler
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $result = ($this->handler)($method, $url, $headers, $body);
        if ($result instanceof Response) {
            return $result;
        }

        /** @var array<string, mixed> $result */
        $status = isset($result['status']) ? (int) $result['status'] : 200;
        /** @var array<string, string> $respHeaders */
        $respHeaders = isset($result['headers']) && is_array($result['headers']) ? $result['headers'] : [];
        $respBody = isset($result['body']) ? (string) $result['body'] : '';

        $lowered = [];
        foreach ($respHeaders as $k => $v) {
            $lowered[strtolower((string) $k)] = (string) $v;
        }

        return new Response($status, $lowered, $respBody);
    }
}
