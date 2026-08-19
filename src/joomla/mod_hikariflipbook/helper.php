<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

use Hikari\Flipbook\Core\Buyers;
use Hikari\Flipbook\Platform\JoomlaPlatform;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

require_once __DIR__ . '/lib/bootstrap.php';

/**
 * The address a bought book is read from.
 *
 * A document sold as a product is not a public file, so it is read out here, by
 * com_ajax, which is Joomla's own way for an extension to answer a request:
 *
 *   index.php?option=com_ajax&module=hikariflipbook&method=book&format=raw&product=42
 *
 * The purchase is checked again on every request. Somebody who has the address
 * but not the order gets nothing, and so does a search engine.
 */
class ModHikariflipbookHelper
{
    public static function bookAjax(): string
    {
        $app     = Factory::getApplication();
        $product = (string) $app->getInput()->getInt('product', 0);

        if (!Buyers::send(new JoomlaPlatform(new Registry()), $product)) {
            throw new RuntimeException('Not found', 404);
        }

        // send() has already written the file to the browser.
        $app->close();

        return '';
    }
}
