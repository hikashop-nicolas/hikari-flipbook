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
        $this->media  = $media !== '' ? $media : plugin_dir_url(dirname(__DIR__) . '/hikari-flipbook.php');
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
        return __($key, 'hikari-flipbook');
    }

    public function asset(string $path): string
    {
        return $this->media . 'media/' . ltrim($path, '/');
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
        wp_script_add_data($name, 'type', 'module');
    }
}
