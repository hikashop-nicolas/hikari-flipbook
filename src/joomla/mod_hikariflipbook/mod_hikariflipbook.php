<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

use Hikari\Flipbook\Core\Config;
use Hikari\Flipbook\Core\Renderer;
use Hikari\Flipbook\Core\Source;
use Hikari\Flipbook\Core\SourceException;
use Hikari\Flipbook\Platform\JoomlaPlatform;
use Joomla\CMS\Helper\ModuleHelper;

require_once __DIR__ . '/lib/bootstrap.php';

$platform = new JoomlaPlatform($params);
$error    = '';
$html     = '';

try {
    $source = Source::fromPath($platform, (string) $params->get('path', ''));
    $config = new Config($params->toArray());
    $html   = (new Renderer($platform))->render($source, $config, 'hikari-flipbook-' . $module->id);
} catch (SourceException $e) {
    $error = $e->getMessage();
}

require ModuleHelper::getLayoutPath('mod_hikariflipbook', $params->get('layout', 'default'));
