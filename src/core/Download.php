<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

/**
 * Sends one file to the browser, for a document that is not in the public root.
 *
 * A book someone bought is not a public file: it is read out by the host, at an
 * address that checks the purchase every time. Shown rather than offered as a
 * download, since what asks for it is the viewer.
 */
final class Download
{
    private const TYPES = [
        'pdf'   => 'application/pdf',
        'epub'  => 'application/epub+zip',
        'cbz'   => 'application/vnd.comicbook+zip',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'png'   => 'image/png',
        'webp'  => 'image/webp',
        'gif'   => 'image/gif',
        'avif'  => 'image/avif',
        'html'  => 'text/html; charset=utf-8',
        'xhtml' => 'text/html; charset=utf-8',
        'htm'   => 'text/html; charset=utf-8',
    ];

    public static function type(string $path): string
    {
        return self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    /** Headers and bytes. Nothing may have been printed before this. */
    public static function send(string $path): void
    {
        // Whatever the host has already buffered would land in the middle of the
        // file, so it goes first.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . self::type($path));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        // One reader's copy, never a shared cache's: the next visitor may not have
        // bought it.
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        // Ranges are not served, so a reader that asks for one is given the whole
        // file instead. pdf.js is happy either way; half a PDF would not be.
        header('Accept-Ranges: none');

        readfile($path);
    }
}
