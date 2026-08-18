<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

use Hikari\Flipbook\Platform\Platform;

/**
 * The page-turn recordings a site has available.
 *
 * The folder is read rather than hard-coded, so dropping another recording into
 * media/.../sounds is all it takes to offer it: nothing here knows the names of
 * the two we ship.
 */
final class Sounds
{
    public const FOLDER = 'sounds';

    private const TYPES = ['mp3', 'ogg', 'wav', 'm4a', 'aac', 'opus'];

    /**
     * Every recording in the folder, by filename, in the order a person would
     * expect. Empty when the folder is missing, which is not an error: a site
     * that deleted them simply turns pages quietly.
     *
     * @return string[]
     */
    public static function available(Platform $platform): array
    {
        $folder = $platform->mediaPath() . '/' . self::FOLDER;

        if (!is_dir($folder)) {
            return [];
        }

        $found = [];
        foreach (scandir($folder) ?: [] as $entry) {
            if (!is_file($folder . '/' . $entry)) {
                continue;
            }
            if (in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), self::TYPES, true)) {
                $found[] = $entry;
            }
        }

        natcasesort($found);

        return array_values($found);
    }

    /**
     * The URLs to hand the viewer: one chosen recording, or all of them for it to
     * pick between. A choice that no longer exists falls back to all of them
     * rather than to silence, since a renamed file should not quietly kill the
     * sound on a site that asked for it.
     *
     * @return string[]
     */
    public static function urls(Platform $platform, string $chosen = ''): array
    {
        $available = self::available($platform);

        if ($available === []) {
            return [];
        }

        // Only a name, never a path: this value comes from site configuration.
        $chosen = basename(trim($chosen));

        if ($chosen !== '' && in_array($chosen, $available, true)) {
            $available = [$chosen];
        }

        return array_map(
            static function (string $name) use ($platform): string {
                return $platform->asset(self::FOLDER . '/' . $name);
            },
            $available
        );
    }
}
