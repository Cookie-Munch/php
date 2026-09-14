<?php

declare(strict_types=1);

namespace CookieMunch\Http;

/**
 * A raw HTTP response as returned by a {@see Transport}. Deliberately minimal:
 * a status code, response headers (lower-cased keys), and the undecoded body.
 */
final class Response
{
    /**
     * @param int                   $status  HTTP status code.
     * @param array<string, string> $headers Response headers, keyed by lower-cased name.
     * @param string                $body    Raw, undecoded response body.
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {
    }

    /** Case-insensitive header lookup. */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
