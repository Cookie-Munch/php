<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** DPIA, PIA, LIA, TIA, AI-impact and vendor assessments — the /v1/assessments surface. */
final class Assessments
{
    public function __construct(private readonly Client $client)
    {
    }

/** @return array<string, mixed> */
public function templates(): array
{
    return $this->client->requestJson('GET', '/v1/assessments/templates');
}

/** @return array<string, mixed> */
public function list(): array
{
    return $this->client->requestJson('GET', '/v1/assessments');
}

/** @return array<string, mixed> */
public function start(string $template, string $subject): array
{
    return $this->client->requestJson('POST', '/v1/assessments', ['template' => $template, 'subject' => $subject]);
}

/** @return array<string, mixed> */
public function get(string $id): array
{
    return $this->client->requestJson('GET', '/v1/assessments/' . rawurlencode($id));
}

/** @return array<string, mixed> */
public function answer(string $id, string $questionId, string|int|float|bool $value): array
{
    return $this->client->requestJson('POST', '/v1/assessments/' . rawurlencode($id) . '/answer', ['questionId' => $questionId, 'value' => $value]);
}

/** @return array<string, mixed> Fill from the latest data map. Never overwrites a human answer. */
public function autoPopulateFromMap(string $id): array
{
    return $this->client->requestJson('POST', '/v1/assessments/' . rawurlencode($id) . '/autopopulate-from-map');
}

/**
 * Fill from evidence you supply, stamped with $source. Never overwrites a human answer.
 *
 * @param array<string, mixed> $evidence questionId => answer
 *
 * @return array<string, mixed>
 */
public function autoPopulate(string $id, array $evidence, ?string $source = null): array
{
    $body = ['evidence' => $evidence];
    if ($source !== null) {
        $body['source'] = $source;
    }

    return $this->client->requestJson('POST', '/v1/assessments/' . rawurlencode($id) . '/autopopulate', $body);
}

/** @return array<string, mixed> */
public function submit(string $id): array
{
    return $this->client->requestJson('POST', '/v1/assessments/' . rawurlencode($id) . '/submit');
}

/** @return array<string, mixed> $by becomes the approval record — pass the person who approved. */
public function approve(string $id, string $by): array
{
    return $this->client->requestJson('POST', '/v1/assessments/' . rawurlencode($id) . '/approve', ['by' => $by]);
}

/** @return array<string, mixed> */
public function reject(string $id, string $by, string $reason): array
{
    return $this->client->requestJson('POST', '/v1/assessments/' . rawurlencode($id) . '/reject', ['by' => $by, 'reason' => $reason]);
}
}
