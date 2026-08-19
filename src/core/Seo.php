<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

use Hikari\Flipbook\Platform\Platform;

/**
 * What is in the page for whoever is not running the viewer.
 *
 * A book is built by JavaScript, so without this a crawler sees an empty div and
 * a reader with JavaScript off sees nothing at all. Both get the same thing here:
 * a link to the document itself, and the words on its pages where the host can
 * read them.
 */
final class Seo
{
    /**
     * @param  array<int,string> $urls The document, or every image, as public URLs.
     */
    public static function markup(Platform $platform, Source $source, array $urls, Config $config): string
    {
        if ($urls === [] || !$config->get('seo')) {
            return '';
        }

        $escape = static fn (string $value): string => $platform->escape($value);

        // Inside <noscript> so it never shows twice: a reader who has the viewer
        // has the book itself, and text hidden with CSS from everyone but a
        // crawler is the oldest way there is to be penalised for cloaking.
        $out = '<noscript><div class="hikari-flipbook-text">';

        if ($source->kind() === Source::KIND_PDF) {
            $out .= '<p><a href="' . $escape($urls[0]) . '">'
                . $escape(Strings::server($platform)['openDocument']) . '</a></p>';

            foreach (Text::pages($platform, $source->files()[0]) as $number => $page) {
                if ($page === '') {
                    continue;
                }
                $out .= '<p>' . $escape($page) . '</p>';
                unset($number);
            }
        } else {
            // A book of images has no words. The pages themselves are the content,
            // and an <img> is something a crawler can actually index.
            foreach ($urls as $index => $url) {
                $out .= '<img src="' . $escape($url) . '" alt="'
                    . $escape(self::pageLabel($platform, $index + 1, count($urls))) . '" loading="lazy">';
            }
        }

        return $out . '</div></noscript>';
    }

    /** The same words, and the same {page} of {total} template, the viewer uses. */
    private static function pageLabel(Platform $platform, int $number, int $total): string
    {
        return str_replace(
            ['{page}', '{total}'],
            [(string) $number, (string) $total],
            Strings::viewer($platform)['pageOf']
        );
    }
}
