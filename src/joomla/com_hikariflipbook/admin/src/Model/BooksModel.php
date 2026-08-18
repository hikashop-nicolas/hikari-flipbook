<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Component\Hikariflipbook\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

/** The list of books. */
class BooksModel extends ListModel
{
    public function __construct($config = [])
    {
        $config['filter_fields'] = $config['filter_fields'] ?? ['id', 'a.id', 'title', 'a.title', 'published', 'a.published'];

        parent::__construct($config);
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['a.id', 'a.title', 'a.path', 'a.published', 'a.access', 'a.created']))
            ->select($db->quoteName('l.title', 'access_title'))
            ->from($db->quoteName('#__hikariflipbook_books', 'a'))
            ->leftJoin($db->quoteName('#__viewlevels', 'l') . ' ON ' . $db->quoteName('l.id') . ' = ' . $db->quoteName('a.access'));

        $search = (string) $this->getState('filter.search');
        if ($search !== '') {
            $like = $db->quote('%' . str_replace(' ', '%', trim($search)) . '%');
            $query->where('(' . $db->quoteName('a.title') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('a.path') . ' LIKE ' . $like . ')');
        }

        $published = $this->getState('filter.published');
        if (is_numeric($published)) {
            $query->where($db->quoteName('a.published') . ' = :published')
                ->bind(':published', $published, \Joomla\Database\ParameterType::INTEGER);
        }

        return $query->order($db->escape($this->getState('list.ordering', 'a.title')) . ' '
            . $db->escape($this->getState('list.direction', 'ASC')));
    }
}
