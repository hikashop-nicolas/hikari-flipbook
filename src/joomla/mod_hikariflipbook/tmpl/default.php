<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 *
 * @var string $html   The rendered book, empty when the source could not be read
 * @var string $error  A message for the site administrator, empty when all is well
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

if ($error !== '') :
    // Only someone who can fix it is told what is wrong.
    if (Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_modules')) : ?>
        <div class="hikari-flipbook-error">
            <?php echo Text::sprintf('MOD_HIKARIFLIPBOOK_ERROR', htmlspecialchars($error, ENT_QUOTES, 'UTF-8')); ?>
        </div>
    <?php endif;
    return;
endif;

echo $html;
