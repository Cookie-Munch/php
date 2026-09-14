<?php

declare(strict_types=1);

namespace CookieMunch;

use RuntimeException;

/**
 * Thrown when the underlying HTTP transport fails before an HTTP status is
 * received (DNS failure, connection refused, timeout, TLS error, …).
 *
 * This is distinct from {@see ApiException}, which represents a completed
 * request that returned a non-2xx status.
 */
final class TransportException extends RuntimeException
{
}
