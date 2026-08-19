<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

use Hikari\Flipbook\Platform\Platform;

/**
 * A picture of the first page, made once on the server.
 *
 * A book that opens over the page shows only its cover until someone asks for it,
 * and today the browser gets that cover by downloading the whole PDF and rendering
 * a page of it. On a catalogue that is several megabytes for one thumbnail, on
 * every page view, for every reader, most of whom never open the book.
 *
 * Where the host can make the picture itself, it is made once and cached, and the
 * document is downloaded only when a reader actually opens it.
 */
final class Cover
{
    /** Wide enough for a retina thumbnail, small enough to stay a thumbnail. */
    private const WIDTH = 640;

    /**
     * The cover's public URL, or '' where this host cannot make one. Never throws:
     * a missing cover is a slower page, not a broken one.
     */
    public static function url(Platform $platform, Source $source): string
    {
        if ($source->kind() !== Source::KIND_PDF) {
            // A book of images already has a picture of its first page.
            return '';
        }

        $file = $source->files()[0];
        $name = self::name($file);

        if ($name === '') {
            return '';
        }

        $path = self::dir($platform) . '/' . $name;

        if (is_file($path)) {
            return self::publicUrl($platform, $path);
        }

        return self::make($platform, $file, $path) ? self::publicUrl($platform, $path) : '';
    }

    /** Keyed by the document's path, size and time, so a new upload is redrawn. */
    private static function name(string $file): string
    {
        if (!is_file($file)) {
            return '';
        }

        return md5($file . '|' . filesize($file) . '|' . filemtime($file)) . '.png';
    }

    /** Somewhere this host can write and a browser can read. */
    private static function dir(Platform $platform): string
    {
        $storage = $platform->storage();

        return rtrim(Paths::normalise($storage['path']), '/') . '/covers';
    }

    private static function publicUrl(Platform $platform, string $path): string
    {
        $storage = $platform->storage();

        return rtrim($storage['url'], '/') . '/covers/' . basename($path);
    }

    private static function make(Platform $platform, string $file, string $path): bool
    {
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        if (self::withImagick($file, $path) || self::withPoppler($file, $path)) {
            self::prune($dir);

            return is_file($path);
        }

        return false;
    }

    private static function withImagick(string $file, string $path): bool
    {
        if (!class_exists('Imagick')) {
            return false;
        }

        try {
            $image = new \Imagick();
            $image->setResolution(150, 150);
            // The first page only: reading a 300 page catalogue to draw one cover
            // is exactly the cost this is here to avoid.
            $image->readImage($file . '[0]');
            $image->setImageFormat('png');
            $image->setImageBackgroundColor('white');
            $image = $image->flattenImages();
            $image->thumbnailImage(self::WIDTH, 0);
            $written = $image->writeImage($path);
            $image->clear();

            return (bool) $written;
        } catch (\Throwable $e) {
            // Imagick without its PDF delegate, or a policy that forbids PDFs.
            return false;
        }
    }

    /** pdftoppm, from the same package as the pdftotext the text layer uses. */
    private static function withPoppler(string $file, string $path): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }

        foreach (['/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm', '/opt/homebrew/bin/pdftoppm', 'pdftoppm'] as $binary) {
            // -singlefile makes the output exactly the path we asked for, without
            // pdftoppm's usual -1 page number suffix.
            $command = [
                $binary, '-png', '-f', '1', '-l', '1', '-singlefile',
                '-scale-to-x', (string) self::WIDTH, '-scale-to-y', '-1',
                $file, substr($path, 0, -4),
            ];

            $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

            if (!is_resource($process)) {
                continue;
            }

            fclose($pipes[1]);
            fclose($pipes[2]);

            if (proc_close($process) === 0 && is_file($path)) {
                return true;
            }
        }

        return false;
    }

    /** Same reason as the text cache: a folder nobody ever empties by hand. */
    private static function prune(string $dir, int $keep = 200): void
    {
        $files = glob($dir . '/*.png') ?: [];

        if (count($files) <= $keep) {
            return;
        }

        usort($files, static fn ($a, $b) => filemtime($a) <=> filemtime($b));

        foreach (array_slice($files, 0, count($files) - $keep) as $old) {
            @unlink($old);
        }
    }
}
