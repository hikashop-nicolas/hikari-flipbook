<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Component\Hikariflipbook\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;

/** Editing one book. */
class BookModel extends AdminModel
{
    protected $text_prefix = 'COM_HIKARIFLIPBOOK';

    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_hikariflipbook.book', 'book', ['control' => 'jform', 'load_data' => $loadData]);

        return $form ?: false;
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_hikariflipbook.edit.book.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        // The form works with the settings spread out; the table keeps them as one
        // JSON column, since the core reads them as a set.
        if ($item && isset($item->params)) {
            $params = is_string($item->params) ? json_decode($item->params, true) : $item->params;
            $item->params = is_array($params) ? $params : [];
        }

        return $item;
    }

    public function save($data)
    {
        if (isset($data['params']) && is_array($data['params'])) {
            $data['params'] = json_encode($data['params']);
        }

        return parent::save($data);
    }
}
