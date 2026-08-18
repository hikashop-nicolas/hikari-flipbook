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

HTMLHelper::_('behavior.multiselect');
?>
<form action="<?php echo Route::_('index.php?option=com_hikariflipbook&view=books'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<table class="table" id="bookList">
				<caption class="visually-hidden"><?php echo Text::_('COM_HIKARIFLIPBOOK_BOOKS'); ?></caption>
				<thead>
					<tr>
						<td style="width:1%" class="text-center">
							<?php echo HTMLHelper::_('grid.checkall'); ?>
						</td>
						<th scope="col" style="width:1%" class="text-center"><?php echo Text::_('JSTATUS'); ?></th>
						<th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
						<th scope="col"><?php echo Text::_('COM_HIKARIFLIPBOOK_FIELD_PATH'); ?></th>
						<th scope="col" style="width:10%"><?php echo Text::_('JGRID_HEADING_ACCESS'); ?></th>
						<th scope="col" style="width:5%"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if (empty($this->items)) : ?>
					<tr>
						<td colspan="6">
							<div class="alert alert-info">
								<?php echo Text::_('COM_HIKARIFLIPBOOK_NO_BOOKS'); ?>
							</div>
						</td>
					</tr>
				<?php endif; ?>
				<?php foreach ($this->items as $i => $item) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="text-center">
							<?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?>
						</td>
						<td class="text-center">
							<?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'books.', true); ?>
						</td>
						<th scope="row">
							<a href="<?php echo Route::_('index.php?option=com_hikariflipbook&task=book.edit&id=' . (int) $item->id); ?>">
								<?php echo $this->escape($item->title); ?>
							</a>
						</th>
						<td><?php echo $this->escape($item->path); ?></td>
						<td><?php echo $this->escape($item->access_title ?? ''); ?></td>
						<td><?php echo (int) $item->id; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php echo $this->pagination->getListFooter(); ?>
		</div>
	</div>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="boxchecked" value="0">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
