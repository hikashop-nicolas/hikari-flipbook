<?php
/**
 * Plugin Name:       Hikari Flipbook
 * Plugin URI:        https://github.com/hikashop-nicolas/hikari-flipbook
 * Description:       Shows a PDF, an EPUB, a comic archive or a folder of pages as a book with turning pages.
 * Version:           0.2.0
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

use Hikari\Flipbook\Core\Buyers;
use Hikari\Flipbook\Platform\WordPressPlatform;
use Hikari\Flipbook\WordPress\Books;
use Hikari\Flipbook\WordPress\BookType;
use Hikari\Flipbook\WordPress\Settings;

if (!defined('ABSPATH')) {
    exit;
}

define('HIKARI_FLIPBOOK_VERSION', '0.2.0');
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

/**
 * The address a bought book is read from.
 *
 * A document sold as a product is not a public file, so it is read out here. The
 * purchase is checked again on every request: somebody who has the address but
 * not the order gets nothing, and so does a search engine.
 */
function hikari_flipbook_serve(): void
{
    $product = isset($_GET['hikari_flipbook_book'])
        ? (string) (int) $_GET['hikari_flipbook_book']
        : '';

    if ($product === '' || $product === '0') {
        return;
    }

    if (!Buyers::send(new WordPressPlatform([]), $product)) {
        status_header(404);
        exit;
    }

    exit;
}

add_shortcode('hikari_flipbook', 'hikari_flipbook_shortcode');
add_action('init', 'hikari_flipbook_serve');

add_action('init', static function (): void {
    load_plugin_textdomain('hikari-flipbook', false, dirname(plugin_basename(HIKARI_FLIPBOOK_FILE)) . '/languages');

    register_block_type(__DIR__ . '/blocks/flipbook', [
        'render_callback' => 'hikari_flipbook_render_block',
    ]);
});

Settings::register();
BookType::register();
