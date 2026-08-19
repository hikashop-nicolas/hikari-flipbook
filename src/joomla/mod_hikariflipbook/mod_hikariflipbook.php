<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

use Hikari\Flipbook\Core\Buyers;
use Hikari\Flipbook\Core\Config;
use Hikari\Flipbook\Core\Renderer;
use Hikari\Flipbook\Core\Source;
use Hikari\Flipbook\Core\SourceException;
use Hikari\Flipbook\Core\Book;
use Hikari\Flipbook\Platform\JoomlaBookStore;
use Hikari\Flipbook\Platform\JoomlaPlatform;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;

require_once __DIR__ . '/lib/bootstrap.php';

$platform = new JoomlaPlatform($params);
$error    = '';
$html     = '';

// A saved book brings its own settings; anything set on the module wins over them,
// so one book can be shown twice and look different each time.
$wanted   = (int) $params->get('book');
$book     = (new JoomlaBookStore())->find($wanted);
$settings = $book instanceof Book ? $book->merged($params->toArray()) : $params->toArray();

try {
    // A book that is unpublished, or not for this language or this visitor, is
    // simply not found. Saying so beats a module that renders nothing.
    if ($wanted > 0 && !$book instanceof Book) {
        throw new SourceException(Text::_('MOD_HIKARIFLIPBOOK_BOOK_UNAVAILABLE'));
    }

    $config = new Config($settings);
    // A book sold as a product needs no path of its own: the file the product is
    // sold with is the book. Null when this visitor may not have it, which the
    // renderer answers rather than treating as a mistake.
    $source = (string) ($settings['path'] ?? '') === '' && $config->get('bought') !== ''
        ? Buyers::document($platform, $config)
        : Source::fromPath($platform, (string) ($settings['path'] ?? ''));
    $html   = (new Renderer($platform))->render($source, $config, 'hikari-flipbook-' . $module->id);
} catch (SourceException $e) {
    $error = $e->getMessage();
}

require ModuleHelper::getLayoutPath('mod_hikariflipbook', $params->get('layout', 'default'));
