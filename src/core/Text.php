<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

use Hikari\Flipbook\Platform\Platform;

/**
 * The words in a PDF, read on the server.
 *
 * The browser already reads them, for search and for the text layer, but a crawler
 * runs no JavaScript: without this a catalogue is a picture of a catalogue as far
 * as a search engine is concerned. Reading a PDF properly is a large job, so this
 * asks pdftotext, which most hosts have, and quietly gives up where they do not.
 */
final class Text
{
    /** Where pdftotext usually is. The bare name relies on PATH, which many hosts unset. */
    private const CANDIDATES = [
        '/usr/bin/pdftotext',
        '/usr/local/bin/pdftotext',
        '/opt/homebrew/bin/pdftotext',
        '/opt/local/bin/pdftotext',
        'pdftotext',
    ];

    /** Long enough for a catalogue, short enough not to bloat every page of the site. */
    private const MAX_CHARS = 120000;

    /** Beyond this a document is left alone: the extraction would outlive the request. */
    private const MAX_BYTES = 100000000;

    /**
     * One string per page, in order. An empty array means the words could not be
     * read, which is not an error: it is a host without the tool.
     *
     * @return array<int,string>
     */
    public static function pages(Platform $platform, string $file): array
    {
        if (!is_file($file) || filesize($file) > self::MAX_BYTES) {
            return [];
        }

        $cached = self::cached($platform, $file);

        if ($cached !== null) {
            return $cached;
        }

        $raw = self::read($file);
        // pdftotext separates pages with a form feed, which is the only page
        // boundary it gives us.
        $pages = $raw === '' ? [] : array_map([self::class, 'tidy'], explode("\f", $raw));

        // A trailing form feed leaves an empty last page that was never in the book.
        while ($pages !== [] && end($pages) === '') {
            array_pop($pages);
        }

        self::store($platform, $file, $pages);

        return $pages;
    }

    /** @return array<int,string>|null Null when nothing usable is cached. */
    private static function cached(Platform $platform, string $file): ?array
    {
        $path = self::cacheFile($platform, $file);

        if ($path === '' || !is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? array_map('strval', $decoded) : null;
    }

    /** @param array<int,string> $pages */
    private static function store(Platform $platform, string $file, array $pages): void
    {
        $path = self::cacheFile($platform, $file);

        if ($path === '') {
            return;
        }

        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        // Written even when it is empty: a host without pdftotext should ask once
        // per document, not once per page view.
        @file_put_contents($path, json_encode(array_values($pages), JSON_UNESCAPED_UNICODE), LOCK_EX);
        self::prune($dir);
    }

    /**
     * A re-uploaded document is a new key, so the old one would sit there forever.
     * Nobody empties this folder by hand, so it empties itself.
     */
    private static function prune(string $dir, int $keep = 200): void
    {
        $files = glob($dir . '/*.json') ?: [];

        if (count($files) <= $keep) {
            return;
        }

        usort($files, static fn ($a, $b) => filemtime($a) <=> filemtime($b));

        foreach (array_slice($files, 0, count($files) - $keep) as $old) {
            @unlink($old);
        }
    }

    /** Keyed by the document's own path, size and time, so a new upload is re-read. */
    private static function cacheFile(Platform $platform, string $file): string
    {
        $dir = rtrim(Paths::normalise($platform->cachePath()), '/');

        if ($dir === '') {
            return '';
        }

        $key = md5($file . '|' . filesize($file) . '|' . filemtime($file));

        return $dir . '/text/' . $key . '.json';
    }

    /** The whole document as pdftotext gives it, or an empty string. */
    private static function read(string $file): string
    {
        if (!function_exists('proc_open') || in_array('proc_open', self::disabled(), true)) {
            return '';
        }

        foreach (self::CANDIDATES as $binary) {
            // The array form of proc_open runs the program directly: there is no
            // shell, so a path with a quote in it is a path and not a command.
            $process = @proc_open(
                // No -nopgbrk: the form feed between pages is the only thing that
                // says where one page ends and the next begins.
                [$binary, '-enc', 'UTF-8', '-q', $file, '-'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );

            if (!is_resource($process)) {
                continue;
            }

            $out = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);

            if ($status === 0 && $out !== '') {
                return $out;
            }
        }

        return '';
    }

    /** @return array<int,string> */
    private static function disabled(): array
    {
        return array_map('trim', explode(',', (string) ini_get('disable_functions')));
    }

    private static function tidy(string $page): string
    {
        // Runs of whitespace become single spaces: a PDF's line breaks are where
        // the type was set, not where the sentence ended.
        $page = preg_replace('/\s+/u', ' ', $page);
        $page = trim((string) $page);

        return mb_substr($page, 0, self::MAX_CHARS);
    }
}
