<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Component\Hikariflipbook\Administrator\View\Book;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $item;

    public function display($tpl = null): void
    {
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');

        // The toolbar's Save asks the validator whether the form is valid, and a
        // validator that was never loaded is not a quiet no: it throws, and the
        // button does nothing at all.
        $this->getDocument()->getWebAssetManager()
            ->useScript('keepalive')
            ->useScript('form.validate');

        ToolbarHelper::title(
            Text::_($this->item && $this->item->id ? 'COM_HIKARIFLIPBOOK_EDIT_BOOK' : 'COM_HIKARIFLIPBOOK_NEW_BOOK'),
            'book'
        );
        ToolbarHelper::apply('book.apply');
        ToolbarHelper::save('book.save');
        ToolbarHelper::cancel('book.cancel', 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }
}
