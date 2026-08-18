<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\WordPress;

use Hikari\Flipbook\Core\Config;
use Hikari\Flipbook\Core\Renderer;
use Hikari\Flipbook\Core\Source;
use Hikari\Flipbook\Core\SourceException;
use Hikari\Flipbook\Platform\WordPressPlatform;

/**
 * One place that turns a set of attributes into a book, used by both the shortcode
 * and the block, so the two cannot drift apart.
 */
final class Books
{
    /** @var int Books rendered so far, for unique element ids. */
    private static $count = 0;

    /**
     * @param  array<string,mixed> $atts Whatever the shortcode or block supplied.
     * @return string
     */
    public static function render(array $atts): string
    {
        // Attribute names arrive lowercased from a shortcode, so the settings are
        // matched case-insensitively before the site defaults fill the rest in.
        $lower = [];
        foreach ($atts as $key => $value) {
            $lower[strtolower((string) $key)] = $value;
        }

        $params = [];
        foreach (array_merge(Settings::all(), ['path' => '']) as $key => $default) {
            $params[$key] = $lower[strtolower($key)] ?? $default;
        }

        $platform = new WordPressPlatform($params);

        try {
            $source = Source::fromPath($platform, (string) $params['path']);
        } catch (SourceException $e) {
            return self::complain($e->getMessage());
        }

        self::$count++;

        return (new Renderer($platform))->render(
            $source,
            new Config($params),
            'hikari-flipbook-' . self::$count
        );
    }

    /** Only someone who could fix the page is told what is wrong with it. */
    private static function complain(string $message): string
    {
        if (!current_user_can('edit_posts')) {
            return '';
        }

        return '<div class="hikari-flipbook-error">'
            . esc_html(sprintf(__('Flipbook: %s', 'hikari-flipbook'), $message))
            . '</div>';
    }
}
