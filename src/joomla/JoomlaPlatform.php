<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Platform;

use Hikari\Flipbook\Core\Shop;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/** The Joomla side of the Platform contract. */
final class JoomlaPlatform implements Platform, Shop
{
    /** @var Registry */
    private $params;

    /** @var string */
    private $media;

    public function __construct(Registry $params, string $media = 'hikariflipbook')
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

    public function mediaPath(): string
    {
        return JPATH_ROOT . '/media/' . $this->media;
    }

    public function cachePath(): string
    {
        return JPATH_CACHE . '/hikari-flipbook';
    }

    public function rootPath(): string
    {
        return JPATH_ROOT;
    }

    public function baseUrl(): string
    {
        return Uri::root(true);
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

    /**
     * A HikaShop product, where the site has HikaShop. Nothing else is asked: a
     * shop the site does not have is not an error, it is a hotspot that stays a
     * plain region.
     *
     * @return array{url:string,name:string}|null
     */
    public function product(string $id): ?array
    {
        $id = (int) $id;

        if ($id <= 0 || !is_dir(JPATH_ROOT . '/components/com_hikashop')) {
            return null;
        }

        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['product_name', 'product_alias']))
            ->from($db->quoteName('#__hikashop_product'))
            ->where($db->quoteName('product_id') . ' = :id')
            ->where($db->quoteName('product_published') . ' = 1')
            ->bind(':id', $id, ParameterType::INTEGER);

        try {
            $row = $db->setQuery($query)->loadAssoc();
        } catch (\Throwable $e) {
            // A site with the folder but not the tables, mid-install or mid-removal.
            return null;
        }

        if ($row === null) {
            return null;
        }

        $url = 'index.php?option=com_hikashop&ctrl=product&task=show&cid=' . $id
            . '&name=' . urlencode((string) $row['product_alias']);

        return ['url' => Route::_($url), 'name' => (string) $row['product_name']];
    }
}
