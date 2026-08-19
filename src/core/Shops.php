<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

/**
 * What the two hosts have to agree about when they ask a shop for a file.
 *
 * HikaShop runs on Joomla and on WordPress, with the same tables and the same
 * upload folder in both. Reading its file list is therefore the same work twice,
 * and this is the once.
 */
final class Shops
{
    /** Only a document is a book. A shop sells plenty of other files. */
    private const SHOWABLE = ['pdf', 'epub', 'cbz'];

    /**
     * The first of a product's files this server can read and we can show.
     *
     * A shop may keep a file on another machine: HikaShop marks those with a
     * scheme, an @ or a # and fetches them itself. We can only read what is on
     * this one, so those are passed over rather than guessed at.
     *
     * @param  array<int,string> $paths  file_path, in the shop's own order.
     * @param  string            $root   The site root, for a relative folder.
     * @param  string            $folder HikaShop's uploadsecurefolder setting.
     */
    public static function hikaShopFile(array $paths, string $root, string $folder): ?string
    {
        $folder = trim(Paths::normalise($folder));
        $folder = $folder === '' ? 'media/com_hikashop/upload/safe' : rtrim($folder, '/');

        $base = preg_match('#^([A-Za-z]:)?/#', $folder) === 1
            ? $folder
            : rtrim(Paths::normalise($root), '/') . '/' . ltrim($folder, '/');

        foreach ($paths as $path) {
            $path = Paths::normalise(trim((string) $path));

            if ($path === '' || strpos($path, '://') !== false || strpos('@#', $path[0]) !== false) {
                continue;
            }

            foreach ([$base . '/' . ltrim($path, '/'), $path] as $candidate) {
                $real = self::showable($candidate);

                if ($real !== null) {
                    return $real;
                }
            }
        }

        return null;
    }

    /** The file, resolved, when it is one we can show, and null when it is not. */
    public static function showable(string $path): ?string
    {
        if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::SHOWABLE, true)) {
            return null;
        }

        $real = realpath($path);

        return $real === false || !is_file($real) ? null : Paths::normalise($real);
    }
}
