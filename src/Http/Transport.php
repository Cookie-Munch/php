<?php

declare(strict_types=1);

namespace CookieMunch\Http;

use CookieMunch\TransportException;

/**
 * The pluggable HTTP layer. Implement this (or wrap a closure with
 * {@see CallableTransport}) to run the client against mocks in tests, or to
 * swap in a PSR-18 client / streams / a shared curl handle in production.
 */
interface Transport
{
    /**
     * Perform one HTTP request and return the raw response.
     *
     * @param string                $method  Upper-case HTTP verb.
     * @param string                $url     Fully-qualified request URL (query string included).
     * @param array<string, string> $headers Request headers, keyed by header name.
     * @param string|null           $body    Request body, or null for no body.
     *
     * @throws TransportException When the request cannot be completed (no HTTP status received).
     */
    public function send(string $method, string $url, array $headers, ?string $body): Response;
}
