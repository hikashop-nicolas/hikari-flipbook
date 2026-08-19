<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Platform;

use Hikari\Flipbook\Core\Shop;

if (!defined('ABSPATH')) {
    exit;
}

/** The WordPress side of the Platform contract. */
final class WordPressPlatform implements Platform, Shop
{
    /** @var array<string,mixed> */
    private $params;

    /** @var string */
    private $media;

    public function __construct(array $params, string $media = '')
    {
        $this->params = $params;
        $this->media  = $media !== '' ? $media : plugin_dir_url(self::pluginFile());
    }

    /**
     * The plugin's own entry file.
     *
     * Deliberately not derived from where this class sits: the build copies the
     * shared core into lib/, so a path worked out from __DIR__ points one folder
     * too deep and every asset URL comes out with lib/ in it.
     */
    private static function pluginFile(): string
    {
        return defined('HIKARI_FLIPBOOK_FILE') ? HIKARI_FLIPBOOK_FILE : dirname(__DIR__, 2) . '/hikari-flipbook.php';
    }

    public function config(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * WordPress has capabilities rather than view levels. A level of "public"
     * is everyone; anything else is read as a capability name.
     */
    public function can(string $level): bool
    {
        return $level === '' || $level === 'public' ? true : current_user_can($level);
    }

    public function translate(string $key): string
    {
        // The core speaks in keys and WordPress has no key catalogue, so each is
        // mapped to its English here: gettext translates the English, which is
        // what a translator expects to be given.
        $strings = self::catalogue();

        return $strings[$key] ?? __($key, 'hikari-flipbook');
    }

    /**
     * @return array<string,string>
     */
    private static function catalogue(): array
    {
        static $strings = null;

        if ($strings !== null) {
            return $strings;
        }

        $strings = [];
        foreach (\Hikari\Flipbook\Core\Strings::catalogue() as $key => $english) {
            // The English is the msgid, so the .pot the build writes and the call
            // made here are the same string.
            $strings[$key] = __($english, 'hikari-flipbook');
        }

        return $strings;
    }

    public function asset(string $path): string
    {
        return $this->media . 'media/' . ltrim($path, '/');
    }

    public function mediaPath(): string
    {
        return untrailingslashit(plugin_dir_path(self::pluginFile())) . '/media';
    }

    public function cachePath(): string
    {
        $uploads = wp_upload_dir();

        return $uploads['basedir'] . '/hikari-flipbook';
    }

    public function rootPath(): string
    {
        return untrailingslashit(ABSPATH);
    }

    /**
     * The uploads folder, not the plugin folder: uploads is the one place a
     * WordPress site is expected to be writable, and it survives an update.
     *
     * @return array{path:string,url:string}
     */
    public function storage(): array
    {
        $uploads = wp_upload_dir();

        return [
            'path' => $uploads['basedir'] . '/hikari-flipbook',
            'url'  => $uploads['baseurl'] . '/hikari-flipbook',
        ];
    }

    public function baseUrl(): string
    {
        return rtrim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
    }

    public function escape(string $value): string
    {
        return esc_html($value);
    }

    public function enqueue(string $handle, string $path, string $type = 'script'): void
    {
        $name = $handle . '-' . sanitize_key(basename($path));

        if ($type === 'style') {
            wp_enqueue_style($name, $this->asset($path), [], HIKARI_FLIPBOOK_VERSION);
            return;
        }

        wp_enqueue_script($name, $this->asset($path), [], HIKARI_FLIPBOOK_VERSION, true);
        self::asModule($name);
    }

    /**
     * Marks a script as an ES module.
     *
     * wp_script_add_data($handle, 'type', 'module') does not reach the tag, and the
     * bundle is a module: without type="module" the browser stops at the first
     * import and the book never appears. wp_enqueue_script_module() would do this
     * properly but it arrived in 6.5, and this plugin supports 6.4.
     */
    private static function asModule(string $handle): void
    {
        static $filtered = false;

        wp_script_add_data($handle, 'hikari_flipbook_module', true);

        if ($filtered) {
            return;
        }
        $filtered = true;

        add_filter(
            'script_loader_tag',
            static function (string $tag, string $handle): string {
                if (!wp_scripts()->get_data($handle, 'hikari_flipbook_module')) {
                    return $tag;
                }

                return str_contains($tag, ' type=')
                    ? preg_replace('/ type=(["\'])[^"\']*\1/', ' type="module"', $tag, 1)
                    : preg_replace('/^<script /', '<script type="module" ', $tag, 1);
            },
            10,
            2
        );
    }

    /**
     * A WooCommerce product, where the site has WooCommerce. A site without a
     * shop is not a broken site: the hotspot simply stays a plain region.
     *
     * @return array{url:string,name:string}|null
     */
    public function product(string $id): ?array
    {
        // Whichever shop the site has. HikaShop runs on WordPress as well as on
        // Joomla, so asking WooCommerce and stopping there would leave half our
        // own users with hotspots that link nowhere.
        return $this->fromHikaShop($id) ?? $this->fromWooCommerce($id);
    }

    /** @return array{url:string,name:string}|null */
    private function fromHikaShop(string $id): ?array
    {
        global $wpdb;

        // The link builder is HikaShop's own, so the URL respects the shop's
        // pages and permalinks rather than being guessed at here.
        if (!function_exists('hikashop_completeLink') || !isset($wpdb)) {
            return null;
        }

        $product = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT product_name, product_alias FROM {$wpdb->prefix}hikashop_product"
                . ' WHERE product_id = %d AND product_published = 1',
                (int) $id
            )
        );

