<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

/**
 * The regions a site has drawn on a book's pages.
 *
 * They are stored as JSON by both hosts and end up in a page's HTML, so this is
 * where they are checked: a hotspot arrives from a form and leaves as an anchor's
 * href, which is exactly the path an injected javascript: URL would like to take.
 */
final class Hotspots
{
    /** Anything else in a stored hotspot is dropped. */
    private const KEYS = ['page', 'x', 'y', 'width', 'height', 'href', 'target', 'goToPage', 'label', 'data'];

    /**
     * Reads what was stored, in whatever shape the host kept it.
     *
     * @param  mixed $raw JSON, or an array already decoded by the host.
     * @return array<int,array<string,mixed>>
     */
    public static function decode($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $item) {
            $spot = self::one(is_array($item) ? $item : []);

            if ($spot !== null) {
                $out[] = $spot;
            }
        }

        return $out;
    }

    /** @param array<int,array<string,mixed>> $spots */
    public static function encode(array $spots): string
    {
        $json = json_encode(array_values($spots), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? '[]' : $json;
    }

    /**
     * @param  array<string,mixed> $item
     * @return array<string,mixed>|null
     */
    private static function one(array $item): ?array
    {
        $spot = [
            'page'   => max(0, (int) ($item['page'] ?? 0)),
            'x'      => self::fraction($item['x'] ?? 0),
            'y'      => self::fraction($item['y'] ?? 0),
            'width'  => self::fraction($item['width'] ?? 0),
            'height' => self::fraction($item['height'] ?? 0),
        ];

        // A region with no area cannot be clicked, so it is not a hotspot.
        if ($spot['width'] <= 0.0 || $spot['height'] <= 0.0) {
            return null;
        }

        $href = self::url($item['href'] ?? '');

        if ($href !== '') {
            $spot['href'] = $href;

            if (($item['target'] ?? '') === '_blank') {
                $spot['target'] = '_blank';
            }
        }

        if (isset($item['goToPage']) && $item['goToPage'] !== '') {
            $spot['goToPage'] = max(0, (int) $item['goToPage']);
        }

        $label = trim((string) ($item['label'] ?? ''));

        if ($label !== '') {
            $spot['label'] = mb_substr($label, 0, 255);
        }

        $data = self::data($item['data'] ?? []);

        if ($data !== []) {
            $spot['data'] = $data;
        }

        // Bound to nothing and named nothing: it would announce itself to a screen
        // reader as a control that does nothing at all.
        if (!isset($spot['href']) && !isset($spot['goToPage']) && $data === []) {
            return null;
        }

        return $spot;
    }

    /** @return array<string,string> */
    private static function data($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }
            $key = preg_replace('/[^a-zA-Z0-9_-]/', '', $key);

            if ($key !== '' && $key !== null) {
                $out[$key] = mb_substr((string) $value, 0, 255);
            }
        }

        return $out;
    }

    private static function fraction($value): float
    {
        $value = is_numeric($value) ? (float) $value : 0.0;

        return max(0.0, min(1.0, $value));
    }

    /**
     * Only a link a browser will follow to another document: http, https, mailto,
     * tel, or a path on this site. Everything else, javascript: above all, is not
     * a link a site owner meant to draw on a page.
     */
    private static function url($value): string
    {
        $value = trim((string) $value);

        if ($value === '' || strlen($value) > 2048) {
            return '';
        }

        // A control character can hide a scheme from the check and not from the
        // browser: "java\nscript:alert(1)" is a URL Chrome will happily run.
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return '';
        }

        if (preg_match('~^(https?|mailto|tel):~i', $value) === 1) {
            return $value;
        }

        // "//host" is a URL to somewhere else wearing a relative link's clothes.
        if (strncmp($value, '//', 2) === 0) {
            return '';
        }

        // What is left must be relative: a path, a query or a fragment. A colon
        // anywhere before the first slash would make it a scheme instead.
        $head = strstr($value, '/', true);

        return strpos($head === false ? $value : $head, ':') === false ? $value : '';
    }
}
