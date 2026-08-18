<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Component\Hikariflipbook\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;

class BooksController extends AdminController
{
    public function getModel($name = 'Book', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
