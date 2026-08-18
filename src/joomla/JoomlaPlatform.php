<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Platform;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/** The Joomla side of the Platform contract. */
final class JoomlaPlatform implements Platform
{
    /** @var Registry */
    private $params;

    /** @var string */
    private $media;

    public function __construct(Registry $params, string $media = 'mod_hikariflipbook')
    {
        $this->params = $params;
        $this->media  = $media;
    }

    public function config(string $key, $default = null)
    {
        return $this->params->get($key, $default);
    }

    public function can(string $level): bool
    {
        $user = Factory::getApplication()->getIdentity();

        return $user !== null && in_array((int) $level, $user->getAuthorisedViewLevels(), true);
    }

    public function translate(string $key): string
    {
        return Text::_($key);
    }

    public function asset(string $path): string
    {
        return Uri::root(true) . '/media/' . $this->media . '/' . ltrim($path, '/');
    }

    public function cachePath(): string
    {
        return JPATH_CACHE . '/hikari-flipbook';
    }

    public function rootPath(): string
    {
        return JPATH_ROOT;
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public function enqueue(string $handle, string $path, string $type = 'script'): void
    {
        $assets = Factory::getApplication()->getDocument()->getWebAssetManager();
        $name   = $handle . '.' . basename($path);

        if ($type === 'style') {
            $assets->registerAndUseStyle($name, 'media/' . $this->media . '/' . $path);
            return;
        }

        $assets->registerAndUseScript($name, 'media/' . $this->media . '/' . $path, [], ['type' => 'module']);
    }
}