        if (!$product) {
            return null;
        }

        $url = hikashop_completeLink(
            'product&task=show&cid=' . (int) $id . '&name=' . rawurlencode((string) $product->product_alias)
        );

        return $url ? ['url' => (string) $url, 'name' => (string) $product->product_name] : null;
    }

    /**
     * Whether the visitor has bought this product, from whichever shop has it.
     *
     * Asked of both shops for the same reason the link is: a WordPress site can
     * be running HikaShop, WooCommerce, or both.
     */
    public function hasBought(string $id): bool
    {
        if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
            return false;
        }

        return $this->boughtOnHikaShop($id) || $this->boughtOnWooCommerce($id);
    }

    private function boughtOnHikaShop(string $id): bool
    {
        global $wpdb;

        $id = (int) $id;

        if ($id <= 0 || !isset($wpdb) || !function_exists('hikashop_completeLink')) {
            return false;
        }

        $statuses = $this->hikaShopPaidStatuses();
        $marks    = implode(',', array_fill(0, count($statuses), '%s'));

        // The order belongs to a shop account, which belongs to a site account.
        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM {$wpdb->prefix}hikashop_order_product AS op"
                . " INNER JOIN {$wpdb->prefix}hikashop_order AS o ON o.order_id = op.order_id"
                . " INNER JOIN {$wpdb->prefix}hikashop_user AS u ON u.user_id = o.order_user_id"
                . ' WHERE op.product_id = %d AND u.user_cms_id = %d'
                // Carts and wishlists live in the same table as sales do.
                . " AND o.order_type = 'sale' AND o.order_status IN ($marks) LIMIT 1",
                array_merge([$id, get_current_user_id()], $statuses)
            )
        );

        return $found !== null;
    }

    /**
     * The order statuses HikaShop treats as paid for, from its own configuration:
     * the same ones that let a customer download a file they bought.
     *
     * @return array<int,string>
     */
    private function hikaShopPaidStatuses(): array
    {
        global $wpdb;

        $value = (string) $wpdb->get_var(
            "SELECT config_value FROM {$wpdb->prefix}hikashop_config"
            . " WHERE config_namekey = 'order_status_for_download'"
        );

        $statuses = array_filter(array_map('trim', explode(',', $value)), 'strlen');

        return $statuses === [] ? ['confirmed', 'shipped'] : array_values($statuses);
    }

    private function boughtOnWooCommerce(string $id): bool
    {
        if (!function_exists('wc_customer_bought_product')) {
            return false;
        }

        return (bool) wc_customer_bought_product('', get_current_user_id(), (int) $id);
    }

    /** @return array{url:string,name:string}|null */
    private function fromWooCommerce(string $id): ?array
    {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product((int) $id);

        if (!$product || !$product->is_visible()) {
            return null;
        }

        $url = get_permalink($product->get_id());

        return $url === false ? null : ['url' => (string) $url, 'name' => (string) $product->get_name()];
    }
}
