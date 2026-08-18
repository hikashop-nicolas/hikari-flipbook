<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Platform;

use Hikari\Flipbook\Core\Book;
use Hikari\Flipbook\Core\BookStore;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\Database\ParameterType;

/** Books kept in #__hikariflipbook_books. */
final class JoomlaBookStore implements BookStore
{
    public function find($id): ?Book
    {
        $id = (int) $id;

        if ($id <= 0) {
            return null;
        }

        $app  = Factory::getApplication();
        $db   = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $user = $app->getIdentity();

        $query = $db->getQuery(true)
            ->select($db->quoteName(['title', 'path', 'params', 'access']))
            ->from($db->quoteName('#__hikariflipbook_books'))
            ->where($db->quoteName('id') . ' = :id')
            ->where($db->quoteName('published') . ' = 1')
            ->bind(':id', $id, ParameterType::INTEGER);

        // Access is enforced in the query rather than checked afterwards: a book
        // someone may not see should not be read at all.
        $levels = $user ? $user->getAuthorisedViewLevels() : [1];
        $query->whereIn($db->quoteName('access'), $levels);

        if (Multilanguage::isEnabled()) {
            $tags = [$app->getLanguage()->getTag(), '*'];
            $query->whereIn($db->quoteName('language'), $tags, ParameterType::STRING);
        }

        $row = $db->setQuery($query)->loadAssoc();

        return $row === null ? null : Book::fromRow($row);
    }
}
