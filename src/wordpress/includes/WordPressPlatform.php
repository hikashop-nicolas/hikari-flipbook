<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Platform;

if (!defined('ABSPATH')) {
    exit;
}

/** The WordPress side of the Platform contract. */
final class WordPressPlatform implements Platform
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
}
