<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Component\Hikariflipbook\Administrator\View\Books;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    protected $items;
    protected $pagination;
    protected $state;

    public function display($tpl = null): void
    {
        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');

        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_HIKARIFLIPBOOK_BOOKS'), 'book');

        $user = Factory::getApplication()->getIdentity();

        if ($user->authorise('core.create', 'com_hikariflipbook')) {
            ToolbarHelper::addNew('book.add');
        }
        if ($user->authorise('core.edit.state', 'com_hikariflipbook')) {
            ToolbarHelper::publish('books.publish', 'JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('books.unpublish', 'JTOOLBAR_UNPUBLISH', true);
        }
        if ($user->authorise('core.delete', 'com_hikariflipbook')) {
            ToolbarHelper::deleteList('COM_HIKARIFLIPBOOK_CONFIRM_DELETE', 'books.delete');
        }
        if ($user->authorise('core.admin', 'com_hikariflipbook')) {
            ToolbarHelper::preferences('com_hikariflipbook');
        }
    }
}
