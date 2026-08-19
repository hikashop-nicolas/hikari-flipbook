<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var Joomla\CMS\MVC\View\HtmlView $this */
?>
<form action="<?php echo Route::_('index.php?option=com_hikariflipbook&layout=edit&id=' . (int) $this->item->id); ?>"
	method="post" name="adminForm" id="book-form" class="form-validate">

	<?php echo HTMLHelper::_('uitab.startTabSet', 'bookTab', ['active' => 'general', 'recall' => true]); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'bookTab', 'general', Text::_('COM_HIKARIFLIPBOOK_TAB_GENERAL')); ?>
		<div class="row">
			<div class="col-lg-9">
				<?php echo $this->form->renderField('title'); ?>
				<?php echo $this->form->renderField('path'); ?>
			</div>
			<div class="col-lg-3">
				<?php echo $this->form->renderField('published'); ?>
				<?php echo $this->form->renderField('access'); ?>
				<?php echo $this->form->renderField('language'); ?>
			</div>
		</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'bookTab', 'display', Text::_('COM_HIKARIFLIPBOOK_TAB_DISPLAY')); ?>
		<div class="row">
			<div class="col-lg-6">
				<?php foreach (['mode', 'showCover', 'hardCovers', 'rtl', 'lightbox'] as $field) : ?>
					<?php echo $this->form->renderField($field, 'params'); ?>
				<?php endforeach; ?>
			</div>
			<div class="col-lg-6">
				<?php foreach (['zoom', 'sound', 'soundFile', 'download', 'share', 'deepLink', 'seo', 'analytics'] as $field) : ?>
					<?php echo $this->form->renderField($field, 'params'); ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'bookTab', 'appearance', Text::_('COM_HIKARIFLIPBOOK_TAB_APPEARANCE')); ?>
		<div class="row">
			<div class="col-lg-6">
				<?php foreach (['barColour', 'pageColour', 'maxHeight', 'breakpoint', 'flippingTime'] as $field) : ?>
					<?php echo $this->form->renderField($field, 'params'); ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'bookTab', 'hotspots', Text::_('COM_HIKARIFLIPBOOK_TAB_HOTSPOTS')); ?>
		<div class="row">
			<div class="col-lg-12">
				<?php echo $this->form->renderField('hotspotsShown', 'params'); ?>
				<?php echo $this->form->renderField('hotspots', 'params'); ?>
			</div>
		</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.endTabSet'); ?>

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
