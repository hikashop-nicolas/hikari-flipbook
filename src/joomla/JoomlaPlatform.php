<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Platform;

use Hikari\Flipbook\Core\Shop;
use Hikari\Flipbook\Core\Shops;
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

    /**
     * Under the extension's own media folder: writable on a normal Joomla site,
     * public by definition, and removed with the extension.
     *
     * @return array{path:string,url:string}
     */
    public function storage(): array
    {
        return [
            'path' => $this->mediaPath() . '/cache',
            'url'  => Uri::root(true) . '/media/' . $this->media . '/cache',
        ];
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

    /**
     * Whether the visitor has an order for this product that HikaShop counts.
     *
     * Which statuses count is the shop's own setting, the one it uses to decide
     * whether a downloadable file may be fetched: a book sold as a download and a
     * book shown as a flipbook are the same sale, and should let a reader in at
     * the same moment.
     */
    public function hasBought(string $id): bool
    {
        $id   = (int) $id;
        $user = Factory::getApplication()->getIdentity();

        if ($id <= 0 || $user === null || $user->guest || !is_dir(JPATH_ROOT . '/components/com_hikashop')) {
            return false;
        }

        $cms = (int) $user->id;
        $db  = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__hikashop_order_product', 'op'))
            ->innerJoin(
                $db->quoteName('#__hikashop_order', 'o')
                . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('op.order_id')
            )
            // The order belongs to a shop account, which belongs to a site account.
            ->innerJoin(
                $db->quoteName('#__hikashop_user', 'u')
                . ' ON ' . $db->quoteName('u.user_id') . ' = ' . $db->quoteName('o.order_user_id')
            )
            ->where($db->quoteName('op.product_id') . ' = :id')
            ->where($db->quoteName('u.user_cms_id') . ' = :cms')
            // Carts and wishlists live in the same table as sales do.
            ->where($db->quoteName('o.order_type') . ' = ' . $db->quote('sale'))
            ->whereIn($db->quoteName('o.order_status'), $this->paidStatuses(), ParameterType::STRING)
            ->bind(':id', $id, ParameterType::INTEGER)
            ->bind(':cms', $cms, ParameterType::INTEGER)
            ->setLimit(1);

        try {
            return $db->setQuery($query)->loadResult() !== null;
        } catch (\Throwable $e) {
            // A site with the folder but not the tables: nobody has bought anything.
            return false;
        }
    }

    /**
     * The document HikaShop holds for this product, and where we answer for it.
     *
     * The shop's own download address is deliberately not used: it counts against
     * the customer's download limit, and a book being read is not a download.
     *
     * @return array{path:string,url:string}|null
     */
    public function productDocument(string $id): ?array
    {
        $id = (int) $id;

        if ($id <= 0 || !is_dir(JPATH_ROOT . '/components/com_hikashop')) {
            return null;
        }

        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('file_path'))
            ->from($db->quoteName('#__hikashop_file'))
            ->where($db->quoteName('file_ref_id') . ' = :id')
            ->where($db->quoteName('file_type') . ' = ' . $db->quote('file'))
            ->order($db->quoteName('file_ordering') . ' ASC, ' . $db->quoteName('file_id') . ' ASC')
            ->bind(':id', $id, ParameterType::INTEGER);

        try {
            $paths = $db->setQuery($query)->loadColumn();
        } catch (\Throwable $e) {
            return null;
        }

        $path = Shops::hikaShopFile($paths ?: [], JPATH_ROOT, $this->hikaShopSetting('uploadsecurefolder'));

        return $path === null ? null : ['path' => $path, 'url' => $this->documentUrl($id)];
    }

    /**
     * Where a buyer's browser asks for it. com_ajax rather than a page of our own:
     * it is Joomla's own address for an extension that has to answer a request,
     * and it needs nothing installed beyond the module, which is already here.
     */
    private function documentUrl(int $id): string
    {
        return Route::_(
            'index.php?option=com_ajax&module=hikariflipbook&method=book&format=raw&product=' . $id,
            false
        );
    }

    /** One value out of HikaShop's own configuration table. */
    private function hikaShopSetting(string $key): string
    {
        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('config_value'))
            ->from($db->quoteName('#__hikashop_config'))
            ->where($db->quoteName('config_namekey') . ' = :key')
            ->bind(':key', $key);

        try {
            return (string) $db->setQuery($query)->loadResult();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * The order statuses HikaShop treats as paid for, from its own configuration.
     *
     * @return array<int,string>
     */
    private function paidStatuses(): array
    {
        $statuses = array_filter(
            array_map('trim', explode(',', $this->hikaShopSetting('order_status_for_download'))),
            'strlen'
        );

        return $statuses === [] ? ['confirmed', 'shipped'] : array_values($statuses);
    }
}
