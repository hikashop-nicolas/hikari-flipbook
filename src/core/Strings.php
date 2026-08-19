<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

use Hikari\Flipbook\Platform\Platform;

/**
 * The words the viewer says, in one place.
 *
 * The viewer ships English and takes replacements, so the strings have to come
 * from the host: a French site should have a French tooltip on the next-page
 * button, and a screen reader should hear the site's own language.
 *
 * Each entry maps the viewer's own name for a string to the key a host looks up
 * and the English it falls back to. One table, so a host cannot answer for a
 * string the viewer does not use, or miss one that it does.
 */
final class Strings
{
    private const VIEWER = [
        'first'          => ['HIKARI_FLIPBOOK_FIRST', 'First page'],
        'prev'           => ['HIKARI_FLIPBOOK_PREV', 'Previous page'],
        'next'           => ['HIKARI_FLIPBOOK_NEXT', 'Next page'],
        'last'           => ['HIKARI_FLIPBOOK_LAST', 'Last page'],
        'pageOf'         => ['HIKARI_FLIPBOOK_PAGE_OF', 'Page {page} of {total}'],
        'goToPage'       => ['HIKARI_FLIPBOOK_GO_TO_PAGE', 'Go to page'],
        'zoomIn'         => ['HIKARI_FLIPBOOK_ZOOM_IN', 'Zoom in'],
        'zoomOut'        => ['HIKARI_FLIPBOOK_ZOOM_OUT', 'Zoom out'],
        'zoomReset'      => ['HIKARI_FLIPBOOK_ZOOM_RESET', 'Reset zoom'],
        'fullscreen'     => ['HIKARI_FLIPBOOK_FULLSCREEN', 'Fullscreen'],
        'exitFullscreen' => ['HIKARI_FLIPBOOK_EXIT_FULLSCREEN', 'Exit fullscreen'],
        'close'          => ['HIKARI_FLIPBOOK_CLOSE', 'Close'],
        'download'       => ['HIKARI_FLIPBOOK_DOWNLOAD', 'Download'],
        'share'          => ['HIKARI_FLIPBOOK_SHARE', 'Copy a link to this page'],
        'shared'         => ['HIKARI_FLIPBOOK_SHARED', 'Link copied'],
        'shareFailed'    => ['HIKARI_FLIPBOOK_SHARE_FAILED', 'Copy this link'],
        'open'           => ['HIKARI_FLIPBOOK_OPEN', 'Open the book'],
    ];

    /** Words the server writes into the page, for whoever is not running the viewer. */
    private const SERVER = [
        'openDocument' => ['HIKARI_FLIPBOOK_OPEN_DOCUMENT', 'Open the document'],
        // {product} is replaced by a link to the product, so it has to survive
        // translation: a translator moves it, and must not drop it.
        'buyersOnly'   => ['HIKARI_FLIPBOOK_BUYERS_ONLY', 'This book is for buyers of {product}.'],
        'buyersOnlyPlain' => ['HIKARI_FLIPBOOK_BUYERS_ONLY_PLAIN', 'This book is for buyers only.'],
    ];

    /**
     * What the host says, by the name the viewer knows it by.
     *
     * A host that has no string for a key hands the key back, which is the
     * contract; that is when the English below is used instead.
     *
     * @return array<string,string>
     */
    public static function viewer(Platform $platform): array
    {
        $out = [];

        foreach (self::VIEWER as $name => [$key, $english]) {
            $said = $platform->translate($key);
            $out[$name] = ($said === '' || $said === $key) ? $english : $said;
        }

        return $out;
    }

    /**
     * What the host says for the strings the server writes, same contract.
     *
     * @return array<string,string>
     */
    public static function server(Platform $platform): array
    {
        $out = [];

        foreach (self::SERVER as $name => [$key, $english]) {
            $said = $platform->translate($key);
            $out[$name] = ($said === '' || $said === $key) ? $english : $said;
        }

        return $out;
    }

    /**
     * The keys and their English, for a host that has to map them, and for the
     * build that writes the translator's catalogue.
     *
     * @return array<string,string>
     */
    public static function catalogue(): array
    {
        $out = [];

        foreach (array_merge(self::VIEWER, self::SERVER) as [$key, $english]) {
            $out[$key] = $english;
        }

        return $out;
    }
}
