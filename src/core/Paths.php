<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

use Hikari\Flipbook\Platform\Platform;

/**
 * One definition of the site root, used by everything that compares paths against it.
 *
 * It has to be canonical: a site served from a symlinked root (common on macOS and
 * on plenty of hosts) otherwise fails to recognise its own files, which shows up
 * once as a book that refuses to load and once as a filesystem path leaking into
 * the page.
 */
final class Paths
{
    public static function root(Platform $platform): string
    {
        $raw  = rtrim(str_replace('\\', '/', $platform->rootPath()), '/');
        $real = realpath($raw);

        return $real === false ? $raw : rtrim(str_replace('\\', '/', $real), '/');
    }

    public static function normalise(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    public static function isInside(string $path, string $root): bool
    {
        return $path === $root || strpos($path, $root . '/') === 0;
    }
}
