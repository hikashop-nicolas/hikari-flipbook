<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Platform;

/**
 * Everything the core needs from its host, and nothing more.
 *
 * The core is written against this interface alone, which is what keeps one
 * codebase serving both Joomla and WordPress. Each host ships one implementation.
 */
interface Platform
{
    /** A configuration value for the current instance. */
    public function config(string $key, $default = null);

    /** True when the current visitor may see something at this access level. */
    public function can(string $level): bool;

    /** Translate a key. Returns the key itself when the host has no string for it. */
    public function translate(string $key): string;

    /** Public URL of a file shipped with the extension, e.g. "js/flipview.js". */
    public function asset(string $path): string;

    /** Absolute path of a writable cache directory for rendered pages. */
    public function cachePath(): string;

    /** Absolute filesystem path of the host's public root. */
    public function rootPath(): string;

    /**
     * The site's base path, without a trailing slash, empty at a domain root.
     * A site installed in a subdirectory serves its files from under it, so a
     * URL built by stripping the filesystem root alone would miss the site.
     */
    public function baseUrl(): string;

    /** Escape a string for HTML output. */
    public function escape(string $value): string;

    /** Register a script or stylesheet with the host's asset pipeline. */
    public function enqueue(string $handle, string $path, string $type = 'script'): void;
}
