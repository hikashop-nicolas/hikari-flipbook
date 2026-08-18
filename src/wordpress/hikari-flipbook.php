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

use Hikari\Flipbook\Core\Config;
use Hikari\Flipbook\Core\Renderer;
use Hikari\Flipbook\Core\Source;
use Hikari\Flipbook\Core\SourceException;
use Hikari\Flipbook\Platform\WordPressPlatform;

if (!defined('ABSPATH')) {
    exit;
}

define('HIKARI_FLIPBOOK_VERSION', '0.1.0');

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/WordPressPlatform.php';

/**
 * [hikari_flipbook path="uploads/catalogue.pdf" mode="auto"]
 */
function hikari_flipbook_shortcode($atts): string
{
    static $count = 0;

    $atts = shortcode_atts(
        [
            'path'         => '',
            'mode'         => 'auto',
            'showcover'    => '1',
            'rtl'          => '0',
            'zoom'         => '1',
            'breakpoint'   => '700',
            'flippingtime' => '700',
        ],
        $atts,
        'hikari_flipbook'
    );

    // Shortcode attribute names arrive lowercased, the core speaks camelCase.
    $params = [
        'mode'         => $atts['mode'],
        'showCover'    => $atts['showcover'],
        'rtl'          => $atts['rtl'],
        'zoom'         => $atts['zoom'],
        'breakpoint'   => $atts['breakpoint'],
        'flippingTime' => $atts['flippingtime'],
    ];

    $platform = new WordPressPlatform($params);

    try {
        $source = Source::fromPath($platform, (string) $atts['path']);
    } catch (SourceException $e) {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return '<div class="hikari-flipbook-error">'
            . esc_html(sprintf(__('Flipbook: %s', 'hikari-flipbook'), $e->getMessage()))
            . '</div>';
    }

    $count++;

    return (new Renderer($platform))->render($source, new Config($params), 'hikari-flipbook-' . $count);
}

add_shortcode('hikari_flipbook', 'hikari_flipbook_shortcode');

add_action('init', static function (): void {
    load_plugin_textdomain('hikari-flipbook', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
