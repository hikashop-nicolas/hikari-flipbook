<?php
/**
 * Plugin Name:       Hikari Flipbook
 * Plugin URI:        https://github.com/hikashop-nicolas/hikari-flipbook
 * Description:       Shows a PDF or a folder of images as a book with turning pages.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Hikari Software
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       hikari-flipbook
 * Domain Path:       /languages
 *
 * @package Hikari.Flipbook
 */

use Hikari\Flipbook\WordPress\Books;
use Hikari\Flipbook\WordPress\BookType;
use Hikari\Flipbook\WordPress\Settings;

if (!defined('ABSPATH')) {
    exit;
}

define('HIKARI_FLIPBOOK_VERSION', '0.1.0');
define('HIKARI_FLIPBOOK_FILE', __FILE__);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/Books.php';
require_once __DIR__ . '/includes/BookType.php';

/**
 * [hikari_flipbook path="uploads/catalogue.pdf" mode="single"]
 *
 * Any setting from the options screen works as an attribute; what is not given
 * falls back to the site default.
 *
 * @param  mixed $atts
 * @return string
 */
function hikari_flipbook_shortcode($atts): string
{
    return Books::render(is_array($atts) ? $atts : []);
}

/**
 * The block renders through the same path as the shortcode, so the two cannot
 * disagree about what a setting means.
 *
 * @param  array<string,mixed> $attributes
 * @return string
 */
function hikari_flipbook_render_block(array $attributes): string
{
    // A block leaves unset settings empty; the site default fills them in.
    return Books::render(array_filter($attributes, static function ($value): bool {
        return $value !== '' && $value !== null;
    }));
}

add_shortcode('hikari_flipbook', 'hikari_flipbook_shortcode');

add_action('init', static function (): void {
    load_plugin_textdomain('hikari-flipbook', false, dirname(plugin_basename(HIKARI_FLIPBOOK_FILE)) . '/languages');

    register_block_type(__DIR__ . '/blocks/flipbook', [
        'render_callback' => 'hikari_flipbook_render_block',
    ]);
});

Settings::register();
BookType::register();
