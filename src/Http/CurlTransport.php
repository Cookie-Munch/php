<?php

declare(strict_types=1);

namespace CookieMunch\Http;

use CookieMunch\TransportException;

/**
 * The default {@see Transport}, built on ext-curl. No third-party dependencies.
 */
final class CurlTransport implements Transport
{
    /**
     * @param int $timeout        Total request timeout, in seconds.
     * @param int $connectTimeout Connection timeout, in seconds.
     */
    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {
        if (!\extension_loaded('curl')) {
            throw new TransportException('The curl PHP extension is required by CurlTransport.');
        }
    }

    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new TransportException('Failed to initialise a curl handle.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseHeaders = [];
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HEADERFUNCTION => function ($_ch, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new TransportException(sprintf('curl request failed (%d): %s', $errno, $error));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return new Response($status, $responseHeaders, (string) $result);
    }
}
