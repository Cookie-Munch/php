<?php

declare(strict_types=1);

namespace CookieMunch\Resources;

use CookieMunch\Client;

/** The hosted rights page — /v1/sites/{cbid}/rights-page. */
final class RightsPage
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @return array<string, mixed> The draft, with publish warnings. */
    public function get(string $cbid): array
    {
        /** @var array<string, mixed> $out */
        $out = $this->client->request('GET', self::path($cbid)) ?? [];

        return $out;
    }

    /**
     * Replace the draft — PUT. Nothing is live until publish().
     * Add 'baseUpdatedAt' (the draft's updatedAt as last read; null if it had none) to $page to
     * refuse overwriting a newer save: a mismatch is a 409 draft_conflict.
     *
     * @param array<string, mixed> $page
     *
     * @return array<string, mixed>
     */
    public function put(string $cbid, array $page): array
    {
        return $this->client->requestJson('PUT', self::path($cbid), self::shape($page));
    }

    /**
     * Merge into the draft — PATCH: objects merge, lists replace, null removes.
     *
     * @param array<string, mixed> $page
     *
     * @return array<string, mixed>
     */
    public function patch(string $cbid, array $page): array
    {
        return $this->client->requestJson('PATCH', self::path($cbid), self::shape($page));
    }

    /**
     * Validate a write without saving it (?dryRun=1).
     *
     * @param array<string, mixed> $page
     *
     * @return array<string, mixed>
     */
    public function dryRun(string $cbid, array $page, bool $replace = false): array
    {
        return $this->client->requestJson($replace ? 'PUT' : 'PATCH', self::path($cbid) . '?dryRun=1', self::shape($page));
    }

    /** @return array<string, mixed> */
    public function getPublished(string $cbid): array
    {
        /** @var array<string, mixed> $out */
        $out = $this->client->request('GET', self::path($cbid) . '/published') ?? [];

        return $out;
    }

    /**
     * Publish the draft; it goes live at once. Pass $baseUpdatedAt (the draft's updatedAt as you
     * read it; null if it had none) so only the draft you saw goes live: 409 draft_conflict otherwise.
     *
     * @return array<string, mixed>
     */
    public function publish(string $cbid, int|string|null $baseUpdatedAt = null): array
    {
        $body = func_num_args() > 1 ? ['baseUpdatedAt' => $baseUpdatedAt] : new \stdClass();

        return $this->client->requestJson('POST', self::path($cbid) . '/publish', $body);
    }

    /** @return array<string, mixed> */
    public function versions(string $cbid): array
    {
        /** @var array<string, mixed> $out */
        $out = $this->client->request('GET', self::path($cbid) . '/versions') ?? [];

        return $out;
    }

    /**
     * Publish an earlier version again; it goes live at once and REPLACES the current draft
     * (unpublished edits are lost). Pass $baseUpdatedAt to refuse (409 draft_conflict) if the
     * draft changed since you read it.
     *
     * @return array<string, mixed>
     */
    public function rollback(string $cbid, int $version, int|string|null $baseUpdatedAt = null): array
    {
        $body = ['version' => $version];
        if (func_num_args() > 2) {
            $body['baseUpdatedAt'] = $baseUpdatedAt;
        }

        return $this->client->requestJson('POST', self::path($cbid) . '/rollback', $body);
    }

    /*
     * PHP decodes a JSON object into an array, so an empty one ({}) comes back as [] and would be
     * sent as a list, which the API refuses (422 "must be an object"). These put the objects of a
     * rights page back where the document has them, so get() → put() round-trips unchanged.
     * Lists stay lists, even when empty.
     */

    /**
     * @param array<string, mixed> $page
     *
     * @return array<string, mixed>
     */
    private static function shape(array $page): array
    {
        foreach ($page as $k => $v) {
            if (!is_array($v)) {
                continue;
            }
            $page[$k] = match ($k) {
                'theme', 'seo' => self::obj($v),
                'sections' => self::each($v, [self::class, 'section']),
                'form' => self::form($v),
                'i18n' => self::i18n($v),
                default => $v,
            };
        }

        return $page;
    }

    /** @param array<mixed> $a */
    private static function obj(array $a): array|\stdClass
    {
        return $a === [] ? new \stdClass() : $a;
    }

    /**
     * Apply $f to each array element of a list.
     *
     * @param array<mixed> $list
     *
     * @return array<mixed>
     */
    private static function each(array $list, callable $f): array
    {
        return array_map(fn ($x) => is_array($x) ? $f($x) : $x, $list);
    }

    /**
     * A map (keys of the caller's choosing): always an object, each value shaped by $f. Never left as an
     * array: keys that are all digits from 0 (section ids such as "0", "1") would otherwise encode as a list.
     *
     * @param array<mixed> $map
     */
    private static function map(array $map, callable $f): \stdClass
    {
        return (object) array_map(fn ($x) => is_array($x) ? $f($x) : $x, $map);
    }

    /** @param array<mixed> $s */
    private static function section(array $s): array|\stdClass
    {
        foreach (['hidden', 'style'] as $k) {
            if (isset($s[$k]) && is_array($s[$k])) {
                $s[$k] = self::obj($s[$k]);
            }
        }
        if (isset($s['props']) && is_array($s['props'])) {
            $p = $s['props'];
            if (isset($p['dpo']) && is_array($p['dpo'])) {
                $p['dpo'] = self::obj($p['dpo']);
            }
            foreach (['items', 'columns'] as $k) {
                if (isset($p[$k]) && is_array($p[$k])) {
                    $p[$k] = self::each($p[$k], [self::class, 'obj']);
                }
            }
            $s['props'] = self::obj($p);
        }

        return self::obj($s);
    }

    /** @param array<mixed> $f */
    private static function form(array $f): array|\stdClass
    {
        foreach (['requestTypes', 'fields'] as $k) {
            if (isset($f[$k]) && is_array($f[$k])) {
                $f[$k] = self::each($f[$k], [self::class, 'obj']);
            }
        }
        if (isset($f['success']) && is_array($f['success'])) {
            $f['success'] = self::obj($f['success']);
        }

        return self::obj($f);
    }

    /** @param array<mixed> $i */
    private static function i18n(array $i): array|\stdClass
    {
        if (isset($i['overrides']) && is_array($i['overrides'])) {
            $i['overrides'] = self::map($i['overrides'], [self::class, 'localeCopy']);
        }

        return self::obj($i);
    }

    /**
     * One locale's overrides: section copy by section id, and form copy.
     *
     * @param array<mixed> $l
     */
    private static function localeCopy(array $l): array|\stdClass
    {
        if (isset($l['sections']) && is_array($l['sections'])) {
            $l['sections'] = self::map($l['sections'], [self::class, 'obj']);
        }
        if (isset($l['form']) && is_array($l['form'])) {
            $c = $l['form'];
            foreach (['requestTypes', 'fields'] as $k) {
                if (isset($c[$k]) && is_array($c[$k])) {
                    $c[$k] = self::map($c[$k], [self::class, 'obj']);
                }
            }
            if (isset($c['success']) && is_array($c['success'])) {
                $c['success'] = self::obj($c['success']);
            }
            $l['form'] = self::obj($c);
        }

        return self::obj($l);
    }

    private static function path(string $cbid): string
    {
        return '/v1/sites/' . rawurlencode($cbid) . '/rights-page';
    }

    /**
     * Custom hostnames serving the rights page — GET /v1/sites/{cbid}/rights-page/domains.
     * Returns { domains, limit (null = unlimited), used, target }.
     *
     * @return array<string, mixed>
     */
    public function listDomains(string $cbid): array
    {
        return $this->client->requestJson('GET', self::domains($cbid));
    }

    /**
     * Add a custom hostname (e.g. privacy.example.com); `dns` holds the records to create.
     *
     * @return array<string, mixed>
     */
    public function addDomain(string $cbid, string $hostname): array
    {
        return $this->client->requestJson('POST', self::domains($cbid), ['hostname' => $hostname]);
    }

    /**
     * Check it now: DNS while pending, the HTTPS answer once routed.
     *
     * @return array<string, mixed>
     */
    public function verifyDomain(string $cbid, string $hostname): array
    {
        return $this->client->requestJson('POST', self::domains($cbid) . '/' . rawurlencode($hostname) . '/verify');
    }

    /** Remove it: it stops being served and its certificate is not renewed. */
    public function removeDomain(string $cbid, string $hostname): void
    {
        $this->client->request('DELETE', self::domains($cbid) . '/' . rawurlencode($hostname));
    }

    private static function domains(string $cbid): string
    {
        return self::path($cbid) . '/domains';
    }
}
