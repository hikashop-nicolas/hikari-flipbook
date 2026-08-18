<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Component\Hikariflipbook\Administrator\Table;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/** One row of #__hikariflipbook_books. */
class BookTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_hikariflipbook.book';

        parent::__construct('#__hikariflipbook_books', 'id', $db);
    }

    public function check(): bool
    {
        $this->title = trim((string) $this->title);
        $this->path  = trim((string) $this->path);

        if ($this->title === '') {
            $this->setError(\Joomla\CMS\Language\Text::_('COM_HIKARIFLIPBOOK_ERROR_TITLE'));

            return false;
        }
        if ($this->path === '') {
            $this->setError(\Joomla\CMS\Language\Text::_('COM_HIKARIFLIPBOOK_ERROR_PATH'));

            return false;
        }

        return parent::check();
    }
}
