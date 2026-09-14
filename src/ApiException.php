<?php

declare(strict_types=1);

namespace CookieMunch;

use RuntimeException;

/**
 * Thrown for any non-2xx response from the API.
 *
 * `message` is the server's `error` field when the body is JSON, otherwise a
 * generic fallback. The raw HTTP {@see $status}, the undecoded response
 * {@see $body}, and the server's optional machine {@see $code} (e.g.
 * "banner_in_use") are always available.
 */
final class ApiException extends RuntimeException
{
    /**
     * @param int         $status    HTTP status code of the failed response.
     * @param string      $message   Human-readable message (server `error` field when present).
     * @param string      $body      Raw, undecoded response body.
     * @param string|null $errorCode Server `code` field, when present (e.g. "banner_in_use").
     */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly string $body = '',
        public readonly ?string $errorCode = null,
    ) {
        // Note: named `errorCode` (not `code`) to avoid clashing with Exception::$code.
        parent::__construct($message, $status);
    }

    /** HTTP status code of the failed response. */
    public function getStatus(): int
    {
        return $this->status;
    }

    /** Raw, undecoded response body. */
    public function getBody(): string
    {
        return $this->body;
    }

    /** Server-supplied machine code (e.g. "banner_in_use"), when present. */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
